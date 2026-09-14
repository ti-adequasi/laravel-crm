<?php

namespace Webkul\LeadPeering\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the public PeeringDB REST API
 * (https://www.peeringdb.com/apidocs/, spec at
 * https://docs.peeringdb.com/api_specs/).
 *
 * Unlike GoogleMapsService's RapidAPI scraper, this is PeeringDB's own,
 * stable, officially documented API: "In order to access the API as a
 * guest simply omit any authentication" (confirmed live — org/net/fac list
 * and detail requests all succeed with no key at all). An API key is
 * therefore optional here, not required — configuring one only unlocks
 * "Users"-visibility point-of-contact rows in addition to "Public" ones
 * (PeeringDB's own poc visibility tiers), it does not gate org/net/fac
 * search or detail requests, and search()/get() below work with none set.
 */
class PeeringDbService
{
    public const BASE_URL = 'https://www.peeringdb.com/api';

    /**
     * The three PeeringDB object types this module prospects against.
     * (PeeringDB also has ix/ixlan/ixpfx/netfac/netixlan/campus/carrier —
     * out of scope: those describe relationships/infrastructure, not a
     * prospectable company.)
     */
    public const TYPES = ['org', 'net', 'fac'];

    /**
     * Search one object type with PeeringDB's own filter syntax already
     * built by the caller (e.g. ['name__contains' => 'Equinix', 'country'
     * => 'BR']) — see LeadPeeringController::buildFilters(), which is the
     * one place that knows which user-facing fields are valid for which
     * type (a 'net' object has no country/city of its own; passing one is
     * silently ignored by the API rather than erroring, so the filter is
     * never built for that type in the first place — see the note on
     * LeadPeeringController::buildFilters()).
     *
     * @return array list of raw PeeringDB objects (the response's "data" array)
     *
     * @throws \InvalidArgumentException on an unknown object type
     * @throws \RuntimeException when the request fails
     */
    public function search(string $type, array $filters = [], int $limit = 50): array
    {
        $this->assertValidType($type);

        $query = array_merge($filters, ['limit' => max(1, min($limit, 300))]);

        $response = $this->client()->get(self::BASE_URL.'/'.$type, $query);

        if ($response->failed()) {
            Log::error('LeadPeering PeeringDbService request failed', [
                'type' => $type,
                'query' => $query,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException(trans('leadpeering::app.search.error.request-failed', [
                'status' => $response->status(),
            ]));
        }

        return $response->json('data') ?? [];
    }

    /**
     * Fetch a single object by id, optionally expanding related sets.
     *
     * @param  int  $depth  0 = don't expand (default); 1-2 expand nested
     *                      sets to ids/objects respectively — see the API
     *                      spec. Not used by the search/import flow today
     *                      (a flat, un-expanded result is enough to build
     *                      a prospect row), kept as a documented building
     *                      block for a future detail view that wants a
     *                      facility's own network list in one call.
     */
    public function get(string $type, int $id, int $depth = 0): ?array
    {
        $this->assertValidType($type);

        $response = $this->client()->get(self::BASE_URL."/{$type}/{$id}", ['depth' => $depth]);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json('data');

        return $data[0] ?? null;
    }

    /**
     * A pre-configured HTTP client — adds the Authorization header only
     * when a key is actually set, since (unlike GoogleMapsService) a
     * missing key is not an error condition here.
     */
    protected function client()
    {
        $client = Http::timeout(30)->retry(2, 1000);

        $key = $this->apiKey();

        return $key ? $client->withHeaders(['Authorization' => "Api-Key {$key}"]) : $client;
    }

    protected function assertValidType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("Unknown PeeringDB object type: {$type}");
        }
    }

    /**
     * The PeeringDB API key — set from the LeadPeering settings screen
     * (Configuration > PeeringDB Leads), falling back to
     * `.env`/`config('services.peeringdb.key')` for an ops-managed deploy
     * that prefers not to store secrets in the database. Entirely optional
     * — see the class docblock.
     */
    protected function apiKey(): ?string
    {
        return core()->getConfigData('lead_peering.settings.api_keys.peeringdb_api_key')
            ?: config('services.peeringdb.key');
    }
}
