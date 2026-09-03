# Feature notes from adequa.crm

Source: [`ti-adequasi/adequa.crm`](https://github.com/ti-adequasi/adequa.crm) — a
sibling Krayin instance (Laravel 10, not 12) with real, valuable customizations
built without the module pattern documented in
[`crm-package-development`](../.github/skills/crm-package-development/SKILL.md).
Explored 2026-09-03 to decide what's worth porting into this repo, properly
packaged. Every recommendation below assumes that skill's recipe.

None of this is built yet except §1 — this is the "what and why," not a plan
with dates.

---

## 1. LeadGreen — Google Maps lead prospecting ✅ Built

Done, as `packages/Webkul/LeadGreen` — see its
[README](../packages/Webkul/LeadGreen/README.md). Ported prospecting *and*
enrichment together (the user chose not to phase them); LinkedIn enrichment
was left out. API keys are configured from **Configuration > Lead Green** in
the admin UI, not `.env` — a deliberate improvement over the source, and now
documented in the skill under "Admin-Configurable Settings". Tests:
`tests/Feature/LeadGreenTest.php`.

<details>
<summary>Original audit (for reference — the section below describes the *source*, not what was built)</summary>

An SDR searches free text ("Escolas em Mogi das Cruzes - SP"), the CRM calls a
Google Maps business-search API, previews results (name, phone, rating,
hours, website, geo), filters closed/no-website businesses, dedupes against
already-imported leads, and queues them (`novo` → `em prospecção` →
`convertido`/`descartado`/`reaproveitável`) so two SDRs never work the same

An SDR searches free text ("Escolas em Mogi das Cruzes - SP"), the CRM calls a
Google Maps business-search API, previews results (name, phone, rating,
hours, website, geo), filters closed/no-website businesses, dedupes against
already-imported leads, and queues them (`novo` → `em prospecção` →
`convertido`/`descartado`/`reaproveitável`) so two SDRs never work the same
lead. One click converts a row into a real Organization + Person + Lead.
Replaced a prior n8n workflow that did the same scraping externally.

- API: a RapidAPI Maps scraper (not the official Places API) —
  `LeadGreen/src/Services/GoogleMapsService.php`. Needs `RAPIDAPI_MAPS_KEY`.
- Conversion: `LeadGreen/src/Repositories/LeadGreenRepository.php::convertToLead()`.
- **Closest to "did it right" of everything found** — has its own Models,
  Repositories, Services, Migrations. It just stopped halfway: Controller,
  routes, views, DataGrid, `acl.php`, `menu.php` all live in
  `packages/Webkul/Admin` instead of inside `LeadGreen` itself. Not
  registered in `config/concord.php`'s `modules` array. Its
  `loadViewsFrom`/`loadTranslationsFrom` calls point at a `Resources/`
  directory that doesn't exist (dead code). No `CREATE TABLE` migration for
  its own table (`base_lead_google`) exists — it predates the package.

**To port:** finish the move — Controller/routes/views/DataGrid/ACL/menu into
the package, add the missing `CREATE TABLE` migration, register the
`ModuleServiceProvider`, fix or delete the dead resource-loading calls.

</details>

---

## 2. Lead enrichment ✅ Engine built (as part of §1) — manual trigger on regular leads still open

The engine (site scraping + CNPJ cascade) and its use on LeadGreen prospects
shipped with §1 — `enrich(int $id)` on `LeadGreenController`, scheduled via
`leadgreen:enrich-pending`. **Not built**: the separate "Enriquecer dados"
button on a *regular* (non-Google-sourced) CRM lead's own page, which in the
source lived in `Admin/Http/Controllers/Lead/EnrichmentController.php` — that
would still mean touching `packages/Webkul/Admin` today; a `lead.create.after`
event listener (the skill's second extension mechanism) is the cleaner way to
get it without editing core, if it's still wanted.

<details>
<summary>Original audit</summary>

Turns a bare name + website into a qualified profile. Scrapes the site for
emails (ranked, generic providers penalized), Instagram/Facebook/LinkedIn,
WhatsApp contact, LGPD signals (privacy policy + DPO), and looks up Brazilian
company registry data through a cascade — **BrasilAPI (free) → CNPJá Open
(free, rate-limited) → CNPJá commercial (paid, daily credit cap)**. Runs
automatically for Google-sourced leads (scheduled command) and on-demand for
any lead via an "Enriquecer dados" button that posts the result as an
Activity/Note on the timeline.

- Engine: `LeadGreen/src/Services/LeadEnrichmentService.php` (pure HTTP
  scraping, no LLM) + `LeadGreen/src/Services/CnpjService.php`. Both clean,
  package-local — worth keeping close to as-is.
- Manual trigger: `Admin/Http/Controllers/Lead/EnrichmentController.php` —
  added directly inside Admin's **core** Lead controllers folder, with a
  directly-edited route and a directly-edited `leads/view.blade.php`.

**To port:** move the controller/route/button into LeadGreen (or a dedicated
`packages/Webkul/LeadEnrichment` if it should apply beyond Google-sourced
leads), resolve the engine through the package's own provider instead of an
ad hoc cross-package `app()` call. Since the manual path only ever writes a
note, a `lead.create.after` listener could replace the button entirely if
"auto-enrich every new lead with a site" is ever wanted.

</details>

---

## 3. Kanban card: "time since last activity" badge

A small color-coded badge on every lead card (gray/amber/red by days since
last activity), with a tooltip. Genuinely useful signal for triage.

- Backed by `Lead::getLastActivityDaysAttribute()` — **added directly onto
  the shipped `packages/Webkul/Lead/src/Models/Lead.php`**. This is exactly
  the case the skill's Contract/Proxy override exists for.
- Also exposed through a directly-edited `Admin/Http/Resources/LeadResource.php`.

**To port:** a package-local `Lead extends \Webkul\Lead\Models\Lead` with the
accessor, registered against the `Lead` contract in your own
`ModuleServiceProvider` (last-registered-wins, per the skill). The kanban
Blade file has no clean override point, but it does fire
`view_render_event('admin.leads.index.kanban.content.before')` and similar —
isolate the badge markup into a partial included through one of those instead
of editing the card markup inline.

*(Riding in the same commit, unrelated to the card itself: pagination was
dropped from the kanban data endpoint — `paginate(10)` → `get()` with a
`limit: 99999` on the frontend. Real scale risk once a stage holds thousands
of leads; worth its own fix independent of anything here.)*

---

## 4. Stage Forms — mandatory per-stage capture

Attach a custom form to a specific pipeline stage; dragging a card into that
stage blocks (via a modal) until the form is submitted, if required — e.g.
"must capture loss reason before moving to Lost." A real, generically
reusable feature, not Google/enrichment-specific.

- New entities added straight into **core** `packages/Webkul/Lead`:
  `StageForm`/`StageFormResponse` models, contracts, repositories.
- Controllers/routes/views/grid added straight into **core** Admin.
- The frontend handler exists in **three** separate JS files plus a
  **duplicate copy inline inside `kanban.blade.php`** — two copies of the
  same logic that can silently drift apart.
- A daily cleanup job registered directly in `app/Console/Kernel.php`.

**To port:** a single self-contained `packages/Webkul/StageForm` — this is a
new feature/entity, not an extension of an existing one, so per the skill it
shouldn't touch core Lead or core Admin at all. Delete the inline
kanban-embedded handler copy in favor of the one external implementation;
move the cleanup schedule into the package's own provider.

---

## Lower priority / flagged, not detailed

- **PBX / click-to-call (RVR Telecom)** — two generations exist (a WebRTC
  softphone, since superseded by a server-side REST integration), neither
  packaged. Lives in `app/Models`, `app/Services/Pbx` (outside the package
  system entirely), adds a column directly to the core `users` table. Real
  feature, needs its own `packages/Webkul/Pbx` if it's worth porting — ask
  before investing time here, it's a bigger lift than the four above.
- **Multi-tenancy** — planning docs only (`documentacao/MULTITENANCY_PLANO.md`),
  no code. Worth building new packages tenant-aware from day one if this is
  coming, nothing to port yet.
- **2FA** — present (`pragmarx/google2fa-laravel`) but predates this fork's
  own commit history; likely inherited from whatever Krayin snapshot it
  started from, not an Adequa-built feature.
- **No WhatsApp messaging** — "WhatsApp" only appears as a contact-link
  detector inside the enrichment scraper. No outbound integration exists to
  port.

## Hygiene notes (not features, just flagged)

adequa.crm has committed compiled Blade view caches, left debug
`\Log::info()` calls dumping full request payloads in a production
controller, and ~10 ad hoc planning `.md` files sprawled at the repo root —
worth avoiding here as these packages get built. This very file is a
deliberate exception (one file, one location, superseded once the features
above are actually built — delete it then rather than letting it go stale).
