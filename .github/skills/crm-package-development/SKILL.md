---
name: crm-package-development
description: Use when creating a new Krayin CRM package or module, extending CRM functionality via a package, or adding custom business logic without modifying core files. Also use for any translation work — adding or moving lang files, adding a locale, or deciding whether a package gets its own Resources/lang directory — and whenever a fix or feature needs a CHANGELOG.md entry. Covers Laravel package structure, service providers, migrations, models, repositories, routes, controllers, views, localization, changelog entries, config, ACL, and menus — including which of the two module shapes to build, the four files that wire a module into the app, and how to override a model or hook into an existing one (Contract/Proxy rebinding, before/after events) without editing core.
---

# Skill: CRM Package Development (Krayin CRM)

## Purpose
This skill guides the AI agent to design and develop a new CRM package
(module) for Krayin CRM in a clean, upgrade-safe, and maintainable way.

---

## When to Activate
Activate this skill when the user wants to:
- Create a new CRM package/module
- Extend CRM functionality via a package
- Add custom business logic without modifying core files
- Add, move, or delete translation (lang) files, or add a new locale
- Fix a bug or ship a feature — the change needs a `CHANGELOG.md` entry

---

## Project Context
- Framework: Laravel
- Product: Krayin CRM
- Krayin CRM is already installed and running
- The package must integrate seamlessly with existing CRM modules

---

## Development Rules
- Follow Krayin CRM architecture and conventions
- Do NOT modify core Krayin files unless explicitly required
- Use Laravel package-based structure
- All database changes must be done using migrations
- Ensure backward compatibility and safe upgrades

---

## Package Structure Guidelines

A CRM package should follow this structure:

```text
packages/
└── Webkul/
    └── PackageName/
        ├── src/
        │   ├── Providers/
        │   │   └── PackageServiceProvider.php
        │   ├── Models/
        │   ├── Contracts/
        │   ├── Repositories/
        │   ├── Http/
        │   │   ├── Controllers/
        │   │   └── Requests/
        │   ├── Routes/
        │   │   ├── admin.php
        │   │   └── api.php
        │   ├── Database/
        │   │   └── Migrations/
        │   ├── Resources/
        │   │   ├── views/          # only if the package renders its own Blade
        │   │   └── lang/           # ONLY if views/ exists — see Localization
        │   └── Config/
        │       ├── package.php
        │       ├── menu.php
        │       ├── core_config.php
        │       └── acl.php
        └── composer.json
```

---

## Module Shape: Split vs Self-Contained

Two shapes exist in this codebase today. Pick deliberately — this decides which
files you're editing months from now.

- **Split** (`Lead`, `Contact`, `Quote`, `Product`, `User`, …) — the package
  holds only `Contracts/`, `Models/` (+ `*Proxy`), `Repositories/`,
  `Database/Migrations/`. Every controller, route, Blade view, ACL entry and
  menu entry lives centrally in `Admin`, under a same-named sub-folder
  (`Admin/Http/Controllers/Lead/`, `Admin/Routes/Admin/leads-routes.php`, …).
- **Self-contained** (`WebForm`, `GoogleContact`) — one package holds every
  layer, including its own `Http/Controllers`, `Routes`, `Resources/views`,
  `Config/acl.php`, `Config/menu.php`.

**Build the self-contained shape for a new module.** It ships a feature with
zero edits inside `packages/Webkul/Admin` — nothing to merge-conflict with
core, nothing to lose on the next upstream upgrade.

---

## Wiring a Module In

Nothing scans `packages/Webkul/`. A module is registered by hand, in four
places — miss one and the symptom is different each time (blank page, silent
no-op, class-not-found):

| File | Add | Why |
|---|---|---|
| root `composer.json` → `autoload.psr-4` | `"Webkul\\Name\\": "packages/Webkul/Name/src"` | PHP's autoloader has to find the classes at all |
| `bootstrap/providers.php` | `NameServiceProvider::class` | Boots the plain provider — migrations, and (self-contained) routes/views/translations |
| `config/concord.php` → `modules` | `NameModuleServiceProvider::class` | Registers `$models` with Concord — required for the override pattern below |
| — | `composer dump-autoload` | Regenerates the class map after the first edit above |

If the module is self-contained and ships `Config/acl.php` / `Config/menu.php`,
merge them into the running config yourself — nothing else does it. In the
plain provider's `register()`:

```php
public function register(): void
{
    $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');
    $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');
}
```

Both files return a plain indexed array (see §5's examples) — `mergeConfigFrom`
concatenates it onto whatever every other package already contributed to
`menu.admin` / `acl`, in provider-boot order. Skip this and the routes and
Blade views work fine, but the page never appears in the sidebar and nothing
enforces access to it.

Don't be misled by each package's own `composer.json` (declares a name like
`krayin/laravel-lead` and an `extra.laravel.providers` block) — it is **not**
what wires the package into this app. Root `composer.json` never `require`s
these package names and `vendor/krayin/` carries no such package; that file
exists only so the package could be published standalone to Packagist. The
four wires above are the only thing actually running.

---

## Extending an Existing Module Without Touching Core

Two sanctioned mechanisms. Reach for one of these before editing a file under
`packages/Webkul/<CoreModule>` or `packages/Webkul/Admin`.

**1. Contract + Proxy override — changes what a model *is*, everywhere.**
Every relation resolves through a Proxy (`PipelineProxy::modelClass()`), never
a concrete class, and every repository's `model()` returns the Contract, not
the class. Concord's `registerModel()` does `$this->models[$abstract] =
$concrete` — a plain array write, so the *last* module registered for a given
contract wins. To change what `Lead` resolves to app-wide, register your own
subclass against the same contract from a `ModuleServiceProvider` listed
*after* `LeadModuleServiceProvider` in `config/concord.php`:

```php
protected $models = [
    \Webkul\Lead\Contracts\Lead::class => \Webkul\YourPackage\Models\Lead::class,
];
```

Your class must extend the original — Concord enforces `is_subclass_of()` and
throws if it doesn't.

**2. Before/after events — runs code around an action.** Every admin
controller fires `<entity>.<action>.before` / `.after` around create, update
and delete (241 call sites at last count — `grep -rn "Event::dispatch"
packages/Webkul` to see the current set for a given entity). Listen from your
own package's `boot()`:

```php
Event::listen('lead.create.after', function ($lead) {
    // notify, sync, validate — zero edits to Lead or Admin
});
```

**3. `view_render_event()` — injects UI into an existing core page.** Core
Blade files are threaded with hook points like
`{!! view_render_event('admin.leads.view.actions.after', ['lead' => $lead]) !!}`
(`grep -rn view_render_event packages/Webkul/Admin/src/Resources/views` to
see what a given page offers). Listen from your provider's `boot()` and
register a partial — the `$params` array becomes that partial's view data:

```php
Event::listen('admin.leads.view.actions.after', function (\Webkul\Core\ViewRenderEventManager $manager) {
    $manager->addTemplate('your_module::partials.your-button');
});
```

A module that only adds a button/action this way — no new entity — needs no
`Model`, `Contract`, `Proxy`, migration, or `config/concord.php` entry at
all. It's still self-contained: own `Controller`, `Route`, `Resources/views`,
own provider — just with an empty data layer. `LeadEnrichment` is a real
example: one route, one injected button, zero tables.

Edit the core file directly only when neither mechanism covers the change,
and say so explicitly in the PR description.

---

## A Package's Tailwind Classes Need Admin's Build to Scan Them

The compiled admin CSS (`public/admin/build/`) comes from
`packages/Webkul/Admin`'s own Vite/Tailwind build — a **separate** build
context from the repo-root one (`public/build/`, used by self-contained
packages like `Sandbox`). Whether your package's Blade markup renders a full
page (`LeadGreen`'s search screen) or an injected partial (mechanism #3
above, e.g. `LeadEnrichment`'s button), its classes only make it into the
compiled CSS if `packages/Webkul/Admin/tailwind.config.js`'s `content` array
scans your package's files. Confirm it covers every package, not just
Admin's own views:

```js
content: [
    "./src/Resources/**/*.blade.php",
    "./src/Resources/**/*.js",
    "../*/src/Resources/**/*.blade.php",
    "../*/src/Resources/**/*.js",
],
```

If it doesn't, any class unique to your package — an arbitrary value like
`z-[9999]`, an opacity modifier like `bg-black/50`, anything Admin's own
views don't happen to also use — silently never gets generated. This fails
**invisibly**: no build error, no console error, nothing in the code. It only
shows up as broken layout in the browser (missing backdrop, wrong stacking
order, elements in the wrong place), so a code review or an HTTP-only smoke
test (`curl`/`wget`) won't catch it — only actually looking at the rendered
page will. Real-world example: a modal with `z-[9999]` and `bg-black/50`
rendered with `z-index: auto` and a transparent backdrop until the `content`
array above was widened and both `packages/Webkul/Admin`'s **own** build was
rerun (`cd packages/Webkul/Admin && npm install && npm run build` —
`npm run build` at the repo root only rebuilds `public/build/`, a different
Vite context entirely) and the browser was checked again.

---

## A Custom Attribute You're Relying On May Not Exist

Don't assume an entity attribute exists just because it's a natural fit
(e.g. an `Organization` "site" field) — Krayin's stock attribute set is
narrower than it looks (`php artisan tinker --execute="dd(app(\Webkul\Attribute\Repositories\AttributeRepository::class)->findWhere(['entity_type' => 'organizations'])->pluck('code'))"`
to check what really ships). If a package's logic depends on one, create it
in a migration, guarded so two packages needing the same attribute don't
collide:

```php
if (! DB::table('attributes')->where('code', 'site')->where('entity_type', 'organizations')->exists()) {
    DB::table('attributes')->insert([
        'code' => 'site', 'name' => 'Site', 'type' => 'text',
        'entity_type' => 'organizations', 'validation' => 'url',
        'sort_order' => 10, 'is_required' => 0, 'is_unique' => 0,
        'quick_add' => 0, 'is_user_defined' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}
```

Leave `down()` empty — dropping the attribute would silently discard real
data on it.

---

## Admin-Configurable Settings (`Config/core_config.php`)

If a module needs a credential or a toggle an admin should be able to change
from the UI instead of editing `.env`, ship `Config/core_config.php` and
merge it the same way as `acl.php`/`menu.php`:

```php
$this->mergeConfigFrom(dirname(__DIR__).'/Config/core_config.php', 'core_config');
```

The generic Configuration screen (`Admin/Http/Controllers/Configuration`)
renders whatever is merged into `core_config` — **a new module needs no
controller, route, or view of its own** to get a working settings page. The
file is a flat list of entries forming a three-level dotted key — tab,
section, field-group — where only the deepest level carries `fields`:

```php
return [
    ['key' => 'your_module', 'name' => '...', 'sort' => 20],                    // tab
    ['key' => 'your_module.settings', 'name' => '...', 'icon' => '...', 'sort' => 1], // section
    ['key' => 'your_module.settings.api_keys', 'name' => '...', 'sort' => 1, 'fields' => [
        ['name' => 'api_key', 'title' => '...', 'type' => 'password', 'validation' => 'nullable|string'],
    ]],
];
```

Field `type`s in use elsewhere: `text`, `password`, `boolean`, `select`,
`number`, `color`, `image`, `editor`.

**Never write `nullable` or `string` into a field's `validation` string —
the Save button will silently stop working for that field.** The same
string drives both server-side Laravel validation (`Webkul\Admin\Http\Requests\ConfigurationForm`,
which understands `nullable` natively) *and* client-side VeeValidate
(`ItemField::getValidations()`, `packages/Webkul/Core/src/SystemConfig/ItemField.php`) —
and only one Laravel→VeeValidate translation exists (`'min' => 'min_value'`).
Any other Laravel-only rule name (`nullable`, `string`, …) reaches VeeValidate
literally, an unregistered rule name, and breaks that field's client-side
validation — the form silently never submits, with no visible error. Every
stock Krayin field either omits `validation` entirely for an optional field
(the omission itself is what makes it optional in both ecosystems — do the
same instead of writing `nullable`) or uses only rule names that exist
natively in `@vee-validate/rules` (`required`, `email`, `min`, `max`,
`required_if`, …) or in the translation map. When unsure, grep
`packages/Webkul/Admin/src/Config/core_config.php` for a comparable field's
`validation` value rather than reasoning from Laravel's rule set alone.

Read a saved value back with
`core()->getConfigData('your_module.settings.api_keys.api_key')` — falling
back to `config('services.your_module.key')` costs nothing and lets an
ops-managed deploy keep using `.env` instead of the database if it prefers.

---

## Package-Owned Scheduled Commands

Register the command and its schedule from the module's own provider — never
add it to `app/Console/Kernel.php`:

```php
public function boot(): void
{
    if ($this->app->runningInConsole()) {
        $this->commands([YourCommand::class]);

        $this->app->booted(function () {
            $this->app->make(\Illuminate\Console\Scheduling\Schedule::class)
                ->command(YourCommand::class)
                ->everyMinute()
                ->withoutOverlapping();
        });
    }
}
```

---

## Localization (Translations)

### The rule

A package owns a `Resources/lang/` directory **only if it also owns
`Resources/views/`** — that is, only if it renders its own Blade templates.
Every other package ships **zero** translation files; its strings live in the
**Admin** package under `admin::app.*`.

Before creating any lang file, ask:

> Does this package have `src/Resources/views/` with Blade templates?

- **Yes** → give it `Resources/lang/<locale>/app.php` and register its own
  namespace with `loadTranslationsFrom()` in the service provider.
- **No** → do **not** create a lang directory and do **not** call
  `loadTranslationsFrom()`. Put the keys in the Admin package and reference them
  as `admin::app.…`.

Current state of the repo — preserve this invariant:

| Owns views | Packages | Translations live in |
|---|---|---|
| Yes | `Admin`, `Installer`, `WebForm` | the package itself (`admin::`, `installer::`, `web_form::`) |
| No | everything else (`Core`, `DataTransfer`, `Lead`, `Contact`, `Quote`, `User`, …) | `Admin` (`admin::app.*`) |

"Backend-only" strings are still translations and follow the same rule —
validation-rule messages, importer error maps, and `title` values referenced
from `Config/*.php` all belong in Admin when the package has no views.

### Where keys go for a view-less package

Nest them under the Admin key that already owns that domain so one feature's
strings stay together. Established precedent:

| Before | After |
|---|---|
| `core::app.validations.code` | `admin::app.validations.message.code` |
| `data_transfer::app.importers.persons.title` | `admin::app.settings.data-transfer.importers.persons.title` |
| `data_transfer::app.validation.errors.system` | `admin::app.settings.data-transfer.validation.errors.system` |

When you move keys out of a package, finish the job: rewrite every reference,
delete the package's `Resources/lang/`, and remove its `loadTranslationsFrom()`
call so the provider does not point at a path that no longer exists.

### Working with Admin lang files

- Admin ships one `app.php` per locale: `ar`, `en`, `es`, `fa`, `ko`, `pt_BR`,
  `tr`, `vi`, `zh_CN`. **Add every new key to all of them in the same change** —
  a key present only in `en` renders as the raw key string in other locales.
- If a real translation is unavailable, use the English text as the value. Never
  omit the key, and never leave the key name as its own value.
- Preserve placeholders exactly: `:attribute`, `:days`, `:count` (Laravel) and
  `%s` (importer messages formatted with `sprintf`).
- Pint enforces a **single space** around `=>` (`pint.json` →
  `binary_operator_spaces`). Do not align `=>` into columns.
- Name the file `app.php` in every locale directory. A misnamed file (e.g.
  `ar/ar.php`) is never loaded and the locale silently falls back to English.

### Adding a new locale

1. Create `<locale>/app.php` in each package that owns a lang directory
   (`Admin`, `Installer`, `WebForm`), mirroring the `en` key structure exactly.
2. Register it in `config/app.php` → `available_locales`. This drives both the
   admin locale dropdown (via `Core@locales`) and the web installer.
3. Add it to `$locales` in
   `packages/Webkul/Installer/src/Console/Commands/Installer.php` so the CLI
   installer offers it.
4. If the script is right-to-left, add it to the `['fa', 'ar']` checks in the
   Admin layout Blade files.

### Verifying

Confirm parity with `en` — same key set, same placeholders — then check that
keys actually resolve:

```bash
php artisan tinker --execute='app()->setLocale("<locale>"); echo trans("admin::app.<your.key>");'
./vendor/bin/pint --test packages/Webkul/<Package>
```

A key that resolves to its own name (`admin::app.foo.bar`) is missing.

---

## Changelog

### The rule

**Every bug fix and every feature gets a `CHANGELOG.md` entry, in the same
change.** Do not leave it for someone else and do not treat it as optional
follow-up work — a fix that is not in the changelog is invisible to the release
notes.

Add an entry when the change is user-visible or operator-visible: a bug fix, a
new feature, an enhancement, a security fix, a configuration option, or a
convention that affects how the product is built. Skip it only for changes with
no observable effect at all — a typo in a code comment, a pure test refactor.

### Format

Entries live at the top of [CHANGELOG.md](../../../CHANGELOG.md), newest version
first, and use exactly this shape:

```markdown
## **v2.2.5 (Upcoming)**

* #2601[fixed] Fixed lead value not saved when the currency is changed.

* #2604[feature] Added Chinese (Simplified) translation.
```

- One blank line between every entry — the file is read as rendered markdown.
- No space between the issue number and the tag: `#2601[fixed]`, not `#2601 [fixed]`.
- Write the description as a complete sentence ending in a period, phrased from
  the user's point of view, not the implementation's.

Valid tags, and when each applies:

| Tag | Use for |
|---|---|
| `[fixed]` | Something was broken and now works. |
| `[feature]` | New capability that did not exist before. |
| `[enhancement]` | Existing behaviour improved, refactored, or made faster. |
| `[security]` | Vulnerability fix. Reference the CVE when one exists. |

### Which version section

Add to the topmost section if it is still unreleased. If the top section is
already tagged `*Release*`, open a new one above it:

```markdown
## **v<next patch version> (Upcoming)**
```

Replace `(Upcoming)` with the real date in the release commit — the existing
released headings show the expected style: `## **v2.2.4 (20th of July 2026)** *Release*`.

### Two things not to do

- **Never invent an issue or PR number.** They point at real GitHub PRs, so a
  guessed number creates a false reference. If the PR does not exist yet, omit
  the `#NNNN` prefix and leave the tag, then fill the number in once it is
  opened.
- **Do not bump the version constant as part of a feature or fix.**
  `KRAYIN_VERSION` in `packages/Webkul/Core/src/Core.php` is incremented in the
  release commit, together with dating the changelog heading — not per change.

---

## Keeping This Skill Current

This file is documentation of record for the module architecture — a gap in
it is a bug in it. When you work out a pattern that isn't written down here —
a new convention, an architectural decision, a gotcha that cost time to find —
fold it back into this file in the same change, the same way `CHANGELOG.md`
gets a line for every fix. A skill that drifts from what the codebase actually
does is worse than no skill at all.
