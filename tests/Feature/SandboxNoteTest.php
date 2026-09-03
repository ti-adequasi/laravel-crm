<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Sandbox\Contracts\Note as NoteContract;
use Webkul\Sandbox\Models\Note;
use Webkul\Sandbox\Models\NoteProxy;

/**
 * Sandbox is the reference module for the crm-package-development skill's
 * "self-contained module" recipe — see packages/Webkul/Sandbox/README.md.
 * These tests exercise the same layers the recipe documents: Concord's
 * Contract/Proxy binding, the ACL/menu registration, and the auth-gated
 * CRUD flow through the repository.
 */
uses(DatabaseTransactions::class);

it('resolves the Note contract to the Note model through Concord', function () {
    expect(NoteProxy::modelClass())->toBe(Note::class);
    expect(app(NoteContract::class))->toBeInstanceOf(Note::class);
});

it('registers the sandbox module in the acl and the admin menu', function () {
    expect(collect(config('acl'))->pluck('key'))->toContain('sandbox');
    expect(collect(config('menu.admin'))->pluck('key'))->toContain('sandbox');
});

it('redirects guests away from the sandbox notes page', function () {
    test()->get(route('admin.sandbox.notes.index'))
        ->assertRedirect(route('admin.session.create'));
});

it('shows the sandbox notes page to an authenticated admin', function () {
    test()->actingAs(getDefaultAdmin())
        ->get(route('admin.sandbox.notes.index'))
        ->assertOk()
        ->assertSee('Sandbox Notes');
});

it('lets an admin create a note', function () {
    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.sandbox.notes.store'), [
            'title' => 'Pest coverage',
            'body'  => 'Written to validate the self-contained module recipe.',
        ])
        ->assertRedirect(route('admin.sandbox.notes.index'));

    expect(Note::where('title', 'Pest coverage')->exists())->toBeTrue();

    test()->actingAs(getDefaultAdmin())
        ->get(route('admin.sandbox.notes.index'))
        ->assertSee('Pest coverage');
});

it('requires a title to create a note', function () {
    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.sandbox.notes.store'), [
            'body' => 'No title attached.',
        ])
        ->assertSessionHasErrors('title');

    expect(Note::where('body', 'No title attached.')->exists())->toBeFalse();
});

it('lets an admin delete a note', function () {
    $note = Note::create([
        'title' => 'To be deleted',
        'body'  => 'Temporary.',
    ]);

    test()->actingAs(getDefaultAdmin())
        ->delete(route('admin.sandbox.notes.destroy', $note->id))
        ->assertRedirect(route('admin.sandbox.notes.index'));

    expect(Note::find($note->id))->toBeNull();
});
