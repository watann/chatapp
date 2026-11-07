ChatApp - Simplified Institution Management / Unified Approval Upgrade
=======================================================================
Review date: 2026-10-31

INSTALLATION / UPGRADE
----------------------
Existing installation with data:
1. Back up the database first.
2. Replace the project files with this upgraded version.
3. If upgrading from the 2026-10-30 version, run DATABASE_MIGRATION_2026_10_31.sql once. DATABASE_MIGRATION.sql also contains the appended upgrade section.
   If your database is older than 2026-10-30, run the 2026-10-30 migration first, then 2026-10-31.
4. Sign in as the Institution Manager (database role remains super_admin for backward compatibility).
5. Open Institution Structure, confirm department responsibilities, and assign an official Staff account as head of each department.
6. Department heads submit staffing/resource/procurement/budget/other requests from Department Requests. The Institution Manager approves or rejects them.
7. Finance staff use Finance Management to record receipts/expenses. Finance entries made by department staff/head remain pending until manager approval.

Brand-new installation:
- Import chatapp.sql.
- Open admin/setup.php once to create the first Institution Manager / Super Admin account.

Do NOT import chatapp.sql over a database whose existing data must be preserved.


SIMPLIFIED MANAGEMENT MODEL (2026-10-31)
----------------------------------------
- The sidebar and dashboard now show only tools relevant to the signed-in department.
- Every department account has a dedicated My Department page.
- Central Setup is the single page for institution departments, department managers, study departments, teacher certificate types, teacher specializations, and staff workplaces.
- The Institution Administration department head may propose Central Setup changes; these changes remain pending until the Institution Manager approves them.
- Study departments can now have an official teacher assigned as study-department head.
- New registry records and registry status changes submitted by department heads are routed to Institution Manager approval instead of applying immediately.
- Teacher certificate type is mandatory for teacher registry creation; common certificate types are seeded for new/updated installations.
- Manager Approvals uses the existing department request queue for both department needs and high-impact configuration/registry changes.
- Routine operational work remains inside the responsible department so the Institution Manager does not become a bottleneck for every small daily action.

PROFESSIONAL INSTITUTION STRUCTURE
----------------------------------
The application now has a real organizational hierarchy instead of treating every administrator as a generic admin.
Default departments are:
- Academic Affairs
- Finance
- Institution Administration
- Student Affairs
- Human Resources
- Information Technology

Each department has a responsibilities field. The default schema/migration supplies a clear responsibility scope for the default departments, and newly created departments can be given their own responsibility statement.

Institution Manager (database role: super_admin):
- Creates/nests and activates/deactivates departments.
- Assigns/reassigns official Staff members as department heads.
- Reviews every formal request submitted by department heads.
- Can directly perform manager-authorized staffing changes.
- Reviews pending Finance transactions.
- Can see the cross-system administrative Audit Log.

Department Head:
- Can see only their own department in Institution Structure.
- Cannot directly add/remove department staff.
- Must submit a formal request for staffing changes or other departmental needs.
- Can request a genuinely new staff position (Staff Requisition), assign an existing registered staff member, remove a department staff member, request resources, procurement, budget, or another need.
- Can cancel only their own still-pending requests.
- Cannot forge a department_id in POST data; the backend derives the department from the authenticated head account.

Department Staff:
- Receives only the operational permissions attached to the assigned department type.
- Cannot manage the institution hierarchy or department staffing.

If a department or any parent department is inactive, linked department access is denied while the assignment remains preserved.

MANAGER APPROVAL WORKFLOW
-------------------------
Department request states:
- pending
- approved
- rejected
- cancelled
- completed

Request types:
- New staff requisition (request a new position/person without selecting an existing account)
- Add existing employee
- Remove employee
- Resource/equipment
- Procurement/expense
- Budget
- Other

Important controls:
- Add/remove employee requests are re-validated again at approval time to prevent stale/race-condition assignments.
- An existing employee cannot be silently taken from another department by a department head.
- Approved add/remove actions execute automatically and are marked completed with an executed timestamp.
- Procurement/budget requests remain approved until Finance processes the linked expense, then become completed.
- Staff requisition/resource/other requests can be marked completed by the Institution Manager after fulfillment.
- Rejection requires a written review reason.
- Duplicate still-pending requests are blocked where the target/title matches.
- Approval/cancellation/execution actions are written to the administrative audit log.

FINANCE MODULE
--------------
Finance is now an operational module rather than only a department name.
It provides:
- Income and expense recording.
- Transaction date, source/payee, reference number, and description.
- Pending -> approved/rejected manager workflow for entries submitted by Finance staff/head.
- Manager-created entries are manager-authorized immediately.
- Only APPROVED transactions affect income, expense, and current balance.
- Approved procurement/budget requests can be linked to their expense.
- Linked expense amount must exactly match the approved request amount.
- A linked request cannot have a second pending/approved Finance transaction.
- Linked-request processing is transaction-locked to reduce concurrent duplicate disbursement risk.
- Rejected Finance entries do not permanently block a corrected resubmission.
- The application exposes no edit/delete operation for approved ledger entries.
- Finance approvals/rejections and request completion are audited.

AUDIT / GOVERNANCE
------------------
A new Institution Manager-only Audit Log shows the latest administrative audit events, including:
- Department request submission/cancellation/approval/rejection/completion.
- Finance submission/approval/rejection and linked request completion.
- Department creation, activation/deactivation, head assignment, and staff add/remove operations.

OTHER LOGIC / SECURITY FIXES VERIFIED OR IMPROVED
-------------------------------------------------
- Suspended registry-linked accounts are denied social, academic, and department-admin access.
- Suspended accounts are excluded from incoming friend requests, accepted-friend lists, and profile friend previews/counts.
- Chat-group deletion now cleans up stored attachment files after a successful database deletion instead of leaving orphan files on disk.
- If the last member leaves a chat group, attachment cleanup is also performed when the empty group is deleted.
- Logout is now POST-only and CSRF-protected for both user and admin portals.
- The unused legacy api/set-language.php state-changing endpoint was removed; active language controls use the current application flow.
- Database connection failures are logged server-side and no longer expose the raw DB connection error to users.
- Database settings can be supplied through CHATAPP_DB_HOST, CHATAPP_DB_USER, CHATAPP_DB_PASS, and CHATAPP_DB_NAME environment variables. Existing localhost/root/chatapp defaults remain for local compatibility.
- Official department heads/staff must have a linked active platform account before receiving department-admin access.
- Student annual progression remains idempotent per academic year and transaction/unique-key protected.
- Timetable conflict checks, grade approval locking/audit, protected media visibility, block/privacy rules, bounded chat history, stale-call cleanup, and presence handling were rechecked.
- Official Student/Teacher/Staff role remains authoritative and cannot be self-assigned from Settings.

DEPARTMENT PERMISSION SCOPE
---------------------------
- Academic Affairs: academic groups, timetable coordination, and grade review/approval.
- Student Affairs: student official records and student progression.
- Human Resources: teacher/staff official records and workforce administration.
- Institution Administration: central official registry administration.
- Finance: institutional finance ledger and approved disbursement processing.
- Information Technology: organizational assignment exists for IT operations/support; no unrelated technical-ticket system was invented.
- Institution Manager: institutional oversight and manager-only approval/governance controls.

DATABASE FILES
--------------
- chatapp.sql: complete schema for a fresh installation.
- DATABASE_MIGRATION_2026_10_30.sql: upgrade for an existing database.
- DATABASE_MIGRATION.sql: migration history plus this upgrade.

DEPLOYMENT / TEST NOTE
----------------------
A live MySQL/MariaDB server and PHP MySQL driver were not available in the review environment, so database integration could not be executed against a running server here. The SQL schema, migration structure, foreign-key references, PHP syntax, JavaScript syntax, parameter bindings, permissions, CSRF coverage, and translation coverage were statically validated. Always run the migration first on a backed-up staging copy when possible.

STUDY DEPARTMENTS & TWO-LEVEL REGISTRY LIST APPROVAL (2026-11-04)
----------------------------------------------------------------
IMPORTANT: Existing database with records: make a BACKUP first and run
DATABASE_MIGRATION_2026_11_04.sql in phpMyAdmin (SQL tab) on the chatapp
MySQL database AFTER the 2026-10-31 migration. Do NOT import chatapp.sql
over an existing database: it is a full reset/fresh-install schema.
For a completely new installation, importing chatapp.sql is sufficient.

New pages:
- /admin/study-departments.php: clean page for study departments.
  Institution Manager can approve a new department immediately using the
  explicit approve/add action. Administration Head submits additions, renames,
  status changes and academic-head assignments to Manager Approvals.
- /admin/registry-batches.php: create/view official registration LISTS.
- /admin/registry-batch.php?id=N: review and edit individual rows of a list.
- /admin/records.php: official records only; old direct-create form removed.
- /admin/requests.php: pending registration lists are shown next to other
  formal approval requests for the Institution Manager and Administration Head.

Default institution department with code `registry` (type `other`) is created.
Assign official Staff as Registry Department Head and other official Staff
as Registry Department Staff through the existing Institution Structure
/ Department Requests tools; access becomes available once assigned and active.
Existing Student Affairs / HR personnel can also draft lists of the relevant
person types; Administration staff can prepare lists as well.

THE WORKFLOW (all of it enforced on the SERVER):
1) Registry department staff (or authorized Student Affairs / HR staff)
   creates a DRAFT list specifying one type: students, teachers or staff.
2) Registry adds people to the list. Student department dropdown contains
   ONLY active approved study departments. Teacher certificate type is required.
3) Registry submits the entire list to the Administration Department.
4) Administration staff/head may EDIT / DELETE / ADD rows during review.
   Only the Administration Department Head may approve and forward to the
   Institution Manager, or return the list to Registry with a reason.
5) The Institution Manager reviews the FULL list, may return it to
   Administration with a reason, or gives FINAL APPROVAL.
6) On final approval, all rows are validated again and inserted into the
   official institution_registry in one MySQL transaction. Only then are
   generated IDs issued. Any failure rolls the entire list back.
7) An approved list is immutable. Repeated final approval is blocked.

Each batch has a history of the actor and decision (registry_batch_events).
Duplicate identity numbers inside a list are rejected by a unique index,
and duplicate identity numbers already issued in official records are rejected.
Staff cannot forge a later workflow status with form data; permissions and
transitions are enforced in RegistryBatchController on the server.

The old api/create-record.php endpoint now refuses direct registration.
Previously created official records and unrelated social/finance/academic
features are left untouched. Existing old-style, already-pending legacy
registration requests may still be completed through the legacy approvals
screen to avoid silently destroying historical requests; create all new
registrations through Registry Lists.

KNOWN DEPLOYMENT VERIFICATION REQUIRED:
- This environment has PHP but no php-mysqli/php-mbstring modules and no
  running MySQL server, so a real web/browser + database integration test
  was not possible here. PHP syntax and JavaScript checks were run.
- On a staging copy with the new migration, test: Registry Staff creates
  a student list > adds two rows > submits > Administration Staff edits
  a row > Administration Head approves > Manager opens the list > Manager
  approves > both official IDs appear in Official Records.
- Also test all rejection/return paths, duplicate identities, inactive
  study departments, and permission denial for Finance staff.
