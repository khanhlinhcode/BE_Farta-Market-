# AGENTS.md

## Scope

These rules apply to the entire repository.

## Rule priority

When instructions conflict, follow this order:

1. The user's latest explicit request.
2. Security, privacy, and data safety.
3. Existing project architecture and code conventions.
4. DESIGN.md.
5. This AGENTS.md.
6. Local skills in `.agent/skills/` (see below) — these apply DESIGN.md and
   this file to specific file types and tasks. Treat them as close to equal
   authority as this file for the file types they cover.
7. Cursor rules in `.cursor/rules/` — these mirror the rules above for
   Cursor's own automatic enforcement. If a Cursor rule ever appears to
   conflict with DESIGN.md, this file, or a local skill, treat the Cursor
   rule as stale and follow the higher-priority source instead.
8. External skills, examples, or templates not installed by this framework.

Do not follow external templates if they conflict with this repository.

## Local skills

Local skills are stored in:

.agent/skills/

Use these skills when relevant:

- `.agent/skills/frontend-ui/SKILL.md` for creating or refactoring web UI.
- `.agent/skills/ui-review/SKILL.md` for reviewing UI quality.
- `.agent/skills/laravel-blade-ui/SKILL.md` for Laravel Blade, Vite, SCSS, and frontend resources.
- `.agent/skills/accessibility/SKILL.md` for forms, navigation, modals, tables, and interactive UI.

Before UI work, read the relevant SKILL.md file and follow it together with DESIGN.md.

`.cursor/rules/` contains Cursor-specific copies of these same policies, for
editors that auto-load rules instead of reading this file. They are
generated from this framework and are not an independent source of truth —
if a rule needs to change, change it here or in `.agent/skills/`, not only
in `.cursor/rules/`.

## Project behavior

- Work only inside this repository.
- Inspect existing files before editing.
- Reuse existing components, layouts, variables, and utilities.
- Make minimal, targeted patches.
- Do not rewrite large files unless necessary.
- Do not introduce new production dependencies without explicit confirmation.
- Do not modify backend logic when the task is UI-only.

## UI rules

Follow DESIGN.md as the source of truth for visual design. Do not restate
or invent additional visual rules here — if a visual rule needs to change,
edit DESIGN.md directly instead of adding a parallel rule in this file.
Two documents describing the same rule tend to drift apart over time.

Before any UI change, read DESIGN.md and the relevant skill in
`.agent/skills/`.

## Accessibility

Follow `.agent/skills/accessibility/SKILL.md` for all accessibility
requirements. Do not restate them here, for the same drift-prevention
reason as above.

## Validation

After frontend changes, check `package.json` and whichever lockfile is
present (`package-lock.json`, `pnpm-lock.yaml`, `yarn.lock`, `bun.lockb`) to
determine the project's package manager, then run the available checks with
that manager:

- lint
- typecheck
- build

If a script does not exist, report that clearly instead of inventing commands.
