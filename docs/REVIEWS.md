# Reviews

Everything waiting for review, one section per milestone, newest first: what was built, what to know before pushing, import results and the questions for the project owner. Add each new milestone's review at the top. When the owner has signed a milestone off, mark it **✅ reviewed** with the date, and keep it here for the record.

| Milestone | State |
|---|---|
| [M11 Billing and invoicing](#m11-billing-and-invoicing-2026-10-06) | 🟡 committed and pushed to `main` (2026-10-06), owner review pending |
| [M10 ISIN and payment servicing](#m10-isin-and-payment-servicing-2026-10-04) | 🟡 committed (`58685d2`), owner review pending |
| [M9 Security creation](#m9-security-creation-2026-10-04) | 🟡 committed (`16f7bac`), owner review pending |
| [M8 Execution](#m8-execution-2026-10-04) | 🟡 committed (`1fb9e19`), owner review pending |
| [M7 Documentation](#m7-documentation-2026-10-04) | 🟡 committed (`b0983a1`), owner review pending |

Details for M7–M10 are in [`PLAN.md`](PLAN.md) §4 and [`PHASE-2-PLAN.md`](PHASE-2-PLAN.md). The sections below list what's still open on each.

---

## M11 Billing and invoicing (2026-10-06)

What was built, what to know about the local setup, and what's still open.

### 1. Local setup and checks

- **Committed and pushed to `main`** on 2026-10-06 (commit titled "M11: billing and invoicing …").
- **The real billing import already ran once on your local `nexora` database** (11:44 on 2026-10-06). The run was stopped partway, but its first pass had already finished. That's why the local database has Stack's invoices.
- **Your local database is migrated.** The new billing tables (`0001_01_01_000016_create_billing_tables`) are in, and the permission seeder was re-run. Nothing was dropped.
- **Still running locally:**
  - WAMP's MySQL. It was started by hand (`mysqld.exe --defaults-file=…/my.ini`) because the Windows service needs admin rights. Stop it, or restart it from WAMP.
  - `php artisan serve` on port 8000.
- **UICHECK login** is back to inactive, with no permissions and no 2FA. The demo invoices made for the browser check were deleted, and the invoice number sequences were put back.
- **Checks, last run on 2026-10-06:**
  - All 333 Pest tests pass. Run the legacy tests without `--parallel`, because they share one legacy test database.
  - PHPStan, Pint, ESLint and Prettier are clean, and `npm run build` passes.
  - Headless Chrome at 375 / 768 / 1024 / 1440px in both themes, on real data: no sideways scroll on any billing screen.

---

### 2. What was built

#### How billing works
1. **Draft:** a maker drafts a proforma from the deal's fee periods, unbilled out-of-pocket expenses and other fees.
2. **Issue:** a checker, who must be a different person, issues it. It gets its number (e.g. `BTL/2627/INV001`), GST at today's rate, a PDF, and an email to the deal's billing contacts. The checker can also **send it back** with a reason.
3. **Receipts** (amount received + TDS) are recorded against the **proforma**, as Stack does.
4. **Tax invoice:** the proforma converts to a tax invoice with its own number, GST at today's rate, and an IRN when the client has a GSTIN. If the e-invoice portal refuses it, nothing is saved and the number isn't used.
5. **Credit notes** (maker → checker) reduce a tax invoice line by line, at the tax invoice's own GST rates. They lower what the proforma is owed.
6. **Cancel:**
   - a proforma or reimbursement bill, until money is recorded on it (what it billed can then be billed again);
   - a tax invoice or credit note only within 24 hours of issue (GST rule). After that, use a credit note.
7. **Reimbursement bills** (Stack's "debit notes", numbered DN) bill out-of-pocket expenses, without GST.

Other rules:
- **Each fee period is billed only once.** It records the invoice that billed it.
- **Rebuilding a fee schedule** no longer deletes billed periods; only the periods after the last billed one are rebuilt.
- **A fee that has been billed** can't be switched off.

#### Screens
- The deal's **Invoices** tab: what's outstanding and overdue, its invoices, the fee schedule with what billed each period, and expenses.
- **Invoice page:** lines and totals, IRN, PDF, actions, receipts, history.
- **Billing → Invoices:** tabs Due, Drafts, Proforma, Tax, Credit notes, Reimbursement, Cancelled. Search and Excel export.
- **Billing → Billing queue:** fee periods ready to bill, 20 deals a page.
- **Dashboard:** *Outstanding on invoices* and *Invoices overdue* figures. Queue items for invoices to issue, drafts sent back, deals ready to bill, and overdue invoices.
- **God Mode** editors for invoices (heading), invoice lines (totals re-worked at the invoice's own rates), receipts and expenses.

#### Permissions
`billing.view`, `billing.raise` (maker), `billing.approve` (checker), `billing.receipts`. Stack's billing permissions are mapped to these in `config/legacy.php`. The mapping only applies after `legacy:import roles` is run again.

#### Settings (`config/billing.php`, `.env.example`)
- `EINVOICE_DRIVER=fake`: made-up IRNs, so the whole flow works offline. `none` issues invoices without an IRN.
- `BILLING_QUEUE_DAYS=30`, `BILLING_PAYMENT_TERMS_DAYS=30`, SAC `997154`, number format `BTL/{fy}/{code}{serial}`.
- Beacon's name, address, CIN, PAN and bank details printed on invoices default to the values in Stack's config.

---

### 3. What Stack's code and data showed (and the design choices that follow)

- **Nexora replaces Stack's invoicing (decision 6).** `tblpushbillingdata` is Stack's own invoice table, not an export to another system.
- **Payments are on the proforma.** 10,156 of Stack's payment rows are on proformas and only 22 on tax invoices. Nexora does the same.
- **One deal per invoice.** No Stack invoice covers more than one deal, so the "group deals into one proforma" option was dropped.
- **Stack's debit notes are out-of-pocket expense bills**, not GST debit notes.
- **Numbers are unique per kind.** In 2018-19, Stack gave tax invoices the same number as their proforma. Stack's counter also had no lock, so some numbers repeat within a kind. The import keeps the first and adds `-{Stack id}` to the later one.
- **SAC** `997154` is on almost every Stack bill (5 use `997156`).

---

### 4. Local import results (`legacy:import billing`)

| | Count |
|---|---|
| Invoices | 10,616 (1 Stack row of type "other" skipped) |
| Receipts | 4,804 (114 Stack rows with nothing received skipped) |
| Out-of-pocket expenses | 193 |
| Fee periods linked to their proforma | 952 |

Totals of invoices that aren't cancelled are the same in Stack and Nexora:

| Kind | Total |
|---|---|
| Proformas | ₹69,32,60,216.56 |
| Tax invoices | ₹62,69,36,959.89 |
| Credit notes | ₹3,50,15,007.60 |
| Reimbursement bills | ₹6,77,60,626.34 |

- **Number sequences** continue after Stack's highest in each year (FY 26-27: proforma 264, tax 23, DN 4).
- **844 warnings,** listed in `storage/app/private/legacy-import/2026-10-06_114451_billing.json`:
  - payments with no date received in Stack, dated from the TDS date, then the entry date, then the invoice date;
  - old (mostly 2017-18) invoices whose total isn't their amounts plus tax, kept exactly as Stack billed them.
- **A second local run hasn't been done.** The "re-running changes nothing" check is covered by the automated test (a bug where re-runs rewrote converted proformas was found and fixed), but not yet confirmed on the real data. Optional: `php -d memory_limit=1G artisan legacy:import billing` and check every row says *unchanged*.
- **Run it with `memory_limit=1G`.** The default 256 MB is too small for the full Stack table.

---

### 5. Questions for the project owner (also in `PHASE-2-PLAN.md` §4)

| # | Question |
|---|---|
| 6 | Confirm nothing outside Stack reads `tblpushbillingdata`, since Nexora replaces it. |
| 6a | **946 of the 947 imported proformas / reimbursement bills with money due are overdue.** Stack mostly didn't record payments. Are they genuinely unpaid, or should they be settled (in God Mode, or by a rule such as "proforma with a tax invoice = paid")? Decide this before any overdue reminders are added. |
| 6b | **438 deals have past fee periods with no invoice.** Some were probably billed in Stack without the bill being tied to the schedule row. Mark the old ones billed, or raise them now? |
| 7 | Number formats, proforma first, who approves credit notes. Built as Stack: `BTL/2627/INV001`, `TAX`, `CN`, `DN`; proforma first; credit notes maker → checker. |
| 8 | IRIS e-invoice UAT credentials, and the HSN/SAC per fee type. |
| — | **Beacon's GSTIN:** Stack's config has `27AAGCB5444C1ZX`. Confirm it, then enter it in Tax settings. It hasn't been set anywhere. |
| — | Who gets the billing maker, checker and receipts roles. |

---

### 6. Not built yet

- IRIS e-invoice driver (waiting for UAT credentials).
- TDS certificate uploads per quarter, and an ageing report.
- Payment links (NTT Data Atom) and EA billing, both moved to Phase 3.

---

### 7. Small things noticed along the way

- **Legacy tests clash when run in parallel.** They share the `nexora_legacy_testing` database. They pass when run on their own (`php artisan test tests/Feature/Legacy`).
- **Legacy tests write report files into the real `storage/app/private/legacy-import/` folder.** That's why that folder has extra reports with timestamps such as 11:51 and 11:55. They're harmless, but easy to confuse with real import runs.
- **The timesheet hook fires on every Bash command,** not only after `git push` (already in PLAN.md §6).

---

### 8. Files changed

**New**
- `app/Actions/Billing/*`: `DraftInvoice`, `IssueInvoice`, `RecordReceipt`, `ManageExpenses`, `BillingSupport`
- `app/Services/Billing/*`: `InvoiceTotals`, `InvoiceNumbers`, `InvoiceRenderer`
- `app/Services/EInvoice/*`: gateway interface plus `fake` and `none` drivers
- Models: `Invoice`, `InvoiceLine`, `InvoiceReceipt`, `InvoiceMail`, `DealExpense`
- Enums: `InvoiceKind`, `InvoiceStatus`, `InvoiceLineKind`
- Controllers: `Billing/InvoiceController`, `Billing/BillingQueueController`, `Deals/DealInvoiceController`
- Requests: `Http/Requests/Billing/*`
- `Exports/InvoicesExport`, `Notifications/InvoiceIssued`
- God Mode editors: `InvoiceEditor`, `InvoiceLineEditor`, `InvoiceReceiptEditor`, `DealExpenseEditor`
- `Legacy/Importers/BillingImporter`
- Migration `0001_01_01_000016_create_billing_tables`, `config/billing.php`
- PDF view: `resources/views/invoices/document.blade.php`
- Frontend: `Pages/Invoices/{Index,Show}.jsx`, `Pages/Billing/Queue.jsx`, `Components/billing/draft-invoice-sheet.jsx`, `Components/deals/invoices-panel.jsx`
- Tests: `tests/Feature/Billing/*` (3 files), `tests/Feature/Legacy/BillingImportTest.php`, `tests/Support/billing.php`

**Changed**
- Actions: `RegenerateSchedule` and `SaveFees` (billed periods kept), `StoresDocumentFiles` (expense proof)
- Controllers: `DealController` (Invoices tab), `DealDocumentController` (expense files), `DashboardController`, `GodModeController`
- Models and support: `Transaction`, `FeeSchedulePeriod`, `FinancialYear`
- Wiring: `AppServiceProvider`, `routes/web.php`, `config/permissions.php`, `config/legacy.php`, `LegacyImport` command, `GodMode/Editors.php`
- Frontend: `navigation.js`, `Pages/Deals/Show.jsx`
- Tests: `tests/Pest.php`, `tests/Support/legacy.php`
- Docs: `docs/PHASE-2-PLAN.md`, `docs/PLAN.md`, `docs/HOW-TO-USE.md`, `README.md`, `.env.example`

---

## M10 ISIN and payment servicing (2026-10-04)

- **Built:** ISINs per deal (with the ISIN check digit validated), allotments, interest and principal schedules (generated from a frequency, uploaded in Stack's CSV format, or added one date at a time), recording payments as paid / defaulted / redeemed early, the ISINs list with Excel export, and a daily reminder email.
- **Local import:** 2,067 ISINs, 779 allotments, 27,055 interest and 4,525 principal payments, 8,722 files.
- **Owner questions** (PHASE-2-PLAN §4):
  - **#4:** which Stack schedule tables are current. Built on the `_new` ones.
  - **#5:** who gets reminders, and how many days ahead. Built: the vertical team and RM, 7 days ahead.
  - **#5a:** **8,728 payments Stack never marked paid show as overdue.** Settle them before reminders go live.
- **Not built:** a preview step for CSV uploads, and Stack's call / put option history (58 rows).
- **To review:** the deal's ISIN tab, the ISINs list, the reminder email (in `storage/logs/laravel.log`), and the roles.

---

## M9 Security creation (2026-10-04)

- **Built:** securities per legal document; ROC / CERSAI / pledge registrations with create → modify → satisfy history; a due diligence checklist with maker / checker; a warning (not a block) when closing a deal that still has registrations in force.
- **Local import:** 150 empanelled agencies, 2,369 securities, 142 registrations, 509 due diligence items, 799 files.
- **Owner question #3:** must all securities be satisfied before a deal is redeemed or closed? Also, Stack's obvious test registrations (e.g. amount 12341234): delete them in God Mode, or should the import skip them?
- **Not built:** a registry search across deals, NeSL/DLT, and share monitoring (Phase 3).
- **To review:** the Security tab and the roles.

---

## M8 Execution (2026-10-04)

- **Built:** POA holders master; sending, scheduling and recording executed copies per document; checker verification; the pickup list; dashboard items.
- **Local import:** 32 POA holders (+12 inactive), 4,483 executions, 4,266 executed copies.
- **Owner question #2:** must execution be complete before Documentation → Live? Also, imported deals with every document verified show as *Ready for pickup*: should the old ones be marked picked up?
- **Not built:** the annexure email when the DTD is uploaded, and the documents ZIP (both waiting on mail settings / owner confirmation).
- **To review:** the Execution tab, the pickup list and the roles.

---

## M7 Documentation (2026-10-04)

- **Built:** masters for issuing authorities, legal documents and CP/CS documents; the deal's Documentation tab (legal documents and CP/CS checklists with maker / checker, due dates, overdue flags); private file storage.
- **Local import:** 4,562 deal documents, 24,606 CP/CS items, 31,281 file records. Re-runs change nothing.
- **Owner question #1:** Stack's document masters came over as they are (143 legal documents, 49 CP + 19 CS). Who makes and who checks documents?
- **To review:** the Documentation tab, the masters clean-up, and the roles.

---

## Open for every milestone

- **Inputs still owed by the project owner:** see [`PLAN.md`](PLAN.md) §6 (mail settings, Beacon's GSTIN, GST rate, EL wording, approvers, Stack's uploads folder, and more).
- **Roles:** none of the maker / checker permissions from M7–M11 are given to anyone yet, apart from what the Stack roles import maps.
- **Stack's uploads folder:** imported files show *not copied from Stack yet* until a copy is set in `LEGACY_UPLOADS_PATH`.
