# Frontend review of the foundation and workspace contracts

Author: Claude (frontend). Date: 28 October 2026.
Reviews: `foundation-ui.md` revision 2, `workspace-access.md` revision 1.
Written as a separate file rather than edited into Codex's contracts, because workspace implementation is in flight.

## 1. Verified working

Signed in at `/login` with the seeded demo identity and reached `/home`. This is the first time the shared app shell has rendered under a real session.

- The shell renders correctly authenticated: sidebar, topbar, flash region and content area all behave.
- Sidebar ability gating works as designed. With no membership or permissions, every module item is correctly withheld and only Dashboard renders, because Dashboard is the one item declared with `'can' => null`.
- The account menu opens and exposes the signed-in email and the logout form. The topbar logout is wired to the real `logout` route.
- Login renders in the guest shell and submits to the real endpoint.

## 2. Fixed on the frontend, no action needed

**`full_name` does not exist; the accessor is `name`.** The topbar read `$user->full_name ?? $user->email`, which silently fell back to the email address, so the shell showed `admin@meridian.test` in the corner while the page heading showed `Demo Admin`. This came from Claude asking for a `full_name` accessor before revision 2 settled on `name`. The topbar now reads `$user->name`. There are no remaining `full_name` references in `resources/views`.

## 3. Shell view data: one real gap and three questions

The topbar partial resolves `$user` itself through `auth()->user()`, and defaults `$currentOrganization` to `null`, `$availableOrganizations` to an empty collection and `$unreadNotificationCount` to `0`. Nothing throws when the backend supplies none of them, so these are coordination questions, not breakage.

1. **`unreadNotificationCount` is absent from both contracts.** The topbar renders a notification bell and is ready to show a count. `workspace-access.md` lists `currentOrganization`, `availableOrganizations` and `currentMembership`, with no notification figure. Confirm this arrives under P1.07, and say whether it will be shell view data or something the frontend should query. Until then the bell renders with no count and invents nothing.

2. **`currentMembership` is supplied but currently unused.** The topbar can show the viewer's role beside the workspace name, which is useful once a user belongs to more than one organization. Please state the shape, specifically whether it exposes a role name safe to display.

3. **How is shell data attached?** If `currentOrganization` and `availableOrganizations` are passed per controller, every future controller has to remember, and a missed one degrades silently to "Select workspace" rather than failing loudly. A view composer bound to `layouts.partials.topbar`, or middleware sharing them, would make that structural. Frontend has no preference beyond wanting it to be impossible to forget.

4. **Confirm the `$user` source is acceptable.** The topbar calls `auth()->user()` directly rather than receiving it as view data. If the workspace layer needs the user resolved through membership instead, say so and the partial will take it as data.

## 4. Ability keys: convention needs settling before P1.05 lands

These 15 keys are provisional frontend input, not an authority. They are what the sidebar currently passes to `@can`:

```
activities.view.own     attendance.view.own    announcements.view
audit.view              departments.manage     documents.view
leave.view.own          organization.manage    payroll.manage
payslips.view.own       projects.view          reports.view.own
roles.manage            staff.view             tasks.view.own
```

Two problems to resolve:

- **They mix two conventions.** Some encode scope in the string (`reports.view.own`), others do not (`staff.view`, `roles.manage`). `workspace-access.md` describes scope as a separate dimension with the values `own`, `direct_reports`, `department` and `organization`. Decide which is authoritative: a single scoped ability string, or an ability plus a scope argument. The sidebar currently passes one string, and will be rewritten to match whichever you choose.
- **`direct_reports` was not anticipated.** None of the keys above account for it. If a manager's scope is `direct_reports` rather than `department`, the reports and attendance items need keys that express it.

Restating the rule both agents are bound by: hiding a nav item is a usability affordance only. Every destination must enforce authorization server-side regardless of what the sidebar renders.

## 5. Route names: one divergence

Route names in the sidebar that match your planned list: `staff.index`, `departments.index`, `roles.index`, `settings.organization`, `audit.index`.

Divergence: you plan `teams.index` and `teams.store` as separate routes. The sidebar has a single item labelled "Departments & Teams" pointing at `departments.index`. Either teams get their own nav item, or teams are managed inside the departments screen. This is a navigation decision, so frontend will follow your routing and would prefer teams nested inside departments until there are enough of them to deserve a top-level item.

The remaining sidebar routes (`tasks.mine`, `projects.index`, `activities.index`, `reports.index`, `attendance.index`, `leave.index`, `documents.index`, `announcements.index`, `payroll.index`, `payslips.mine`) belong to later phases and are expected to be absent.

Every route name is resolved through `Route::has()` and renders as a disabled item when missing, so no sidebar entry can produce a routing error as names land one at a time.

## 6. Blocker observed while testing

`/home` currently returns a 500 for any signed-in user:

```
SQLSTATE[42S02]: Base table or view not found: 1146
Table 'lorine.organization_memberships' doesn't exist
```

`database/migrations/2026_10_28_000001_create_workspace_access_tables.php` exists and `php artisan migrate:status` reports it **Pending**, while the code querying those tables is already live. The frontend has not run the migration: it is backend-owned, the shared database holds existing data, and `docs/backend-setup.md` requires a verified backup before migrations. This is flagged rather than fixed.

Note that the database is still named `lorine` while the product is now Meridian. That is cosmetic and should not be renamed casually.

## 7. One copy request

The user has asked that em dashes not appear anywhere in the product. All 33 occurrences have been removed from `resources/`. Backend-authored user-facing strings, including validation messages and seeded content, should avoid the character too.
