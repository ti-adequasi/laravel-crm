<?php

namespace Webkul\LeadGreen\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsService
{
    /**
     * Search businesses on Google Maps through the RapidAPI maps-data provider.
     *
     * @param  string  $query  e.g. "Escolas em Mogi das Cruzes - SP"
     * @param  int  $limit  number of results to fetch (provider max ~300)
     * @param  int  $zoom  map zoom level used by the provider
     * @return array  list of raw business results (the API "data" array)
     *
     * @throws \RuntimeException when the API key is missing or the request fails
     */
    public function search(string $query, int $limit = 100, int $zoom = 13): array
    {
        $key = $this->apiKey();
        $host = $this->apiHost();

        if (empty($key)) {
            throw new \RuntimeException(trans('leadgreen::app.search.error.no-api-key'));
        }

        // The underlying scraper occasionally answers a legitimate query with
        // an empty result set (HTTP 200, {"data": []}) rather than an error —
        // observed directly against a real key, not just a theoretical case.
        // Laravel's own ->retry() only re-runs on a thrown exception, which a
        // 200 response never causes, so an empty-but-successful answer is
        // retried here explicitly before it's treated as "no results."
        $attempts = 3;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $response = Http::timeout(120)
                ->retry(2, 5000)
                ->withHeaders([
                    'x-rapidapi-host' => $host,
                    'x-rapidapi-key' => $key,
                ])
                ->get("https://{$host}/searchmaps.php", [
                    'query' => $query,
                    'country' => 'br',
                    'lang' => 'pt',
                    'zoom' => $zoom,
                    'limit' => $limit,
                ]);

            if ($response->failed()) {
                Log::error('LeadGreen GoogleMapsService request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException(trans('leadgreen::app.search.error.request-failed', [
                    'status' => $response->status(),
                ]));
            }

            $data = $response->json('data');

            if (! empty($data)) {
                return $data;
            }

            if ($attempt < $attempts) {
                Log::info('LeadGreen GoogleMapsService got an empty result set, retrying', [
                    'query' => $query,
                    'attempt' => $attempt,
                ]);

                sleep(2);
            }
        }

        return [];
    }

    /**
     * The RapidAPI key — set from the LeadGreen settings screen
     * (Configuration > Lead Green), falling back to `.env`/`config('services.rapidapi_maps.key')`
     * for an ops-managed deploy that prefers not to store secrets in the database.
     */
    protected function apiKey(): ?string
    {
        return core()->getConfigData('lead_green.settings.api_keys.rapidapi_maps_key')
            ?: config('services.rapidapi_maps.key');
    }

    protected function apiHost(): string
    {
        return core()->getConfigData('lead_green.settings.api_keys.rapidapi_maps_host')
            ?: config('services.rapidapi_maps.host', 'maps-data.p.rapidapi.com');
    }
}
