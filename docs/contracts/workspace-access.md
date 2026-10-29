# Workspace and staff contract - revision 2

Author: Codex. Frontend reviewer: pending Claude. Scope: P1.01-P1.08.

Updated 29 October 2026. Workspace, staff, roles, invitation, verification and platform-provisioning backend implemented. No organization permissions are inferred from a user's name or email. Frontend verification and release acceptance remain separate.

All workspace routes require an authenticated session and verified email and resolve `current_organization_id` to a fresh active membership in an active organization. Password-hash session checks invalidate sessions after a password reset. A stale or suspended workspace selection returns 403; it never silently switches the operation to another tenant. POST `/workspace/switch` (`workspace.switch`) accepts `organization`; membership is checked before storing the selection. This recovery route remains available when the old selection is suspended. Invalid selections return 404 without metadata. The account home permits a user with no memberships, but workspace operations do not.

Organization-owned queries always constrain `organization_id`. Staff detail and search apply role scope as well as tenancy. Supported staff scopes: own, direct_reports, department, organization. An assigned_project grant grants no staff access. Payroll preparation/approval/management are excluded from the Organization Admin template; personal payslip visibility is allowed for all employee roles. Platform provisioning does not confer workspace membership.

Claude's staff, roles, departments/teams, audit, settings and invitation pages now consume the backend. `Accept: application/json` requests receive the same scoped data. Ordinary form writes redirect back to the relevant page with success/errors; JSON writes return `data` (200/201) or status. Validation returns 422 for JSON or redirects with field errors for HTML. All writes use CSRF, server validation and transactions. No posted organization ID overrides the session workspace. IDs are integer database keys; dates are `YYYY-MM-DD`; timestamps are UTC. No financial amount fields exist in this module.

## Shell and authorization

`ResolveWorkspace` middleware shares `currentOrganization` (Organization model or null on home), `availableOrganizations` (active organizations Collection), `currentMembership` (OrganizationMembership with organization and roles loaded), and `unreadNotificationCount` (current membership only). Use `$currentMembership?->roles` to display role names. `auth()->user()` remains the global identity source. Do not trust any of these view flags as authorization.

The authoritative model is ability plus scope (`config/permissions.php`). Navigation Gates accept both `staff.view` (any held scope) and `staff.view.own` (own or organization grant). Managers' general list navigation should use the unscoped ability; the query service applies direct-report filtering. `WorkspaceAccess` enforces staff relationships and tenancy; future business modules must add their own record policies, especially assigned-project scope and approver independence.

`Role.is_system_role` is the protected-template flag. Templates cannot be edited; custom roles can. Role creation/update/assignment may only delegate abilities/scopes the actor holds. The last active Owner cannot be suspended/offboarded or lose its owner role. Grants record assigning membership/time. Membership, role and reporting-line writes lock the organization row to serialize conflicting changes.

## Routes and payloads

All paths below start `/workspace`, unless otherwise stated.

| Method and path | Route name | Permission and input |
| --- | --- | --- |
| GET `/staff` | `staff.index` | `staff.view`; optional search (100 chars), per_page (1-100), page. Scoped paginated `data`; fixed ID sort. |
| GET `/staff/{membership}` | `staff.show` | Same record scope; unavailable records return 404. |
| PATCH `/staff/{membership}` | `staff.update` | `staff.manage`; optional employee_number, title, department_id, team_id, manager_membership_id, start_date, end_date; required reason (500 chars). Foreign references must belong to current organization; cycles rejected. |
| POST `/staff/{membership}/status` | `staff.status` | `staff.manage`; status active/suspended/offboarded, required reason. Suspended may reactivate; offboarded cannot reactivate through this endpoint. |
| PUT `/staff/{membership}/roles` | `staff.roles` | `roles.manage`; role_ids array, including empty to remove roles subject to last-owner protection. |
| GET/POST `/departments`, `/teams` | `departments.index/store`, `teams.index/store` | `departments.manage`; POST name (unique per organization). |
| GET `/roles` | `roles.index` | `roles.manage`; roles with grants and authoritative ability/scope catalogue. |
| POST `/roles`, PUT `/roles/{role}` | `roles.store/update` | `roles.manage`; name and grants list of `{ability, scope}`. Empty HTML grants string maps to an empty list; absent grants is invalid. Keep the current JS adapter until a later agreed contract changes the list shape. |
| GET/PATCH `/settings` | `settings.organization/update` | `organization.manage`; name/timezone/currency/contact_email/address and settings work_days, work_start, work_end, report_due_days_after_period. PATCH requires reason. |
| GET `/audit` | `audit.index` | `audit.view`; 25 per page, newest first, safe metadata only. No edit/delete endpoint. |
| GET/POST `/invitations` | `invitations.index/store` | `staff.invite`; POST email and role_id. Role must be delegatable. Active duplicate or existing membership returns 422. Raw token/hash never returned. |
| DELETE `/invitations/{invitation}` | `invitations.revoke` | `staff.invite`; accepted invitations cannot be revoked (409). |
| GET `/notifications` | `notifications.index` | Current active membership only; paginated JSON or Claude's notifications page. |
| POST `/notifications/{uuid}/read` | `notifications.read` | Current membership only; idempotent read_at; other recipients return 404. |

Staff rows expose id, name, email, employee_number, title, department_id, team_id, manager_membership_id, status, start_date, end_date. Edit-option collections are supplied only to authorized managers; they never expose unrelated staff names to a direct-report or own-only viewer.

## Invitations, verification and platform provisioning

GET `/invitations/{token}` (`invitations.show`) renders the existing invitation form with token, existingAccount boolean and invitation organization_name/role_name/email/expires_at. Tokens are random, hash-only in the database, seven-day, single-use. Unavailable invitations return 410. Token pages send no-store/no-referrer headers. POST `/invitations/accept` requires token and CSRF; new identities submit name fields and a confirmed password of at least 12 characters. Existing users prove their current password or use their matching authenticated session; names/passwords are never overwritten. A different signed-in identity is rejected. Role, tenant and email always come from the stored invitation. Issuer permissions, organization status and invitation state are rechecked under lock. Guests are redirected to login after acceptance, not automatically signed in.

GET `/verify-email` (`verification.notice`) and POST `/email/verification-notification` (`verification.send`) require login. GET `/verify-email/{id}/{hash}` (`verification.verify`) requires a valid temporary signature for the current identity. Workspace routes require verification. Accepting an emailed invitation verifies that address.

POST `/platform/organizations` (`platform.organizations.store`) requires an explicitly authorized platform administrator and verified email. Input: name, slug, timezone, currency, owner_email. It creates an organization/templates and sends the first owner an invitation; the platform actor gets no workspace membership. Provisioning UI remains a frontend handoff. Trusted operators can authorize an existing verified identity with `php artisan platform:grant EMAIL`; normal owners/admins cannot grant platform access through the website.

Invitation emails queue after commit, use encrypted job payloads, and recheck availability before sending. Retries use backoff. In-app membership activation notices deduplicate by membership/event key. Exactly-once external mail delivery and broader module notifications remain P1.07/P7 follow-up work; no such guarantee is claimed.
