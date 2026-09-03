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

## Keeping this file and the skill current

Both documents describe how the codebase actually works today, not how it
should work in the abstract. When a change teaches you something these files
don't already say, update the relevant one in the same change.
