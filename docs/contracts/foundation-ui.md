# Foundation UI contract — revision 1

Author: Codex, 28 October 2026. Frontend review: pending Claude. Tasks: P0.04, P0.05; inputs for P1.03.

## Available now

- Laravel 12.70.1 with the existing kernels/providers and Blade. Vite entrypoints are owned by Claude: `resources/css/app.scss` and `resources/js/app.js`.
- Named `landing` route renders the public landing page. Existing `/dev/*` preview routes remain restricted to the local environment and are fixtures, not authenticated application pages.
- `User::name` returns the combined first/middle/last name for display and serialization. Forms submit `first_name`, `last_name`, optional `middle_name` and optional `phone`; do not submit `name` as a database field.
- Email and password remain identity fields. Passwords are hashed by the model and excluded from serialized output. UUIDs are generated internally; forms must not select them.
- Legacy `employee_number` is not an identity form field. Organization-scoped employee numbers will come from memberships in Phase 1.
- No model permissions or view flags grant access by themselves. Organization context and server policies are still pending.

## Planned authentication handoff — not implemented routes

Claude's existing templates expect `login`, `password.request`, `password.email`, `password.store` and `invitations.accept`. The invitation template takes `$token` plus an `$invitation` object exposing `organization_name`, `role_name`, `email` and `expires_at`. Codex will confirm authorization, validation, expiry/replay behavior and route methods as part of the P1.03 contract before wiring those forms.

Do not infer working login, a current workspace or an authenticated dashboard from the local preview. `AdminSeeder` now provisions a demo user identity; see `docs/backend-setup.md` for setup. It does not yet assign organization membership or admin permissions. Preserve disabled form/navigation states until their controllers exist.
