<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

uses(DatabaseTransactions::class);

function createTestLead(int $stageId = 1): Lead
{
    return app(LeadRepository::class)->create([
        'title' => 'Test Lead '.uniqid(),
        'lead_value' => 100,
        'lead_pipeline_id' => 1,
        'lead_pipeline_stage_id' => $stageId,
        'status' => 1,
        'person' => [
            'name' => 'Stage Timing Test Person',
            'emails' => [['value' => uniqid().'@example.com', 'label' => 'work']],
            'entity_type' => 'persons',
        ],
        'entity_type' => 'leads',
    ]);
}

it('stamps stage_entered_at when a lead is created', function () {
    $lead = createTestLead();

    expect($lead->stage_entered_at)->not->toBeNull()
        ->and($lead->stage_entered_at->diffInSeconds(now()))->toBeLessThan(5)
        ->and($lead->days_in_stage)->toBe(0);
});

it('does not reset stage_entered_at when other fields are saved without changing the stage', function () {
    $lead = createTestLead();

    // Backdate it directly, the way a lead that has genuinely been sitting
    // for a while would look, so the assertion below actually proves
    // nothing reset it back to "now".
    $lead->forceFill(['stage_entered_at' => now()->subDays(5)])->save();

    // Mirrors LeadController::update()'s real payload shape (entity_type is
    // required by AttributeValueRepository::save(), which update() always
    // calls).
    app(LeadRepository::class)->update([
        'title' => 'Renamed while staying in the same stage',
        'lead_pipeline_stage_id' => 1,
        'entity_type' => 'leads',
    ], $lead->id);

    $lead->refresh();

    expect($lead->days_in_stage)->toBe(5);
});

it('stamps a fresh stage_entered_at when the lead moves to a different stage', function () {
    $lead = createTestLead(stageId: 1);

    $lead->forceFill(['stage_entered_at' => now()->subDays(10)])->save();

    // Mirrors LeadController::updateStage()'s real payload shape and its
    // use of the $attributes argument for a pure stage move.
    app(LeadRepository::class)->update([
        'lead_pipeline_stage_id' => 2,
        'entity_type' => 'leads',
    ], $lead->id, ['lead_pipeline_stage_id']);

    $lead->refresh();

    expect($lead->lead_pipeline_stage_id)->toBe(2)
        ->and($lead->stage_entered_at->diffInSeconds(now()))->toBeLessThan(5)
        ->and($lead->days_in_stage)->toBe(0);
});
