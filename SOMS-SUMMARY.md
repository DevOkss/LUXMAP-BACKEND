# SOMS — Laravel Admin Portal

## Latest Session (August 10, 2026) — single active session + PWA install landing page + deploy readiness

> Backend + admin-portal work. The student PWA got matching changes (offline map fallback, install prompt, security-gate hardening) — see `PWA-SUMMARY.md`.

### One active session per account (device-bound tokens)
- **Migration** `2026_08_12_000005` adds nullable, indexed `personal_access_tokens.device_fingerprint`.
- **`AuthService`** (`attempt`, `register`, new `issueToken`) tags every API token with the calling device's fingerprint (from the `X-Device-Fingerprint` header) and **revokes that device's previous token** so each device holds at most one session.
- **`AuthController`** passes the fingerprint header into `attempt()`/`register()`.
- **`DeviceBindingService`** now revokes the old device's tokens when a binding transfer is **approved** (and on **unbind**), so the previous device is logged out immediately after the binding moves.
- Tests (`DeviceTest` +3): token tagged per device / one session per device; approving a transfer revokes the old device's token while the new device's survives.
- **Login 500 fix**: the pending `device_fingerprint` migration had never been run against MySQL (`Unknown column 'device_fingerprint'`). Ran `php artisan migrate --force`. Two stray `create_paymongo_tables` / `drop_payment_submissions_table` migrations (from the abandoned PayMongo plan) were **rolled back and deleted** — `payment_submissions` table + `payments.payment_submission_id` FK restored via a new `2026_08_12_000006_restore_payment_submission_fk_on_payments` migration. Full suite **321 passed**.

### PWA install landing page (`GET /app`)
- **`LandingController::app()`** + public route `GET /app` (`app.landing`), renders `resources/views/pwa-landing.blade.php` — a promotional page (LuxMap green/amber theme, Plus Jakarta Sans): hero with **Open App** + **Install App** buttons and a large `dashboard.png` mockup (no phone frame / no image shadows), feature cards, a 3-screenshot showcase (shadow removed), a 3-step "How to install", and an amber CTA band.
- **Install button** uses `beforeinstallprompt`; since the prompt only fires on the PWA's own origin, the button navigates to `PWA_URL` where the PWA auto-surfaces its install banner.
- `services.pwa.url` config (`PWA_URL` env) drives the target; `.env`/`.env.example` updated.
- Tests (`LandingPageTest`): public page renders install content + configurable URL.

### Deploy readiness (PWA on Vercel, API on Hostinger)
- **`config/cors.php`** added (env-driven): `paths: api/* + sanctum/csrf-cookie`, `allowed_methods: *`, `allowed_origins` from `CORS_ALLOWED_ORIGINS` (comma-separated), `allowed_headers` incl. `Authorization` + `X-Device-Fingerprint`.
- **`.env`/`.env.example`** gain `PWA_URL`, `CORS_ALLOWED_ORIGINS`, and clarified `APP_URL`.
- PWA side: `vercel.json` (SPA rewrite + `no-cache` on `/sw.js`), `.env.example` documenting `VITE_API_URL`/`VITE_QR_KEY`/`VITE_VAPID_PUBLIC_KEY`; API already returns relative `/storage/...` URLs and the PWA prefixes the `VITE_API_URL` origin.
- **LuxMap rename**: `APP_NAME=LuxMap`; admin login subtitle, landing page meta, and test updated.

## Earlier on August 9, 2026 — admin dashboard overview + transactions Excel export

> Backend/admin-portal only; the student PWA was unchanged this session (see PWA-SUMMARY.md for the device-binding pairing).

### Admin dashboard rewrite (`/admin/dashboard`)
- **`Admin\DashboardController`** rebuilt as a single overview: scope (heads/officers → their org, session `current_organization_id` override, super admin → all), selected-term resolution, and a rich prop set.
- **Income is paid-only**: `total_income` sums `Payment::paid()` (status `paid`) scoped to org + term; exempted money is reported separately as `exempted_amount` (`Payment::exempted()`). `income_breakdown` splits paid income into `fees` vs `penalties`.
- **`income_chart`** — monthly paid-income series; grouping happens in PHP (not DB date functions) so SQLite tests and MySQL agree; the current month is highlighted for an active term. `seriesAxis()` builds the months inside the term's start/end window (fallback: months present in data, then current month).
- **`org_breakdown`** — per-org `students` (via `EligibilityService::studentIds`) + `officers`; `stats` add distinct student/officer counts and pending verifications.
- **`officerQuery` fixed**: replaced `whereHas('organizations', wherePivotIn(...))` (which emits a broken `pivot_in` SQL clause) with a direct subquery on `organization_user` selecting distinct `user_id` for staff roles — heads are not counted as their own org's officers.
- **Frontend**: `admin/Dashboard/Index.vue` rewritten (stat cards Income/Exempted/Students/Officers/Pending, term picker via router + axios reload, income bar chart, income donut, org breakdown chart, recent payments, upcoming events). New `components/charts/DashboardBarChart.vue` (income-focused, currency `formatPhp`, on-top bar labels, tooltips) + `DashboardDonut.vue` (Net Expenses donut with center revenue).
- Tests: `DashboardTest` (9) — guests redirect, admin access, non-admin forbidden, term exposure + active default, income scoped to term, fee/penalty breakdown, org students+officers, officer unified dashboard, no-term render. (An incidental term-year collision in the income test was fixed.)

### Transactions total excludes waived/exempted amounts
- `PaymentService::totalAmount()` now passes `exclude_exempted`; `PaymentRepository::sum()` filters out `status = 'exempted'` **or** `isExempted = true`. The Payments Transactions summary card therefore shows only actually-collected money (matches the earlier dashboard income semantics). Regression test added.

### Transactions Excel export (new)
- **Route** `GET /admin/payments/export` → `PaymentController::export` (officers + heads; super admin forbidden), registered **before** the `{uuid}` route.
- **`app/Services/PaymentExportService.php`** (PhpSpreadsheet, mirrors `AttendanceExportService`): one row per transaction item with columns **Receipt No. | Date | Student Name | Student No. | Organization | Academic Term | Type | Description | Amount | Payment Method | Reference No. | Status | Processed/Exempted By | Notes**. Exempted rows get an amber row fill (`FFFEF3C7`); header row styled. Filename `payments-{term-slug}.xlsx`.
- **`PaymentController::export()`** builds filters (scope org ids, selected term, `include_fees`/`include_penalties` toggles, `fee_ids[]`/`event_ids[]` item filters via `parseIdList()` for comma-separated or repeated ids). Index props `export_fees` (posted fees in scope+term) + `export_penalty_events` (published/completed events in scope+term) feed the picker.
- **`admin/payments/Index.vue`** — **Export** button in the Transactions toolbar opens a dialog: Include Fees / Include Penalties toggles plus scrollable multi-select lists of specific fees and penalty events (empty selection = all of that type; Download disabled when both toggles off). Download builds the query and triggers the streamed download.
- Tests (`AdminPaymentsTest`, +3): officer/head allowed + super admin 403 + xlsx content-type/disposition; workbook parsed back with receipt numbers present and exempted row amber-filled; fee-only/penalty-only/individual-fee filtering. Full suite: **317 passed (1429 assertions)**.

## Earlier on August 9, 2026 — device binding + face recognition (attendance/device security)

### One-device binding (new `DeviceController` API)
- **Tables** (all migrated `2026_08_12_0000xx`):
  - `device_bindings` — `user_id` (FK cascade), `device_fingerprint` (unique index), `device_meta` (JSON), `bound_at`. A student account can be bound to exactly **one** device at a time.
  - `device_transfer_requests` — pending moves of a binding to a new phone: `user_id`, `from_fingerprint`, `to_fingerprint`, `to_meta`, `status` (pending/approved/rejected), `expires_at` (24h), `status_changed_by`.
  - `device_unbind_audits` — admin action trail: `user_id`, `device_fingerprint`, `device_meta`, `reason`, `unbound_by`.
- **Endpoints** (`Api\DeviceController`, auth + the `X-Device-Fingerprint` header):
  - `GET /api/device/status` — current binding (+ `bind_token`, managed state, face-enrolled flag) or `null` before any bind.
  - `POST /api/devices/bind` — idempotent: binds the presented fingerprint; same fingerprint re-binds silently (rotates the bind token); rejects a fingerprint already bound to another account.
  - `POST /api/devices/transfer/request` — new phone requests a transfer (pending); a phone matching the **already-bound** fingerprint is rejected (422 — it must just re-bind).
  - `GET /api/devices/transfer/requests` — list with an `incoming`/`outgoing` direction per request.
  - `POST /api/devices/transfer/requests/{transfer}/approve` / `.../reject` — only callable **from the currently bound device**; approving moves the binding to the new fingerprint; the requester cannot approve their own transfer.
  - Transfer request creation emits a **notification** for the account owner.

### Face enrollment / verification (new `Face` API)
- **`face_enrollments`** table (`2026_08_12_000001`): `user_id` (FK cascade), `descriptors` (JSON, **encrypted at rest** via the app key), `enrolled_at`.
- **Endpoints** (`Api\FaceController`):
  - `POST /api/face/enroll` — upsert: replaces the previous 128-float descriptor array; validates 128 floats in `[-1, 1]`.
  - `GET /api/face/enrollment` — own descriptors only (another user's id → 404).
  - `DELETE /api/face/enrollment` — remove own enrollment.
- `User` gained `hasOne(FaceEnrollment)` (`User.php`), and `deviceBinding()`.
- Adviser enrollment requirement: `GET /api/device/status` reports `is_face_enrolled`; the PWA new-app onboarding gates /security check on both presence + binding.

### Admin device-binding portal (`/admin/device-bindings`)
- **`Admin\DeviceBindingController`**: `index` (super_admin sees all; heads auto-scoped to their organizations' students; search by student number / fingerprint), `unbind` (admin only, **reason required** via `DeviceUnbindRequest`).
- `unbind` **removes only the binding** and writes a `device_unbind_audits` row — the student's **face profile + enrollment are kept** so they can re-bind without re-enrolling.
- New Inertia page **`admin/device-bindings/Index.vue`** — search + scoped list + Unbind-with-reason **Dialog**.
- **`PermissionRegistry`**: `device_bindings` module added for `super_admin` + all heads (officers/students → 403). Sidebar shows "Device Bindings".

### Tests / verification
- 25 new tests: `Api\FaceTest` (7), `Api\DeviceTest` (11), `Admin\DeviceBindingTest` (7). Full suite: **307 passed (1263 assertions)**; admin `npm run build` + ESLint clean; PWA `npm run build` (incl. `vue-tsc`) clean — face models verified in `dist/models/` + SW `/models/` cache-first route.

## Earlier on August 9, 2026 — payment accounts viewable by officers + QR image view; receipt number parity with the student PWA

### Payment accounts: officers can view; QR image now viewable
- **`routes/web.php`**: `GET /admin/payment-accounts` is now open to **heads + officers** (read-only for officers); `POST`/`DELETE` remain heads-only.
- **`PaymentAccountController::index`**: new `can_manage` prop (`isSuperAdmin() || $user->hasRole(UserRole::headRoles())`) replaces the dead `can_manage_all`; officers receive `can_manage = false`.
- **`PermissionRegistry`**: `payment_accounts` added to `ADMIN_OFFICER_MODULES` so officers get the "Payment Account" sidebar item (view-only). (An accidental `payment_submissions` module for officers was reverted — the Pending Verification tab is reached through the Payments module.)
- **`admin/payment-accounts/Index.vue`**: each account now renders a **QR thumbnail** + a **"View QR" modal** (large image, account details, "Open in new tab"); the Configure form **previews the newly selected file**; the **Configure form and Remove button are gated on `can_manage`** (officers see a read-only list).

### Officer-side receipt number matches the student side
- **`admin/payments/Show.vue`**: the Official Receipt panel header now shows the **actual `receipt_number`** from the batch's receipts (the same number the student sees in the PWA) instead of the batch UUID, with a "+N more" note when a batch issued multiple receipts. The UUID is retained in the page title/breadcrumbs.

### Tests / verification
- `AdminPaymentsTest`: "payment accounts are viewable by officers but managed only by heads" — officer `GET` ok + `can_manage=false`, officer `POST` forbidden, super admin `GET` still forbidden.
- `AdminEventsTest`: `/admin/payment-accounts` removed from the officer-forbidden module list.
- `PermissionRegistryTest`: officer modules now include `payment_accounts`.
- Full suite: **282 passed (1165 assertions)**; `npm run build` passes; prettier clean.

## Earlier on August 8, 2026 — payment transaction batches, UUID ids, receipt UI, outstanding ordering

### Payments: one row per transaction session (membership + penalty exemptions collapse into a single row)
- New `payments.batch_id` (uuid): every payment created in the same cash / exemption / submission-approval session shares one `batch_id`. `PaymentService::recordCash`, `exemptObligations`, and `settleFromSubmission` stamp the batch (`PaymentService.php`); `PaymentRepository::paginateBatches()` + `forBatches()` paginate/slice by batch so a batch is never split across pages.
- `PaymentController::index` now renders the Transactions tab as grouped **batches** (`groupTransactionBatches()`): one row per session showing student, obligation item badges, batch total + count, method, status (`paid` / `exempted`), date, uuid link. Pagination covers # of batches; `transactions_total` still sums all matched payments.

### Payment detail page — receipt design + exemption visibility
- `admin/payments/Show.vue` rewritten around a new controller payload (`batch`, `student`, `organization`, `term`, `history`, `can_process`):
  - **Official-receipt-style card** (dashed border): header `#UUID8` + date + term; Payer / Organization spread with `justify-between`; itemized table with per-item exempt/amount; total + count + **amount in words**; reference; issued receipt numbers; a highlighted **"Exemption Granted"** panel showing the exemption **reason** (`notes`), the officer who granted it, and when.
  - **"All Payments This Term"** — every other batch the student made for the same term, each linkable; the current batch is highlighted.
- `show()` now resolves by `uuid` (`showByUuid` / `findByUuid`) instead of int id.

### Payment identifiers are now UUIDs (admin-facing)
- New columns `uuid` (unique) + `batch_id` (indexed) on `payments`. Model auto-generates `uuid` on create; factory seeds both. Migration `2026_08_11_000002` backfills existing rows (`uuid` = `batch_id` = fresh uuid per row).
- Admin URLs are `/admin/payments/{uuid}` (route param `{uuid}`, string binding). Legacy DB int PK + int `id` are retained for the **mobile API** (`/api/payments/{id}`) so the existing app client keeps working; `PaymentResource` ids unchanged.

### Outstanding / receipts / per-page polish
- **Outstanding tab sorted by balance desc** (`sortByDesc('total_balance')`) — students with an outstanding balance stay on top; students just cleared (e.g. newly exempted) sink to the bottom.
- Receipt card Payer/Organization row now uses `justify-between` (spread instead of bunched left).
- Payments index `per_page` **default changed 25 → 30** (applies to Transactions, Pending Verification, and Outstanding tabs).

### Tests
- `AdminPaymentsTest` updated (`transactions.*.amount` → `transactions.*.total`, pagination meta stays on batches) + two new tests: **exempted fee+penalty appear as one transaction batch**, and **show page exposes batch, exemption reason, and term history**. `AdminPaymentsTest` 12 passing; `Api\PaymentTest` 9 passing. Full suite: **280 passed (1141 assertions)** + the 1 pre-existing flaky `AcademicTermsAdminTest` "heads cannot review shift requests" (duplicate `institutes.code` in seeding).
- `pint` formatted changed files; `eslint` clean on `Index.vue`/`Show.vue`. `vue-tsc` blocked by pre-existing global tsconfig type-root errors, unrelated to these files.

## Earlier on August 8, 2026 — payments portal role reallocation + term-scoped monitoring

### Role reallocation: fees / payments / pending verification / payment-accounts
- **Super admin is fully removed** from fees, payments, pending verification, and payment-accounts — direct URLs return 403 via the `routes/web.php` role groups (not just hidden sidebar items).
- **Heads only** (`ssc_head, institute_head, sro_head`): set fee + penalty amounts, create/manage payment accounts, monitor paid/unpaid by term (view-only, no processing). **Officers only** (`ssc_officer, isc_officer, sro_officer`): record cash payments, grant exemptions, approve/reject pending cashless submissions.
- `PermissionRegistry`: super admin modules trimmed to `dashboard, heads, users, institutes, notifications, activity_logs, shift_requests, academic_terms`; `payment_submissions` dropped from `HEAD_MODULES` + `ADMIN_OFFICER_MODULES` (sidebar "Pending Verification" item removed — reached only via the Payments tab); `payment_accounts` dropped from `OFFICER_MODULES` (later re-added to `ADMIN_OFFICER_MODULES` for officer **view** access — see Latest Session Aug 9); `OFFICER_CAPABILITIES` swapped `manage_*` for `view_fees` / `view_penalties`.
- New semantic: `UserRole::staffRoles()` (officers only) gates processing actions — `PaymentController::authorizeProcessor()` / `canProcess()`, `PaymentSubmissionService::authorizeVerifier()` (drives `can_verify`); `officerRoles()` (heads + officers) is unchanged for PWA/API scoping. Heads are monitor-only: `admin/payments/StudentDetail.vue` hides Record Cash / Exempt / reason when `can_process` is false.
- `PaymentSubmissionController::scopedOrgIds()` signature fixed (took `Request`, called with `User`); `PaymentController` undefined-method 500 fixed (`accountRow` → `accountFor`).

### Payments admin page — term dropdown, search, date filters, pagination, totals
- `PaymentController::index` resolves the selected term (dropdown, default `AcademicTermService::current()`), threads it into **transactions, pending, and outstanding** datasets.
- **Summary card**: a single context-aware card above the tab bar — the active tab decides which total shows (Transactions / Outstanding / Pending). Totals reflect **all** matched records, not just the current page.
- **Transactions**: student search (name/#); date range moved into a **Date Filters dialog** (no inline date inputs; Apply lives in the dialog, outside Apply button removed); paginated via the reusable `Pagination.vue`. `PaymentRepository` refactored into a shared filter builder + `paginate()` / `sum()`; `PaymentService` gains `paginatedList()` / `totalAmount()`.
- **Pending Verification**: searchable (`pending_search`), scoped to the selected term, grouped per `group_key`, paginated, with aggregate total (`pendingSnapshot`).
- **Outstanding**: now computed for **all eligible students in scope/term** (was search-only) — paginated list + aggregate total (`outstandingSnapshot`). Heavier per page load by design (user-approved).
- Fixed latent bug: `ObligationService::forUser()` read `$item['organization_id']` but `feeObligations()` emits `org_id` — undefined array key whenever fees exist.

### Tests / verification
- `AdminPaymentsTest` (10 tests): role access, term default + filter, transaction search/date-range, **pagination + full-scope totals**, pending search/term/total, outstanding aggregate/pagination, payment-accounts **head-managed** (opened to officer *view* on Aug 9 — see Latest Session), `can_process` / `can_verify`. Updated `AdminFeesTest`, `AdminEventsTest`, `PermissionRegistryTest` (sidebar module removal locked in). Full suite: **279 passed (1096 assertions)**; `npm run build` passes.

## Earlier on August 8, 2026 — payments ledger + fee/penalty refactor

### `payments` is now a transaction ledger only (fees/penalties computed on demand)
- **Core rule**: the `payments` table holds **only actual transactions** — a row is created only when a student pays or a head grants an exemption. It is **never pre-populated** with unpaid obligations. Outstanding balances are computed dynamically on every request.
- **Fees**: `FeeService::studentObligations()` is unchanged and already dynamic — posted fees scoped by org (SSC/ISC/SRO) × institute/program × `required_years`, annotated `obligation_status` (`paid` via completed payment, `exempted` via exempted payment, else `due`). Nothing writes fee obligations to `payments`.
- **Penalties**: new `app/Services/PenaltyService.php` → `studentOutstanding(User)` computes penalties **without persisting**:
  - orgs of the student (`EligibilityService::userOrganizations` — extracted/shared with `FeeService`),
  - required events with `status IN (published, ongoing, completed)` (completed always; published/ongoing must be `ended()`),
  - every required `qr_configuration` with no attendance = **1 absence**,
  - `amount = total absences × latest org `penalty_fee` (`PenaltyFee::currentAmountFor`)`,
  - skips events already settled (completed **or** exempted payment for that event). Never writes rows.
- **Pre-population removed**: deleted `PenaltyGenerationService`, the `GenerateEventPenalties` command (`penalties:generate`), the `everyThirtyMinutes()` scheduler entry, and the `generateForEvent()` call in `EventService::completeAttendance`.
- **New endpoint**: `GET /api/fees/my/penalties` → `FeeController::penalties` returns the computed obligations `{ data: [...] }` (event + event.organization, absences, missing_qr_configurations with type/valid_from/valid_until, amount, status `pending`).
- **Tests**: `PenaltyTest` rewritten for dynamic computation — per-missing-QR charge, full-attendance → none, latest-amount, settled/exempted exclusion, **never persists** (payments count stays 0), ended-published included, draft/future excluded. Full suite: **258 passed, 2 pre-existing** (`EventTest` `/start` + `/cancel` 404).

### Fee/Penalty refactor (unified obligations model) — same period
- Dropped `fee_user` pivot + `penalties` table (`2026_08_08_000003`); unified `payments` reworked (`2026_08_08_000002`): `fee_type` (`fee`|`penalty`), `event_id`, `isExempted`, `exempted_by`, `exempted_at`, `paid_at`, `notes`, unique `(user_id, event_id)`; `penalty_fee` table (`2026_08_08_000001`): org-scoped `amount`/`effective_at`/`set_by`, seed carry-over from `organizations.config.penalty_amount`.
- `PaymentResource` adds derived `absences` + `missing_qr_configurations` (from event QR configs minus scanned attendance) for penalty rows — computed, no column.
- Admin **Fees page** (`/admin/fees`, `admin/fees/Index.vue`) now owns both fees **and** the penalty amount: segmented **Fees / Penalty** toggle, per-org penalty amount input → `POST /admin/fees/penalty` (`FeeController::storePenalty`, head-only + scope-checked). `PenaltyFeeService::current()` added. Removed `/admin/penalty-fees` routes + `Admin\PenaltyFeeController`; `PenaltyFeeRequest` deleted. Sidebar `penalties` module removed.
- Backend `npm run build` passes.

## Previous Session (August 7, 2026)

### Push notifications (Web Push / VAPID) — new
- **Recipients**: for an org-scoped message the recipient set is the union of the org's covered students and the org's officers/heads. `NotificationService::recipientsForOrganization()` — SSC covers all enrolled students; ISC covers its institute's students; SRO covers its program's students — merged with everyone holding a `UserRole::officerRoles()` pivot role in that org (deduped).
- **Delivery**: every DB notification now also triggers a Web Push via **`minishlink/web-push`** (new composer dep). `WebPushService::sendToUser()` queues a VAPID-signed push to each of the user's `push_subscriptions`; on `isSubscriptionExpired()` it auto-deletes the dead subscription. No third-party account — keys are P-256 VAPID.
- **Storage**: new `push_subscriptions` table (`user_id`, `endpoint` unique, `p256dh`, `auth`) replaces the single-string `users.push_token`. `PUT /api/notifications/push-token` now validates/upserts a full subscription `{ endpoint, keys: { p256dh, auth } }`; new `DELETE /api/notifications/push-subscription` removes it. (The old `push_token` column is now unused.)
- **Triggers**:
  - `FeeController::publish` → `notifyFeePosted` — "Fee posted: {name} — {amount} due by {date}".
  - `EventController::publish` → `notifyEventPosted` — "New activity: {title} — {org} • {date} • {venue}".
  - **Fee due reminders**: new `FeeDueDateReminder` command (`php artisan notifications:fees-due`) picks `posted` fees with `due_date` in `[now, now+3days]` and sends `notifyFeeDue` — "Fee due in N day(s)". Scheduled `dailyAt('08:00')` in `routes/console.php`.
- **PWA**: `vite-plugin-pwa` switched to `injectManifest` + a custom `src/sw.ts` (push + notificationclick handlers), `services/push.ts` subscribes/unsubscribes, wired into `authStore.login()`/`logout()`; `VITE_VAPID_PUBLIC_KEY` added. See `pwa-soms/PWA-SUMMARY.md`.
- **Config**: VAPID keys in `.env`/`.env.example` (`VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`) read through `config/services.php → services.webpush`. `.env.example` also gained the missing `QR_ENCRYPTION_KEY` placeholder parity note (existing known gap, unchanged behavior).
- **Tests**: `Api\NotificationTest` reworked for the subscription endpoint (+ upsert + delete cases); new `Feature\PushNotificationsTest` covers recipient union, fee/event posting notifications, the 3-day reminder command, and the out-of-window exclusion. Full suite: **248 passed, 2 failed (pre-existing `/start` + `/cancel`)**.

### Officer Workspace Migration (Laravel admin panel)
- **Architecture**: The PWA is now **students-only**. All officer functionality moved into the Laravel admin panel. Officers authenticate, manage events + QR configs, and export attendance there; heads are view-only for events; super admin retains full control.
- **Separate login pages**:
  - `/login` → **`OfficerSessionController`** + **`OfficerLoginRequest`** — **ID number** (`student_number`) + password, requires a staff/officer role (`hasStaffRole()`). Renders `auth/OfficerLogin` (PWA-style design).
  - `/admin/login` → **`AdminSessionController`** + existing `LoginRequest` — **email** + password (heads/super admin). Renders `auth/AdminLogin` (PWA-style design). Old `auth/Login.vue` removed.
  - `/logout` redirects back to the right login based on role. Root `/` → officer login.
- **Role gates** (`EnsureUserHasRole`): base admin group now includes `ssc_officer,isc_officer,sro_officer`. Fees/payments/penalties wrapped in a heads+super_admin-only group (officers 403). Event **view** (index/show/calendar) for all admin roles; event **control** (create/edit/update/delete/publish/unpublish/complete, QR config, attendance export) gated to `role:super_admin,ssc_officer,isc_officer,sro_officer` — heads are view-only.
- **Permissions**: `PermissionRegistry::ADMIN_OFFICER_MODULES = ['dashboard','events','calendar','notifications']`; `AppSidebar` branches on `permissions.role` (not module count) so officers get the admin nav.
- **Admin event management (backend)**: `Admin\EventController` gained create/store/edit/update/destroy/publish/unpublish/complete (+ `qr` page + `exportAttendance`); new **`Admin\QrConfigurationController`** (store/update/generate/destroy, same-event config guard + scope). **`AttendanceExportService`** extracted (shared by the API + admin export) — `AttendanceController::exportEvent` now delegates to it.
- **Admin frontend**: new `admin/events/{Create,Edit,QrConfig}.vue` (Leaflet added to the admin frontend + `GeofenceMap.vue` ported), `Index.vue` (uuid links + New Activity button + `can_manage_events`), `Show.vue` rewritten to current schema with officer action buttons + Download Attendance; officer-aware dashboard (`DashboardController` `officer_mode` + `Dashboard/Index.vue` officer view).
- **Prerequisite fixed**: admin events/calendar now expose `uuid` and link `/admin/events/{uuid}` (was numeric-id → 404); `Show.vue` no longer references dropped columns.
- **PWA students-only**: `WorkspaceService::getAvailableWorkspaces` returns only the student workspace; PWA officer routes/pages removed (see PWA-SUMMARY.md).
- Tests: `OfficerAuthenticationTest`; updated `AdminAuthenticationTest` (admin at `/admin/login`) + `RoleMiddlewareTest` (officer role passes); new **`AdminEventsTest`** (uuid scoping, officer control/head view-only, officer module 403s, super admin control); updated `WorkspaceServiceTest`.
- **Login form matches the PWA (post-migration)**: `resources/js/layouts/auth/TcgcAuthLayout.vue` rewritten to the PWA login layout — full-page `#f4f7f5`, TCGC `/bg.webp` banner on top for small screens / left for desktop, **no `/logo.png` on small screens**, PWA-style green heading + amber accent bar. `auth/OfficerLogin.vue` no longer shows a "Sign in as admin/head" cross-link.
- **QR Config "Reuse last session"**: `admin/events/QrConfig.vue` — the reuse checkbox is **disabled until a QR is generated** on the event (`lastGeneratedConfig`); when checked it **fills the form** with the last generated session's location/radius/required-years, and `saveConfig()` submits `reuse_from` (omitting those fields) so the backend copies them. (Earlier the form always sent default radius/years, which blocked reuse.) Added `AdminEventsTest` reuse case.
- **QR code PDF download**: `GET /admin/events/{event}/qr-configurations/{config}/download` → `Admin\QrConfigurationController@download` (officer + super admin only). Renders `resources/views/pdf/qr-config.blade.php` via **`barryvdh/laravel-dompdf`** (new dependency): event title on top, date + time below, Time In/Out, `valid_from – valid_until`, and the QR **centered at 70% width**. Embeds the stored SVG QR (dompdf renders SVG natively — avoids the imagick-only PNG backend in simple-qrcode). `QrConfigurationController` now shares a `buildPayload()` between `generate` and `download`. `QrConfig.vue` "Download" now links to the PDF (was the raw SVG). Added `AdminEventsTest` PDF cases.

### Completed
- **New endpoint `GET /api/attendance/events`** → `AttendanceController@studentEvents`. Returns `{ organization: {id,name,type}, data: [...] }` where each item is a per-event attendance summary: `event` (uuid, title, event_date, venue, status, organization), `attended_count`, `total_qr_configs` (event's QR config count), `complete` (all QR sessions attended), and `attendances[]` (id, qr_configuration_id, type, scanned_at, synced_at).
- `AttendanceService::getStudentEventsByOrg()` — groups the user's attendances by `qrConfiguration.event_id` (filters out null-config rows) and computes counts. Drives the PWA per-org attendance view.
- **Fixed SQLite/MySQL schema mismatch**: migration `2026_08_02_135628` now drops the old attendances columns on **all** drivers (rebuilds the table on SQLite since `ALTER TABLE DROP COLUMN` can't remove an FK-referenced column). Previously the SQLite test schema kept `event_id` NOT NULL, so every attendance write failed in tests. This un-broke the whole `AttendanceTest` file.
- `QrConfiguration` model now uses `HasFactory` (factory existed but wasn't wired → `QrConfiguration::factory()` was undefined).
- Updated stale tests: `AttendanceServiceTest::rejects duplicate attendance` now uses `qr_configuration_id` (old test wrote the dropped `event_id` column). Added tests for the new endpoint.
- **Events expose required years**: `EventResource` now includes `required_years` — the merged, unique `required_years` list across all the event's QR configurations (`['all']` takes precedence). `EventRepository::all()`/`getUpcoming()` and `EventController@show` eager-load `qrConfigurations` to avoid N+1. Added `EventTest` cases for aggregation and the `all` precedence.

### Security fix — event/QR-config authorization (same session)
- **`AccessScopeService::scopeOrganizationIds()` no longer falls back to a student's enrollment orgs.** Previously a pure student got their SSC/ISC/SRO in the *manage* scope, so `isWithinScope()` (used by `EventController@store/storeDraft`, `SomEventPolicy@update/delete`, and every QR-config mutation via `Gate::authorize('update', $event)`) let any student create/update/delete events and QR configs in their own orgs. Now the manage scope is only orgs where the user holds an officer/head role; **read access** (`viewableOrganizationIds`) is unchanged, so students still browse their orgs' events.
- **`QrConfigurationController` now scopes configs to their event**: `update`/`generate`/`destroy` abort 404 if `$config->event_id !== $event->id` (prevents cross-event manipulation via the `{config}` id), and `store` rejects `reuse_from` unless it references a config of the same event (422).
- Tests: `AccessScopeServiceTest` "pure student has no manageable organizations"; `EventTest` student cannot create/update/delete their org's events (403), officer of another org cannot modify (403), creating org's officer can; new **`QrConfigurationTest`** (student/other-org officer 403s, own-org officer allowed, cross-event config 404, `reuse_from` same-event 422).
- **Officer workspace isolation (same session)**: `EventController@index` and `SomEventPolicy::view` no longer use `viewableOrganizationIds` for users with officer roles. New `resolvedScopeIds()` returns **manage scope only** (`scopeOrganizationIds`) when `hasOfficerRole()`, and viewable (student) scope for pure students; super admin gets all orgs. This means an officer can only **list, view, and manage events of their own organization(s)** — no-org-filters and tampered `organization_id` values now resolve to the officer's manage orgs (403 for others). `GET /api/events/upcoming` is likewise scoped (was returning SSC events to anyone); `EventService::getUpcoming()`/`EventRepository::getUpcoming()` now accept an org-id array. QR-config view endpoints inherit the tightened `view` policy.
- Tests: officer lists only their own org's events, cannot list/view another org's event (403), can list/view own org's; pure student can list/view their own org's events.

- **Event attendance export (same session)**: new `GET /api/events/{event}/attendance/export` → `AttendanceController@exportEvent`, gated by `Gate::authorize('update', $event)` (org's officers + super admin only; others 403). `AttendanceService::getEventExportData()` builds an attendance matrix (per-QR-config time columns, students sorted by name). Streams a real **.xlsx** via `phpoffice/phpspreadsheet` (new dependency): header block `EVENT NAME / DATE / TIME FROM-TO`, then `ID NUMBER | NAME | TIME IN (QR CONFIG {id}) | TIME OUT (QR CONFIG {id}) | ...`, blank cell when a config wasn't scanned. Test count updated below.

### Notes
- **Offline sync fix (same session)**: `POST /api/attendance/sync` now processes records **inline** via `AttendanceService::syncOffline` (was dispatching the `ProcessAttendanceSync` queue job and returning success immediately — with `QUEUE_CONNECTION=database` and no worker running, attendance was never saved). Response is now `{ processed, saved, skipped, results }`; duplicates are skipped. The `ProcessAttendanceSync` job was deleted. Added tests proving records are saved and duplicate batches yield one row.
- **Attendance export timezone fix (same session)**: `getEventExportData()` now formats `scanned_at` in **`Asia/Manila`** (`->setTimezone('Asia/Manila')`) — the app stores `scanned_at` as UTC (PWA sends `toISOString()`), so the Excel previously showed UTC times (e.g., 08:25 AM) while the app showed Manila time (04:25 PM). Added `AttendanceExportTest` timezone case (08:25 UTC → "04:25 PM").
- **QR removal confirmation (same session)**: `admin/events/QrConfig.vue` now uses a proper **Dialog** ("Remove QR Configuration") instead of `window.confirm`, warning that **attendances recorded using this QR will be deleted**. To match, both `Admin\QrConfigurationController::destroy` and the API `QrConfigurationController::destroy` now delete `attendances` with that `qr_configuration_id` before deleting the config. Added `AdminEventsTest` case (attendances removed).
- **"Published" → "Posted" (admin)**: `admin/events/Index.vue` status badges now render friendly labels (`published` → "Posted") like the PWA and `Show.vue` already did. No backend value changes.
- **Activity Log (same session)**: every officer action is logged to `event_logs` with the acting user + timestamp — event create/update/delete/publish/unpublish/complete (via `EventService::log`, now public) and new entries for **QR config create/update/generate/delete** + **attendance export** (logged in `Admin\QrConfigurationController` / `Admin\EventController@exportAttendance`). New **`/admin/activity-logs`** page (`Admin\ActivityLogController` + `admin/activity-logs/Index.vue`) lists Officer / Action / Event / Timestamp, scoped to the user's manage orgs — **heads see their officers' actions**; officers + super admin see it too. Added `activity_logs` module to `PermissionRegistry` (all portal roles) and the sidebar (icon History; also renamed the old "Logs" label for notifications to "Notifications"). Tests: action logging + org-scoped activity log.
- **Activity Log filters + pagination (same session)**: `/admin/activity-logs` now supports a **From/To date range filter** (`from`/`to` query params → `whereDate('event_logs.created_at', ...)`), an Apply/Reset UI, and uses the reusable **`Pagination`** component (rows-per-page selector 10–100 + Previous/Next that preserve the filters via `withQueryString()`). Added an `AdminEventsTest` date-filter case.
- **Heads-managed Fees (same session)**:
  - Schema: `fees` gained `term` (free text) + `required_years` (JSON, default `["all"]` — 1st–4th year); dropped `type`/`frequency`; `status` is now `draft`/`posted`. New **`fee_user`** pivot (`fee_id`, `user_id`, `amount`, `status` pending/paid, unique pair).
  - `FeeService`: `create` (draft), `update` (syncs assignments when posted), `publish` (**assigns students**: SSC → all enrolled; ISC → institute students; SRO → program students; filtered by `required_years`), `unpublish` (removes assignments), `delete` (removes assignments; **payment records preserved** — `payments.fee_id` is `nullOnDelete`).
  - `PaymentService::complete` marks the student's `fee_user` row `paid`; `FeeResource`/API `GET /fees` scoped to the student's assignments with pivot; API fee mutations now require a **head** role (explicit checks — the `Gate::before` super-admin override would otherwise bypass the policy).
  - `FeePolicy` + admin routes: fees **control** is heads-only; fees **view** is heads + super admin; **super admin is view-only for fees and events** (event control group is now officers-only; `canManageEvents` = staff roles only).
  - Admin frontend: `fees/Index.vue` (term/required-years/assigned/status columns + New Fee), `Create.vue`, `Edit.vue` (with All / 1st–4th year checkboxes), `Show.vue` (Post/Unpost/Edit/Delete). PWA fees page shows real pivot pending/paid + term/org.
  - Tests: `FeeServiceTest` (assignment scoping SSC/ISC/SRO × years, draft no-assign, unpost/delete, update sync, payments kept), rewritten `Api\FeeTest` (student sees only assigned fees with pivot; students/super admin cannot create), new `AdminFeesTest` (heads control, super admin view-only, officer 403), `AdminEventsTest` super admin view-only.
- Remaining pre-existing failures: `EventTest` "can start event" / "can cancel event" — the `/start`/`/cancel` endpoints don't exist (documented in prior sessions). Test count: **241 passed, 2 failed (pre-existing)**.
- New composer dependencies: `phpoffice/phpspreadsheet` (event attendance export), `barryvdh/laravel-dompdf` (QR PDF download).
- Behavior note: an officer who is *also* enrolled as a student now sees only their officer orgs' events via the API (strict isolation). The student events browser lists events of the student's enrolled orgs and works for pure students; officer-role users are scoped to their manage orgs everywhere.

## Previous Session (August 6, 2026)

### Completed
- **Organizations now link to institutes/programs**: New migration `2026_08_05_153115` adds `institute_id` + `program_id` (nullable FKs) to `organizations` with a **unique `[institute_id, program_id]` pair**. `Organization` model gained `institute()`, `program()`, `scopeForInstitute()`, `scopeForProgram()`. ISC/SRO orgs are now discovered via these FKs instead of name/code matching.
- **Org-based resolution refactor**: `AccessScopeService`, `EventService::getStudentOrganizationStats()`, and `AttendanceService::getStudentStats()` now resolve ISC/SRO through `organization.institute_id` / `organization.program_id`.
- **New student events route**: `GET /api/events/student` → `EventController@studentEvents` (student-facing org/event browser for the PWA).
- **Seeder updates**: `OrganizationSeeder` populates `institute_id`/`program_id` for ISC/SRO orgs (with `update()` fallback for existing rows).

### Notes / Known Discrepancies
- `POST /api/events/{event}/start` and `POST /api/events/{event}/cancel` do **NOT exist** (older summary listed them). `EventService` has no start/cancel methods; `EventRequest` status allows only `draft`, `published`, `completed`.
- `GET /api/attendance/event/{event}` is registered in `routes/api.php` but `AttendanceController@eventAttendance` is **undefined** → the route 500s if hit.
- `AttendanceService::scan()` no longer calls `decryptPayload()` — it looks up the `QrConfiguration` (or `Event` by id) directly and dedups on `[qr_configuration_id, user_id]` / `[event_id, user_id]`. `QrCodeService::decryptPayload()` is defined but **never called** app-wide (the PWA does its own Web Crypto decrypt).
- **Dead code**: `GpsValidationService` (no callers), `AttendanceStatus` enum (no references).
- **Latent bugs**: `AttendanceRepository::findByEventAndUser()` referenced at `AttendanceService.php:37` but never defined; `AttendancePolicy::manageable()` reads dropped `attendance.organization_id`; `QrCodeService::generatePayload()` references dropped `events.attendance_end`; `AppServiceProvider` imports non-existent `EventPolicy` (dead import; the registered policy is `SomEventPolicy`).
- `QR_ENCRYPTION_KEY` is set in `.env` but **missing from `.env.example`** — fresh setups fall back to `app.key` (base64), and `hex2bin()` of it produces garbage. Must be added to `.env.example`.
- **Admin Events pages are stale** vs the schema: `resources/js/Pages/admin/events/Show.vue` still renders dropped columns (`type`, `latitude`, `longitude`, `geofence_radius`, `max_participants`, `attendance_start`, `attendance_end`), and event links use numeric `event.id` while `getRouteKeyName()` = `'uuid'` → **broken event detail navigation** (should use `event.uuid`).

### Key Files Modified
- `database/migrations/2026_08_05_153115_add_institute_id_program_id_to_organizations.php` — NEW org↔institute/program FKs + unique pair
- `app/Models/Organization.php` — institute()/program() relations + forInstitute/forProgram scopes
- `app/Models/User.php` — push_token (from `2026_07_24_000080`)
- `app/Services/AccessScopeService.php` — resolves orgs via institute/program FKs
- `app/Services/EventService.php` — getStudentOrganizationStats via org FKs
- `app/Services/AttendanceService.php` — getStudentStats via org FKs
- `app/Http/Controllers/Api/EventController.php` — new studentEvents()
- `database/seeders/OrganizationSeeder.php` — populates institute_id/program_id

### Previous Sessions (August 2, 2026)
- **Attendances table simplified**: Dropped `organization_id`, `qr_data`, `gps_latitude`, `gps_longitude`, `gps_accuracy`, `device_info`, `ip_address`, `is_offline`. Now `qr_configuration_id`, `user_id`, `scanned_at`, `synced_at`.
- **ProcessAttendanceSync queue job**: Bulk sync dispatches a queued job that processes records in background.
- **EventRequest**: `organization_id` required only on POST (create), nullable on PUT (update).
- **QrCodeService**: `hex2bin($key)` + `OPENSSL_RAW_DATA` for AES-256-CBC encrypt/decrypt matching PWA Web Crypto.
- **QR config times**: `valid_from`/`valid_until` explicit UTC via `setTimezone('UTC')->toIso8601String()`. Added `valid_time_from`/`valid_time_until` raw time strings to payload.
- **ReportService**: Attendance report joins `events` table for `organization_id`.
- **Event logs**: All event actions logged to `event_logs` (created, updated, published, unpublished, started, completed, cancelled, deleted).
- **Event UUID**: `uuid` column, `getRouteKeyName()` returns `'uuid'`, `EventResource` returns `uuid` as `id`.
- **Events**: `type` and `latitude/longitude/geofence_radius/max_participants` dropped; these live on `qr_configurations` now.

## 1. Tech Stack
- **Backend**: Laravel 12, PHP 8.2
- **Frontend**: Inertia.js 2.x + Vue 3.5 (Composition API, TypeScript)
- **UI**: shadcn-vue (Tailwind CSS)
- **Auth**: Laravel Sanctum (API tokens), `EnsureUserHasRole` middleware
- **PDF/QR**: `simplesoftwareio/simple-qrcode` (QR generation), `openssl_encrypt` (AES-256-CBC payload encryption)
- **Testing**: Pest

## 2. Database Schema

### Users
| Column | Type | Purpose |
|---|---|---|
| id | bigint | PK |
| student_number | varchar(20) unique nullable | Student ID for PWA login |
| name | varchar | Full name |
| email | varchar unique | Login identifier for admin portal |
| password | varchar | Hashed (bcrypt) |
| remember_token | varchar | Laravel "remember me" cookie |
| institution_password_enc | text | Encrypted institution password for re-auth |
| phone | varchar(20) | nullable |
| year_level | tinyint(1-5) | nullable |
| sex | varchar(10) | nullable (M/F) |
| profile_photo | varchar | nullable |
| push_token | text | nullable | Device push token for notifications |
| is_enrolled | boolean default false | TRUE = onboarded/propagated |
| institute_id | FK → institutes | nullable | Student's institute |
| program_id | FK → programs | nullable | Student's program |
| email_verified_at | timestamp | nullable |
| created_at, updated_at | timestamp | |
| deleted_at | timestamp | soft deletes |

**Removed columns**: `is_active` (replaced by `is_enrolled`), `institute` (string, replaced by `institute_id` FK), `program` (string, replaced by `program_id` FK)

### Organizations
| Column | Type | Purpose |
|---|---|---|
| id | bigint | PK |
| parent_id | FK → organizations | nullable | Parent org for hierarchy |
| institute_id | FK → institutes | nullable | **NEW (Aug 5)** — links ISC orgs to their institute |
| program_id | FK → programs | nullable | **NEW (Aug 5)** — links SRO orgs to their program |
| name | varchar | |
| code | varchar unique | e.g. `SSC`, `ICS-ISC`, `BSCS-SRO` |
| type | enum(ssc,isc,sro) | OrganizationType |
| description | text | nullable |
| config | json | e.g. `{"penalty_amount": 50}` |
| is_active | boolean | |
| created_at, updated_at, deleted_at | | |

Unique constraint: `[institute_id, program_id]` (nullable columns). ISC/SRO orgs are matched to students via `institute_id`/`program_id` rather than name/code string matching.

### Organization Hierarchy
```
SSC (ssc)
└── ICS-ISC (isc) — e.g. Institute of Computer Studies
│   └── BSCS-SRO (sro)
│   └── BSBA-SRO (sro)
└── IAS-ISC (isc)
    └── AB English-SRO (sro)
```

### Organization_User (pivot)
| Column | Type |
|---|---|
| organization_id, user_id | FKs |
| role | varchar (`UserRole` enum value) |
| position | varchar nullable |
| assigned_at | timestamp nullable |

### Institutes
| Column | Type |
|---|---|
| id | bigint |
| code | varchar unique (e.g. `ICS`) |
| name | varchar |
| logo_path | varchar nullable |
| is_active | boolean |

### Programs
| Column | Type |
|---|---|
| id | bigint |
| institute_id | FK → institutes |
| code | varchar (e.g. `BSCS`) |
| name | varchar |
| is_active | boolean |

### Institution Accounts (mock institution API)
| Column | Type |
|---|---|
| id | bigint |
| stud_id | varchar(20) unique |
| password | varchar (hashed) |
| stud_cnum | varchar(20) nullable |
| stud_fname, stud_lname, stud_mname | varchar |
| stud_sex | varchar(10) nullable |
| stud_year | tinyint |
| is_graduated, is_enrolled | boolean |

Seeded with 100 real BSCS students. All passwords: `12345678`.

### Events
| Column | Type |
|---|---|
| id | bigint |
| organization_id | FK → organizations |
| title | varchar |
| description | text nullable |
| venue | varchar nullable |
| attendance_start, attendance_end | datetime |
| event_date | date |
| qr_secret | text nullable |
| status | varchar default 'draft' |
| created_at, updated_at, deleted_at | |

Status flow: `draft` → `published` → `ongoing` → `completed` or `cancelled`

**Removed columns**: `type`, `latitude`, `longitude`, `geofence_radius`, `max_participants`

### QR Configurations
| Column | Type |
|---|---|
| id | bigint |
| event_id | FK → events |
| type | enum(time_in, time_out) |
| valid_from, valid_until | time (event_date + time = full datetime) |
| latitude, longitude | decimal(10,7) nullable | Geofence center |
| geofence_radius | int nullable |
| required_years | json | e.g. `["1","2","3","4"]` or `["all"]` |
| qr_data | text | Base64 SVG data URI (the QR image) |
| is_generated | boolean |

### Event Logs
| Column | Type |
|---|---|
| id | bigint |
| event_id | FK → events |
| user_id | FK → users |
| action | varchar | created, updated, deleted, published, unpublished, started, completed, cancelled |
| details | json nullable |
| created_at | timestamp |

### Attendances
| Column | Type |
|---|---|
| id | bigint |
| user_id | FK → users |
| qr_configuration_id | FK → qr_configurations, nullable, nullOnDelete |
| scanned_at | datetime |
| synced_at | datetime nullable |
| created_at, updated_at | timestamp |

Unique constraint: `[qr_configuration_id, user_id]`
**Removed**: `event_id`, `organization_id`, `qr_data`, `gps_latitude`, `gps_longitude`, `gps_accuracy`, `device_info`, `ip_address`, `is_offline`, `webauthn_credential_id`

### Removed Tables
- **enrollments** — replaced by `institute_id` + `program_id` + `is_enrolled` on users
- **webauthn_credentials** — passkey feature removed entirely
- **webauthn_credential_id** FK from attendances — removed

### Device Binding + Face Recognition (Aug 9)
| Table | Column | Purpose |
|---|---|---|
| `device_bindings` | user_id FK, device_fingerprint (unique), device_meta json, bound_at | One device per account |
| `device_transfer_requests` | user_id FK, from/to_fingerprint, to_meta json, status (pending/approved/rejected), expires_at, status_changed_by | Move a binding to a new phone |
| `device_unbind_audits` | user_id FK, device_fingerprint, device_meta, reason, unbound_by | Admin unbind trail |
| `face_enrollments` | user_id FK, descriptors json (encrypted at rest), enrolled_at | Biometric descriptor (128 floats) for the PWA |

## 3. Auth & Roles

### UserRole Enum
```php
enum UserRole: string {
    case STUDENT = 'student';
    case SRO_OFFICER = 'sro_officer';
    case ISC_OFFICER = 'isc_officer';
    case SSC_OFFICER = 'ssc_officer';
    case SRO_HEAD = 'sro_head';
    case INSTITUTE_HEAD = 'institute_head';
    case SSC_HEAD = 'ssc_head';
    case SUPER_ADMIN = 'super_admin';
}
```

### Role Groups
- `adminPortalRoles()`: super_admin, ssc_head, institute_head, sro_head
- `headRoles()`: ssc_head, institute_head, sro_head
- `staffRoles()`: ssc_officer, isc_officer, sro_officer
- `officerRoles()`: heads + staff officers
- `adviserRoles()`: institute_head, sro_head (shown on Advisers page)

### Route Middleware
```php
// web.php
$adminRoles = 'super_admin,ssc_head,institute_head,sro_head';
Route::middleware(['auth', 'verified', "role:{$adminRoles}"])->group(...)
```

`EnsureUserHasRole` middleware checks `$user->hasRole($roles)` via the `organization_user` pivot.

### Permission Registry
`App\Support\PermissionRegistry` — maps roles to capabilities and UI modules:

| Role | Sidebar Modules |
|---|---|
| SUPER_ADMIN | dashboard, heads, users, institutes, notifications, **device_bindings** |
| SSC_HEAD | dashboard, advisers, officers, events, calendar, fees, payments, notifications, **device_bindings** |
| INSTITUTE_HEAD / SRO_HEAD | dashboard, students, officers, events, calendar, fees, payments, notifications, **device_bindings** |

> Fees + Penalty management are consolidated under the single **Fees** module (`/admin/fees` has a Fees/Penalty toggle); there is no separate `penalties` sidebar module.

### Auth Flow (Admin Portal)
1. Login via email + password (web login page)
2. `HandleInertiaRequests` resolves permissions via `PermissionRegistry::permissionsFor($user)`
3. Sidebar renders modules from `permissions.modules`

### Auth Flow (PWA / API)
1. Login via `student_number` (or email) + password → `AuthService::attempt()`
2. Checks `users` table first, then falls back to `InstitutionAccountService` (mock API)
3. First login: creates stub User with `is_enrolled = false` (NOT propagated)
4. Onboarding: sets `institute_id`, `program_id`, `is_enrolled = true`
5. Re-login: syncs name/year/sex from institution API

## 4. API Endpoints

### Auth (api.php)
```
POST   /api/login
POST   /api/logout
GET    /api/user
POST   /api/me/refresh
GET    /api/onboarding
PATCH  /api/onboarding
GET    /api/workspaces
PUT    /api/workspace/{organization}
POST   /api/register
POST   /api/institution/auth
```

### Events
```
GET    /api/events
POST   /api/events
POST   /api/events/draft/store
GET    /api/events/{event}
PUT    /api/events/{event}
DELETE /api/events/{event}
POST   /api/events/{event}/publish
POST   /api/events/{event}/unpublish
POST   /api/events/{event}/complete
GET    /api/events/upcoming
GET    /api/events/student        — NEW: student-facing org/event browser
```

> **Note**: `POST /api/events/{event}/start` and `POST /api/events/{event}/cancel` are **NOT implemented** (older docs listed them). There is no start/cancel endpoint in the API.

### QR Configurations
```
GET    /api/events/{event}/qr-configurations
GET    /api/events/{event}/qr-configurations/last
POST   /api/events/{event}/qr-configurations
PUT    /api/events/{event}/qr-configurations/{config}
POST   /api/events/{event}/qr-configurations/{config}/generate
DELETE /api/events/{event}/qr-configurations/{config}
```

### Attendance
```
POST   /api/attendance/scan          — { qr_configuration_id, scanned_at } → server looks up config/event, dedups
POST   /api/attendance/sync          — { records: [{ qr_configuration_id, user_id, scanned_at }] } → dispatches ProcessAttendanceSync
GET    /api/attendance/history       — ?organization_id=&per_page=&page=
GET    /api/attendance/student-stats — per-org (SSC/ISC/SRO) attendance totals
GET    /api/attendance/event/{event} — ⚠️ REGISTERED BUT BROKEN (controller method eventAttendance() undefined)
```

### Device Binding (Api\DeviceController — auth + `X-Device-Fingerprint` header)
```
GET    /api/device/status          — current binding (or null) + bind_token + is_face_enrolled
POST   /api/devices/bind           — idempotent bind of the presented fingerprint
POST   /api/devices/transfer/request — request moving the binding to a new device (pending; 422 if from the bound device)
GET    /api/devices/transfer/requests — transfer list (incoming/outgoing direction)
POST   /api/devices/transfer/requests/{transfer}/approve — only from the bound device; moves the binding
POST   /api/devices/transfer/requests/{transfer}/reject   — only from the bound device
```

### Face Enrollment (NEW, descriptors encrypted at rest)
```
POST   /api/face/enroll         — upsert 128-float descriptor array (validated) by/for the user
GET    /api/face/enrollment      — own descriptors (another user's id → 404)
DELETE /api/face/enrollment      — remove own enrollment
```

### Fees, Payments, Penalties, Receipts, Notifications, Reports, Audit Logs, Organizations
```
GET    /api/fees/my                 — student's computed outstanding fees (obligation_status per fee)
GET    /api/fees/my/penalties       — student's computed outstanding penalties (never persisted; derived from attendance)
POST   /api/fees                    — create fee (head-only)
PUT    /api/fees/{id}               — update fee (head-only)
DELETE /api/fees/{id}               — delete fee (head-only; payment records preserved)
GET    /api/payments                — payment/transaction history (filters: organization_id, user_id, status, payment_method, fee_type)
POST   /api/payments                — record an actual payment/exemption transaction (fee_type fee|penalty, fee_id/event_id, amount, payment_method)
PATCH  /api/payments/{id}/complete  — mark transaction completed (+ auto receipt)
PATCH  /api/payments/{id}/exempt    — waive/exempt (clears payment_method, sets isExempted)
PATCH  /api/payments/{id}/refund    — refund a completed transaction
GET    /api/payments/{id}/receipt   — receipt for a transaction
GET    /api/receipts                — receipt index
GET    /api/reports/attendance|financial|penalty — reports (penalty report reads transactions; no auto-generated obligations)
GET    /api/audit-logs              — activity log index
GET    /api/notifications           — notification list (+ read / read-all / push-token / push-subscription)
GET    /api/organizations           — organizations
```
> **Design rule**: outstanding fees and penalties are **computed dynamically**; `payments` only stores transactions (payment or exemption). There is no `penalties` resource — penalty obligations come from `GET /api/fees/my/penalties`.

## 5. Key Architectural Decisions

### Enrollment Removal
Students' institute/program affiliation is stored directly on `users` as `institute_id` + `program_id` FKs. The `enrollments` table was dropped. Onboarding no longer requires ISC/SRO organizations to exist — a student can be placed in an institute/program without an org record.

### `is_active` → `is_enrolled`
`is_enrolled` represents "has completed onboarding and is fully propagated in the system." Replaced `is_active` everywhere. Students are NOT considered enrolled until they complete onboarding.

### Institute/Program Strings → FK
Old `institute` (varchar, e.g. "ICS") and `program` (varchar, e.g. "BSCS") columns on users replaced with `institute_id` and `program_id` foreign keys to the `institutes` and `programs` tables.

### Organizations → Institute/Program FK (Aug 5)
`organizations` also gained nullable `institute_id`/`program_id` FKs (unique pair). ISC orgs are tied to an institute, SRO orgs to a program. `AccessScopeService`, `EventService`, and `AttendanceService` resolve a student's ISC/SRO workspaces via these columns instead of name/code matching.

### Stub User Pattern
When a student first logs in via institution API, only `student_number`, `name`, `password`, and `is_enrolled = false` are stored. Full profile (phone, year_level, sex) is populated on re-login via `syncFromInstitution()`. Institute/program are set during onboarding.

### Passkey/WebAuthn Removal
`web-auth/webauthn-lib` package removed from the backend. All backend models, controllers, services, migrations, routes for WebAuthn deleted. `webauthn_credential_id` dropped from attendances. **However, the PWA still ships a Biometric Settings page (`profile/Settings.vue` + `services/webauthn.ts`) that calls `/webauthn/*` endpoints — which no longer exist on the backend (all 404). Known mismatch; see PWA-SUMMARY.md.**

### QR Encryption
QR data is AES-256-CBC encrypted. The key is `config('services.qr_key')` (`QR_ENCRYPTION_KEY` env, 64-hex) falling back to `app.key`. Payload fields: `event_id`, `qr_config_id`, `type`, `event_title`, `event_date`, `time_from`, `time_to`, `valid_time_from`, `valid_time_until`, `venue`, `valid_from`, `valid_until` (UTC ISO8601), `latitude`, `longitude`, `geofence_radius`, `issued_at`.
- Backend only **encrypts** (`QrCodeService::encryptPayload()`) when generating QR images. `decryptPayload()` exists but is **unused** — attendance validation is done server-side by looking up the `QrConfiguration`/`Event` directly; the PWA decrypts the QR client-side with Web Crypto.

### QR Storage
`qr_data` on qr_configurations stores the SVG data URI (base64 image), not the encrypted text. The encrypted payload is embedded within the QR image itself.

## 6. Admin Web Routes

```
/admin/dashboard         — Dashboard
/admin/users             — User list (super_admin only)
/admin/heads             — Head management (super_admin only)
/admin/advisers          — Advisers read-only list (ssc_head only)
/admin/officers          — Officer management (all heads)
/admin/officers/assign   — Assign officer
/admin/officers/search   — Search candidates (JSON)
/admin/institutes        — Institute CRUD (super_admin only)
/admin/students          — Student list (institute_head, sro_head only)
/admin/events, /calendar — event views (officers control; heads view-only)
/admin/fees              — Fees **and Penalty** management (Fees/Penalty toggle; per-org penalty amount via POST /admin/fees/penalty; heads control)
/admin/payments          — payment transactions view (tabs: Transactions / Pending Verification / Outstanding; per_page default 30)
/admin/payments/{uuid}   — payment batch detail (receipt view + student term history + exemption reason)
/admin/notifications     — Notification log
```
- `/admin/device-bindings` (NEW Aug 9) — device binding management (super_admin all; heads scoped to their orgs' students), search + Unbind-with-reason (audit trail kept), in the heads+admin role group (officers/students 403).

## 7. Frontend Architecture

### Layout Stack
```
AppShell → SidebarProvider
  ├── AppSidebar (modules from permissions)
  └── AppContent (SidebarInset)
      ├── AppSidebarHeader (breadcrumbs + avatar initials)
      └── <slot> (page content, wrapped in p-4 md:p-6)
```

### Key Components
- `AppSidebar.vue` — renders nav items from `permissions.modules`, active detection via `page.url`
- `AppSidebarHeader.vue` — top bar with breadcrumb + notification bell + avatar initials
- `Pagination.vue` — reusable pagination with per-page selector (10/30/50/100)
- `StatCard.vue`, `PageHeader.vue` — UI primitives
- `useFlashToast.ts` — watches `router.on('finish')` for session flash messages, shows vue-sonner toasts

### Sidebar Module Rendering
```ts
// modules from backend permissions
const officerModules = modules.filter(m =>
  ['dashboard', 'heads', 'advisers', 'users', 'officers', 'events', 
   'calendar', 'fees', 'payments', 'penalties', 'notifications', 
   'institutes', 'students'].includes(m)
)
// Rendered if modules.length >= 5 (admin, not student)
```

## 8. Testing
- Framework: Pest
- Test count: 307 tests, 1263 assertions (Aug 9 — incl. 25 new device/face tests: `Api\FaceTest`, `Api\DeviceTest`, `Admin\DeviceBindingTest`)
- Test files: `Feature/Api/{Auth,LoginFlow,InstitutionAuth,Event,Attendance,Fee,Payment,Penalty,Notification,Organization,Report,Face,Device}Test`, `Feature/Admin/{AdminUsers,AdminHeads,AdminOfficers,AdminAdvisers,AdminInstitutes,DeviceBinding,Payments,Fees,Events}Test`, `Feature/Auth/{OfficerAuthentication,AdminAuthentication,RoleMiddleware,EmailVerification,PasswordConfirmation}Test`, `Feature/Settings/*`, `Unit/Services/{AccessScopeService,AttendanceService,QrCodeService,WorkspaceService,PermissionRegistry}Test`
- **Caveat**: `phpunit.xml` runs SQLite `:memory:` while `.env` uses MySQL. The attendances migration (`2026_08_02_135628`) only drops the old columns on MySQL — on SQLite the old columns remain, so test writes using `event_id` pass locally but the column doesn't exist in production MySQL.

## 9. Migrations (Chronological List)

| Migration | Purpose |
|---|---|
| 0001_01_01_000000 | Users table (base) |
| 2026_07_24_000001 | Organizations |
| 2026_07_24_000002 | Organization_user pivot |
| 2026_07_24_000004 | Soms fields: student_number, phone, institute, program, year_level, profile_photo, is_active |
| 2026_07_24_000010 | Events table |
| 2026_07_24_000020 | Attendances (webauthn_credential_id removed) |
| 2026_07_24_000030–000090 | Penalties, Fees, Payments, Receipts, Notifications, push_token, Audit logs |
| 2026_07_31_000001 | Institution accounts |
| 2026_07_31_000002 | Email nullable, sex column on users |
| 2026_08_01_000001 | Institution password on users |
| 2026_08_01_000002 | Institutes table |
| 2026_08_01_000003 | Programs table |
| 2026_08_01_000010 | Add logo_path to institutes |
| 2026_08_01_082638 | is_enrolled, institute_id, program_id on users; drop enrollments |
| 2026_08_01_084432 | Drop institute, program string columns from users |
| 2026_08_01_095516 | Drop is_active from users |
| 2026_08_01_123343 | QR configurations table |
| 2026_08_01_144032 | Drop type from events |
| 2026_08_01_145346 | Event logs table |
| 2026_08_01_152448 | QR config: session→type, time_in/time_out→valid_from/valid_until (datetime) |
| 2026_08_01_153043 | QR config: valid_from/valid_until back to time |
| 2026_08_01_155409 | Drop latitude, longitude, geofence_radius, max_participants from events |
| 2026_08_02_030559 | Add uuid to events |
| 2026_08_02_040155 | Drop attendance_start/end, add time_from/to to events |
| 2026_08_02_135628 | Replace event_id with qr_configuration_id in attendances (MySQL-only drop) |
| **2026_08_05_153115** | **Add institute_id, program_id to organizations (+ unique pair)** |
| **2026_08_08_000001** | **Create `penalty_fees` table** (org-scoped amount/effective_at/set_by; seeds orgs' config.penalty_amount) |
| **2026_08_08_000002** | **Rework `payments`**: add fee_type, event_id, isExempted, exempted_by/at, paid_at, notes, unique (user_id, event_id); drop penalty_id |
| **2026_08_08_000003** | **Drop `fee_user` and `penalties` tables** — obligations are computed, `payments` is transaction-only |
| **2026_08_11_000001** | **Create `payment_submissions` and `payment_accounts` tables** |
| **2026_08_11_000002** | **Add `uuid` (unique) + `batch_id` (indexed) to `payments`** and backfill existing rows (`uuid` = `batch_id` = fresh uuid) |
| **2026_08_12_000001** | **Create `face_enrollments`** (user_id FK, encrypted descriptors json, enrolled_at) |
| **2026_08_12_000002** | **Create `device_bindings`** (user_id FK, unique device_fingerprint, device_meta json, bound_at) |
| **2026_08_12_000003** | **Create `device_transfer_requests`** (from/to fingerprint + status + expires_at) |
| **2026_08_12_000004** | **Create `device_unbind_audits`** (user_id, fingerprint, reason, unbound_by) |

**Deleted migrations**: `_create_enrollments_table`, `_create_webauthn_credentials_table`, `_add_credential_public_key_to_webauthn_credentials`

## 10. Known Dead Code & Bugs

| Item | Location | Status |
|---|---|---|
| `GpsValidationService` | `app/Services/GpsValidationService.php` | Dead — no callers anywhere in `app/` |
| `AttendanceStatus` enum | `app/Enums/AttendanceStatus.php` | Dead — no references |
| `AttendanceRepository::findByEventAndUser()` | called from `AttendanceService.php:37` | **Undefined method** — would throw if the `event_id` fallback path is exercised (currently unreachable via HTTP since `AttendanceRequest` requires `qr_configuration_id`) |
| `AttendancePolicy::manageable()` | `app/Policies/AttendancePolicy.php:31` | Reads `$attendance->organization_id` — column dropped from attendances |
| `QrCodeService::generatePayload()` | `app/Services/QrCodeService.php:52` | References dropped `events.attendance_end` (returns null → falls back to `now()->addDay()`) |
| Dead `EventPolicy` import | `app/Providers/AppServiceProvider.php:15` | Imported but no such class; registered policy is `SomEventPolicy` |
| `QR_ENCRYPTION_KEY` missing from `.env.example` | `.env.example` | Setup gap — fresh installs get no QR key and `hex2bin()` of base64 `APP_KEY` produces garbage |
| Stale admin Events pages | `resources/js/Pages/admin/events/Show.vue` | Renders dropped columns (`type`, `latitude`, `longitude`, `geofence_radius`, `max_participants`, `attendance_start/end`) |
| Event detail links use numeric id | admin frontend | `getRouteKeyName()` = `uuid`, but links build `/admin/events/{event.id}` → broken navigation (should use `event.uuid`) |
| Dead admin stores | `resources/js/stores/eventStore.ts`, `organizationStore.ts` | Unused; `organizationStore` hits non-existent `/admin/organizations` routes |
| `/dashboard` registered twice | `routes/web.php:10` + `:114` | Duplicate named route (same controller) |
| `HeadController::store()` passes `is_active` | `app/Http/Controllers/Admin/HeadController.php` | Column dropped from users (harmless — not in `$fillable`) |
| Broken attendance route | `routes/api.php:55` | `GET /api/attendance/event/{event}` → undefined `AttendanceController@eventAttendance` |
