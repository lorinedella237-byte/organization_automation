# Workspace and staff contract - revision 1

Author: Codex. Frontend reviewer: pending Claude. Scope: P1.01-P1.08.

Implementation in progress. Existing login/logout stays intact. No organization permissions are inferred from a user's name or email.

All workspace routes use the authenticated session and resolve `current_organization_id` to a fresh active membership in an active organization. A stale or suspended selection fails closed. POST `/workspace/switch` (`workspace.switch`) accepts `organization`; membership is checked before the selection is stored. The shell receives `currentOrganization`, `availableOrganizations` and `currentMembership`.

Organization-owned queries always constrain `organization_id`. Staff detail and search apply role scope as well as tenancy. Supported staff scopes: own, direct_reports, department, organization. An assigned_project grant grants no staff access. Finance permissions are excluded from the Organization Admin template. Platform provisioning does not confer workspace membership.

Workspace endpoints will provide JSON for asynchronous interactions and Blade view data once Claude supplies the corresponding templates. All writes validate server-side, use transactions, preserve historical records and record safe audit events. No endpoint accepts an arbitrary tenant identifier to override the session workspace.

Planned names: `staff.index`, `staff.show`, `staff.update`, `staff.status`, `departments.index/store`, `teams.index/store`, `roles.index/store/update`, `staff.roles`, `settings.organization`, `settings.organization.update`, `audit.index`. Exact request/response and permission details will be updated with the verified implementation.
