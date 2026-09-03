<?php

namespace Webkul\LeadGreen\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * JSON cast that tolerates legacy double-encoded values.
 *
 * A source that stores JSON encoded twice (e.g. "\"[\\\"a\\\"]\"") would decode
 * only once under a plain `array` cast and yield a string instead of an array.
 * This cast decodes again when the first pass still returns a string.
 */
class SafeJsonCast implements CastsAttributes
{
    /**
     * Cast the stored value to an array.
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        // Double-encoded values decode to a JSON string on the first pass.
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Prepare the value for storage (encode exactly once).
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        // Already a JSON string: store as-is to avoid re-encoding.
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value);
    }
}
