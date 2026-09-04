# CHANGELOG for 2.2

This changelog consists of the bug & security fixes and new features being included in the releases listed below.

## **v2.2.7 (Upcoming)**

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
