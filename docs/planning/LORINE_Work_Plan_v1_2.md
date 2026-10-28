# LORINE: Consolidated Master Work Plan

**Version:** 1.2  
**Prepared:** 26 October 2026  
**Source documents:** Claude's *Organization Management System: Understanding & Build Overview* and ChatGPT's *Organization Management Platform: Detailed Implementation Plan*.  
**Status:** Proposed implementation baseline. Architectural decisions and scope below reconcile the two planning sources. Technical assumptions about existing code must be verified in the repository.

**Version 1.2 change:** Staff Attendance Tracking is now an explicitly highlighted core module, with its own workflows, dashboards, data model, delivery tasks, acceptance tests, and exports. The delivery phases and original task references outside Phase 5 remain unchanged.


## A. Executive decision

Build LORINE as a **responsive, secure, multi-organization management platform** using a modular Laravel monolith. Launch with **one pilot organization** and platform-admin-created workspaces, but enforce organization data isolation from the first migration. The initial complete release covers people, departments, teams, dynamic permissions, projects and tasks, daily activities, reports, private documents, **staff attendance tracking**, leave, payroll preparation and payment records, notifications, announcements, dashboards, and audit history. Attendance is a dedicated operational module. It is not the same as a daily activity report or a task time log.

**Two release checkpoints:**

- **Operational pilot, after Phase 3:** organizations, identity/RBAC, projects, activities, and weekly reporting are functional.
- **Full Release 1, after Phase 7:** private documents, staff attendance tracking, leave, payroll records, notifications, operational dashboards, and recovery tests are production ready.

### A1. Core operational modules at a glance

| Module | What the organization can manage | Planned delivery |
|---|---|---|
| Staff and access | Staff profiles, departments, managers, teams, invitations, roles and permissions | Phase 1 |
| Projects and tasks | Projects, assignments, milestones, deadlines and progress | Phase 2 |
| Daily activities and reports | Staff work entries, time logs, weekly reports, reviews and revisions | Phase 3 |
| Private documents | Folders, uploads, version history and internal sharing | Phase 4 |
| **Staff Attendance Tracking** | **Clock-in, clock-out, lateness, missing attendance, hours worked, correction requests, daily and monthly attendance summaries, manager and HR oversight** | **Phase 5** |
| Leave management | Staff leave requests, balances, manager approval and HR approval | Phase 5 |
| Payroll records | Compensation, payroll approval, payslips and payments made outside LORINE | Phase 6 |
| Dashboards and release | Staff, manager, HR and owner dashboards, security, backups and production checks | Phase 7 |

**Important:** Attendance tracking is a core requirement for Release 1, not an optional future feature. Work logs answer what an employee did, while attendance answers when an employee was present or scheduled to work. Approved leave explains authorized time away. Payroll remains a separately approved financial process.

**Not in Release 1:** bank disbursements, automated statutory tax computation/filings, subscription billing, public organization registration, public share links, native apps, expense claims, live chat, biometrics/GPS, complex shifts and overtime engines, multi-level arbitrary approval builders, social login, advanced payroll accruals.

## B. Comparison and resolved choices

| Decision | Claude plan | ChatGPT plan | Final choice / reason |
|---|---|---|---|
| Tenancy | Single organization, users have department | Multiple organizations, membership per organization | Multi-tenant from first migration. Retrofitting tenancy is costly and high-risk; first pilot still has one organization. |
| Signup | Public staff register then admin approval | Organization admin invites staff | Invitation-first. Optional approved applications only later; prevents uninvited access. |
| Authentication | Hand-rolled controllers | Framework authentication, unspecified | Supported Laravel authentication primitives/starter components; customize invitation and membership authorization; do not invent password-reset/security internals. |
| Roles | Admin-configurable roles; ability.scope codes | Predefined roles | Global permission catalogue + organization-specific roles seeded from templates; scope and relationship checked together. |
| Authorization | Gates and reusable visibleTo scope | Context-aware checks and workspace isolation | Laravel policies/Gates + tenant-scoped query services + database constraints, applied to detail, list, export, files and jobs. |
| Reports | Uploaded documents with categories and approvals | Structured weekly reports, activity prefill, immutable versions | One reports domain supporting both structured and attachment-based templates; weekly default. |
| Daily work | work_logs hours per project/task | daily_activities with optional hours | Keep activities separate from approved work/time logs; audit_logs is a different domain. |
| Projects | Projects, members, tasks | Adds milestones, comments, checklist and board | All in coherent project module; board a progressive enhancement. |
| Documents | Private folders and grants; version column only | Versions, inheritance, scanning, trash | Version records, folder grants, private download controller, quarantine/scanning, configurable retention. |
| Staff attendance and leave | Omitted | Clock-in/out, exception tracking, corrections, two-stage leave approvals | **Mandatory Release 1 modules**. Provide separate attendance records, dashboards, summaries and manager oversight in Phase 5. |
| Payroll | Monthly/daily/hourly from work logs; mark Paid | Monthly pay, approval, installments | Monthly manual-payroll first; partial-payment records and independent approval; hourly/day calculation deferred until business rules are validated. |
| Notifications | DB bell + mail outbox | Emails/jobs/reminders | Laravel database notifications + durable idempotent email delivery/queue; scheduled reminders. |
| UI | Bootstrap CDN | Blade + JS | Blade + Bootstrap 5 + Alpine.js where needed, bundled via Vite (no hard dependency on an external CDN). |
| Framework | Laravel 9 | Laravel 9 with eventual upgrade | Upgrade before business development to Laravel 13/PHP >=8.3, subject to dependency audit. |
| Migrations | Fix originals if no database | Additive, preserve data | If verified empty/unrun, clean migrations. Otherwise additive migration with backups; **never** reset a database with data. |

## C. Product boundaries and roles

### C1. Organization model

- **organizations:** name, slug, status, branding/contact, address, timezone, currency, work-week schedule, report deadlines, leave year/holidays, settings.
- **users:** login identity, email, name, password hash, global verified email/2FA preferences, security timestamps. No organization-specific `department_id` or `employee_number` here.
- **organization_memberships:** organization_id, user_id, employee_number, title, department_id, team_id, manager_membership_id, employment/start/end dates, lifecycle status. Unique `(organization_id, user_id)` and `(organization_id, employee_number)`; employee numbers unique only within a workspace.
- A single user can hold multiple memberships, and can choose the current workspace. Every request resolves active membership server-side and ensures workspace is active. A posted organization ID or path slug is not access proof.
- Organization suspension blocks operations, not history preservation. Offboarding revokes access immediately, historical records stay attributable.

### C2. Roles and permission strategy

Seed standard role templates: **Platform Administrator** (platform provisioning only), **Owner**, **Organization Admin**, **HR Officer**, **Finance Officer**, **Manager**, **Staff**. Owners/admins may create additional roles by ticking an organization-specific permissions matrix. One membership can hold multiple roles. Role grants record assigning actor/time. Protect system templates from deletion but allow separate customizable organization copies. No platform-admin by-default access to confidential organization contents; any future support access needs explicit authorization, scope, expiry and full audit.

Permission **ability** examples: `staff.view`, `staff.manage`, `roles.manage`, `reports.create`, `reports.review`, `projects.manage`, `tasks.assign`, `files.share`, `attendance.clock_in`, `attendance.clock_out`, `attendance.view`, `attendance.view_team`, `attendance.correct`, `attendance.export`, `leave.approve_manager`, `leave.approve_hr`, `payroll.prepare`, `payroll.approve`, `payslips.view`, `audit.view`. Scope on role grant: `own`, `direct_reports`, `department`, `assigned_project`, `organization`, with allowed scopes per ability. All access requires both a grant and the applicable relationship and organization match. Prefer policies for record actions, safe query builders for lists, and service-level checks for commands. Hiding menus is usability, **not security**.

Explicit rules: No self-approval for reports/leave/payroll; organization admins do not automatically get payroll permissions; project lead cannot automatically manage all staff. Managers only see explicitly assigned direct reports where relevant; department-wide visibility is an independently granted scope.

### C3. Staff lifecycle

Platform admin creates organization -> invite owner -> owner/admin configures organization -> invite staff -> staff accepts expiring one-use invitation, establishes account/verified email -> membership active after any required admin assignment -> department/team/manager and roles assigned. Admin can suspend, reinstate, transfer, or offboard with an audit reason. Optional open application and pending approval mode are deferred.

## D. Technical architecture

- **Application:** Laravel 13 (supported major version as of 26 Oct 2026), PHP >=8.3; retain existing business assets only after audit; modular monolith, not microservices.
- **Frontend:** Server-rendered Blade, Bootstrap 5, Vite-managed assets; modest Alpine.js/vanilla JS for dropdowns, task board and interactive forms. Shared responsive app shell, role-aware navigation, focus/error states and mobile support.
- **Database:** MySQL 8-compatible supported version with InnoDB, foreign keys, consistent indexes, transactions and row locking. Choose DB version supported by the exact Laravel 13 release and driver.
- **Queues/cache:** Initially database queues are acceptable for low load; use Redis when latency/concurrency justifies it. Background jobs for mail, scanning, exports, reminders. Scheduler and supervised queue workers on a VPS or managed platform.
- **Private file storage:** nonpublic local disk for pilot or private S3-compatible object storage in production. Random opaque keys; original filename is metadata only. Downloads/previews via authorized controller or short-lived signed URLs with server-side authorization. Separate tenant namespaces/buckets as appropriate.
- **Auth:** Laravel sessions, email verification, password reset and rate limiting. Optional 2FA for staff; required for finance/owners/admins in production policy. CSRF/session hardening, secure cookies, HTTPS. Avoid making custom password/token crypto.
- **Application layers:** Routes -> form requests/validation -> policies -> application services/commands -> models/repos/DB -> events/jobs. Controllers remain thin. Core workflows callable independently of Blade to support future API/mobile.
- **Operational:** environment config and secrets outside repository; CI lint/static analysis/tests; logging/error alerting, DB and file backups, verified restore, deployment rollback, queue health and schedule monitoring.
- **Dates/money:** persist timestamps in UTC; evaluate work dates/deadlines in workspace timezone; represent money using fixed-precision decimal (e.g. DECIMAL(18,2)), never float, and apply currency-specific rounding/display (e.g. XAF). Store compensation effective dates and immutable payroll snapshots.

**Laravel lifecycle verification:** Laravel 9 security support ended Feb 6, 2024; Laravel 13 released Mar 17, 2026, requires PHP 8.3 and is security-supported until Mar 17, 2028 (official Laravel release notes: https://laravel.com/framework/docs/releases). Upgrade dependency feasibility must be tested on the real repository; no production deployment should stay on unsupported Laravel 9.

### D1. Migration strategy (data-safe)

1. Back up the repo and database if any. Document `php -v`, `composer show laravel/framework`, `php artisan --version`, `git status`, migration history and actual schema/data counts.
2. Compare existing Laravel 9 skeleton to fresh supported Laravel 13 baseline; inspect third-party compatibility and consider creating a clean 13 baseline then porting *only* custom migrations/models/config, because both plans describe very little implemented application code. Preserve Git history in any approach.
3. Verify whether *any* database has been migrated and contains records. **Only** an empty, disposable database may use rewritten migrations and `migrate:fresh`. Live/existing data requires additive migrations, backup and rehearsed transform.
4. Fix users model/factory mismatch (`name` versus `first_name`/`last_name`), empty migration down methods, audit timestamps, foreign keys, pivot joins/indexes, singular `permission` naming, `activity_logs` file versus `audit_logs` table naming, `.env.example` without secrets, stable Composer constraints and obsolete dependencies.
5. Add data migration only if existing employee metadata is present; do not silently assign users to a made-up organization.
6. Commit database migration and rollback tests plus installation/setup guide.

## E. Functional specifications and acceptance rules

### E1. Project management

Projects belong to an organization, may be department-associated, have owner/lead, status (`planned`, `active`, `on_hold`, `completed`, `archived`), dates, membership, budget only if approved as a requirement. Tasks have assignee, priority, due date, optional milestone, checklist, comments, files and status (`todo`, `in_progress`, `blocked`, `done`, `cancelled`). One canonical assignee, additional followers optional later. List view and My Tasks first; drag board after stable data workflows. Progress = completed non-cancelled tasks / all non-cancelled tasks, with zero-task definition displayed as 'Not started'. Always show overdue/blocked counts separately.

**Acceptance:** Users only see projects they are entitled to; membership changes revoke project file/task access; complete task does not auto-approve any report.

### E2. Daily activities and approved time logs

Activity: membership, date, title, narrative, progress, blockers, optional project/task, optional duration and attachments. Staff can save/edit according to submission status; supervisors can comment. `work_logs` explicitly hold duration/approval (if payroll-relevant), with decimal hours and actor/date; do not confuse with immutable `audit_logs`. Attendance is a separate record and does not automatically prove approved billable hours. Future hourly/daily payroll can read approved time logs once policy established.

**Acceptance:** No cross-tenant project reference; managers see only authorized direct reports; unauthorized edits/approval fail with 403.

### E3. Reports

One *logical report* per membership + type + reporting window. Categories/templates: weekly structured (default), monthly, quarterly, annual, incident, ad hoc, attachment-driven, configurable `requires_attachment`. Structured fields: work completed, referenced daily activities and projects, blockers, needs, next-period plan. Auto-create **draft suggestions** from activities, but require author confirmation. Submitted **version snapshot** is immutable; edits to activities cannot rewrite it. Flow: `draft -> submitted -> approved` OR `submitted -> changes_requested -> revised draft -> resubmitted`; manager/authorized reviewer gives reason for changes; no self-approval. Status and review history append-only. Due times set per organization timezone; default Monday-Sunday reporting week and configurable submission cutoff (a Friday deadline may be inconsistent with a reporting period that ends on Sunday. Before the pilot, set the deadline relative to the period or explicitly specify the Friday after the period ends). Export PDF and summary CSV after core workflow works. Reports can carry secure attachments.

**Acceptance:** Submission/version immutable; no duplicate logical report per period; late/missing/returned filters reliable; no unprivileged downloads; no self-approval or silent revision.

### E4. Internal document management

Nested organization-scoped folders, library documents, immutable versions (one upload/version record per replacement), metadata/search, internal shares to membership/team/department/project/org; Viewer, Contributor, Manager grants. Inheritance from parent folders must be computed consistently, with clear UI; access to a document never bypasses the more restrictive parent or its linked business context. Attachment links use the parent workflow's permissions; linking a private file to a broader audience requires explicit acknowledgement and authorized access. Private storage, extension/MIME/content checks, file size (default 25 MB configurable), malware scanning/quarantine (fail closed), no executable public uploads, controlled preview. Soft-delete/trash for ordinary library objects; retention and purge by policy. Report/payroll evidence follows record-specific retention, not generic 30-day trash policy.

**Acceptance:** Revoking a share blocks new downloads/previews and derived exports; old versions follow same access; quarantined files are inaccessible.

### E5. Staff Attendance Tracking

**Purpose:** Allow organizations to monitor staff presence and punctuality fairly, while giving employees a clear personal attendance history. The module must distinguish attendance, work activity, approved leave and payroll. It must work in organization-local dates and time zones, with all actual timestamps stored in UTC.

#### E5.1. Staff clock-in and clock-out

- Staff can clock in and clock out from their authorized workspace on desktop or mobile web.
- Store the employee membership, organization, assigned work date, clock-in time, clock-out time, record origin, calculated duration, status and audit timestamps.
- Initial policy: one attendance session per scheduled workday, while allowing that session to cross midnight. Support configurable working days and expected start and end times at organization level; optional department or employee-specific schedules can be added where explicitly configured.
- Prevent duplicate open sessions, including simultaneous requests. Do not allow staff to alter stored clock timestamps directly.
- If a staff member forgets to clock out, mark the session **Incomplete**. Never assume an end time or invent hours.
- Calculate elapsed time consistently from UTC timestamps. Display hours and minutes with a clear indication that raw elapsed time is not necessarily paid work time. Break tracking and multi-shift scheduling can be planned as later extensions.

#### E5.2. Daily attendance status and exceptions

Attendance dashboards and reports must identify:

| Indicator | Meaning |
|---|---|
| Present | Valid attendance record exists for the scheduled day |
| Late arrival | Clock-in is later than scheduled start plus any configured grace period |
| Early departure | Clock-out is earlier than the expected end, subject to approved exceptions |
| Absent or missing attendance | No valid session for a scheduled day, and no approved leave, holiday or other approved exemption |
| Incomplete | Clock-in exists but no valid clock-out has been recorded |
| On approved leave | Approved leave covers the scheduled day |
| Holiday or non-working day | The organization calendar indicates attendance was not expected |
| Corrected | An authorized correction changed the effective record; its original history remains available |

Statuses should be derived from policy and reviewed exceptions; do not mark a person absent merely because they have not clocked in **yet** on the current workday. Non-attendance should become an actionable exception after the relevant scheduled window or review cutoff, not an unqualified disciplinary conclusion.

#### E5.3. Attendance correction and review

- Staff can submit an attendance correction request with the date, proposed adjustment and reason. Managers or HR can accept or reject requests according to scoped permissions.
- Authorized managers can correct direct-report records using a mandatory reason, but the system must preserve the original timestamps, corrected values, reviewer and decision date in append-only history.
- No manager can change a record from an unrelated organization or an employee outside their approved scope.
- Every correction that changes computed hours or a status triggers recalculation of the affected daily and monthly summaries.
- Approved leave and holidays take precedence over ordinary missing-attendance alerts, but leave details remain private.

#### E5.4. Attendance screens and management reporting

**Staff view:** Current clock-in status, clock-out action, today's recorded hours, personal calendar/history, lateness and missing-record indicators, and outstanding correction requests.

**Manager view:** Authorized direct reports or teams, who is checked in, not yet checked in, late, absent for finalized periods, on leave or missing a clock-out. Filter by employee, team, department, date and status. Managers can review corrections without viewing unrelated departments.

**HR and authorized administrator view:** Organization-wide daily attendance register, punctuality trends, scheduled versus recorded days, total recorded hours, attendance exceptions, correction audit and monthly summaries. Department comparisons are permitted only where access has been granted.

**Exports:** Daily register and staff-level weekly/monthly CSV exports, including work date, staff employee number, department/team, expected schedule, actual clock-in/out, elapsed hours, attendance status, approved exceptions and corrected status. PDF printable summaries can follow once the CSV/reporting logic is stable.

**Suggested dashboard figures:** Scheduled staff, checked-in staff, late arrivals, confirmed missing attendance, incomplete records, approved leave count, total recorded hours and correction requests awaiting review. Counts must be calculated only from authorized records and clearly show their date range.

#### E5.5. Privacy, payroll boundaries and reliability

- No GPS, facial recognition, biometric verification, surveillance screenshots or location monitoring in the initial release.
- Limit access to attendance by active organization membership and role or management relationship. Restrict bulk exports and audit who downloads them.
- Use calendar-aware logic for weekends, holidays, leave and shifts crossing midnight. Store actual instants in UTC, with local dates resolved using the organization's configured time zone.
- An attendance record does not automatically prove productive work and does not automatically trigger salary deduction or overtime. Finance may review attendance as context during payroll preparation, but any pay adjustment requires a separate authorized rule or explicit entry.
- Retention periods and local employment/privacy requirements must be agreed before production deployment.

**Acceptance:** A staff member can record a real working day, see their attendance history and request a correction. A manager can review their team, and HR can generate a daily register and monthly attendance summary. Duplicate clock-ins, overnight sessions, holidays, leave days, late arrivals, missing clock-outs, correction history and cross-organization access checks all pass automated tests.

### E6. Leave management

HR configures leave types, annual allowances, holidays and working days. Full-day requests, reason and private attachments, balance view. Requested dates exclude weekends and configured holidays; block overlapping active requests and insufficient balances. Balance **reserved** on request, released on withdrawal/rejection, finalized on HR approval. Flow: `submitted -> manager_approved -> hr_approved` (or rejected at either stage). HR may cancel approved leave and post restoring balance transaction. No self-approval. Separate approvers by stage as required; if none available, pending/escalate to owner for approver assignment without overriding the self-approval prohibition. Balances managed with transaction + row locking and append-only ledger. Sensitive leave reasons visible only to requester and approved HR/reviewers. Team calendar exposes availability without medical/reason details.

**Acceptance:** Concurrent requests do not overbook allowance; rejection/withdrawal/cancel restore exact values; holiday/overlap rules pass tests.

### E7. Payroll (manual payment records only)

Finance maintains effective-dated compensation, recurring allowances/deductions. Default monthly periods, optional one-off additions/deductions. Prepare a run with **snapshotted** pay lines so future raises don't mutate past results. Flow: `draft -> submitted -> approved -> partially_paid/paid`, with return-for-correction before approval; finance preparer cannot approve own run. Approved runs locked; corrections via linked adjustment entries, not edits. Record external payments per employee: amount, date, method, reference, proof file; multiple installments allowed, sum cannot exceed due. Produce authenticated downloadable payslips, finance CSV and reconciliation. Attendance, leave, reports displayed as context only; no automated penalties/deductions. Statutory taxes, filings, country-specific compliance and auto-proration are a separate policy/legal project; do not suggest generic deduction types are legally complete. Hourly/daily derived payroll and auto-disbursements deferred.

**Acceptance:** Currency-accurate totals; duplicate jobs cannot double-create pay lines/payment entries; approver independence; historic rates fixed; partial-pay balance reconciles exactly; staff see only own payslips.

### E8. Communications, dashboards and audit

In-app bell + email: invites, assignments/deadlines, report reminders/decisions, leave approvals, file shares, payslip releases. Queued email with retries, backoff, failure capture, deduplication event+recipient+channel, no sensitive payroll/medical details in email; deep links require fresh permission check. Announcements to organization/departments/teams with schedule/expiry; attachment access follows audience.

Role-specific dashboards: staff (today, check-in/out status, attendance history, tasks, reports, leave), manager (reviews, checked-in staff, late/missing attendance, correction requests, team leave), HR (staff attendance register, daily/weekly/monthly summaries, late arrival trends, incomplete records, leave), finance (runs and recorded payments, attendance context without automatic deductions), owner (staff, attendance trends, projects, pending approvals). Counts and exports use the same security scopes as detail pages.

Audit events include organization, actor user/membership, action, entity, before/after of safe fields, timestamp, IP/request_id, reason; append-only and limited-view. Never log password hashes, tokens, full payroll/medical content. Preserve workflows' own event histories, in addition to general audit.

## F. Suggested relational data inventory

This is a **logical schema checklist**, not a mandate to create every table on day one. Final names and indexes belong in migration design review. Prefer reusable file linkage and approval history infrastructure where appropriate.

| Area | Proposed tables |
|---|---|
| Identity & tenancy | `users`, `organizations`, `organization_settings`, `organization_memberships`, `organization_invitations`, `departments`, `teams`, `team_memberships` |
| RBAC & trust | `roles`, `permissions`, `role_permissions`, `membership_roles`, `audit_logs`, `notifications`, `mail_outbox`, queue/cache support tables |
| Projects | `projects`, `project_members`, `milestones`, `tasks`, `task_checklist_items`, `task_comments` |
| Work & reporting | `daily_activities`, `work_logs`, `report_categories`, `report_templates`, `reports`, `report_versions`, `report_reviews`, `report_activity_snapshots` (or JSON snapshots) |
| Files | `folders`, `documents`, `document_versions`, `document_shares`, `document_links` |
| **Staff attendance tracking** | `attendance_sessions`, `attendance_correction_requests`, `attendance_corrections`, `attendance_exception_reviews`, `work_calendars`, `organization_holidays` (daily summaries may be computed rather than stored initially) |
| Leave | `leave_types`, `leave_policies`, `leave_balances`, `leave_requests`, `leave_approvals`, `leave_balance_transactions` |
| Payroll | `compensation_history`, `compensation_adjustments`, `pay_periods`, `payroll_runs`, `payroll_lines`, `payroll_line_items`, `payroll_approvals`, `payment_records`, `payslips` |
| Communications | `announcements`, `announcement_audiences`, `notification_dispatches` (if not covered by mail outbox) |

**Schema rules:** All organization-owned records carry `organization_id`. Staff references use `organization_membership_id` rather than a naked global `user_id`. Cross-table relationships must validate matching organizations through both application policies and database constraints where practical. Unique composites enforce membership/email invitations/reports and payment idempotency. Index `(organization_id, status)`, date, owner and foreign keys used in tenant-scoped queries. Do not soft-delete append-only history (audit, approved payroll, report versions, balance ledger). For confidential record retention use restricted archive + explicit purge policies, not universal soft delete.

## G. Build plan: Estimated 22 focused weeks

Durations assume one experienced full-time developer with ordinary tooling and access to a tester/stakeholder for acceptance. **Estimate, not a promise:** approximately 18 to 28 or more calendar weeks depending on requirements, infrastructure, and feedback. If the project is team-staffed, dependencies matter more than dividing the estimate by headcount.

### Phase 0: Audit and foundation (Weeks 1 to 2)

**Goal:** Supported, repeatable Laravel foundation; no missing env/model/schema blockers.

- [ ] P0.01 Snapshot Git branch/repo, inventory source migrations and actual database; back up data.
- [ ] P0.02 Verify framework/PHP/packages; select upgrade path to supported Laravel 13 and PHP 8.3+.
- [ ] P0.03 Establish `.env.example`, secure app key for local env, local MySQL, queue, mail log and private filesystem.
- [ ] P0.04 Resolve users/Factory columns, migration down(), timestamps, table names, FK/pivots, stability of Composer lockfile.
- [ ] P0.05 Adopt Blade + Bootstrap + Vite; shared navigation/form/error layouts; install tests and quality tools.
- [ ] P0.06 CI checks: migrations, auth smoke, test runner, static analysis/lint; no secrets in source.
- [ ] P0.07 Write setup guide and clear data-safe rollback strategy.

**Done when:** Brand-new developer checkout starts, migrates and runs tests; migrations are safe to execute; audit event timestamp exists; framework supported.

### Phase 1: Tenancy, accounts, teams and permissions (Weeks 3 to 5)

- [ ] P1.01 Create organizations, workspace settings and membership models/migrations.
- [ ] P1.02 Provision first pilot organization and owner using platform admin.
- [ ] P1.03 Invitation accept/expire, login/logout, verified email, password reset, session controls and membership suspension.
- [ ] P1.04 Departments, teams, manager hierarchy (reject cycles), employment profiles and lifecycle.
- [ ] P1.05 Predefined role templates, custom role editor, ability/scope matrix, scoped policy layer.
- [ ] P1.06 Workspace switcher and role-aware navigation; organization resolution middleware.
- [ ] P1.07 Audit events, admin membership/roles pages, baseline notifications and mail queue.
- [ ] P1.08 Seed two test organizations; add forbidden URL/list/search/export tests across organization boundaries.

**Done when:** User joins two organizations with different roles, switches context, and cannot obtain even metadata from an unrelated workspace. Suspended membership loses access immediately. Organization admin has no finance privileges unless granted.

### Phase 2: Projects and task execution (Weeks 6 to 7)

- [ ] P2.01 CRUD project, project member/lead, status and activity history.
- [ ] P2.02 Milestones, task assignment, status, priority, due dates and checklists.
- [ ] P2.03 Comments and task-level activity trail; permission-checked assignee notifications.
- [ ] P2.04 My Tasks, project list/details, simple progress and overdue/blocked indicators.
- [ ] P2.05 Enforce membership and department/project scopes in queries, detail and exports.

**Done when:** Manager assigns work; staff sees assigned tasks; project lead can manage only the assigned project; progress cannot hide overdue tasks.

### Phase 3: Daily activities and reports (Weeks 8 to 10)

- [ ] P3.01 Daily activity form/history, blockers, optional project/task links and optional hours.
- [ ] P3.02 Optional approvable time-log records separate from attendance; manager comments.
- [ ] P3.03 Report categories/templates and organization-defined windows/deadlines.
- [ ] P3.04 Weekly report auto-draft from activities, review by author, submit, return for changes, revise/resubmit, approve.
- [ ] P3.05 Immutable report versions and review/status history, no self-approval.
- [ ] P3.06 Secure report attachment MVP; missing/late/approved manager view; email/bell notifications.
- [ ] P3.07 PDF report and CSV status export when core workflow stable.
- [ ] P3.08 Run small pilot: three staff, one manager, actual week of test data.

**Pilot milestone:** A real organization can plan, assign, log, submit and review an entire weekly work cycle without spreadsheets.

### Phase 4: Document library and collaboration (Weeks 11 to 13)

- [ ] P4.01 Private storage adapter; upload validation, quarantine/antivirus scanning policy, download endpoint.
- [ ] P4.02 Folder hierarchy, document metadata, search, version history and trash.
- [ ] P4.03 Grant/sharing engine: user, team, department, project and organization audiences; inherited permission checks.
- [ ] P4.04 Link attachments to projects/tasks/activities/reports without broadening confidential access silently.
- [ ] P4.05 Announcements and publication/expiry; basic comments where missing.
- [ ] P4.06 Prove revocation instantly blocks new previews/downloads and links.

**Done when:** Sharing and versioning work without exposing private files by URL guessing, direct storage links or cross-tenant references.

### Phase 5: Staff attendance tracking and leave management (Weeks 14 to 16)

**Staff Attendance Tracking is a first-class deliverable in this phase.** Implement the employee-facing attendance controls and management reporting before completing the leave approval workflows. Reuse the organization permissions, identity, working calendar and audit foundations from earlier phases.

**Attendance implementation checklist**

- [ ] P5.01 Define organization workdays, hours, time zone, grace period, holidays and optional individual schedule assignments.
- [ ] P5.02 Build attendance sessions with clock-in and clock-out, UTC event timestamps, local work date, status and calculated elapsed time.
- [ ] P5.03 Prevent duplicate open sessions; handle overnight work and forgotten clock-outs without fabricating hours.
- [ ] P5.04 Calculate daily attendance indicators: present, late, early departure, absent or missing, incomplete, on leave, holiday and non-working day.
- [ ] P5.05 Build the employee attendance screen, calendar and personal history, including visible clock-in/out state and recorded hours.
- [ ] P5.06 Add correction requests, manager/HR approval or rejection, original-value history and action audit events.
- [ ] P5.07 Build manager team attendance, HR daily register and organization attendance dashboards with access-scoped counts.
- [ ] P5.08 Build daily, weekly and monthly attendance filtering and CSV export, including employee, department, scheduled versus actual times and exception statuses.
- [ ] P5.09 Test active staff, suspended staff, simultaneous clocks, time zones, overnight sessions, missed punches, leaves, holidays, corrections and cross-tenant access.

**Leave implementation checklist**

- [ ] P5.10 Configure leave types, annual allowances, calendars, balances, full-day requests and date overlaps.
- [ ] P5.11 Build manager first approval and HR final approval, rejection, withdrawal and cancellation flows; block self-approval.
- [ ] P5.12 Keep leave reservations and finalized balance changes in a transaction-safe append-only ledger.
- [ ] P5.13 Display team leave calendars, apply approved leave to attendance exception logic, and protect confidential leave reasons.
- [ ] P5.14 Test concurrent leave requests, balance reversals, manager/HR scope, holidays and linked attendance summaries.

**Done when:** Employees can clock in and out, review and correct their attendance; managers can identify authorized staff who are late, absent or missing a punch; HR can export accurate daily and monthly attendance reports; and leave balances remain exact and fully auditable.

### Phase 6: Payroll and payment records (Weeks 17 to 19)

- [ ] P6.01 Effective-dated compensation and recurring/one-off allowance/deduction records.
- [ ] P6.02 Monthly pay period and payroll run generation with immutable earnings snapshots.
- [ ] P6.03 Independent approval; return, approve/lock and separate adjustment workflow.
- [ ] P6.04 Partial or full external payment recording with reference/proof and balance reconciliation.
- [ ] P6.05 Secure payslip PDF, staff payslip access, finance dashboards and exports.
- [ ] P6.06 Payroll calculation/idempotency/cross-tenant tests; legal/accountant review of displayed rules.

**Done when:** Preparer/approver separation is enforced; runs cannot be silently changed; payment totals never exceed payroll due; staff only access their payslips.

### Phase 7: Final release, security and operations (Weeks 20 to 22)

- [ ] P7.01 Polish staff, manager, HR, finance and owner dashboards with identical data scopes.
- [ ] P7.02 Notification retries/reminders, queue health, dedupe and sensitive email redaction.
- [ ] P7.03 Security pass: access control (including export/storage), upload handling, rate limits, CSRF, 2FA for privileged roles, secrets, audit.
- [ ] P7.04 Mobile/usability and accessibility tests; meaningful empty/error/loading states.
- [ ] P7.05 Load check with representative volume; indexing/query review and queue/DB sizing.
- [ ] P7.06 Staging production-like VPS setup: HTTPS, process supervisor, scheduler, monitoring and logs.
- [ ] P7.07 Scheduled DB/document backups and **full restore drill**; disaster recovery runbook.
- [ ] P7.08 Pilot UAT with multiple staff/manager/HR/finance users; fix critical issues.
- [ ] P7.09 Versioned production deployment, rollback playbook and admin user manual/training.

**Release gate:** Full staffing-to-payroll cycle passes acceptance; organization separation test suite passes; backup restore proven; no known critical/high security defects; designated owner signs off.

## H. Dependencies and critical path

`Foundation -> Tenancy/Identity/RBAC -> Projects/Tasks -> Activities -> Reports -> Pilot`

`Tenancy/RBAC -> Files -> Secure attachments/Collaboration`

`Tenancy + Calendar -> Staff Attendance Tracking -> Attendance Summaries and Corrections`

`Tenancy + Calendar + HR Rules -> Leave Approval -> Attendance Exception Reconciliation`

`Tenancy + Membership/Compensation -> Payroll -> Approval/Payments/Payslips`

`Notifications, Audit, Security, QA, Operations` run continuously and finish with release gate.

**Do not** put the document library ahead of tenant/permission design; do not derive payroll blindly from attendance/worklogs; do not implement payroll before compensation histories and approval policies.

## I. Acceptance test catalogue (minimum)

| ID | Critical scenario | Pass condition |
|---|---|---|
| SEC-01 | Open a record from another org via modified URL | 404 or 403 without data leakage |
| SEC-02 | Find another org's record via list, search, dashboard or CSV | No results or counters leaked |
| SEC-03 | Download cross-tenant or revoked file | Denied before bytes are streamed |
| SEC-04 | Suspended membership with live session | Access revoked immediately |
| SEC-05 | Grant non-finance org admin role | No payroll access without finance permission |
| RPT-01 | Submit report, later edit underlying activity | Old submitted version unchanged |
| RPT-02 | Reject report without explanation or self-approve | Rejected by validation/authorization |
| PROJ-01 | Cross-org task assignee and project membership | Validation rejects mismatch |
| FILE-01 | Upload malware/forbidden executable, attempt direct URL | Quarantined/blocked and never publicly served |
| ATT-01 | Two concurrent check-ins | Only one open attendance session |
| ATT-02 | Clock out after midnight | Correct timestamp, duration and org-local work date |
| ATT-03 | Staff clocks in after grace period | Late indicator uses configured schedule and grace period |
| ATT-04 | No clock-in for scheduled day after cutoff | Missing attendance flagged, except approved leave and holidays |
| ATT-05 | Staff forgets to clock out | Remains incomplete; no fabricated time |
| ATT-06 | Staff submits correction and manager decides | Original and new values, reason and decision history retained |
| ATT-07 | Manager from another team/org requests attendance | Access denied according to membership scope |
| ATT-08 | HR exports monthly attendance | Dates, totals and exception counts match source records |
| ATT-09 | Attendance record and payroll are linked in review | No automatic salary deduction or overtime payment |
| ATT-10 | User with two organization memberships switches workspace | Clock-ins and attendance summaries remain isolated by organization |
| LEAVE-01 | Two simultaneous requests exhaust remaining balance | No negative balance; one denied when insufficient |
| LEAVE-02 | Reject/withdraw/cancel reserved/approved leave | Exact ledger reversal with actor/reason |
| PAY-01 | Increase salary after previous payroll approval | Past pay lines unchanged |
| PAY-02 | Same actor prepares and approves payroll | Forbidden |
| PAY-03 | Record duplicate or over-amount payment | Idempotent/rejected; due cannot go negative |
| NOTIF-01 | Retry same reminder/job | No duplicate recipient event; email non-sensitive |
| OPS-01 | Restore full database + object/file backup | Attachments and record links functional |

Test scope: unit tests for calculations/permissions; feature tests for workflow; integration for queue, storage, email; at least one staging end-to-end walkthrough; concurrency tests on actual MySQL (not only SQLite, which differs in locking/constraints).

## J. Deployment and operations approach

Development -> CI -> Staging -> Production with an approval gate. Use Git branches/PRs, environment-specific secrets, locked Composer/npm dependencies. For the first pilot a modest VPS running Nginx + PHP-FPM + MySQL (or managed MySQL) + supervisor-managed queue workers + scheduled artisan tasks is enough, sized after actual measurements. Do not impose microservices/Kubernetes. Keep queue/scheduler processes alive and monitor failures; shared hosting without reliable workers is a poor fit for scanning/mail reminders.

Security baseline: TLS, least-privilege service accounts, firewall, DB not exposed to internet, security updates, privileged 2FA, signed/logged backups, retention plan for personnel records, encrypted sensitive attributes when warranted, anti-malware scans, upload quotas, dependency vulnerability checks, incident-response contacts. Review payroll and personal-data legal obligations for jurisdictions of intended organizations before production onboarding.

Metrics: failed queue jobs, invitation delivery, pending approvals, weekly submission compliance, backups age, errors, slow DB queries, upload/scan failures; alert on failures in money/approval workflows. Document restore RPO/RTO goals with the organization before launch rather than claiming an arbitrary SLA.

## K. Immediate first-five-day execution agenda

**Day 1: Verify reality**
- [ ] Save both source plans and this master plan in `/docs/planning` under version control.
- [ ] On the actual repository, capture `git status`, `php -v`, `composer show laravel/framework`, `php artisan --version`, migrations and DB table counts.
- [ ] Backup any existing DB; identify whether it is empty; inventory custom Laravel 9 modifications.

**Day 2: Modern baseline**
- [ ] Branch `feature/foundation-modernization`.
- [ ] Test migration path to supported Laravel/PHP; prefer clean current skeleton + careful port for a largely unimplemented starter repository.
- [ ] Align User, factory, auth and environment config; choose MySQL and private files.

**Day 3: Tenant ERD**
- [ ] Draw ERD for users, organizations, memberships, teams/departments, manager relation, roles/permissions.
- [ ] Finalize organization ID uniqueness and indexes; write authorization invariants.
- [ ] Define first-run organization/owner bootstrap and invitation flow.

**Day 4: Code first slice**
- [ ] Migrate organizations, memberships, departments, roles/permissions.
- [ ] Seed safe test data for **two** organizations.
- [ ] Implement tenant context resolver and first policies.

**Day 5: Demonstrate isolation**
- [ ] Test login, switch organization and deny foreign data access.
- [ ] Review sprint backlog and criteria for Phase 1; fix failures before moving on.

## L. Delivery management rules

Every task gets an ID (P0.01 etc.), owner, priority, estimate, dependency, status (`Backlog`, `Ready`, `In Progress`, `In Review`, `Blocked`, `Done`) and acceptance evidence. Do **not** mark a task done until tests, authorization checks, error messages and relevant audit events exist. Keep weekly sprint demonstrations and a small change log. If time runs short, protect tenant isolation, auth, activity/report integrity and financial locks; defer visual enhancements instead.

**Definition of Done for a feature:** migration and rollback strategy; server validation; policy/scope on endpoints and exports; correct organization ID assignment; functional UI; audit if state-changing; tests for happy/forbidden/edge paths; basic docs; reviewer sign-off.

**Decisions needing stakeholder validation (do not block foundations):** Attendance grace period, staff schedules, weekend and holiday rules, attendance correction reviewers, cutoff for missing attendance, data retention, whether multi-workspace is truly needed commercially (chosen yes for this plan); who provisions owners; reporting cutoff relative to reporting week; leave allowance details; country payroll obligations; retention policy; required email/domain infrastructure; optional 2FA policy; staffing/headcount for delivery. These may change configuration or later phases but should not overturn tenant/identity/security foundations without an explicit decision.


**One-line priority:** First make the platform *safe for two organizations*, then make *one team's weekly work cycle useful*, then add *documents, comprehensive staff attendance tracking, leave and finance*, and finally harden for release.
