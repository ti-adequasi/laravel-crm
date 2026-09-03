<?php

namespace Webkul\LeadGreen\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadEnrichmentService
{
    /**
     * Common contact pages to probe when the homepage has no e-mail.
     *
     * @var array
     */
    protected array $contactPaths = ['contato', 'contact', 'fale-conosco', 'sobre', 'quem-somos'];

    /**
     * Generic e-mail domains that should not be treated as the company domain.
     *
     * @var array
     */
    protected array $genericMailDomains = [
        'gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'yahoo.com.br',
        'bol.com.br', 'uol.com.br', 'live.com', 'icloud.com', 'terra.com.br',
    ];

    /**
     * Hosts that are contact links / social profiles / maps — not a company site.
     * The privacy policy / DPO of these belongs to the platform, not the lead.
     *
     * @var array
     */
    protected array $nonCompanyHosts = [
        'wa.me', 'whatsapp.com', 'api.whatsapp.com', 'chat.whatsapp.com',
        'm.me', 'messenger.com', 't.me', 'telegram.me', 'telegram.org',
        'instagram.com', 'facebook.com', 'fb.com', 'fb.me', 'fb.watch',
        'linkedin.com', 'twitter.com', 'x.com', 'tiktok.com',
        'youtube.com', 'youtu.be', 'goo.gl', 'maps.app.goo.gl', 'g.page',
    ];

    /**
     * Link-aggregator hosts (the company's real site is usually linked inside).
     *
     * @var array
     */
    protected array $aggregatorHosts = [
        'linktr.ee', 'linktree.com', 'bio.link', 'beacons.ai', 'beacons.page',
        'campsite.bio', 'linkin.bio', 'lnk.bio', 'about.me', 'flow.page',
        'znap.link', 'linklist.bio', 'tap.bio', 'solo.to', 'msha.ke',
        'linkme.bio', 'biolinky.co', 'linke.to', 'many.link',
    ];

    /**
     * Enrich a single lead from its website.
     *
     * @param  string  $website
     * @return array  enrichment fields (email, socials, whatsapp, score, status)
     */
    public function enrichFromWebsite(string $website): array
    {
        $result = [
            'email'              => null,
            'email_source'       => null,
            'email_quality'      => null,
            'emails_found'       => [],
            'instagram'          => null,
            'facebook'           => null,
            'linkedin'           => null,
            'whatsapp'           => null,
            'has_privacy_policy' => false,
            'privacy_policy_url' => null,
            'has_dpo'            => false,
            'dpo_name'          => null,
            'dpo_email'         => null,
            'cnpj'              => null,
            'enrichment_status' => 'failed',
            'enrichment_score'  => 0,
        ];

        $base = $this->normalizeUrl($website);

        if (! $base) {
            $result['enrichment_status'] = 'failed';

            return $result;
        }

        $host = parse_url($base, PHP_URL_HOST) ?: '';

        $emails  = [];
        $socials = ['instagram' => null, 'facebook' => null, 'linkedin' => null];
        $whatsapp = null;
        $homeHtml = null;
        $privacyUrl = null;
        $cnpj = null;

        // The "website" is actually a contact link / social profile (wa.me,
        // instagram, maps...). Capture the contact from the URL itself, but do
        // NOT scrape it — its privacy policy / DPO belongs to the platform.
        if ($this->hostMatches($host, $this->nonCompanyHosts)) {
            $result = array_merge($result, $this->contactFromUrl($base));
            $result['enrichment_score']  = $this->score($result);
            $result['enrichment_status'] = $result['enrichment_score'] > 0 ? 'enriched' : 'empty';

            return $result;
        }

        // The "website" is a link aggregator (Linktree etc.). Its own links are
        // the company's, so capture them — and try to reach the real company
        // site behind it, which is what we should actually scrape.
        if ($this->hostMatches($host, $this->aggregatorHosts)) {
            $aggHtml = $this->fetch($base);

            if ($aggHtml === null) {
                return $result;
            }

            $whatsapp            = $this->extractWhatsapp($aggHtml);
            $socials['instagram'] = $this->extractSocial($aggHtml, 'instagram');
            $socials['facebook']  = $this->extractSocial($aggHtml, 'facebook');
            $socials['linkedin']  = $this->extractLinkedin($aggHtml);

            $companyUrl = $this->findCompanyLink($aggHtml, $base);

            if (! $companyUrl) {
                // No real company site behind it: keep the contacts, skip privacy/DPO.
                $result['instagram'] = $socials['instagram'];
                $result['facebook']  = $socials['facebook'];
                $result['linkedin']  = $socials['linkedin'];
                $result['whatsapp']  = $whatsapp;
                $result['enrichment_score']  = $this->score($result);
                $result['enrichment_status'] = $result['enrichment_score'] > 0 ? 'enriched' : 'empty';

                return $result;
            }

            // Switch to the company's own site for the full analysis.
            $base = $companyUrl;
            $host = parse_url($base, PHP_URL_HOST) ?: '';
        }

        $domain = $this->rootDomain($host);

        // Fetch the homepage first to discover real internal contact links.
        $homeHtml = $this->fetch($base);

        // Resilience: if the given host fails (SSL/timeout/redirect), try the
        // www/apex variant — many sites only serve one of them reliably.
        if ($homeHtml === null) {
            $alt = $this->toggleWww($base);

            if ($alt && ($altHtml = $this->fetch($alt)) !== null) {
                $base     = $alt;
                $host     = parse_url($base, PHP_URL_HOST) ?: $host;
                $domain   = $this->rootDomain($host);
                $homeHtml = $altHtml;
            }
        }

        $urls = [$base];

        if ($homeHtml !== null) {
            // Real internal links (contato, equipe, sobre, time...) win over guesses.
            $urls = array_merge($urls, $this->extractInternalLinks($homeHtml, $base));
        }

        // Fall back to common guessed contact paths.
        $urls = array_merge($urls, array_map(
            fn ($p) => rtrim($base, '/') . '/' . $p,
            $this->contactPaths
        ));

        // Visit at most 6 distinct pages.
        $urls = array_slice(array_values(array_unique($urls)), 0, 6);

        foreach ($urls as $index => $url) {
            $html = $index === 0 ? $homeHtml : $this->fetch($url);

            if ($html === null) {
                continue;
            }

            $emails = array_merge(
                $emails,
                $this->extractEmails($html),
                $this->extractJsonLdEmails($html)
            );

            // Capture socials/whatsapp only from the first reachable page (homepage usually).
            if (! $socials['instagram']) {
                $socials['instagram'] = $this->extractSocial($html, 'instagram');
            }
            if (! $socials['facebook']) {
                $socials['facebook'] = $this->extractSocial($html, 'facebook');
            }
            if (! $socials['linkedin']) {
                $socials['linkedin'] = $this->extractLinkedin($html);
            }
            if (! $whatsapp) {
                $whatsapp = $this->extractWhatsapp($html);
            }

            // Structured data (JSON-LD) often carries phone + social links.
            $jsonLd = $this->extractJsonLdContacts($html);
            if (! $whatsapp && ! empty($jsonLd['phone'])) {
                $whatsapp = $jsonLd['phone'];
            }
            foreach (['instagram', 'facebook', 'linkedin'] as $net) {
                if (! $socials[$net] && ! empty($jsonLd[$net])) {
                    $socials[$net] = $jsonLd[$net];
                }
            }

            // Look for a privacy policy link on any visited page.
            if (! $privacyUrl) {
                $privacyUrl = $this->extractPrivacyLink($html, $base);
            }

            // Look for the company's CNPJ (usually in the footer).
            if (! $cnpj) {
                $cnpj = app(CnpjService::class)->extractCnpj($html);
            }

            // Visit every discovered page (capped at 6) to collect all e-mails.
        }

        $emails = array_values(array_unique($emails));
        $email  = $this->pickBestEmail($emails, $domain);

        $result['email']         = $email;
        $result['email_source']  = $email ? 'site' : null;
        $result['email_quality'] = $email ? $this->classifyEmail($email, $domain) : null;
        $result['emails_found']  = $this->rankEmails($emails, $domain);
        $result['instagram']    = $socials['instagram'];
        $result['facebook']     = $socials['facebook'];
        $result['linkedin']     = $socials['linkedin'];
        $result['whatsapp']     = $whatsapp;
        $result['cnpj']         = $cnpj;

        // Privacy policy & DPO (Encarregado LGPD) analysis.
        $result['has_privacy_policy'] = ! empty($privacyUrl);
        $result['privacy_policy_url'] = $privacyUrl;
        $result['has_dpo']            = false;
        $result['dpo_name']           = null;
        $result['dpo_email']          = null;

        if ($privacyUrl) {
            $privacyHtml = $this->fetch($privacyUrl);

            if ($privacyHtml !== null) {
                $dpo = $this->analyzePrivacyPolicy($privacyHtml, $domain);

                $result['has_dpo']   = $dpo['has_dpo'];
                $result['dpo_name']  = $dpo['dpo_name'];
                $result['dpo_email'] = $dpo['dpo_email'];
            }
        }

        $result['enrichment_score']  = $this->score($result);
        $result['enrichment_status'] = $result['enrichment_score'] > 0 ? 'enriched' : 'empty';

        return $result;
    }

    /**
     * Find a privacy-policy URL among the page's links.
     */
    protected function extractPrivacyLink(string $html, string $base): ?string
    {
        // Match anchors whose href or text mentions privacy / LGPD / data protection.
        if (! preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $needle = '/(pol[ií]tica[\s\-_]*de[\s\-_]*privacidade|privacidade|privacy[\s\-_]*policy|privacy|lgpd|prote[cç][aã]o[\s\-_]*de[\s\-_]*dados)/iu';

        foreach ($matches as $m) {
            $href = $m[1];
            $text = strip_tags($m[2]);

            if (preg_match($needle, $href) || preg_match($needle, $text)) {
                return $this->resolveUrl($href, $base);
            }
        }

        return null;
    }

    /**
     * Inspect a privacy-policy page for a named DPO / Encarregado (LGPD).
     *
     * @return array{has_dpo: bool, dpo_name: ?string, dpo_email: ?string}
     */
    protected function analyzePrivacyPolicy(string $html, string $domain): array
    {
        $out = ['has_dpo' => false, 'dpo_name' => null, 'dpo_email' => null];

        // Normalize to plain text for proximity/name matching.
        $text = preg_replace('/\s+/u', ' ', trim(strip_tags($html)));

        $hasMention = (bool) preg_match('/\b(encarregad[oa]|data\s+protection\s+officer|\bdpo\b)/iu', $text);

        if (! $hasMention) {
            return $out;
        }

        // 1) Try to capture a name right after the role label.
        $name = '([\p{Lu}][\p{L}\'\-]+(?:\s+(?:d[aeo]s?\s+)?[\p{Lu}][\p{L}\'\-]+){1,4})';

        $namePatterns = [
            '/(?i:nome\s+do[\s\(]*encarregad[oa][\s\)]*)\s*[:\-–]\s*' . $name . '/u',
            '/(?i:encarregad[oa](?:\s+(?:de\s+(?:prote[cç][aã]o\s+de\s+)?dados|pelo\s+tratamento\s+de\s+dados\s+pessoais))?(?:\s*\(?\s*dpo\s*\)?)?)\s*[:\-–]\s*' . $name . '/u',
            '/(?i:data\s+protection\s+officer|\bdpo\b)\s*[:\-–]\s*' . $name . '/u',
        ];

        foreach ($namePatterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $out['dpo_name'] = trim($m[1]);
                break;
            }
        }

        // 2) Find the DPO e-mail: prefer role-based locals, then proximity to the mention.
        $out['dpo_email'] = $this->extractDpoEmail($html, $text, $domain);

        // Only flag a DPO when one is actually identifiable (named or contactable),
        // not merely because the word "encarregado" appears somewhere.
        $out['has_dpo'] = $out['dpo_name'] !== null || $out['dpo_email'] !== null;

        return $out;
    }

    /**
     * Extract the DPO / Encarregado e-mail from the privacy page.
     */
    protected function extractDpoEmail(string $html, string $text, string $domain): ?string
    {
        $emails = array_unique($this->extractEmails($html));

        if (empty($emails)) {
            return null;
        }

        // 1) Role-based local part (dpo@, encarregado@, privacidade@, lgpd@...).
        foreach ($emails as $email) {
            if (preg_match('/^(dpo|encarregad[oa]?|privacidade|lgpd|protecao(de)?dados|dataprotection|dados)@/i', $email)) {
                return $email;
            }
        }

        // 2) E-mail appearing closest to an "encarregado/dpo" mention in the text.
        if (preg_match('/\b(encarregad[oa]|data\s+protection\s+officer|\bdpo\b)/iu', $text, $mm, PREG_OFFSET_CAPTURE)) {
            $anchor = $mm[0][1];
            $best = null;
            $bestDist = PHP_INT_MAX;

            foreach ($emails as $email) {
                $pos = mb_stripos($text, $email);

                if ($pos !== false) {
                    $dist = abs($pos - $anchor);

                    if ($dist < $bestDist && $dist < 400) {
                        $bestDist = $dist;
                        $best = $email;
                    }
                }
            }

            if ($best) {
                return $best;
            }
        }

        // 3) Fall back to a company-domain e-mail if present.
        if ($domain) {
            foreach ($emails as $email) {
                if (str_ends_with($email, '@' . $domain)) {
                    return $email;
                }
            }
        }

        return null;
    }

    /**
     * Resolve a possibly-relative href against the site base URL.
     */
    protected function resolveUrl(string $href, string $base): string
    {
        $href = trim($href);

        if (preg_match('/^https?:\/\//i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'http';

            return $scheme . ':' . $href;
        }

        $root = rtrim(parse_url($base, PHP_URL_SCHEME) . '://' . parse_url($base, PHP_URL_HOST), '/');

        if (str_starts_with($href, '/')) {
            return $root . $href;
        }

        return $root . '/' . ltrim($href, '/');
    }

    /**
     * Fetch a URL and return its HTML, or null on failure.
     */
    protected function fetch(string $url): ?string
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; AdequaCRM-LeadEnricher/1.0)',
                    'Accept'     => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }
        } catch (\Throwable $e) {
            Log::info('LeadEnrichment fetch failed: ' . $url . ' - ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Extract e-mail addresses from HTML (mailto links + plain text).
     */
    protected function extractEmails(string $html): array
    {
        $emails = [];

        // mailto: links (most reliable)
        if (preg_match_all('/mailto:([^"\'?>\s]+)/i', $html, $m)) {
            $emails = array_merge($emails, $m[1]);
        }

        // plain e-mails in the markup
        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $html, $m)) {
            $emails = array_merge($emails, $m[0]);
        }

        // Clean, lowercase and drop obvious asset/file false-positives and placeholders.
        return array_values(array_filter(array_map(function ($e) {
            $e = strtolower(trim(rawurldecode($e)));

            if (preg_match('/\.(png|jpg|jpeg|gif|svg|webp)$/i', $e)) {
                return null;
            }

            return $this->isJunkEmail($e) ? null : $e;
        }, $emails)));
    }

    /**
     * Detect placeholder / tracking / template e-mails that are not real contacts.
     */
    protected function isJunkEmail(string $email): bool
    {
        $junkPatterns = [
            '/@sentry\./i',
            '/@(example|exemplo|dominio|seudominio|domain|email|mail|test|teste)\.[a-z.]+$/i',
            '/^(email|e-mail|seuemail|seu-email|exemplo|example|teste|test|nome|name|your|youremail|usuario|user|info@dominio)@/i',
            '/@2x\./i',
            '/\.(wixpress|w3\.org|schema\.org)/i',
        ];

        foreach ($junkPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Choose the best e-mail by quality ranking (role > person > on-domain > catch-all).
     */
    protected function pickBestEmail(array $emails, string $domain): ?string
    {
        $ranked = $this->rankEmails($emails, $domain);

        return $ranked[0] ?? null;
    }

    /**
     * Rank all e-mails best-first by their qualitative tier.
     *
     * @return array  ordered list of e-mails
     */
    protected function rankEmails(array $emails, string $domain): array
    {
        $emails = array_values(array_unique($emails));

        if (empty($emails)) {
            return [];
        }

        // Lower weight = better.
        $weights = [
            'role'         => 0,
            'person'       => 1,
            'domain_other' => 2,
            'catchall'     => 3,
            'generic'      => 4,
            'other'        => 5,
        ];

        usort($emails, function ($a, $b) use ($domain, $weights) {
            $wa = $weights[$this->classifyEmail($a, $domain)] ?? 5;
            $wb = $weights[$this->classifyEmail($b, $domain)] ?? 5;

            return $wa <=> $wb;
        });

        return $emails;
    }

    /**
     * Classify an e-mail by how useful it is as a sales contact.
     *
     * role         -> contato@, comercial@, vendas@... (best)
     * person       -> joao.silva@dominio (named person on company domain)
     * domain_other -> other address on the company domain
     * catchall     -> dominio@dominio (local part equals the domain name)
     * generic      -> gmail/hotmail/...
     * other        -> anything else
     */
    protected function classifyEmail(string $email, string $domain): string
    {
        $email = strtolower($email);
        [$local, $emailDomain] = array_pad(explode('@', $email, 2), 2, '');

        $roleLocals = [
            'contato', 'contato1', 'comercial', 'vendas', 'venda', 'atendimento',
            'faleconosco', 'fale', 'sac', 'financeiro', 'secretaria', 'administrativo',
            'adm', 'administracao', 'diretoria', 'gerencia', 'marketing', 'rh',
            'recepcao', 'orcamento', 'orcamentos', 'suporte', 'info', 'contabilidade',
        ];

        $onDomain = $domain && ($emailDomain === $domain || str_ends_with($emailDomain, '.' . $domain));

        if (in_array($local, $roleLocals, true)) {
            return 'role';
        }

        // Person-like: "firstname.lastname" or "firstname_lastname".
        if ($onDomain && preg_match('/^[a-z]+[._][a-z]+$/', $local)) {
            return 'person';
        }

        // Catch-all: the local part is just the domain's own name.
        $domainLabel = explode('.', $emailDomain)[0] ?? '';
        if ($onDomain && $local === $domainLabel) {
            return 'catchall';
        }

        if ($onDomain) {
            return 'domain_other';
        }

        if (in_array($emailDomain, $this->genericMailDomains, true)) {
            return 'generic';
        }

        return 'other';
    }

    /**
     * Discover internal links likely to expose contacts (contato, equipe, sobre...).
     *
     * @return array  absolute URLs
     */
    protected function extractInternalLinks(string $html, string $base): array
    {
        if (! preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\']/i', $html, $m)) {
            return [];
        }

        $host = parse_url($base, PHP_URL_HOST);
        $needle = '/(contato|contact|fale[\-_]?conosco|equipe|time|sobre|about|quem[\-_]?somos|nossa[\-_]?empresa|institucional)/i';

        $links = [];

        foreach ($m[1] as $href) {
            // Skip assets and anchors.
            if (preg_match('/\.(css|js|png|jpe?g|gif|svg|webp|pdf|ico)(\?|$)/i', $href)) {
                continue;
            }

            if (! preg_match($needle, $href)) {
                continue;
            }

            $url = $this->resolveUrl($href, $base);

            // Keep only same-host links.
            if (parse_url($url, PHP_URL_HOST) === $host) {
                $links[strtok($url, '#')] = true;
            }
        }

        return array_keys($links);
    }

    /**
     * Extract e-mails declared in JSON-LD / schema.org markup.
     *
     * @return array
     */
    protected function extractJsonLdEmails(string $html): array
    {
        $emails = [];

        // Matches "email": "x@y.z" and mailto: inside structured data.
        if (preg_match_all('/"email"\s*:\s*"(?:mailto:)?([^"]+@[^"]+)"/i', $html, $m)) {
            foreach ($m[1] as $e) {
                $e = strtolower(trim($e));

                if (! $this->isJunkEmail($e)) {
                    $emails[] = $e;
                }
            }
        }

        return $emails;
    }

    /**
     * Extract phone + social links from JSON-LD structured data.
     *
     * @return array{phone: ?string, instagram: ?string, facebook: ?string, linkedin: ?string}
     */
    protected function extractJsonLdContacts(string $html): array
    {
        $out = ['phone' => null, 'instagram' => null, 'facebook' => null, 'linkedin' => null];

        // "telephone": "+55 11 ..."
        if (preg_match('/"telephone"\s*:\s*"([^"]+)"/i', $html, $m)) {
            $digits = preg_replace('/\D/', '', $m[1]);

            if (strlen($digits) >= 8) {
                $out['phone'] = $digits;
            }
        }

        // "sameAs": ["https://instagram.com/...", "https://facebook.com/..."]
        if (preg_match_all('#https?://(?:www\.)?(instagram|facebook|linkedin)\.com/[^"\\\\\s]+#i', $html, $mm)) {
            foreach ($mm[0] as $i => $url) {
                $net = strtolower($mm[1][$i]);

                if (! $out[$net]) {
                    $out[$net] = rtrim(preg_replace('#\\\\/#', '/', $url), '/');
                }
            }
        }

        return $out;
    }

    /**
     * Toggle the www. prefix on a URL (www <-> apex) for fetch fallback.
     */
    protected function toggleWww(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        $newHost = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host;

        return str_replace('//' . $host, '//' . $newHost, $url);
    }

    /**
     * Extract an Instagram/Facebook profile URL.
     */
    protected function extractSocial(string $html, string $network): ?string
    {
        $pattern = $network === 'instagram'
            ? '/https?:\/\/(?:www\.)?instagram\.com\/([a-z0-9_.]+)/i'
            : '/https?:\/\/(?:www\.)?facebook\.com\/([a-z0-9_.\-\/]+)/i';

        if (preg_match($pattern, $html, $m)) {
            $handle = rtrim($m[1], '/');

            // Ignore sharer/plugin/generic paths.
            if (in_array(strtolower($handle), ['sharer', 'sharer.php', 'plugins', 'tr', 'share'], true)) {
                return null;
            }

            return $network === 'instagram'
                ? 'https://instagram.com/' . $handle
                : 'https://facebook.com/' . $handle;
        }

        return null;
    }

    /**
     * Extract a LinkedIn company/profile URL.
     */
    protected function extractLinkedin(string $html): ?string
    {
        // Capture the full slug up to a real delimiter (quote, space, query, fragment),
        // so accents / percent-encoding don't truncate it.
        if (preg_match('/https?:\/\/(?:[a-z]{2,3}\.)?linkedin\.com\/(company|in|school)\/([^"\'\s?#<>\\\\]+)/i', $html, $m)) {
            return 'https://linkedin.com/' . strtolower($m[1]) . '/' . rtrim($m[2], '/');
        }

        return null;
    }

    /**
     * Extract a WhatsApp number from wa.me / api.whatsapp.com links.
     */
    protected function extractWhatsapp(string $html): ?string
    {
        if (preg_match('/(?:wa\.me\/|api\.whatsapp\.com\/send\?phone=)(\+?\d{8,15})/i', $html, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Whether a host equals or is a subdomain of any entry in the list.
     */
    protected function hostMatches(string $host, array $list): bool
    {
        $host = strtolower($host);

        foreach ($list as $entry) {
            if ($host === $entry || str_ends_with($host, '.' . $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract a single contact (whatsapp / social) directly from a link URL.
     *
     * Used when the lead's "website" is itself a wa.me / instagram / facebook URL.
     *
     * @return array  partial result fields
     */
    protected function contactFromUrl(string $url): array
    {
        $out = [];

        // WhatsApp number
        if (preg_match('/(?:wa\.me\/|whatsapp\.com\/send\/?\?phone=|api\.whatsapp\.com\/send\?phone=)(\+?\d{8,15})/i', $url, $m)) {
            $out['whatsapp'] = $m[1];
        }

        if (preg_match('/instagram\.com\/([a-z0-9_.]+)/i', $url, $m) && ! in_array(strtolower($m[1]), ['p', 'reel', 'explore'], true)) {
            $out['instagram'] = 'https://instagram.com/' . rtrim($m[1], '/');
        }

        if (preg_match('/facebook\.com\/([a-z0-9_.\-\/]+)/i', $url, $m)) {
            $handle = rtrim($m[1], '/');

            if (! in_array(strtolower($handle), ['sharer', 'sharer.php', 'plugins', 'tr'], true)) {
                $out['facebook'] = 'https://facebook.com/' . $handle;
            }
        }

        if (preg_match('/linkedin\.com\/(company|in)\/([a-z0-9_.\-]+)/i', $url, $m)) {
            $out['linkedin'] = 'https://linkedin.com/' . strtolower($m[1]) . '/' . rtrim($m[2], '/');
        }

        return $out;
    }

    /**
     * From an aggregator (Linktree) page, find the company's own website link.
     */
    protected function findCompanyLink(string $html, string $base): ?string
    {
        if (! preg_match_all('/href=["\'](https?:\/\/[^"\']+)["\']/i', $html, $m)) {
            return null;
        }

        $baseHost = parse_url($base, PHP_URL_HOST);

        foreach ($m[1] as $href) {
            $host = parse_url($href, PHP_URL_HOST);

            if (! $host || $host === $baseHost) {
                continue;
            }

            // Skip platforms, other aggregators and assets.
            if ($this->hostMatches($host, $this->nonCompanyHosts)
                || $this->hostMatches($host, $this->aggregatorHosts)
                || preg_match('/\.(css|js|png|jpe?g|gif|svg|webp|ico)(\?|$)/i', $href)) {
                continue;
            }

            // First remaining external link is treated as the company site.
            return strtok($href, '#');
        }

        return null;
    }

    /**
     * Compute an enrichment score (0-100) from the collected fields.
     */
    protected function score(array $data): int
    {
        $score = 0;
        $score += $data['email'] ? 40 : 0;
        $score += $data['whatsapp'] ? 20 : 0;
        $score += $data['instagram'] ? 15 : 0;
        $score += $data['facebook'] ? 15 : 0;
        $score += $data['linkedin'] ? 10 : 0;

        return min($score, 100);
    }

    /**
     * Normalize a website value into a fetchable URL.
     */
    protected function normalizeUrl(string $website): ?string
    {
        $website = trim($website);

        if ($website === '') {
            return null;
        }

        if (! Str::startsWith($website, ['http://', 'https://'])) {
            $website = 'http://' . $website;
        }

        return filter_var($website, FILTER_VALIDATE_URL) ? $website : null;
    }

    /**
     * Reduce a host to its root domain (drops a leading "www.").
     */
    protected function rootDomain(string $host): string
    {
        return Str::startsWith($host, 'www.') ? substr($host, 4) : $host;
    }
}
