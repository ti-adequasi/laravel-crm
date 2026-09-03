# LeadEnrichment

A manual "Enrich" button on any CRM lead's page — scrapes the linked
Organization's website (falling back to the contact's e-mail domain) and
looks up its CNPJ, posting the result as a note on the lead's timeline.
Reuses `Webkul\LeadGreen\Services\{LeadEnrichmentService,CnpjService}`
rather than duplicating them.

Ported from `ti-adequasi/adequa.crm`'s `Admin/Http/Controllers/Lead/EnrichmentController`
(not part of this repo — see `docs/adequa-crm-feature-notes.md` §2). The
source resolved a lead's own `site_Lead` custom attribute first; this port
drops that (never set up as a custom attribute on this install) and starts
from the Organization's `site` attribute instead — simpler, and every lead
converted through LeadGreen already has one.

## Shape

No `Contract`, `Model`, `Proxy`, or migration — this package has no entity
of its own, so it isn't in `config/concord.php`'s `modules` list either.
Just a `Controller` + one `Route` + a Blade partial injected into the
existing lead page through `view_render_event('admin.leads.view.actions.after', ...)`
(`Webkul\Core\Http\helpers.php`) — the third extension mechanism, alongside
Contract/Proxy override and before/after events, for adding UI to a core
page without editing it. No dedicated ACL entry either: the route
(`admin.leads.enrich`) inherits the `leads` permission automatically through
the Bouncer middleware's nearest-mapped-ancestor fallback, the same way
Sandbox's sub-routes did.
