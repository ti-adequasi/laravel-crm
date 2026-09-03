# Sandbox (reference module)

Built to validate "Recipe A: Build a new self-contained module" in
[`crm-package-development`](../../../.github/skills/crm-package-development/SKILL.md)
against a real install of this app. Every file here follows that recipe
exactly — same shape as `WebForm`, wired in through the same four places
(`composer.json` autoload, `bootstrap/providers.php`, `config/concord.php`,
`composer dump-autoload`).

Not a product feature. Safe to delete — remove this directory, drop its
entries from the three wiring files, run `composer dump-autoload`, and
`php artisan migrate:rollback` the `sandbox_notes` table (or just drop the
table) if you don't want to keep it as a working example.

To keep it as a live reference instead: leave it as-is. It's a real,
working, self-contained CRUD module — a page under **Sandbox** in the admin
sidebar to add and remove short notes, backed by its own `Contract`,
`Model`/`Proxy`, `Repository`, `Controller`, route file, view, ACL entry,
menu entry and `en` translation file.
