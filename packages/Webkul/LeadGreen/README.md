# LeadGreen

Google Maps business prospecting plus website/CNPJ enrichment. Search →
preview → filter (website presence, minimum rating/reviews, hide
already-imported) → select individually or in bulk → import only what's
picked → work the funnel (`novo` → `em_prospeccao` → `convertido` /
`descartado` / `reaproveitavel`) → convert into a real CRM lead (Organization
+ Person + Lead). The preview keeps every non-closed result, including
businesses without a website — they're visible and filterable, just never
importable, since the CRM has no way to reach them.

Ported from a working ad-hoc implementation in a sibling instance
(`ti-adequasi/adequa.crm`, not part of this repo) into the self-contained
module shape documented in
[`crm-package-development`](../../../.github/skills/crm-package-development/SKILL.md).
See `docs/adequa-crm-feature-notes.md` in this repo for the original audit.

## Configuration

Both external integrations are optional and configured from **Configuration
> Lead Green** in the admin UI (`Config/core_config.php` — no `.env` edit
required, though `.env`/`config/services.php` still works as a fallback for
an ops-managed deploy):

- **RapidAPI key** for the `maps-data` product — required for the search
  itself; without it `GoogleMapsService` throws a clear, user-facing error.
- **CNPJá commercial API key** — optional. BrasilAPI (free) and CNPJá Open
  (free, rate-limited) are tried first; the paid commercial tier is only
  used as a last resort and capped at a configurable daily credit count.

## A known flake in the search

The `maps-data` provider occasionally answers a perfectly good query with
`HTTP 200 {"data": []}` — confirmed directly against a real key, retrying
the identical query moments later returned normal results. `GoogleMapsService::search()`
retries up to 3 times on an empty-but-successful response (Laravel's own
`->retry()` only fires on a thrown exception, which a 200 never causes, so
this is handled separately). If a search still comes back empty after that,
it's a real zero-result query, not this flakiness.

## What's deliberately not here

LinkedIn company-data enrichment (a separate, quota-capped, disabled-by-
default tier in the source) was left out — not part of what this pass
scoped. `docs/adequa-crm-feature-notes.md` has the full picture if it's
wanted later.
