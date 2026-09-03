<?php

namespace Webkul\LeadGreen\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CnpjService
{
    /**
     * Extract the first valid CNPJ found in a page's HTML.
     *
     * @param  string  $html
     * @return string|null  digits-only CNPJ (14 chars)
     */
    public function extractCnpj(string $html): ?string
    {
        // Matches formatted (00.000.000/0000-00) and unformatted variants.
        if (! preg_match_all('/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/', $html, $matches)) {
            return null;
        }

        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate);

            if (strlen($digits) === 14 && $this->isValidCnpj($digits)) {
                return $digits;
            }
        }

        return null;
    }

    /**
     * Look up company data on BrasilAPI for a given CNPJ.
     *
     * @param  string  $cnpj  digits-only CNPJ
     * @return array|null  normalized company fields, or null on failure
     */
    public function lookup(string $cnpj): ?array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            return null;
        }

        // 1) BrasilAPI — grátis, sem limite prático.
        if ($data = $this->lookupBrasilApi($cnpj)) {
            return $data;
        }

        // 2) CNPJá Open — grátis (limitada a ~5 req/min), backup da BrasilAPI.
        if ($data = $this->lookupCnpjaOpen($cnpj)) {
            return $data;
        }

        // 3) CNPJá comercial — paga (consome crédito), só quando as grátis falham
        //    e respeitando o teto diário de créditos.
        if ($this->canUseCnpjaCredit()) {
            if ($data = $this->lookupCnpjaCommercial($cnpj)) {
                $this->incrementCnpjaCredit();

                return $data;
            }
        }

        return null;
    }

    /**
     * BrasilAPI lookup (free).
     */
    protected function lookupBrasilApi(string $cnpj): ?array
    {
        try {
            $response = Http::timeout(20)->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");

            if (! $response->successful()) {
                return null;
            }

            return $this->normalizeBrasilApi($cnpj, $response->json());
        } catch (\Throwable $e) {
            Log::info('CnpjService BrasilAPI failed for ' . $cnpj . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * CNPJá Open API lookup (free, rate-limited).
     */
    protected function lookupCnpjaOpen(string $cnpj): ?array
    {
        try {
            $response = Http::timeout(20)->get("https://open.cnpja.com/office/{$cnpj}");

            if (! $response->successful()) {
                return null;
            }

            return $this->normalizeCnpja($cnpj, $response->json());
        } catch (\Throwable $e) {
            Log::info('CnpjService CNPJá Open failed for ' . $cnpj . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * CNPJá commercial API lookup (consumes a daily credit; includes IE).
     */
    protected function lookupCnpjaCommercial(string $cnpj): ?array
    {
        $key = $this->cnpjaKey();

        if (empty($key)) {
            return null;
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders(['Authorization' => $key])
                ->get("https://api.cnpja.com/office/{$cnpj}", ['registrations' => 'BR']);

            if (! $response->successful()) {
                Log::info('CnpjService CNPJá commercial HTTP ' . $response->status() . ' for ' . $cnpj);

                return null;
            }

            return $this->normalizeCnpja($cnpj, $response->json());
        } catch (\Throwable $e) {
            Log::info('CnpjService CNPJá commercial failed for ' . $cnpj . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Whether we may still spend a CNPJá commercial credit today.
     */
    public function canUseCnpjaCredit(): bool
    {
        if (empty($this->cnpjaKey())) {
            return false;
        }

        return $this->cnpjaCreditsUsedToday() < $this->cnpjaDailyLimit();
    }

    /**
     * The CNPJá commercial API key — set from the LeadGreen settings screen
     * (Configuration > Lead Green), falling back to `.env`/`config('services.cnpja.key')`
     * for an ops-managed deploy that prefers not to store secrets in the database.
     */
    protected function cnpjaKey(): ?string
    {
        return core()->getConfigData('lead_green.settings.api_keys.cnpja_api_key')
            ?: config('services.cnpja.key');
    }

    protected function cnpjaDailyLimit(): int
    {
        return (int) (core()->getConfigData('lead_green.settings.api_keys.cnpja_daily_limit')
            ?: config('services.cnpja.daily_limit', 45));
    }

    /**
     * How many CNPJá commercial credits were spent today.
     */
    public function cnpjaCreditsUsedToday(): int
    {
        return (int) Cache::get($this->creditCacheKey(), 0);
    }

    /**
     * Register a spent CNPJá commercial credit (resets at midnight).
     */
    protected function incrementCnpjaCredit(): void
    {
        $key = $this->creditCacheKey();

        Cache::put($key, $this->cnpjaCreditsUsedToday() + 1, now()->endOfDay());
    }

    /**
     * Per-day cache key for the credit counter.
     */
    protected function creditCacheKey(): string
    {
        return 'cnpja_credits_' . now()->format('Y-m-d');
    }

    /**
     * Normalize the CNPJá (Open/commercial) payload into our column set.
     */
    protected function normalizeCnpja(string $cnpj, array $d): array
    {
        $company = $d['company'] ?? [];

        $socios = [];
        foreach ($company['members'] ?? [] as $member) {
            $socios[] = [
                'nome'         => $member['person']['name'] ?? null,
                'qualificacao' => $member['role']['text'] ?? null,
            ];
        }

        $phone = null;
        if (! empty($d['phones'][0])) {
            $phone = trim(($d['phones'][0]['area'] ?? '') . ($d['phones'][0]['number'] ?? ''));
        }

        // Inscrição Estadual — prefer the first enabled registration (CNPJá commercial only).
        $ie = null;
        $registrations = $d['registrations'] ?? [];
        $chosen = collect($registrations)->firstWhere('enabled', true) ?? ($registrations[0] ?? null);
        if ($chosen) {
            $ie = trim(($chosen['number'] ?? '') . (! empty($chosen['state']) ? ' (' . $chosen['state'] . ')' : ''));
        }

        return [
            'cnpj'               => $this->format($cnpj),
            'razao_social'       => $this->str($company['name'] ?? null),
            'nome_fantasia'      => $this->str($d['alias'] ?? null),
            'situacao_cadastral' => $this->str(($d['status'] ?? [])['text'] ?? null),
            'data_abertura'      => $this->str($d['founded'] ?? null),
            'cnae_code'          => $this->str(($d['mainActivity'] ?? [])['id'] ?? null),
            'cnae_description'   => $this->str(($d['mainActivity'] ?? [])['text'] ?? null),
            'inscricao_estadual' => $this->str($ie),
            'porte'              => $this->str(($company['size'] ?? [])['text'] ?? null),
            'natureza_juridica'  => $this->str(($company['nature'] ?? [])['text'] ?? null),
            'capital_social'     => is_numeric($company['equity'] ?? null) ? $company['equity'] : null,
            'company_phone'      => $this->str($phone),
            'company_email'      => $this->str($d['emails'][0]['address'] ?? null),
            'opcao_simples'      => isset($company['simples']['optant']) ? (bool) $company['simples']['optant'] : null,
            'opcao_mei'          => isset($company['simei']['optant']) ? (bool) $company['simei']['optant'] : null,
            'socios'             => $socios,
            'company_data_at'    => now(),
        ];
    }

    /**
     * Normalize the BrasilAPI payload into our column set.
     */
    protected function normalizeBrasilApi(string $cnpj, array $d): array
    {
        $socios = [];

        foreach ($d['qsa'] ?? [] as $socio) {
            $socios[] = [
                'nome'         => $socio['nome_socio'] ?? null,
                'qualificacao' => $socio['qualificacao_socio'] ?? null,
            ];
        }

        $phone = $d['ddd_telefone_1'] ?? null;

        return [
            'cnpj'               => $this->format($cnpj),
            'razao_social'       => $this->str($d['razao_social'] ?? null),
            'nome_fantasia'      => $this->str($d['nome_fantasia'] ?? null),
            'situacao_cadastral' => $this->str($d['descricao_situacao_cadastral'] ?? null),
            'data_abertura'      => $this->str($d['data_inicio_atividade'] ?? null),
            'cnae_code'          => $this->str($d['cnae_fiscal'] ?? null),
            'cnae_description'   => $this->str($d['cnae_fiscal_descricao'] ?? null),
            'porte'              => $this->str($d['porte'] ?? null),
            'natureza_juridica'  => $this->str($d['natureza_juridica'] ?? null),
            'capital_social'     => is_numeric($d['capital_social'] ?? null) ? $d['capital_social'] : null,
            'company_phone'      => $this->str(is_scalar($phone ?? null) ? $phone : null),
            'company_email'      => $this->str(is_scalar($d['email'] ?? null) ? ($d['email'] ?? null) : null),
            'opcao_simples'      => $this->bool($d['opcao_pelo_simples'] ?? null),
            'opcao_mei'          => $this->bool($d['opcao_pelo_mei'] ?? null),
            'socios'             => $socios,
            'company_data_at'    => now(),
        ];
    }

    /**
     * Format digits into 00.000.000/0000-00.
     */
    protected function format(string $cnpj): string
    {
        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cnpj);
    }

    /**
     * Safe scalar-to-string (returns null for non-scalars/empties).
     */
    protected function str($value): ?string
    {
        if ($value === null || $value === '' || ! is_scalar($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Coerce BrasilAPI boolean-ish values.
     */
    protected function bool($value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    /**
     * Validate a CNPJ using its two check digits.
     */
    public function isValidCnpj(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $calc = function (string $base, array $weights): int {
            $sum = 0;

            foreach (str_split($base) as $i => $digit) {
                $sum += (int) $digit * $weights[$i];
            }

            $rest = $sum % 11;

            return $rest < 2 ? 0 : 11 - $rest;
        };

        $d1 = $calc(substr($cnpj, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $d2 = $calc(substr($cnpj, 0, 13), [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        return (int) $cnpj[12] === $d1 && (int) $cnpj[13] === $d2;
    }
}
