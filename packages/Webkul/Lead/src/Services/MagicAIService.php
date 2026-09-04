<?php

namespace Webkul\Lead\Services;

use Exception;
use Smalot\PdfParser\Parser;

class MagicAIService
{
    /**
     * API endpoint for OpenRouter AI service.
     */
    const OPEN_ROUTER_URL = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * API endpoint for OpenAI's own AI service.
     */
    const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * API endpoint for Anthropic's own AI service.
     */
    const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';

    /**
     * Anthropic requires this on every request; bump when adopting a newer
     * Messages API revision.
     */
    const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * Anthropic's `max_tokens` caps generated output, not input — unlike
     * MAX_TOKENS below (which truncates the input prompt). The extracted
     * lead JSON is small, so this just needs to comfortably clear it
     * without exceeding older Claude models' own output ceilings.
     */
    const ANTHROPIC_MAX_OUTPUT_TOKENS = 4096;

    /**
     * Maximum token limit for AI prompt.
     */
    const MAX_TOKENS = 100000;

    /**
     * Flag to prevent re-entrant calls.
     */
    private static $isExtracting = false;

    /**
     * Extract data from base64-encoded file.
     */
    public static function extractDataFromFile($base64File)
    {
        if (self::$isExtracting) {
            throw new Exception(trans('admin::app.leads.file.recursive-call'));
        }

        self::$isExtracting = true;

        try {
            $text = self::extractTextFromBase64File($base64File);

            if (empty($text)) {
                throw new Exception(trans('admin::app.leads.file.failed-extract'));
            }

            return self::processPromptWithAI($text);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        } finally {
            self::$isExtracting = false;
        }
    }

    /**
     * Extract text from base64-encoded file.
     */
    private static function extractTextFromBase64File($base64File)
    {
        if (
            empty($base64File)
            || ! base64_decode($base64File, true)
        ) {
            throw new Exception(trans('admin::app.leads.file.invalid-base64'));
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'file_');

        file_put_contents($tempFile, self::handleBase64($base64File, 'decode'));

        $mimeType = mime_content_type($tempFile);

        $data = [];

        try {
            if ($mimeType === 'application/pdf') {
                $pdfParser = (new Parser)->parseFile($tempFile);

                $data['text'] = $pdfParser->getText();

                $data['images'][] = '';

                $images = $pdfParser->getObjectsByType('XObject', 'Image');

                foreach ($images as $image) {
                    $data['images'][] = self::handleBase64($image->getContent());
                }
            } else {
                $data['text'] = '';

                $data['images'][] = self::handleBase64(self::handleBase64($base64File, 'decode'));
            }

            if (empty($data)) {
                throw new Exception(trans('admin::app.leads.file.data-extraction-failed'));
            }

            return $data;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Send extracted data to AI for processing. Dispatches to the
     * configured provider's own request/response shape — OpenRouter,
     * OpenAI, and Omniroute all speak the same OpenAI-compatible wire
     * format (only the base URL changes), while Anthropic's Messages API
     * needs its own request shape and its own response normalized back
     * to the `choices[0].message.content` shape the rest of the pipeline
     * (Webkul\Lead\Helpers\MagicAI::mapAIDataToLead()) expects.
     */
    private static function processPromptWithAI($prompt)
    {
        $provider = core()->getConfigData('general.magic_ai.settings.provider') ?: 'openrouter';

        // The "Models" preset dropdown only ever holds OpenRouter-formatted
        // IDs (vendor-prefixed, e.g. openai/gpt-4o-mini) — falling back to
        // it for any other provider would send that OpenRouter-specific
        // format to a provider that doesn't understand it (e.g. OpenAI
        // rejects "openai/gpt-4o-mini"; it wants "gpt-4o-mini"). So it's
        // only ever consulted for OpenRouter itself.
        $model = $provider === 'openrouter'
            ? (core()->getConfigData('general.magic_ai.settings.other_model') ?: core()->getConfigData('general.magic_ai.settings.model'))
            : core()->getConfigData('general.magic_ai.settings.other_model');

        $apiKey = core()->getConfigData('general.magic_ai.settings.api_key');

        if (! $apiKey || ! $model) {
            return ['error' => trans('admin::app.leads.file.missing-api-key')];
        }

        if (
            $provider === 'omniroute'
            && ! core()->getConfigData('general.magic_ai.settings.omniroute_base_url')
        ) {
            return ['error' => trans('admin::app.leads.file.missing-api-key')];
        }

        $promptText = self::truncatePrompt($prompt['text'] ?? '');

        $promptImages = array_values(array_filter($prompt['images'] ?? []));

        if ($provider === 'anthropic') {
            return self::askAnthropic($promptText, $promptImages, $model, $apiKey);
        }

        return self::ask($promptText, $promptImages, $model, $apiKey, self::resolveOpenAiCompatibleUrl($provider));
    }

    /**
     * Base URL for the providers that speak the OpenAI chat-completions
     * wire format. Omniroute is self-hosted, so its address comes from
     * settings rather than being a fixed constant.
     */
    private static function resolveOpenAiCompatibleUrl(string $provider): string
    {
        return match ($provider) {
            'openai' => self::OPENAI_URL,

            'omniroute' => rtrim((string) core()->getConfigData('general.magic_ai.settings.omniroute_base_url'), '/').'/chat/completions',

            default => self::OPEN_ROUTER_URL,
        };
    }

    /**
     * Truncate prompt to fit within token limit.
     */
    private static function truncatePrompt($prompt)
    {
        if (strlen($prompt) > self::MAX_TOKENS) {
            $start = mb_substr($prompt, 0, self::MAX_TOKENS * 0.4);

            $end = mb_substr($prompt, -self::MAX_TOKENS * 0.4);

            return $start."\n...\n".$end;
        }

        return $prompt;
    }

    /**
     * Send prompt request to an OpenAI-compatible endpoint (OpenRouter,
     * OpenAI itself, or a self-hosted Omniroute gateway — all three speak
     * the same chat-completions shape). Images go through as real
     * multimodal `image_url` content blocks — the previous version always
     * sent only the first prompt element as a plain `text` block, so an
     * image-only upload (no PDF text extracted) put its raw base64 data
     * into the model as if it were prose to read, which every model
     * either garbles or errors on — it was never actually able to see the
     * image, regardless of which model was configured.
     */
    private static function ask($text, array $images, $model, $apiKey, string $url)
    {
        try {
            $content = [];

            if (! empty($text)) {
                $content[] = [
                    'type' => 'text',
                    'text' => $text,
                ];
            }

            foreach ($images as $image) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => self::toImageDataUri($image),
                    ],
                ];
            }

            if (empty($content)) {
                throw new Exception(trans('admin::app.leads.file.data-extraction-failed'));
            }

            $response = \Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$apiKey,
            ])->post($url, [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => self::getSystemPrompt(),
                    ], [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
            ]);

            if ($response->failed()) {
                throw new Exception($response->body());
            }

            $data = $response->json();

            if (isset($data['error'])) {
                throw new Exception($data['error']['message']);
            }

            return $data;
        } catch (Exception $e) {
            return ['error' => trans('admin::app.leads.file.insufficient-info')];
        }
    }

    /**
     * Send prompt request to Anthropic's Messages API. Unlike the
     * OpenAI-compatible providers, Anthropic wants: an `x-api-key` header
     * instead of `Authorization: Bearer`, the system prompt as a top-level
     * field rather than a `role: system` message, and image blocks shaped
     * as `{type: image, source: {type: base64, media_type, data}}` with
     * bare base64 data rather than a `data:` URI. The response comes back
     * as `content[]` blocks rather than `choices[]`, so it's normalized
     * here into the same `choices[0].message.content` shape the rest of
     * the pipeline (Webkul\Lead\Helpers\MagicAI::mapAIDataToLead()) expects
     * from every provider.
     */
    private static function askAnthropic($text, array $images, $model, $apiKey)
    {
        try {
            $content = [];

            if (! empty($text)) {
                $content[] = [
                    'type' => 'text',
                    'text' => $text,
                ];
            }

            foreach ($images as $image) {
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => self::detectImageMime($image),
                        'data' => $image,
                    ],
                ];
            }

            if (empty($content)) {
                throw new Exception(trans('admin::app.leads.file.data-extraction-failed'));
            }

            $response = \Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
            ])->post(self::ANTHROPIC_URL, [
                'model' => $model,
                'max_tokens' => self::ANTHROPIC_MAX_OUTPUT_TOKENS,
                'system' => self::getSystemPrompt(),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $content,
                    ],
                ],
            ]);

            if ($response->failed()) {
                throw new Exception($response->body());
            }

            $data = $response->json();

            if (isset($data['error'])) {
                throw new Exception($data['error']['message']);
            }

            $text = collect($data['content'] ?? [])->firstWhere('type', 'text')['text'] ?? '';

            return [
                'choices' => [
                    [
                        'message' => [
                            'content' => $text,
                        ],
                    ],
                ],
            ];
        } catch (Exception $e) {
            return ['error' => trans('admin::app.leads.file.insufficient-info')];
        }
    }

    /**
     * Sniff a base64 image's real MIME type from its decoded bytes rather
     * than assuming one.
     */
    private static function detectImageMime(string $base64Image): string
    {
        $mime = 'image/png';

        $decoded = base64_decode($base64Image, true);

        if ($decoded) {
            $info = @getimagesizefromstring($decoded);

            if (! empty($info['mime'])) {
                $mime = $info['mime'];
            }
        }

        return $mime;
    }

    /**
     * Wrap a base64 image as a data: URI for the OpenAI-compatible
     * providers, which want the image inline as a URL rather than as
     * separate mime/data fields.
     */
    private static function toImageDataUri(string $base64Image): string
    {
        return 'data:'.self::detectImageMime($base64Image).";base64,{$base64Image}";
    }

    /**
     * Define system prompt for AI processing.
     *
     * @return string System prompt for AI model.
     */
    private static function getSystemPrompt()
    {
        return <<<'PROMPT'
            You are an AI assistant specialized in extracting structured data from a document.
            The user will provide either an image of the document (a scan, a photo, a business card) or its plain text, extracted ahead of time.
            Your task is to accurately extract the following fields. If the value is not available, use the default values provided:

            ### **Output Format:** 
            ```json
            {
                "status": 1,
                "title": "Untitled Lead",
                "lead_value": 0,
                "person": {
                    "name": "Unknown",
                    "emails": {
                        "value": null,
                        "label": null
                    },
                    "contact_numbers": {
                        "value": null,
                        "label": null
                    }
                }
            }
            ```
            ### **Fields to Extract:**
            - **Title:** Title of the lead. Default: "Untitled Lead"
            - **Lead Value:** Value of the lead. Default: 0
            - **Person Name:** Name of the person. Default: "Unknown"
            - **Person Email:** Email of the person. Default: null
            - **Person Email Label:** Label for the email. Default: null
            - **Person Contact Number:** Contact number of the person. Default: null
            - **Person Contact Number Label:** Label for the contact number. Default: null
        PROMPT;
    }

    /**
     * process for encoding and decoding base64 data.
     */
    private static function handleBase64($base64, string $type = 'encode')
    {
        if ($type === 'encode') {
            return base64_encode($base64);
        }

        return base64_decode($base64);
    }
}
