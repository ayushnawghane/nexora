# Nexora Phase 2 plan

**Phase 2 goal:** take a DT deal from **Active** (EL issued) through to **money in the bank**: documentation → execution → security creation → ISIN and payment servicing → billing and invoicing → outward register. Every module ships with its slice of the legacy import, so each one is usable on real Stack data the day it lands.
**Status:** draft for owner review · **Written:** 2026-10-04

Related: [`PLAN.md`](PLAN.md) (Phase 1, rules, inputs) · [`legacy-audit.md`](legacy-audit.md) §3.4–3.12 (what Stack does) · [`../DESIGN.md`](../DESIGN.md)

All Phase 1 rules still apply: Eloquent only, a FormRequest on every write mirrored on the client, DB constraints, `brick/math` for money, one transaction per multi-table write, emails queued after commit, idempotent create/issue actions, a test for every Action, shadcn CLI components, and checks in headless Chrome at 4 widths in both themes. God Mode gains an editor for each new record type in the same milestone.

---

## 1. What Stack holds for these modules

Row counts in the local `beacon_stack` copy, which show where the real work is:

| Area | Main Stack tables | Rows |
|---|---|---|
| Documentation | `pre_documents_data`, `post_documents_data`, `legal_compliance_documents_data`, `upload_document_mapping`, `master_legal_documents` | 24,107 · 12,780 · 11,542 · 9,815 · 3,024 |
| Due diligence | `due_dilligience_*` (annexures, ROC search, security certificate, NOC, security docs) | ~500 |
| CP / CS | `cp_documents`, `cs_document`, `cp_mapping`, `cs_mapping` | 116 · 53 · 25 · 15 |
| Execution | `execution_details`, `executed_asset_owner_data` | 20,297 · 433 |
| Security creation | `security_mapping`, `sec_roc_mapping`, `sec_*_asset_type` | 6,164 · 186 · ~190 |
| ISIN and payments | `mon_payment_isin`, `mon_isin_details_new`, `mon_paymt_interst_sch(_new)`, `mon_paymt_prin_sch(_new)`, `mon_payment_schedule(_new)`, `isin_call_details_new` | 3,757 · 3,261 · 83,430 / 71,403 · 33,059 / 17,504 · ~5,900 · 58 |
| Billing and invoicing | `billing_invoice_requested_data`, `tbl_einvoice_details`, `credit_note_update`, `ope_billing_details`, `adhoc_el_billing_data`, `tblpushbillingdata` | 5,614 · 2,171 · 150 · 1,521 · 767 · 26,315 |
| Payments and TDS | `payment_update`, `payment_update_log` | 12,394 · 2,740 |
| Outward and payouts | `master_outward_no`, `payout_instruction_table` | 103,163 · 13,267 |

The `_new` / `_original_*` / dated copies (`tblpushbillingdata_23052025` etc.) are snapshots from earlier fixes. Each import has to pick the one that is current; this is part of every milestone's import work, not an afterthought.

---

## 2. Milestones

Order follows the deal lifecycle. Each milestone stands on its own: schema → actions + tests → screens → God Mode editors → `legacy:import <area>` → browser check → HOW-TO-USE section.

Size: **S** about a week · **M** 2–3 weeks · **L** 4+ weeks (one developer + Claude, rough).

### M7: Documentation (M) 🟡 built, owner review pending (2026-10-04)

**Built:** masters for issuing authorities, legal documents (category + products) and CP/CS documents (with *suggested for* flags; the masters engine gained a yes/no field); the deal's **Documentation** tab with legal documents (standard / copy / supplement / amendment, numbered; execution-version upload with history) and CP / CS checklists (from the master or written for the deal, due dates and overdue flag, multi-file upload that sends the item for checking, checker ≠ uploader, send back with a reason, not applicable with a reason); private file storage with download through the deal; dashboard queue items; God Mode editors; `legacy:import documents`. Moved out: Stack's per-document **security fields** (`legal_compliance_documents_data`: asset owner, charge type, asset address …) describe securities, so they go to **M9**; **due diligence** annexures, ROC search, security cover and NOC (≈500 Stack rows) go with **M8 Execution**, which already handles those documents.

Local import (2026-10-04): see PLAN.md §4 M7 for the counts.

The original scope, for reference:
- **Document master:** legal documents with category, product and listed/unlisted applicability, and stage (pre / post / CP / CS / legal compliance). Stack spreads these across several masters and mapping tables; Nexora uses **one `document_types` table + a pivot to products**.
- **Deal checklist:** when a deal becomes Active, its checklist is generated from the master (by product, listed/unlisted, stage). Items can be added per deal, or marked N/A with a reason.
- **Upload and verify:** maker uploads (PDF/DOCX/image, private storage, size limit, virus-safe MIME check), checker verifies or returns with a reason, **checker ≠ maker** (same rule as the job sheet). Several versions per item, the latest one is current, and the history is kept.
- **CP / CS:** conditions precedent / subsequent are checklist items with a due date and an overdue flag.
- **Due diligence tab:** annexure A/B data, ROC search result, security cover certificate, NOC. Structured fields where Stack has them, otherwise uploads.
- **Sample templates:** download the blank template for a document type.
- Deal workspace: the *Documentation* placeholder tab becomes real (sub-tabs Pre · Post · CP/CS · Legal compliance · Due diligence) plus a progress meter on Overview.
- Import: document master, checklist rows and file records. Files are attached from `LEGACY_UPLOADS_PATH`, the same folder the EL PDFs need.

### M8: Execution (M) 🟡 built, owner review pending (2026-10-04)

**Built:** POA holders master (masters engine gained a date field); per-document execution: send (execution version required, locked while in execution), schedule in batches (place, date/time, internal authorised signatory or POA valid on the date; signatory emailed after commit, no public upload link as Stack had), record the executed copy (PDF, dates, comments), checker ≠ uploader verification with send-back, take out of execution before any copy; *suggest Live* hint instead of Stack's automatic move to Live; pickup list on the dashboard and *Mark picked up*; dashboard *To sign*, checks and sent-back items; God Mode editor; `legacy:import execution`. Not built from the original scope: the annexure email on DTD upload and the documents ZIP (wait for mail settings / owner confirmation); **due diligence** (annexures A/B, ROC search, security cover, NOC) moves to **M9**, next to the security it describes.

The original scope, for reference:
- **Executed documents:** for each document, the execution date, place, stamp duty and the executing parties (asset owners from `executed_asset_owner_data`). The executed copy is uploaded and verified.
- **Internal signatories:** Beacon signatories chosen from users marked *authorised signatory* (already imported with their signature files).
- **Execution email** to the deal team and the client contacts, with an annexure. It's queued after commit and logged.
- **Document bundle:** download all of a deal's executed documents as one ZIP.
- **Pickup list (deferred from M5):** deals whose executed documents are all verified, ready for custody. It joins the dashboard *Waiting on you* queue for the custody team.
- Deal status: the step from Documentation to Live can be set to require execution to be complete. **Owner decision** (see §4).

### M9: Security creation (M) 🟡 built, owner review pending (2026-10-04)

**Built:** masters for asset types, security types, charge types and empanelled agencies; legal documents say which kind of security they create; per-deal securities under their legal document (owner with CIN/PAN check, charge, asset type, what it's over, encumbrance, description, address); ROC / CERSAI / pledge registrations covering chosen securities with a create → modify → satisfy (pledge → release) history and the filing documents on each step; due diligence checklist (ROC search per asset owner, security certificate, NOC, security cover certificate, Annexure A/B, other) issued by empanelled agencies, with maker-checker verification; warning (not a block) when closing a deal with registrations in force; dashboard checks; God Mode editors; `legacy:import security`. Not built: registry search across deals, NeSL/DLT, and share monitoring (Phase 3).

The original scope, for reference:
- Per deal, a list of securities (charge type, asset type, value) with tracks for **ROC charge** (form, SRN, date, charge ID), **CERSAI** (asset ID, registration and satisfaction), **pledge** (DP ID, pledgee, shares, depository) and **DLT** where used.
- Each track has a status flow (pending → filed → registered → satisfied) with documents and an activity log.
- Leaving Live (redemption) can be blocked until securities are satisfied, or show a warning. **Owner decision.**
- Registry search across deals by CERSAI ID, ROC charge ID or ISIN.
- **Out of Phase 2:** share monitoring (NSE feed, price calculation). It's a separate, data-heavy module and moves to Phase 3.

### M10: ISIN and payment servicing (L) 🟡 built, owner review pending (2026-10-04)

**Built:** ISINs per deal (check digit verified; listing, placement, coupon, frequencies, day count, weekend rule, put / call), allotments with the depository credit, interest and principal schedules (generated from a frequency, uploaded in Stack's CSV format, or one date at a time; a moved date keeps the original and the reason), payments recorded as paid / defaulted / redeemed earlier with proof, the ISIN list with Excel export, a daily reminder email per deal plus *Send reminder*, dashboard number and queue items, God Mode editors, `legacy:import isin`. Not built: a preview step for CSV uploads (a file with any bad row is rejected whole instead) and Stack's call / put option history (58 rows). Local import: see PLAN.md §4 M10.

The original scope, for reference:
- **ISIN master per deal:** ISIN, instrument, listed/unlisted, exchange and listing date, face value, coupon (fixed/floating), day-count convention, record date rule, and call/put options.
- **Payment schedules:** interest and principal schedules per ISIN, generated by a `PaymentScheduleService` (frequency, day count, business-day adjustment, partial redemptions) and unit-tested against real Stack ISINs. Hand edits to single rows are allowed and logged. A changed due date keeps the original date.
- **CSV import/export** of schedules in Stack's format (issuers send them that way) with row-level validation and a preview before saving.
- **Reminders:** a daily scheduled job emails upcoming interest/principal dates to the deal team and issuer. Templates and lead times are configurable, and every send is logged with a manual "send now". This replaces Stack's `payment-isin-management-alerts` and `reminder-isin-mail`.
- **Payment confirmation:** record the actual payment (date, amount, UTR) against each schedule row. Late or short payments are flagged on the dashboard.
- ISIN list and MIS export.
- Import: the ISINs and the **current** schedule tables (Stack has old and `_new` copies, so we reconcile them and report differences).

### M11: Billing and invoicing (L) 🟡 built, owner review pending (2026-10-06)

**What Stack actually does (read from its code and the local copy, 2026-10-06).** Decision 6 is answered by the code: `tblpushbillingdata` is Stack's **own** invoice table, not an export. Every proforma, tax invoice, credit note and debit note is a row in it (`invoice_type`), with a status (2 proforma, 3 tax requested, 4 tax issued, 5 credit requested, 6 credit note issued, 7 debit note, 13 cancelled). `erp_database` is only used by one-off migration commands. So Nexora **replaces** Stack's invoicing, and M11 keeps its full size. Other facts that shape the design:
- **No Stack invoice covers more than one deal** (0 of 14,069 deal links), so a Nexora invoice belongs to one deal. Stack's "group CLs into one proforma" option goes.
- Stack's **"debit notes" are out-of-pocket expense (OPE) bills**: reimbursements with no GST, built from `ope_billing_details`. They are not GST debit notes. Nexora calls them *reimbursement bills* and keeps the `DN` number.
- Credit notes reverse part of a tax invoice by fee type and can't exceed the original. They get an IRN.
- Numbers are `BTL/2627/INV265` (proforma), `TAX`, `CN`, `DN`, counted per financial year in `tbloptions` with no lock (two users could get the same number). Nexora keeps the format and uses `NumberSequence`. Sequences continue after Stack's highest number in each year.
- SAC `997154` on 3,388 bills (5 use `997156`). 26 bills were raised without GST (`is_gst_apply`).
- Stack raises a proforma from a *bill request* (one person requests, accounts approves). Nexora keeps that as maker → checker on a draft proforma.
- **Payments are recorded against the proforma** (10,156 of Stack's payment rows; 22 are on tax invoices). The tax invoice is issued once the client pays. Stack keeps one running row per bill (`payment_update`: received, TDS, date, UTR); its log holds test entries and is not imported.
- In 2018-19 Stack gave a tax invoice the same number as its proforma, so Nexora's numbers are unique per kind, not across kinds.

**Design.**
- **Tables:** `invoices` (kind *proforma · tax · credit note · reimbursement*, status *draft · issued · converted · cancelled*, number, date, parent: tax → proforma and credit note → tax, a snapshot of who is billed (name, address, GSTIN, place of supply), SAC, GST rates and amounts, IRN/ack/QR, PDF, who made / issued / cancelled and why); `invoice_lines` (acceptance · service · other · OPE, period, taxable or not, linked to the fee period or expense it bills); `deal_expenses` (OPE with proof files); `invoice_receipts` (amount received, TDS, UTR, date; reversed with a reason, never deleted); `invoice_mails` (send log).
- **A fee period or expense is billed once:** it points at the invoice that bills it (`invoice_id`). That is set when a draft takes it and cleared when the draft is discarded or the invoice is cancelled. `RegenerateSchedule` no longer deletes billed periods. It keeps them and rebuilds only the periods after the last billed one, and a billed fee can't be switched off.
- **Flow:** *Billing queue* (fee periods whose bill date is within 30 days, on open deals) → maker raises a **draft proforma** (periods, unbilled OPE, ad-hoc lines) → checker (≠ maker) **issues** it (number, GST at today's rate, PDF, email after commit) or sends it back with a reason → **receipts** against the proforma (amount + TDS ≤ outstanding) → **convert to tax invoice** (number, GST on the tax invoice date, IRN through the e-invoice driver; if the IRP fails, nothing is saved and the number isn't used) → outstanding / overdue on the proforma (30-day terms, configurable). Proforma cancel releases its periods (not once money is recorded). A tax invoice can be cancelled only within 24 hours of its IRN (GST rule); after that a **credit note** is needed: maker drafts amounts per line, checker issues, and it reduces what the proforma is owed. **Reimbursement bills** follow maker → checker, with no GST, and take their own receipts.
- **E-invoice:** an `EInvoiceGateway` interface with a `fake` driver (same pattern as company lookup). The IRIS driver is written when UAT credentials arrive (decision 8).
- **Permissions:** `billing.view`, `billing.raise` (maker), `billing.approve` (checker: issue, convert, cancel, credit notes), `billing.receipts`.
- **Screens:** Billing queue · Invoices hub (Proforma · Tax · Credit notes · Reimbursement · Due · Cancelled, search, Excel) · Invoice page (lines, totals, IRN, PDF, actions, receipts, history) · the deal's **Invoices** tab (fee periods with billed state, OPE, invoices, outstanding) · dashboard numbers (to approve, to raise, overdue).
- **Import:** `legacy:import billing` brings in invoices by type and status, lines from the fee-type amounts, period links from `master_cl_schedules.proforma_id`, OPE, receipts (`payment_update` + log), IRNs and SAC. Totals are reconciled per financial year and type.

The original scope, for reference. It builds on Phase 1's fee lines, `fee_schedule_periods`, deal billing (address, GSTIN, contacts) and the `GstCalculator`.
- **Billing queue:** periods whose bill date has arrived, per deal, ready to raise. Ad-hoc bills (OPE/reimbursements, one-off fees) can be added by hand.
- **Proforma:** generate from one or more periods, preview, edit before sending, cancel with a reason, send by email (queued, logged). Numbers come from a row-locked sequence per financial year (same pattern as EL numbers).
- **Tax invoice:** convert a proforma once paid, or issue directly, depending on owner rules. GST is split CGST+SGST or IGST from the place of supply, with HSN/SAC on every line. **Issued invoices can't be edited**; corrections go through credit/debit notes.
- **E-invoice (IRIS IRP):** IRN + signed QR for eligible invoices, behind a driver interface with a `fake` driver until UAT credentials arrive (same approach as company lookup).
- **Credit / debit notes:** raised against an invoice with a reason and an approval, with their own numbering.
- **Payments and TDS:** record receipts (full/part), TDS deducted, allocation to invoices, outstanding and ageing. TDS certificates are uploaded per quarter.
- **Invoices hub:** tabs Proforma · Tax · Credit · Debit · Due · Cancelled, with search and Excel export. The deal workspace gets a *Billing* tab.
- Import: invoices, notes, payments, e-invoice details and OPE, reconciled against Stack totals per financial year (as M6 did for billed periods).
- **Out of Phase 2:** payment links (NTT Data Atom) and EA billing. They go to Phase 3 unless the owner moves them up.

### M12: Outward register and payouts (M)
- **Outward register:** every letter or document sent out gets a number from a row-locked sequence (Stack generates them in a nightly batch; Nexora issues them as needed, and none can collide). Category, deal, recipient, date and attachment are recorded. Bulk upload uses validation + preview.
- **Payout instructions:** single and bulk, as PDF instructions plus FT/RTGS bulk files (ZIP), with a checker step before sending and a mail log.
- Import: the 103k outward numbers (the sequence continues after Stack's maximum) and payout history.

### Cross-cutting work (done inside the milestones above)
- **Scheduler and queue:** Phase 2 adds the first scheduled jobs (reminders, overdue flags). This needs a queue worker and `schedule:run` on the server. Set up once in M10 and documented in README.
- **Mail settings:** reminders, invoices and execution emails are real emails to clients. **These can't go live until the owner supplies mail settings (PLAN.md §6).** Locally everything stays on `MAIL_MAILER=log`.
- **Stack's uploads folder:** M7, M8 and M11 all attach legacy files. One copy of the folder serves the EL PDFs and all of these.
- **Dashboard:** each milestone adds its own KPIs and queue items (documents to verify, CP/CS overdue, payments due this week, invoices to raise, invoices overdue).

---

## 3. Moved to Phase 3

Compliance and monitoring (QCR, monthly/CTR, CDD/KYC, covenants, credit ratings), share monitoring, the AIF client portal, the approver mobile app, payment links, EA billing, other products (ST, SECU, Escrow, AIF…), and the reports hub.

---

## 4. Decisions needed from the owner before each milestone starts

| # | Question | Needed for |
|---|---|---|
| 1 | Stack's document masters were imported as they are (143 legal documents, 49 CP + 19 CS documents after merging repeated names). Clean-up can happen in *Masters*. Who makes and who checks documents (roles for *Documents & CP/CS: maker / checker*)? | M7 |
| 2 | Must execution be complete before a deal can move Documentation → Live? (Built: the Execution tab suggests it; nothing blocks or moves it.) Stack's custody table (`stock_holding_data`) is empty in the local copy, so imported deals with every document verified show as *Ready for pickup*: mark the old ones picked up, or should the import do it? | M8 |
| 3 | Must all securities be satisfied before a deal can be Redeemed / Closed, or is that only a warning? (Built: a warning on the status request.) Stack's ROC/CERSAI data includes obvious test entries (e.g. amount 12341234): delete those in God Mode, or should the import skip them? | M9 |
| 4 | Which Stack schedule tables are current: `mon_paymt_*_sch` or `…_new`? Which day-count conventions are used in practice? (Built: the `_new` tables, which Stack's code uses today; day count from Stack's 30/360 · actual/365 · actual/actual list.) | M10 |
| 5 | Who receives ISIN payment reminders, and how many days ahead? (Built: the deal's vertical team email and the RM, 7 days ahead, plus payments overdue up to 30 days; all configurable. Issuers are not emailed yet.) | M10 |
| 5a | **Stack never marked many old payments as paid.** On open deals, 8,728 payments are past their due date and still *Due* (7,739 by more than 90 days; 4,828 on ISINs already past maturity). They show as overdue on the dashboard and ISIN list. Should someone settle them (God Mode, or a one-off clean-up rule such as "due before maturity of a matured ISIN = paid"), or are some genuinely unpaid? Until then reminder emails skip anything overdue by more than 30 days. | M10 (**before reminders go live**) |
| 6 | ~~Where does invoicing live today?~~ **Answered from Stack's code (2026-10-06):** `tblpushbillingdata` is Stack's own invoice table, so Nexora replaces Stack's invoicing (see M11). Confirm nothing outside Stack reads that table. | M11 |
| 6a | **Stack didn't record many payments.** Of 947 imported proformas / reimbursement bills with money due, 946 are past 30 days. Settle them (God Mode, or a clean-up rule such as "proforma with a tax invoice = paid"), or are they genuinely unpaid? | M11 (**before overdue reminders**) |
| 6b | **438 deals have fee periods with no invoice** whose bill date has passed (oldest first in the billing queue). Some were probably billed in Stack in ways the import can't link (the bill isn't tied to the schedule row). Should old ones be marked billed, or raised now? | M11 |
| 7 | Invoice and credit-note number formats; proforma first or tax invoice directly; who approves credit notes. (Built as Stack: `BTL/2627/INV001`, `TAX`, `CN`, `DN`; proforma first; credit notes maker → checker with *billing: approve*.) | M11 |
| 8 | IRIS e-invoice UAT credentials and the HSN/SAC codes per fee type | M11 |
| 9 | Outward number format and categories (142 in Stack); is the nightly batch numbering relied on by anyone? | M12 |

## 5. Suggested start

**M7 (Documentation)** first. It's the next step for 1,221 live and documentation-stage deals, it uses the most Stack data, and it only needs decision 1. In parallel, ask the owner for decision 6, because it decides how large M11 is.
