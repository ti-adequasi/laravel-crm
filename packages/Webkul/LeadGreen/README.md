# LeadGreen

Google Maps business prospecting plus website/CNPJ enrichment. Search →
preview → filter (website presence, phone, minimum rating/reviews, verified,
hide temporarily closed, hide already-imported) before searching, then a category filter
built from this search's own results once they're back → select individually
or in bulk → choose a pipeline and import: each selected business becomes a
real CRM opportunity (Organization + Person + Lead) immediately, at that
pipeline's first stage — importing is never a dead end. The preview keeps
every non-closed result, including businesses without a website — they're
visible and filterable, just never importable, since the CRM has no way to
reach them.

Every import still creates its own `LeadGreen` prospect row first (status
`novo` → `convertido`, linked to the opportunity via `opportunity_id`) before
converting it — that row is what CNPJ/website enrichment and the export/audit
trail operate on, conversion doesn't replace it. A prospect that fails to
convert (a transient error) stays in `novo` and can be converted by hand later
from the Lead Green list, which now also lets you choose the pipeline instead
of silently using whichever one is flagged default.

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
- **CNPJá commercial API key** — optional. BrasilAPI (free), CNPJá Open
  (free, ~5 req/min) and ReceitaWS (free, ~3 req/min) are tried first, in
  that order; the paid commercial tier is only used as a last resort and
  capped at a configurable daily credit count.
- **Detectar política de privacidade e DPO (LGPD)** — on by default; turn off
  for segments where a privacy policy / Data Protection Officer isn't a
  meaningful prospecting signal. Read by `LeadEnrichmentService::enrichFromWebsite()`,
  shared by both Lead Green's own enrichment and the standalone
  `LeadEnrichment` "Enrich" button on a regular CRM lead — one setting covers
  both. Off skips the extra HTTP fetch of the privacy-policy page entirely,
  not just the resulting fields; the "Política de privacidade" / "DPO" grid
  columns hide accordingly. Doesn't touch data already gathered while it was on.

Website enrichment also verifies the picked e-mail via [Disify](https://www.disify.com/)
(free, no key) — a real DNS/MX check plus disposable-domain detection, not
just the regex-based "does this look like a role/person address" scoring
`classifyEmail()` already did. Stored as `email_verified`: `true`/`false` when
Disify actually answered, `null` when it didn't (a transient failure is never
recorded as "this e-mail is bad"). Shown as a badge next to the e-mail in the
prospect detail modal.

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
