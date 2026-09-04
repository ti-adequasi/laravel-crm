# CLAUDE.md

Project-specific instructions for Claude Code in this repository. See also
[AGENTS.md](AGENTS.md) for the full cross-agent conventions (skills, testing,
module layout).

## Before creating or editing a module

Read the `crm-package-development` skill
(`.github/skills/crm-package-development/SKILL.md`, symlinked into
`.claude/skills/`) — it documents the two module shapes this codebase uses,
the four files that wire a module into the app, and the two sanctioned ways
to extend an existing module without editing core (Contract/Proxy rebinding,
before/after events). Claude Code loads it automatically when a task matches;
invoke it explicitly with `/crm-package-development` if in doubt.

Do not modify files under `packages/Webkul/<CoreModule>` or
`packages/Webkul/Admin` unless the skill's extension mechanisms genuinely
don't cover the change — and say so explicitly when they don't.

## After a UI/UX change

Once a change touching an admin-facing Blade/Vue view
(`packages/Webkul/*/src/Resources/views/**/*.blade.php`) is implemented and
functionally verified, invoke the `design-ux-reviewer` subagent (Agent tool,
`subagent_type: design-ux-reviewer`) before considering the task done. It
checks visual/UX consistency against Krayin's own existing conventions —
filter and form layout, icon placement, dark-mode parity, spacing — using a
real browser, and reports concrete findings. Apply what's clearly right; use
judgment (or ask) on anything more subjective. This is a standing project
practice, not a one-off — do it for every such change, not just when asked.

## Keeping this file and the skill current

Both documents describe how the codebase actually works today, not how it
should work in the abstract. When a change teaches you something these files
don't already say, update the relevant one in the same change.
