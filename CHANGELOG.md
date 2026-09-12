# CHANGELOG for 2.2

This changelog consists of the bug & security fixes and new features being included in the releases listed below.

## **v2.2.7 (Upcoming)**

* [feature] PBX Inovalen integration, first step: each tenant can now connect its own PBX domain under Configurações > PBX by pasting the API key generated in the PBX's own panel (Conta > API key) — a "Testar Conexão" button confirms the key resolves to the right domain before saving, with no side effects either way. Every tenant on this install shares the same physical PBX server; the API key alone is what tells the two apart, so a tenant that hasn't connected its own key sees the feature as simply not configured — it never falls back to another tenant's (or a shared default) connection the way some other per-tenant settings deliberately do.

* [feature] PBX Inovalen integration, second step: each user can now be given a phone extension ("Ramal") on the Usuários settings screen, shown on the list and editable by the user themselves as well as an administrator. When the acting user's tenant has a PBX connected, the typed extension is checked against that tenant's own real extension directory before it's accepted — a typo or a reassigned extension is caught at save time rather than only discovered later trying to place a call; a PBX outage while saving doesn't block the save.

* [feature] PBX Inovalen integration, third step: a "Ligar" click-to-call button next to each phone number on a Lead's Person panel, using the acting user's own extension — no audio in the browser, it rings the agent's own desk phone first, then bridges to the number once answered. A new "Fazer Ligações" permission (Configurações > Perfis, under Negócios) controls who can use it; the button itself only shows up for a user who has both that permission and their own extension configured. Every call is tracked so only the person who placed it can check its status or hang it up, and — configurable per tenant, on by default, next to the PBX connection settings — is automatically added to the Lead's timeline as a Call activity the moment it ends, with the correct start/end time filled in even if the browser tab was already closed by then.

* [enhancement] The Usuários list's desktop table lost its own column alignment (Grupo/Status/Criado Em/Ações each shown one column off, with Ações' own icons missing entirely) the moment the "Ramal" column was added in the previous release — fixed; the mobile card view was never affected.

* [enhancement] The PBX "Testar Conexão" button showed the PBX's own raw technical error response (embedded JSON and all) on a failed connection instead of a clean message — fixed, matching how every other error on that screen already reads. The PBX Settings breadcrumb also now correctly shows under "Configurações" like every other settings screen, instead of directly under "Início".

* [feature] Multi-tenancy: a new super-admin-only Tenants screen (Configurações-level, bottom of the sidebar) to create and manage tenants, and per-tenant data isolation is now actually enforced. A user assigned to a tenant only ever sees and creates that tenant's own data — leads, contacts, products, quotes, emails, and everything else across ~40 tables — on every list screen (Kanban, DataGrids, exports), on any record opened directly by its URL, and on anything new they create, which is stamped with their own tenant automatically. A super-admin (no tenant assigned) keeps seeing everything, unfiltered, across every tenant, exactly as before this feature existed — every user and every row that predates this change has a blank tenant, so nothing already in the system changed hands or became invisible. Not yet tenant-isolated, and staying shared on purpose for now: system-wide configuration (API keys, Magic AI settings), uploaded file storage paths, and the scheduled background commands (inbound email, Lead Green enrichment, campaigns) — later steps of this same effort.

* [feature] Tenant management now lives at its own `/super_admin` URL (moved off `/admin/tenants`), so it never mixes into a tenant-scoped admin's own sidebar or pages — a tenant-scoped user no longer even sees the "Tenants" link (it's hidden the same way any other menu item is, just on tenant status rather than a permission). Creating a tenant now provisions everything it needs to actually be used in one step: its own default pipeline (with starter stages), an administrator role, and its first user — previously a brand-new tenant had a bare row and nothing else, and its first login would fail the moment it touched Leads. Each tenant's edit screen also lists its users and can add more to it directly, reusing the same provisioned role.

* [feature] System configuration (Configurações > Ajustes) is now tenant-aware — a tenant can set its own Magic AI API key, model, or any other setting without ever affecting the system-wide default or any other tenant's own override. A tenant that hasn't configured a given setting keeps falling back to the global value exactly as before this feature existed, and a super-admin's own save is that same global value, unchanged.

* [fixed] Fixed every save on the Configurações > Ajustes page silently failing with no visible error whenever the Magic AI "Outro Modelo" field was on the page (i.e., anytime Magic AI was enabled) — introduced by this same effort's own earlier `required_unless` validation rule for that field, which doesn't exist in this codebase's client-side validation library and crashed it outright before the request could even be sent. Fixed by implementing the rule and wiring it to read the sibling field it depends on.

* [fixed] The two scheduled background commands that touch tenant-owned data (`leadgreen:enrich-pending`, `campaign:process`) now run once per active tenant instead of once globally. Previously a large tenant's own backlog could consume `leadgreen:enrich-pending`'s entire per-run limit before a smaller tenant's prospects were ever reached; more seriously, `campaign:process` could send a tenant's own marketing campaign to every OTHER tenant's contacts too, since without a tenant ever bound during the scheduled run, nothing scoped either query to its owner's data. Inbound email processing is unaffected — it reads from one shared mailbox for the whole system, a separate, unresolved limitation this change doesn't touch.

* [security] Every file a tenant uploads or generates — activity attachments, avatars, custom-attribute files/images, configuration uploads (logos, etc.), rich-text editor images, email attachments, and data-import files/error reports — now stores under that tenant's own `tenants/{id}/...` path instead of a bare, shared one. This is collision-avoidance and obscurity, not access control by itself: most of these live on the public disk, so an unguessable path is still directly fetchable by anyone who guesses or enumerates it. Real protection comes from each upload's owning record already refusing a cross-tenant id at the database layer (the same per-tenant isolation as everywhere else in the system) — except configuration uploads, which deliberately don't use that mechanism (they need a global-default fallback a strict filter would break), so their download endpoint now checks the tenant explicitly instead. Nothing already on disk needs to move; every existing file's un-prefixed path keeps resolving exactly as it does today.

* [security] A systematic pass across every tenant-owned model, list screen, direct-record URL, and export found and closed three real cross-tenant gaps that predated this pass (i.e., existed since multi-tenancy's own earlier steps, not introduced by them): (1) four packages (Lead Green, lead website/CNPJ enrichment, per-user e-mail accounts, embeddable web forms) register their admin routes independently of the rest of the admin panel and had never picked up tenant scoping at all — Lead Green in particular was fully exposed, letting any tenant list, view, convert, enrich, discard or export any other tenant's prospects by id, and the lead-enrichment endpoint let any tenant enrich (and read back data from) any other tenant's lead the same way; (2) a public web form's submission handler stamped every Lead/Person it created with no tenant at all instead of the form's own, so real submissions from a tenant's own embedded form were silently invisible to that tenant afterward — a data-loss bug riding on the same gap, not just an exposure; (3) the e-mail thread shown on a Lead's or Person's "Atividades" tab was built from a raw query with no tenant filter of its own, mixing another tenant's e-mails into the current tenant's timeline for a guessed or enumerated lead/person id. Also fixed in passing: the Organizations list silently stopped applying its own (non-tenant) per-user visibility restriction due to unreachable code, found while auditing that same list.

* [feature] Each admin can now connect their own outbound SMTP account under Minha Conta > Minha Conta de E-mail, so emails they send from the CRM go out from their own address instead of always using the system's default mailer. Includes a live connection test before saving. Falls back to the system default automatically for anyone who hasn't configured one — nothing changes for existing users until they opt in. The account's password is encrypted at rest (Laravel's native `encrypted` cast), unlike the existing system-wide IMAP password field, which is a pre-existing gap stored in plain text and left as-is here since it's a separate, unrelated setting.

* [fixed] Fixed the scheduled `inbound-emails:process` command logging a guaranteed failure every 5 minutes on any install still using the default `sendgrid` mail receiver driver. Sendgrid's inbound processor is deliberately webhook-only (Sendgrid pushes mail in, rather than being polled for it) and has always thrown on the bulk/polled processing this scheduled command performs — there was no configuration that could make this succeed. The scheduled run is now skipped entirely while the driver is `sendgrid`, and resumes automatically for anyone who switches to `webklex-imap`.

* [feature] Added a "days in stage" badge to every card on the Leads Kanban board, showing how long the card has been sitting in its current stage — turns red once it passes 14 days. This is distinct from the existing "rotten days" indicator, which measures overall lead age against a fixed per-pipeline threshold set from lead creation, not time in the current stage; a lead can be freshly moved into a stage yet still show as rotten if it's old overall, or genuinely stuck in one stage for weeks without ever crossing the rotten threshold. Stage entry time is stamped the moment a lead is created or its stage actually changes (including via Kanban drag-and-drop), and is left untouched by saves that don't change the stage.

* [feature] Magic AI can now connect to OpenAI, Anthropic (Claude), or a self-hosted Omniroute gateway, in addition to OpenRouter — pick the provider under Configurações > Ajustes > Magic AI, and only the settings that provider actually needs are shown (e.g. the Omniroute base URL field stays hidden unless Omniroute is selected). OpenRouter, OpenAI and Omniroute all speak the same chat-completions request format, so only the endpoint and API key change between them; Anthropic's Messages API uses its own request shape (`x-api-key` header, system prompt as a top-level field, different image-block format) and its response is normalized back to the shape the rest of the lead-extraction pipeline already expects, so no other code had to change. Each provider reads its model name from its own field — OpenRouter's model presets are never used as a fallback for another provider, since they're in OpenRouter's own vendor-prefixed format (e.g. `openai/gpt-4o-mini`) which the other providers reject outright.

* [fixed] Fixed Magic AI's "Adicionar Negócio Usando AI" file upload silently failing (or returning garbage) for any image upload (JPG/PNG/BMP/WEBP), and for a scanned/image-only PDF with no extractable text layer. `MagicAIService::ask()` always sent only the first prompt element as a plain `text` content block — for an image with no PDF text extracted, that put the raw base64 image data into the model as if it were prose to read, never as an actual image, regardless of which model was configured. Now builds real multimodal content: a `text` block only when there's real extracted text, plus a proper `image_url` data-URI block per image (MIME type sniffed from the decoded bytes). Text-only PDF uploads were unaffected and still work the same way.

* [fixed] Fixed Lead Green's pre-search filters (website presence, minimum rating, minimum review count, hide already-imported) only being visible after running a search. They now appear on the search form itself, before searching.

* [fixed] Fixed the Lead Green results preview modal rendering without its backdrop, centering or two-column layout. The Admin package's Tailwind build only scanned its own views for class usage, so utility classes used exclusively by satellite packages (arbitrary values like `z-[9999]`, opacity modifiers like `bg-black/50`) were silently dropped from the compiled CSS. The build now scans every `packages/Webkul/*` package.

* [feature] Added a "Filtros de busca" section to Lead Green's search form, grouping every filter that can be set before running a search (website presence, minimum rating, minimum reviews, has-phone, hide temporarily closed, verified only, hide already-imported) so all of it is visible and decided before clicking Buscar. Added a category filter as a clearly separate second step after the results come back — click-to-toggle chips built from the real Google Maps categories found in the current result set (e.g. "Escola pública", "Pré-escola", "Escola de idiomas"), each showing how many results carry it. Category can't be offered before searching: the provider has no type/category request parameter (confirmed against the live API — passing one is silently ignored), so which categories exist is only knowable from a real result set.

* [feature] Lead Green's "Importar" now creates real CRM opportunities directly, in a pipeline chosen at import time, instead of leaving imported businesses stranded as prospects that needed a separate manual conversion. The pipeline is picked once for the whole batch and every opportunity lands at that pipeline's first stage. The one-at-a-time "Converter em lead" action (for prospects imported before this change) was upgraded the same way — a small dialog with a pipeline choice, replacing a plain confirmation popup that silently used whichever pipeline happened to be flagged default.

* [fixed] Fixed Lead Green's "Converter em lead" and "Descartar" row actions never appearing for prospects that could actually still be converted or discarded. The status column's own cell renderer overwrote the row's status field with its badge HTML before the actions column read it, so the actions column's eligibility check compared against markup instead of the raw status and always failed.

* [fixed] Fixed person creation crashing with an "Undefined array key" error whenever every phone number on the person filtered out as empty (e.g. converting a Lead Green prospect with no phone number, or saving a lead/contact form with a blank phone row). `PersonRepository` built the person's dedupe key by unconditionally reading the first remaining phone number after filtering, even when filtering left nothing.

* [fixed] Fixed an already-converted Lead Green prospect having no way back to the opportunity it became — only a "Visualizar" action that showed the original scraped data, with no link out. The status badge and a new row action now link straight to the opportunity, and the detail modal opens with a banner ("Já convertido em oportunidade") linking there too.

* [feature] Added a "Detectar política de privacidade e DPO (LGPD)" toggle under Configuration > Lead Green > Enrichment, on by default. Lead Green's LGPD-specific enrichment (privacy-policy detection, DPO/Encarregado lookup) was a hardcoded assumption that only fits one kind of prospecting client; it's now optional, for using the CRM across other segments too. Turning it off skips the extra page fetch entirely, not just the resulting fields, and hides the corresponding grid columns. The same toggle also governs the standalone `LeadEnrichment` "Enrich" button on a regular CRM lead, since both share the same enrichment service.

* [feature] Website enrichment now verifies the picked e-mail via Disify (free, no key) — a real DNS/MX check plus disposable-domain detection, shown as a "Verificado" badge next to the e-mail in a prospect's detail modal. Shared by Lead Green and the standalone `LeadEnrichment` "Enrich" button, same as every other enrichment signal.

* [feature] Added ReceitaWS as a third free CNPJ lookup source, tried after BrasilAPI and CNPJá Open and before ever spending a paid CNPJá commercial credit.

* [fixed] Fixed `email_quality` being written as `classifyEmail()`'s category string (`"role"`, `"person"`, ...) into a `tinyint` column instead of its numeric rank — every enrichment was silently storing a truncated/invalid value. Now stores the same weight `rankEmails()` already sorts by.

* [fixed] Fixed the four non-select Lead Green search filters (phone, closed status, verified, hide-duplicates) reading as loose, unlabeled checkboxes dropped after the Site/Nota/Reviews controls, with no shared visual identity and no consistent wrap behaviour at narrower widths. Grouped under a shared "Outros" label matching the other controls' shape.

* [fixed] Fixed the Lead Green prospect list overlapping and clipping text (badges touching, names splitting mid-word) at ordinary laptop widths (1024–1280px), and wrapping its row actions onto two lines even at full width for any prospect with all three actions available. The grid exposed 12 equal-width columns where most Krayin grids run 5–7; State, Category and Review Count are now hidden by default (still filterable, and Category is already visible as chips on the search page before import) and Rating/Reviews are shown as one combined cell ("★ 4.4 (3683)").

* [fixed] Fixed Lead Green's "Converter em lead" row action reusing `icon-add` — the icon Admin uses everywhere else specifically for "create a new X" — for an action that transforms an existing prospect instead. Swapped to `icon-forward`, matching the "view opportunity" action already using it for a converted prospect.

* [fixed] Fixed the "Convertido" status badge being a real link to the resulting opportunity with no visual indication that it's clickable, identical at rest to every other, non-clickable badge on the page. Added an underline.

* [fixed] Fixed Lead Green's search filters at real phone widths: the three filter selects (Site, Nota mínima, Mínimo de avaliações) were squeezed three-across into a cramped row instead of stacking, and the "Outros" checkbox group wrapped inconsistently (sometimes one per line, sometimes two, depending on label length). Both now stack one full-width control per line below the `sm` breakpoint and keep the existing compact row layout on tablet/desktop.

* [enhancement] Reorganized Lead Green's search filters from a flat row of seven unrelated controls into three labeled, visually distinct groups — Alcance (site, phone), Qualidade (rating, reviews, verified), Situação (closed, already-imported) — each grouped by the question it actually answers rather than by control type.

* [feature] Renamed "Lead Green" to "Leads Google" (menu, page titles, Settings tab) — the underlying package, routes and database table keep their original technical names; only the user-facing label changed.

## **v2.2.6 (19th of Aug 2026)**

* [fixed] Added the missing Chinese translations for the users grid's associated group column.

* [fixed] Fixed flaky admin end-to-end tests around organization owner lookup, lead creation and rich-text comment fields.

* [security] Fixed SVG sanitization bypasses in media and configuration file uploads.

* [security] Secured installer APIs.

* [Security] Fix security releated issue.

## **v2.2.5 (4th of Aug 2026)**

* #2631[fixed] Fixed the persons CSV import creating duplicate records and dropping select attribute values on re-import. Existing people are now updated by matched email regardless of a changed phone or organization (so the reported count is accurate), and select/multiselect option labels (or ids) are resolved to their option ids instead of being stored as `0`.

* #2630[fixed] Fixed date attributes in the persons CSV import silently saving as `0000-00-00`. Spreadsheet serial numbers and regional formats such as `DD/MM/YYYY` are now normalised to a valid date, and a value that cannot be parsed is reported as a row error instead of being stored as a zero date.

* [feature] Added Chinese (Simplified) `zh_CN` translation for the Admin, Installer, DataTransfer, WebForm and Core packages.

* [feature] Added a configurable default dashboard date range — 1 month, 3 months, 9 months, 1 year, 2 years or a custom number of days — under Configuration > General > Settings > Dashboard Configurations.

* [fixed] Fixed menu item names set in Configuration not applying to section pages, breadcrumbs and the mobile sidebar. Previously only the desktop sidebar reflected a rename.

* [fixed] Fixed renaming the "Mail" and "Contacts" menu items having no effect anywhere, as their configuration fields did not match the actual menu keys.

* [fixed] Fixed the dashboard date range label omitting the year on ranges spanning more than one calendar year, which rendered as "30 Jul - 30 Jul".

* [fixed] Fixed Arabic DataTransfer translations never loading, as the file was named `ar/ar.php` instead of `ar/app.php`.

* [fixed] Fixed the missing Korean translation for the "None" input validation option on the create and edit attribute forms.

* [enhancement] Moved the Core and DataTransfer package translations into the Admin package. Only packages that ship their own Blade views now carry a `Resources/lang` directory.

* [enhancement] Reduced database queries on every admin page by loading the configured menu names in a single query instead of one per menu item.

* [enhancement] Documented the localization convention in the `crm-package-development` agent skill and in AGENTS.md.
* [feature] Added a collapse/expand toggle to the admin sidebar, matching the Bagisto admin. The choice is remembered across page loads, and page content now reflows to the sidebar width instead of being overlaid by it. The sidebar no longer expands on hover; it is controlled by the toggle only.
* #2614[security] Fixed unauthenticated installer access and executable email attachment upload vulnerabilities.

* #2612[feature] Added import and export support for custom attributes for Leads and Persons.

* #2609[feature] Added Google Contacts export for Persons with Google account connection, duplicate detection, queued export progress, and result summary.

* #2608[fixed] Added missing "none" key to the Korean locale for attribute validation.

* #2606[feature] Added a collapse/expand toggle to the admin sidebar, matching the Bagisto admin. The choice is remembered across page loads, and page content now reflows to the sidebar width instead of being overlaid by it. The sidebar no longer expands on hover; it is controlled by the toggle only.

* #2606[feature] Added an option to show or hide the "Powered by" bar under Configuration > General > Settings > Powered by Section Configurations.

* #2603[feature] Added Korean translations for the Installer, DataTransfer, WebForm, and Core packages.

* #2602[feature] Added Korean translation support for the Admin package.

* #2600[fixed] Fixed invalid activity calendar .ics date-times by emitting UTC RFC 5545 values.

* #2592[security] Fixed webhook validation to reject internal endpoint URLs.

* #2580[enhancement] Added a "None" option to input validation for text attributes.

## **v2.2.4 (20th of July 2026)** *Release*

* #2590[fixed] Fixed page does not refresh after creating a record via Quick Add.

* #2589[fixed] Fixed Quick Add not working for users with group and individual permissions.

* #2582[fixed] Fixed pipeline field visible on public webform.

* #2581[enhancement] Fixed responsive UI issues when page is zoomed.

* #2579[feature] Allow group selection for individual view permission users.

* #2575[enhancement] Added previous month's sales update in Kanban view.

* #2573[enhancement] Added dashboard support for multiple pipelines.

* #2572[enhancement] Added filter by tag option in Contacts > Persons.

* #2571[fixed] Fixed issue with lead creation.

* #2570[fixed] Fixed auto-fill lead email issue.

* #2567[fixed] Fixed IDOR agent record access control vulnerability.

* #2563[fixed] Fixed Kanban infinite scroll duplicates issue.

* #2583[fixed] Fixed SQL injection in rotten lead filter.

* #2585[security] Fixed unrestricted file upload vulnerability (CVE-2026-38526).

* #2559[fixed] Fixed agent record access control issue.

* #2556[fixed] Fixed installation config save issue.

* #2550[fixed] Fixed Kanban infinite scroll duplicates.

* #2549[enhancement] Added support tab feature.

* #2548[enhancement] Allow search by phone and email when creating a lead.

* #2546[feature] Quick Attribute now available at lead form.

* #2545[feature] Added agent skills functionality.

* #2544[enhancement] Added validate skills.

* #2543[enhancement] Added Agents Skills folder.

* #2542[fixed] Fixed stored XSS vulnerability in notes field.

* #2541[fixed] Fixed quote description truncation issue.

* #2539[fixed] Fixed lost revenue arrow UI issue.

* #2538[fixed] Fixed missing translations.

* #2501[fixed] Fixed sales owner not saved in organization.

* #2500[fixed] Fixed activities date filter range issue.

* #2479[fixed] Fixed textarea field not rendered in WebForm.

* #2471[fixed] Fixed missing translations for lead won/lost modal.

* #2420[fixed] Added missing mega search translations for settings and configurations.

* #2533[fixed] Fixed GUI installation issue.

* #2419[security] Fixed stored XSS vulnerability in notes field.

* #2454[fixed] Fixed quote description truncation.

* #2407[fixed] Fixed missing translations.

* #2157[fixed] Fixed auto-fill lead email when creating a lead.

* #2258[fixed] Fixed issue with same-as-billing-address field.

## **v2.2.3 (1st of May 2026)** *Release*

* [fixed] Pipline critical issue resolved.

## **v2.2.2 (1st of May 2026)** *Release*

* [fixed] Update Change Log and version.

## **v2.2.1 (1st of May 2026)** *Release*

* [fixed] Quote fields now auto-fill correctly when a quote is linked to a lead.

* [fixed] Fixed price formatting issue.

* [fixed] Fixed Lead Kanban list ordering.

* [fixed] Fixed header block position at the top.

* [fixed] Updated Activity UI.

* [fixed] Admins can now view and share quote details to a person from the quote list.

* [fixed] Fixed submission issue on the web form.

* [fixed] Fixed activity display issue in the Calendar view.

* [fixed] Logo update issue resolved.

* [enhancement] Drag-and-drop support added to Activity. Admins can now change date and time directly from the Calendar view.

* [feature] Quick App feature added for faster access to key CRM actions.

* [feature] Admins can now add or update a person directly from the lead view page.

* [security] Resolved an authentication bypass vulnerability caused by improper access control in the installer.

## **v2.2.0 (17th of March 2026)** *Release*

* **[Laravel 12 Upgrade]** Upgraded framework to Laravel 12

* #2480[enhancement] Codebase updates and refinements.

* #2478[enhancement] Improved class instantiation handling.

* #2472[enhancement] Upgrade to Laravel 12.

* #2470[enhancement] Updated auto_commits.yml configuration.

* #2469[enhancement] General enhancements and optimizations.

* #2468[enhancement] Documentation updates (MD files).

* #2450[fixed] Added ACL support for warehouses.

* #2444[fixed] Improved global search functionality for organizations.
