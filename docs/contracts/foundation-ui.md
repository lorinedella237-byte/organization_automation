# Foundation UI contract - revision 2

Author: Codex, 28 October 2026. Frontend review: pending Claude. Tasks: P0.04, P0.05; inputs for P1.03.

## Available now

- Laravel 12.70.1 with the existing kernels/providers and Blade. Vite entrypoints are owned by Claude: `resources/css/app.scss` and `resources/js/app.js`.
- Named `landing` route renders the public landing page for guests and redirects signed-in users to `/home`. Existing `/dev/*` preview routes remain restricted to the local environment and are fixtures, not authenticated application pages.
- `User::name` returns the combined first/middle/last name for display and serialization. Forms submit `first_name`, `last_name`, optional `middle_name` and optional `phone`; do not submit `name` as a database field.
- Email and password remain identity fields. Passwords are hashed by the model and excluded from serialized output. UUIDs are generated internally; forms must not select them.
- Legacy `employee_number` is not an identity form field. Organization-scoped employee numbers will come from memberships in Phase 1.
- No model permissions or view flags grant access by themselves. Organization context and server policies are still pending.

## Implemented session authentication

- GET `/login`, named `login`: guest-only existing login form. Authenticated users redirect to `/home`.
- POST `/login`: guest-only, requires CSRF; fields `email`, `password`, optional `remember` (`1` when checked). Email is trimmed/lowercased. Success renews the session and redirects to `/home`; no arbitrary redirect parameter is accepted.
- Failed credentials return the same email-field validation error for unknown users and wrong passwords. Normal form submissions redirect back with errors and safe old input; JSON requests return 422. The existing shared error summary and input components display errors.
- Five failed attempts for an email/IP pair trigger a 60-second lockout with an email-field message. The separate 30 submissions/minute IP limit returns HTTP 429.
- GET `/home`, named `dashboard`: requires session authentication; renders `dashboard.blade.php` in Claude's existing app layout. It shows the signed-in user's name/email and logout, with no invented organization statistics or permissions. Guests redirect to `/login` (JSON requests return 401).
- POST `/logout`, named `logout`: requires authentication and CSRF, clears the session and remember-me cookie, regenerates CSRF, and redirects to `/login`. Existing topbar logout is now functional.
- `AdminSeeder` provisions the demo identity; credentials and install instructions are in `docs/backend-setup.md`. It does not assign organization membership or admin permissions.

Minimal frontend integration by Codex: bind the existing login form directly to `login`, give the remember checkbox value `1`, and add a small authenticated account home using the existing shell. Claude retains visual ownership. No preview flags or permission bypasses are enabled on `/home`.

## Remaining authentication handoff

`password.request`, `password.email`, `password.store` and `invitations.accept` are not implemented. The invitation template takes `$token` plus an `$invitation` object exposing `organization_name`, `role_name`, `email` and `expires_at`. Verification, invite expiry/replay behavior and suspension checks remain part of P1.03. Keep unavailable module and reset controls gated until their routes/controllers exist.
