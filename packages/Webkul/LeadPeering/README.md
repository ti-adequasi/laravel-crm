# LeadPeering

PeeringDB prospecting plus website/CNPJ enrichment — mirrors
[`LeadGreen`](../LeadGreen/README.md)'s architecture and flow for a
different data source: the public directory of network operators (ASNs),
data centers, and organizations behind the internet's own infrastructure,
instead of Google Maps.

Pick what to search for — Network/ISP, Organization, or Data center — then
search → preview → filter (website presence, minimum networks present,
hide already-imported), with a network-type category filter built from this
search's own results once they're back → select individually or in bulk →
choose a pipeline and import: each selected prospect becomes a real CRM
opportunity (Organization + Person + Lead) immediately, at that pipeline's
first stage — importing is never a dead end. The preview keeps every
still-`ok` result, including prospects without a website — they're visible
and filterable, just never importable, since the CRM has no way to reach
them.

Every import still creates its own `LeadPeering` prospect row first (status
`novo` → `convertido`, linked to the opportunity via `opportunity_id`) before
converting it — that row is what CNPJ/website enrichment and the
export/audit trail operate on, conversion doesn't replace it. A prospect
that fails to convert (a transient error) stays in `novo` and can be
converted by hand later from the LeadPeering list, choosing the pipeline
rather than silently using whichever one is flagged default.

## Why a network has no country/city filter

PeeringDB's `net` object (a network operator/ASN) carries no country, city,
or state of its own — confirmed against the live API: `GET /api/net?country=BR`
returns the same unfiltered list a plain `GET /api/net` does, silently
ignoring the parameter rather than erroring. Geography lives on `org`
(the organization that owns the network) and `fac` (the facilities it's
present at) instead. Searching "Network / ISP" therefore hides the
country/city/state fields entirely (with an inline note explaining why)
rather than sending a filter PeeringDB would quietly do nothing with —
switch to Organization or Data center to filter by location.

## Configuration

Configured from **Configuration > PeeringDB Leads** in the admin UI
(`Config/core_config.php` — no `.env` edit required, though
`.env`/`config/services.php` still works as a fallback for an ops-managed
deploy):

- **PeeringDB API key** — optional. Unlike LeadGreen's RapidAPI key,
  PeeringDB allows anonymous read access for org/net/fac search and detail
  requests ("simply omit any authentication" per its own docs — confirmed
  live). Configuring a key only unlocks additional "Users"-visibility
  point-of-contact rows PeeringDB otherwise hides from anonymous requests;
  search itself works fully without one.
- **CNPJá commercial API key** — optional, and a separate setting from
  LeadGreen's own (each self-contained module is configured independently).
  BrasilAPI (free), CNPJá Open (free, ~5 req/min) and ReceitaWS (free,
  ~3 req/min) are tried first, in that order; the paid commercial tier is
  only used as a last resort and capped at a configurable daily credit
  count. The daily-credit cache key is deliberately the exact same one
  LeadGreen's `CnpjService` uses (not scoped to this module) — a business
  realistically has one real CNPJá account, and if the same key ends up
  configured in both modules' settings, they need to share one real daily
  counter rather than each believing it has the full quota alone. See
  `CnpjService::creditCacheKey()`.
- **Detectar política de privacidade e DPO (LGPD)** — on by default; turn
  off for segments where a privacy policy / Data Protection Officer isn't a
  meaningful prospecting signal. Off skips the extra HTTP fetch of the
  privacy-policy page entirely, not just the resulting fields; the
  "Política de privacidade" / "DPO" grid columns hide accordingly. Doesn't
  touch data already gathered while it was on. A separate setting from
  LeadGreen's own identically-named one.

Website enrichment also verifies the picked e-mail via
[Disify](https://www.disify.com/) (free, no key) — a real DNS/MX check plus
disposable-domain detection. Stored as `email_verified`: `true`/`false` when
Disify actually answered, `null` when it didn't (a transient failure is
never recorded as "this e-mail is bad").

## `CnpjService` and `LeadEnrichmentService` are ported, not shared

Both classes are functionally identical to LeadGreen's own — neither has any
real coupling to a specific prospect model (they take/return plain strings
and arrays) — but each self-contained prospecting package owns its own copy
rather than one depending on the other, matching this codebase's module
convention (see `crm-package-development`). If a third such module ever
needs the same logic, that's the point to extract a shared package instead
of a fourth copy.

## Enrichment progress banner

`leadgreen:enrich-pending`'s equivalent here, `leadpeering:enrich-pending`,
runs on a schedule (`routes/console.php`, every minute) rather than
synchronously on import — LeadGreen ships a matching `enrichment-status`
endpoint but never actually wires it to any UI. This module does: the
prospect list shows a small progress banner while anything is still
pending, polling only while a backlog exists and refreshing the grid once
it clears, rather than leaving a freshly-imported batch looking like
nothing is happening.
