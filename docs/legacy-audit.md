# Legacy Audit: beacon-stack → Nexora

Source scanned: `C:\wamp64\www\git\beacon-stack` (`master` @ `302fac566`, 2026-10-02).
Purpose: list every function, screen, integration, background process and config value the rebuild has to cover, plus the code problems the rebuild must not repeat.
Target stack: **Laravel 12 + Breeze (Inertia + React, JavaScript, no TypeScript)**, styled with [`DESIGN.md`](../DESIGN.md).

---

## 1. What exists today

The repo holds **five separate apps** that share data through databases and callback URLs:

| App | Tech | Size | Role |
|---|---|---|---|
| `stack-erp` | Laravel 10, `nwidart/laravel-modules` (35 modules), Blade + jQuery | ~1,880 code files, 284 models, ~4,200 route lines | Internal trustee ERP. This is the core system. |
| `client-app` | Laravel 12 + Inertia + React (JS) + shadcn/Tailwind 4 | 141 files | Client portal: transactions, invoice/document uploads, term sheet mapping |
| `aif-client` | React 19 SPA (Vite), MUI + Bootstrap + Formik + react-hook-form | 111 files | AIF client portal: monthly / quarterly (QCR) / yearly (CTR) compliance filing |
| `approval_app` | React 18 SPA (TypeScript, Vite + CRA), Mantine + MUI + Redux, Firebase, Pusher | 223 files | Approver app (web/mobile): approve or reject deals and transactions |
| `einvoice-app` | Laravel 12, two controllers | 35 files | Gateway to the IRIS GST e-invoice API (UAT and production) |

Deployed hosts referenced in code: `stack.beacontrustee.co.in`, `uat-stack.*`, `erp.beacontrustee.co.in`, `erpstage.*`, `stack-client.*`, `stack-approval.*` (each with `uat-`/`prod-` variants).

**Recommendation for Nexora:** build **one Laravel app with three areas** (internal ERP, client portal, approver area) that share models, permissions and the design system. E-invoicing, payments and lookups become service classes, not separate apps. This removes the copied logic and the callback plumbing between apps.

---

## 2. Business domain

Beacon is a trusteeship company. Every piece of work is a **transaction (deal)**, identified by a `con_id` and a CL/EL number (consent letter / engagement letter), under one of these products:

| Code | Product |
|---|---|
| DT | Debenture Trustee (listed / unlisted, ISIN-based) |
| ST | Security Trustee |
| SECU | Securitisation (PTC) |
| ESCROW | Escrow Agent |
| AIF | AIF Trustee (fund / scheme setup, SEBI compliance) |
| DEB_BT | Debenture / Bond Trustee variant |
| CONSULTING | Consulting engagements |
| SEZ / PTC | SEZ-specific PTC |
| EA | Empanelled Agency billing |
| REIT / InvIT, Share Pledge, Safe Keeping | Smaller product lines found in masters / logic |

Lifecycle: **Lead → Create transaction (per product) → Fees and schedule → Engagement/Consent letter generated → Approval (management, by email or app) → Deal setup (overview steps) → Documentation (pre/post, CP/CS) → Execution → Security creation (ROC/CERSAI/pledge) → ISIN / payment schedules → Ongoing monitoring and compliance → Billing (proforma → tax invoice → credit/debit notes) → Payments / TDS → Outward / payout → Reports.**

---

## 3. Functional inventory (by module) → Nexora screens

Screen names below are proposed. "+" marks actions within a screen.

### 3.1 Auth and users (`Login`, `Master` roles/permissions)
- Login, logout, change password, forced new password
- **2FA** (Google Authenticator: setup + verify), device token save (FCM push)
- Roles, permissions, role↔permission mapping, user↔role, user reset token, teams, verticals, vertical↔product mapping
- **Screens:** Login · 2FA setup · 2FA verify · Change password · Users list/form · Roles & permissions matrix · Teams · Verticals & product mapping · Profile

### 3.2 Dashboard (`Dashboard`, `Overview`)
- Deal counts, financial-year counts, pending lists, pickup lists, pending transactions, approve/reject from the dashboard, stock holding save
- CL/EL overview and versioning, CL preview generation
- **Screens:** Dashboard (KPIs + pending/pickup queues) · Transaction overview (CL/EL with version history)

### 3.3 Leads and transactions (`Transaction`, 44 controllers, the largest module)
- Create transaction per product (DT, ST, SECU, ESCROW, AIF, DEB_BT, CONSULTING, SEZ PTC, other), drafts, draft links, tranche create and activate
- CIN / GST / PAN lookups and lead lookup, contact save
- Issue details per product
- **Fees:** acceptance + service fees, AIF fees, escalation fees, add-on fees, subscription amount calculation, **fee schedule generation and verification** (separate versions for DT, DEB_BT, SECU, ST and Escrow)
- **Letters:** generate and preview EL/CL per product (normal + letterhead), number→words helpers
- **Approval:** send approval mail per product, approval success/reject/edit pages (by email link), approver lists
- Active / closed / draft lists, active list export, outstanding billing and debit billing reports, invoice email send/stop
- **Screens:** Leads · New transaction (product picker → product-specific multi-step wizard: parties → issue details → fees → schedule → letter preview → send for approval) · Drafts · Active transactions · Closed transactions · Transaction approval landing (public signed link) · Fee schedule editor

### 3.4 Deal setup (`Deal`, 19 controllers)
- Deal dashboard, overview **steps 1–10** (step 8/9 are sub-lists), AIF fund/scheme setup overview, PPM table and upload
- Contacts (map, status, CL contacts), billing recipients, SAF billing address list/edit/save/delete
- GST: fetch, manual save, mark N/A, billing status; pincode/CIN address lookups
- Deal status and checker status logs, date changes, regulated/unregulated logs, deal log
- Tabs per deal: bank account, CP, CS, covenants, credit rating, documentation, execution details, issue details, outward, periodic, pre-DTA, security details
- **Screens:** Deal workspace (one page with tabs: Overview · Contacts · Billing addresses · Bank · Documentation · Execution · Security · ISIN · Covenants · Credit rating · Compliance · Outward · Billing · Logs)

### 3.5 Legal documentation (`Legal`, `DueDilligience`, `Execution`)
- Pre / post documents with sample templates, CP/CS document upload/verify/remove, other documents, legal compliance save, document view
- Due diligence: annexures A/B, NOC, ROC search, security cover, security docs, additional data, file verify
- Execution: executed documents, internal signatories, save/verify execution, trigger execution email, annexure mail, document ZIP
- **Screens:** Documentation tab (pre/post checklist with upload + verify) · Due diligence tab · Execution tab

### 3.6 Security creation (`SecurityDetails`, `Search`, `ShareMonitoring`)
- ROC charge, CERSAI, pledge, NSDL/NSEL-DLT: pending forms, activity logs, documents, satisfaction (ROC/CERSAI), DP IDs, pledgee names, amount→words
- Global search: CERSAI, ROC, ISIN
- Share monitoring: pledge details, monitoring types, CSV upload/validate, share calculation, outstanding value update, historical data, NSE feed
- **Screens:** Security tab (ROC · CERSAI · Pledge · DLT sub-tabs) · Share monitoring · Registry search (ROC/CERSAI/ISIN)

### 3.7 ISIN and payments servicing (`ISIN`)
- ISIN form (+ SEZ variant), basic details, listing details, additional info, call/put options, principal and interest payment schedules (edit, due date update, delete), DLT upload for interest/principal, CSV format download, payment schedule export, ISIN MIS, reminder list, manual reminder mail trigger
- **Screens:** ISIN list · ISIN detail (schedule grid + call/put + listing) · ISIN MIS · Reminders

### 3.8 Compliance and monitoring (`Compliance`, `Covenants`, `CreditRating`, `CompanyData`, `BankDetails`, `REF`)
- CDD/KYC (new + existing, documents), entity upload/details, statutory auditors, EA certificates, monitoring certificates by ISIN
- **QCR** (quarterly compliance report) listed/unlisted per ISIN/CIN: fetch, submit, verify/unverify, file delete
- Monthly and CTR yearly compliance (staff side), remarks
- Covenants: upload/verify/list per deal, sample template
- Credit rating: per deal + global insert, bulk file save, rating scale info, export all
- Company documents, bank details (IFSC lookup), REF (search companies/CLs/ISINs, store)
- **Screens:** Compliance hub (filter by company/CL/ISIN) · QCR review · Monthly/CTR review · CDD/KYC · Covenants · Credit ratings · Company master detail

### 3.9 AIF client compliance (`AifClient` API + the `aif-client` SPA)
- Client login/refresh/logout (JWT), scheme list, overview, financial years
- **QCR quarterly wizard, 9 steps:** management tables, domestic/overseas/temporary investments, divestment, borrowing, investor grievances (3 parts), auditor & fund admin, declaration, PPM acknowledgement
- **CTR monthly / yearly wizard:** annex 1–3, combined data, past CTR, upload CTR
- Staff side: client status, deviation report, monthly AIF export, monthly mail
- **Screens (client portal):** Scheme list · Compliance dashboard · Monthly report · Quarterly QCR wizard · Yearly CTR wizard · Past filings · Documentation

### 3.10 Approvals (`Approval`, `Api`, `MobileApp` + the `approval_app` SPA)
- Email approve/reject links, management response, deal-status approvals list/action
- Mobile/API: deal login, pending list, deal details, approve/reject transaction, FCM token
- **Screens (approver area, mobile-first):** Pending approvals · Approval detail (deal / transaction) · Success/reject confirmation · Profile

### 3.11 Billing and invoicing (`Billing`, `BillingInvoicing`, `EABilling`, `Payterms`, `Payments`, plus `einvoice-app`)
- Billing structure per deal, fee schedules (one-off + recurring, auto-schedule), EL billing, grouped CLs (group + mapping), vertical team billing
- **Proforma:** generate, preview, edit, regenerate, cancel (with recipients), transfer, request, send email
- **Tax invoice:** convert proforma→tax, regenerate, delete, send email, cancel request
- **Credit / debit notes:** request, generate, regenerate, convert, verify, send email, debit due
- Reimbursements / OPE (out-of-pocket expenses) upload, BUS promotion, other bills, adhoc EL details
- Payment update/edit/reset, payment log, payment mail, invoices due
- **TDS:** declaration upload, reminders, TDS report
- **E-invoice** (IRIS IRP, GST rates, HSN mapping), **payment links** (NTT Data Atom: create/resend/cancel/status/callback)
- EA billing entry
- Exports: invoicing list, schedule, TDS, cancel, raw, EA billing reports
- **Screens:** Billing workspace per deal · Invoices hub (tabs: Proforma · Tax · Credit · Debit · Due · Cancelled) · Invoice detail/preview · Payments & receipts · TDS · OPE / reimbursements · EA billing · Payment links

### 3.12 Outward and payouts (`Outward`)
- Outward register: number generation (nightly), bulk upload, skipped list, annexure mail, document ZIP, convert to Word
- MIS report bulk upload; payout instructions (single, bulk, vertical, auto), payout email bulk/edit/send, FT and RTGS bulk generation + ZIP, HLF pre-check checkpoints, upload to OneDrive
- **Screens:** Outward register · Bulk uploads (outward / MIS / payout) · Payout instructions · Payout mail log

### 3.13 Masters (`Master`, 81 controllers, ~55 masters)
Company (CIN), contacts, categories / sub-categories, AIF constitution/feature, legal document master, CP/CS document mapping, POA master + categories, empanelled agency, lead, team, transaction type/status, GID, product, scheme, fund, IM/IM-type, trust, investor / investor branch / reporting, arranger, bankers, IFSC, pincode, RTA, NSDL/CDSL, reg. custodians, rating (action/outlook/scale/type/watch, SEBI credit rating), security, charge, asset, account/audit, authority/issuing authority, department, designation, industry type, KYC docs, document category, document↔product mapping, days conventions, domestic/overseas, declaration investment, payterms, options, outward category, relation to issuer, billing name/GST search, branch email.
- **Screens:** **One generic Master screen**, driven by a schema per master (list + drawer form), plus custom screens only where needed (Company/CIN, POA, Empanelled agency, Roles & permissions, Document mapping).

### 3.14 Reports and utilities (`Reports`, `Emailer`, `Pdf`, `Mainlayout`)
- Billing report/summary/pending, ageing, recovery, year-wise, outward and compliance exports, report section
- Emailer (mail template / recipient master), debenture PDF, shared lookups (CL list, originators, IFSC, investors, branches, documents)
- Log viewer (`opcodesio/log-viewer`)
- **Screens:** Reports hub (filters + export) · Email templates · System logs (admin only)

---

## 4. Background processing

**Scheduled (active in `app/Console/Kernel.php`):**

| Time | Command |
|---|---|
| Daily 08:30 | `app:payment-isin-management-alerts` |
| Daily 08:45 | `app:outward-upload-notification-rm-mail` |
| Daily 09:00 | `app:el-mail-management-team` |
| Daily 11:15 | `app:reminder-isin-mail` |
| Daily 11:50 | `app:aif-event-based-mail` |
| Daily 12:15 | `app:aif-complience-mailer` |
| Daily 23:45 | `app:generate-outward-number` |
| Monthly | `app:make-montly-secu-folder` |
| Mondays 15:00–23:58, every minute | `invoices:send-reminders` (suspicious; needs confirming) |

Commented out or disabled: EL number generation, pay-schedule alerts, EL-with-no-ISIN, group mailer, TDS/credit-declaration reminders, SECU Excel read.

**Queued jobs (25):** proforma/tax/credit/debit generation and issuer mails, convert to tax/credit, payout instruction/PDF generation, Excel reads (auto payout, credit rating), fund transfer, invoice file download ZIP, invoice/TDS/credit-declaration reminders, listed/unlisted server callbacks, SECU payout mail, regenerate/request mails.

**Migration commands (~20):** one-off data migrations (fees, ISIN, outward, contacts, masters, legal docs). Useful as **data-mapping references** for the Nexora migration.

---

## 5. External integrations

| Integration | Used for | Env keys |
|---|---|---|
| Codium API (CIN/GST/PAN/lead) + SurePass | Company lookup, lead status sync | `CODIUM_API_*`, `CIN_GST_PAN_JWT_SECRET`, `SURE_PASS_KEY`, `GST_LINK_NEW`, `LEAD_TOKEN` |
| IRIS e-invoice (IRP) | GST e-invoices | `PROD_BILLING_TAX_API`, `UAT_BILLING_TAX_API` |
| NTT Data **Atom** (NDPS) | Payment links + callback | `NDPS_*` |
| Microsoft Graph | Mail sending, OneDrive upload | `MS_CLIENT_ID`, `MS_CLIENT_SECRET`, `MS_TENANT_ID`, `MS_SENDER_EMAIL` |
| SMTP (3 senders: default, invoice, team-SECU) | Mail | `MAIL_*`, `INVOICE_MAIL_*`, `TEAM_SECU_MAIL_*` |
| Firebase (FCM) | Push to approval app | `FIREBASE_*`, `GOOGLE_APPLICATION_CREDENTIALS`, `SERVER_API_KEY` |
| Pusher | Realtime | `PUSHER_*` |
| Sentry | Error tracking | `SENTRY_*` |
| iLovePDF | PDF compress/merge | `ILOVEPDF_*` |
| LibreOffice (local binary) | DOCX → PDF | `LIBREOFFICE_PATH` |
| SFTP (SECU host) | Securitisation summary files | `SECU_*` |
| AWS S3 / SQS | Storage / queue (configured) | `AWS_*`, `SQS_*` |
| Listed / unlisted Stack servers | Push and callback of transactions between environments | `STACK_*`, `STACK_LISTED_*` |
| NSE archives, SEBI SI portal | Share monitoring data, compliance links | none (hardcoded URLs) |

**Databases:** the main MySQL database plus `erp_database`, `stack_database`, `fee_recovery_mysql`, `crm_database` and `website_database` (cross-DB reads), with an optional `sqlsrv` connection.

---

## 6. Environment variables

**There is no usable `.env` in the repo.** `stack-erp/.env` is gitignored and missing, and the root `.env` is empty. The real values have to be taken from the UAT/production servers. The names below were found by scanning `env()` calls. Groups for Nexora's `.env.example`:

- **App:** `APP_*`, `DOMAIN_URL`, `PORTAL_NAME`, `SYSTEM_TYPE`, `PROJECT_TYPE`, `COMPANY_CODE`, `DEVELOPER_ID`
- **DB:** `DB_*`, `ERP_DB_DATABASE`, `STACK_DB_DATABASE`, `CRM_DB_DATABASE`, `DB_WEBSITE_DATABASE`, `FEE_RECOVERY_DB_*`
- **Storage paths:** `DOCUMENT_UPLOAD` (used 68×), `AIF_DOC_UPLOAD`, `COMMON_FOLDER*`, `COMMON_SECU_FILE`, `FADIR_PATH`, `SECU_FILE_ORIGIN/DESTINATION` (also misspelled `DESTINCATION` in code)
- **Tax:** `CGST`, `SGST`, `IGST` (rates kept in env; should become a DB setting)
- **Auth:** `JWT_*`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_*`, `CLIENT_PASSWORD`, `PASSWORD`, `EMAIL`
- **AIF client export headers:** `CLIENT_BORROWING`, `CLIENT_CONTR`, `CLIENT_DIVESTMENT`, `CLIENT_DOMESTIC`, `CLIENT_INVESTOR_GRIEVANCE`, `CLIENT_OVERSEAS`, `CLIENT_TEMPORARY`
- **Mail / integrations:** see §5
- **Infra:** `CACHE_*`, `QUEUE_*`, `REDIS_*`, `MEMCACHED_*`, `BROADCAST_DRIVER`, `LOG_*`, `PAPERTRAIL_*`, `MAIL_TESTING_MODE`, `AUDIT_EMAIL_ID`
- **SPAs:** `VITE_API_URL`, `VITE_BASE_URL`, `VITE_APP_NAME`; Firebase web config (`API_KEY`, `AUTH_DOMAIN`, `PROJECT_ID`, `STORAG_EBUCKET` (misspelled), `MESSAGING_SENDER_ID`, `APP_ID`, `MEASUREMENT_ID`)

---

## 7. Code health: why a rebuild, and the rules for Nexora

| Finding | Evidence | Nexora rule |
|---|---|---|
| No input validation layer | 1 `FormRequest` class in the whole codebase. Validation is ad hoc (~265 inline calls across ~800 endpoints). | Every write endpoint gets a `FormRequest`. Validation errors show up through Inertia `useForm`. |
| Query logic in controllers | ~1,730 raw `DB::table/select/raw` calls. Controllers up to **3,328 lines** (`ReportsController`, `ISINController`, `DealController`, `TdsController` are each over 2,700). | Eloquent models + relationships, and action/service classes per use case. Controllers stay thin. |
| Copy-pasted logic per product | Fee schedule, approval, EL generation and number→words each exist 5–7 times (DT/ST/SECU/ESCROW/DEB_BT/CONSULTING/AIF). | One engine per concept with product strategy classes (e.g. `FeeScheduleService` + `ProductFeeRules`). |
| ~55 masters, each with its own CRUD | `get_*_list` / `*_store` / `*_edit` / `*_active` / `*_delete` routes repeated for each master | One generic master CRUD (config-driven) + shared `DataTable` and `FormDrawer` components. |
| Frontend scattered across views | 307 Blade files with inline `<script>`, ~1,130 `$.ajax`/axios calls; 3 separate SPAs on different UI libraries (MUI 6/9, Mantine, Bootstrap, shadcn) | One Inertia React app, one component library built on `DESIGN.md`, server-driven props (no ad hoc AJAX for page data). |
| Unreliable loading and pre-filling | Data loaded through separate AJAX calls after page render, with no shared loading/error state | Page data comes in Inertia props. Partial reloads / deferred props get skeletons. Every async action has explicit pending/success/error states. |
| No tests | 0 test files | Pest feature tests for every action/endpoint, plus calculation unit tests (fees, GST, schedules). |
| Schema not versioned | 166 migrations **plus 46 raw SQL / text files in `db_changes/`** | Migrations only. Schema reviewed before migration. |
| Leftover debug code | 37 `dd/dump/print_r/var_dump`, ~5,900 commented-out code lines, `test-*` routes in `routes/web.php`, stray files (`*.stackdump`, `recovered.patch`, `tash show -p stash@{0} > recovered.patch`) | CI lint (Pint + ESLint + Prettier) and no debug routes outside `local`. |
| Inconsistent naming / typos | `DueDilligience`, `Complience`, `montly`, `genrate`, `Reqenrate`, `STORAG_EBUCKET`, `SECU_FILE_DESTINCATION`, two `routes`/`Routes` dirs | Naming conventions written down and enforced in review. |
| Home-made permission system | `HasPermissionsTrait` with mixed slug / array checks | `spatie/laravel-permission`, with policies on every model. |
| Config in env that belongs in data | GST rates and export column headers read from env | Settings table + admin screen. |

---

## 8. Proposed dependencies for Nexora

**Composer**
- `laravel/framework` ^12, `laravel/breeze` (React, JS), `inertiajs/inertia-laravel`, `tightenco/ziggy`
- `laravel/sanctum` (approver/mobile API tokens), replacing `tymon/jwt-auth`
- `pragmarx/google2fa-laravel` + `bacon/bacon-qr-code` (2FA)
- `spatie/laravel-permission` (roles/permissions)
- `spatie/laravel-activitylog` (replaces custom `activity_log`/`audit_log` helpers)
- `maatwebsite/excel` (imports/exports), `barryvdh/laravel-dompdf` (PDFs), `webklex/laravel-pdfmerger` or `ilovepdf/ilovepdf-php` (merge/compress), `phpoffice/phpword` (DOCX templates, if letters stay DOCX)
- `kreait/laravel-firebase` (FCM), `pusher/pusher-php-server` **or** `laravel/reverb` (realtime)
- `sentry/sentry-laravel`, `opcodesio/log-viewer`
- `league/flysystem-aws-s3-v3`, `league/flysystem-sftp-v3` (SECU SFTP)
- `microsoft/microsoft-graph` (or raw Guzzle) for Graph mail/OneDrive
- `laravel/horizon` (if Redis queues), `laravel/pail` (dev)
- Dev: `pestphp/pest`, `laravel/pint`, `larastan/larastan`, `barryvdh/laravel-debugbar`

**Dropped:** `ixudra/curl` (use the HTTP client), `phpmailer/phpmailer` (use Laravel Mail), `arrilot/laravel-widgets`, `yajra/laravel-datatables` (server-side pagination through Inertia), `nwidart/laravel-modules` (use domain folders instead), `doctrine/dbal` (not needed on L11+), `zanysoft/laravel-zip` (built-in `ZipArchive`), `endroid/*` QR (bacon covers it), `khanamiryan/qrcode-detector-decoder` (confirm whether it's used).

**npm**
- Breeze base: `react`, `react-dom`, `@inertiajs/react`, `@vitejs/plugin-react`, `tailwindcss` v4, `@tailwindcss/vite`
- UI: `@radix-ui/*` primitives (via shadcn, restyled to DESIGN.md tokens), `lucide-react`, `class-variance-authority`, `clsx`, `tailwind-merge`, `sonner` (toasts)
- Data: `@tanstack/react-table` (all tables), `recharts` (dashboard charts), `date-fns` or `dayjs`, `react-day-picker`
- Forms: Inertia `useForm` + `zod` for client-side checks in multi-step wizards
- Files: `react-dropzone`, `react-pdf` (preview)
- Realtime: `laravel-echo` + `pusher-js`, `firebase` (web push, if kept)
- Dev: `eslint`, `prettier`, `prettier-plugin-tailwindcss`

---

## 9. Open questions for the planning phase

1. **Data:** is Nexora a fresh schema with a migration from the legacy DB, or does it run on the existing database? This affects every model.
2. **Cross-DB dependencies** (`erp`, `crm`, `fee_recovery`, `website`): are they still live, and which ones does Nexora read or write?
3. **Listed / unlisted split:** two Stack servers currently push and call back to each other. Is one Nexora instance with a flag acceptable?
4. **Client portals:** should the client portal, the AIF client portal and the approver app be merged into one Nexora app with separate guards? Is a native mobile app still needed, or is a mobile-first web area enough?
5. **Scope of phase 1:** which product(s) and modules go first? Suggested order: Auth/Users → Masters → Transactions (one product end-to-end) → Deal workspace → Billing.
6. **Documents:** are letters (EL/CL) generated from DOCX templates or HTML→PDF? Is LibreOffice on the server still acceptable?
7. **Environment values:** who will supply the UAT `.env` from the server (secrets were never in the repo)?
8. **The every-minute Monday invoice reminder schedule**: is this intended?
