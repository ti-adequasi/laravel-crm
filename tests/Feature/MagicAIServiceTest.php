<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Lead\Services\MagicAIService;

/**
 * MagicAIService::ask() used to always send only the first prompt element
 * as a plain `text` content block — for an image-only upload (no PDF text
 * extracted), that meant the raw base64 image data went to the model as
 * if it were prose to read, never as an image it could actually see,
 * regardless of which model was configured. Fixed to build real
 * multimodal content: a `text` block only when there's real text, plus one
 * `image_url` block per image.
 */
uses(DatabaseTransactions::class);

// A minimal valid 1x1 PNG.
function magicAiTestPng(): string
{
    return base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
}

it('sends an image-only upload as a real multimodal image_url block, not as base64 text', function () {
    // Every test here sets its own `provider` explicitly rather than
    // relying on the code's "no row yet" default — these tests run against
    // the real app database under DatabaseTransactions, which only rolls
    // back what the test itself writes, not whatever a real admin session
    // already saved (e.g. a provider actually switched to "openai" through
    // the real Settings screen would otherwise leak into this test).
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.provider'], ['value' => 'openrouter']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.api_key'], ['value' => 'test-key']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.model'], ['value' => 'openai/gpt-4o-mini']);

    Http::fake([
        MagicAIService::OPEN_ROUTER_URL => Http::response([
            'choices' => [['message' => ['content' => '{}']]],
        ], 200),
    ]);

    MagicAIService::extractDataFromFile(magicAiTestPng());

    Http::assertSent(function ($request) {
        $content = $request->data()['messages'][1]['content'];

        // The old code sent a single 'text' block whose value was the raw
        // base64 image string. The fix sends no text block at all here
        // (there's no extracted text for a pure image upload) and a proper
        // image_url block instead.
        $hasTextBlock = collect($content)->contains(fn ($block) => $block['type'] === 'text');
        $imageBlock = collect($content)->firstWhere('type', 'image_url');

        return ! $hasTextBlock
            && $imageBlock !== null
            && str_starts_with($imageBlock['image_url']['url'], 'data:image/png;base64,');
    });
});

it('still sends extracted PDF text as a text block when there is no image', function () {
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.api_key'], ['value' => 'test-key']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.model'], ['value' => 'openai/gpt-4o-mini']);

    Http::fake([
        MagicAIService::OPEN_ROUTER_URL => Http::response([
            'choices' => [['message' => ['content' => '{}']]],
        ], 200),
    ]);

    // Reach the private ask() the same way processPromptWithAI() does, via
    // the public entry point — but a PDF needs a real file on disk to parse,
    // so this exercises ask()'s content-building directly through reflection
    // instead, keeping the test focused on the content shape, not PDF parsing.
    $ask = new ReflectionMethod(MagicAIService::class, 'ask');
    $ask->setAccessible(true);
    $ask->invoke(null, 'Contact: jane@example.com', [], 'openai/gpt-4o-mini', MagicAIService::OPEN_ROUTER_URL, 'test-key');

    Http::assertSent(function ($request) {
        $content = $request->data()['messages'][1]['content'];

        return count($content) === 1
            && $content[0]['type'] === 'text'
            && $content[0]['text'] === 'Contact: jane@example.com';
    });
});

it('sends requests straight to OpenAI when that provider is selected', function () {
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.provider'], ['value' => 'openai']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.api_key'], ['value' => 'sk-test']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.other_model'], ['value' => 'gpt-4o-mini']);

    Http::fake([
        MagicAIService::OPENAI_URL => Http::response([
            'choices' => [['message' => ['content' => '{}']]],
        ], 200),
    ]);

    MagicAIService::extractDataFromFile(magicAiTestPng());

    Http::assertSent(function ($request) {
        return $request->url() === MagicAIService::OPENAI_URL
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request->data()['model'] === 'gpt-4o-mini';
    });
});

it('never falls back to the OpenRouter-formatted model preset for a non-OpenRouter provider', function () {
    // A user who previously used OpenRouter (where "Models" holds IDs like
    // "openai/gpt-4o-mini") and then switches the provider to OpenAI
    // without filling in "Outro Modelo" must not silently have that
    // leftover OpenRouter-formatted id sent to OpenAI's own API, which
    // rejects it — they should get the clear "missing model" error instead.
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.provider'], ['value' => 'openai']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.api_key'], ['value' => 'sk-test']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.model'], ['value' => 'openai/gpt-4o-mini']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.other_model'], ['value' => null]);

    Http::fake();

    $result = MagicAIService::extractDataFromFile(magicAiTestPng());

    expect($result)->toHaveKey('error');
    Http::assertNothingSent();
});

it('routes to a self-hosted Omniroute instance using the configured base URL', function () {
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.provider'], ['value' => 'omniroute']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.api_key'], ['value' => 'test-key']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.other_model'], ['value' => 'gpt-4o-mini']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.omniroute_base_url'], ['value' => 'http://localhost:8080/v1']);

    Http::fake([
        'localhost:8080/*' => Http::response([
            'choices' => [['message' => ['content' => '{}']]],
        ], 200),
    ]);

    MagicAIService::extractDataFromFile(magicAiTestPng());

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:8080/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key');
    });
});

it('reports a clear error when Omniroute is selected but no base URL is configured', function () {
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.provider'], ['value' => 'omniroute']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.api_key'], ['value' => 'test-key']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.other_model'], ['value' => 'gpt-4o-mini']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.omniroute_base_url'], ['value' => null]);

    Http::fake();

    $result = MagicAIService::extractDataFromFile(magicAiTestPng());

    expect($result)->toHaveKey('error');
    Http::assertNothingSent();
});

it('sends Anthropic requests with x-api-key auth and base64 image source blocks, normalized back to choices[]', function () {
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.provider'], ['value' => 'anthropic']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.api_key'], ['value' => 'sk-ant-test']);
    DB::table('core_config')->updateOrInsert(['code' => 'general.magic_ai.settings.other_model'], ['value' => 'claude-sonnet-4-5-20250929']);

    Http::fake([
        MagicAIService::ANTHROPIC_URL => Http::response([
            'content' => [
                ['type' => 'text', 'text' => '{"title":"Untitled Lead"}'],
            ],
        ], 200),
    ]);

    $result = MagicAIService::extractDataFromFile(magicAiTestPng());

    Http::assertSent(function ($request) {
        $body = $request->data();

        $imageBlock = collect($body['messages'][0]['content'])->firstWhere('type', 'image');

        return $request->hasHeader('x-api-key', 'sk-ant-test')
            && ! $request->hasHeader('Authorization')
            && $request->hasHeader('anthropic-version', MagicAIService::ANTHROPIC_VERSION)
            && is_string($body['system'] ?? null)
            && $imageBlock !== null
            && $imageBlock['source']['type'] === 'base64'
            && ! str_contains($imageBlock['source']['data'], 'data:');
    });

    // The pipeline downstream of MagicAIService (Webkul\Lead\Helpers\MagicAI)
    // only understands the choices[0].message.content shape every other
    // provider returns natively — Anthropic's own content[] shape must be
    // normalized back into it.
    expect($result)->toHaveKey('choices.0.message.content', '{"title":"Untitled Lead"}');
});
