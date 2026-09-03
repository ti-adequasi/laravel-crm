# LeadGreen

Google Maps business prospecting plus website/CNPJ enrichment. Search →
preview → import → work the funnel (`novo` → `em_prospeccao` → `convertido`
/ `descartado` / `reaproveitavel`) → convert into a real CRM lead
(Organization + Person + Lead).

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

## What's deliberately not here

LinkedIn company-data enrichment (a separate, quota-capped, disabled-by-
default tier in the source) was left out — not part of what this pass
scoped. `docs/adequa-crm-feature-notes.md` has the full picture if it's
wanted later.
