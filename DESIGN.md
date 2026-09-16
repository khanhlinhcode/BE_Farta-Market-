# DESIGN.md

## Design intent

Build a clean, professional, production-ready web interface.
The UI must look consistent across all pages.

This file is the single source of truth for visual design rules. AGENTS.md,
the SKILL.md files, and Cursor rules point back to this file instead of
repeating its content — if a visual rule needs to change, change it here
first.

## Design tokens

Use semantic tokens only.

Recommended token names:

- --color-bg
- --color-surface
- --color-border
- --color-text
- --color-muted
- --color-primary
- --color-success
- --color-warning
- --color-danger
- --radius-sm
- --radius-md
- --radius-lg
- --shadow-sm
- --shadow-md

### Tailwind projects

If the project uses Tailwind instead of CSS custom properties, map these
same semantic names into `tailwind.config.*` under `theme.extend.colors` /
`theme.extend.borderRadius` / `theme.extend.boxShadow` (e.g. `bg-primary`,
`text-muted`, `rounded-md`) instead of creating a second, parallel set of
CSS variables. Do not maintain both systems in the same project.

## Layout

- Use a consistent 8px spacing scale.
- Prefer max-width containers.
- Cards must use consistent padding and radius.
- Avoid random margins and one-off alignment.

## Typography

- Page title: strong, high contrast.
- Section title: semibold.
- Body text: readable.
- Helper text: muted.

## Components

Buttons:
- One primary action per screen.
- Secondary actions must be visually weaker.
- Destructive actions require confirmation.

Cards:
- Use consistent radius, border, padding, and shadow.
- Do not mix many unrelated card styles.

Forms:
- Every input has a label.
- Errors appear near the field.
- Inputs have visible focus states.

Tables:
- Use clear headers.
- Align numeric values right.
- Provide empty, loading, and error states.

## Theming

Dark mode is out of scope unless explicitly requested. If it is added
later, reuse the same semantic tokens with a dark variant instead of
introducing new ad-hoc dark-only colors.

## Prohibited

- No random hex colors.
- No duplicate button/card variants.
- No excessive shadows.
- No unrelated gradients.
- No inconsistent icon styles.
