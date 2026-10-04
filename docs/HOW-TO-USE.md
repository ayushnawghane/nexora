# How to use Nexora

This guide covers what you can do in Nexora **today**. The Phase 2 modules (documentation, execution, billing and more) are still being built. They'll be added here as they go live (see [PLAN.md](PLAN.md)).

What you see depends on your **role**. If a menu item or button mentioned here is missing, you don't have that permission. Ask an administrator.

---

## Contents

1. [Signing in for the first time](#1-signing-in-for-the-first-time)
2. [Signing in day to day](#2-signing-in-day-to-day)
3. [Getting around](#3-getting-around)
4. [Your profile, password and theme](#4-your-profile-password-and-theme)
5. [Forgot your password or lost your phone](#5-forgot-your-password-or-lost-your-phone)
6. [For administrators: users](#6-for-administrators-users)
7. [For administrators: roles and permissions](#7-for-administrators-roles-and-permissions)
8. [Masters (reference lists)](#8-masters-reference-lists)
9. [Companies](#9-companies)
10. [For administrators: tax settings](#10-for-administrators-tax-settings)
11. [Transactions: from draft to engagement letter](#11-transactions-from-draft-to-engagement-letter)
12. [Deals: the deal workspace](#12-deals-the-deal-workspace)
13. [Your dashboard](#13-your-dashboard)
14. [For super-admins: God Mode](#14-for-super-admins-god-mode)
15. [Data brought over from Stack](#15-data-brought-over-from-stack)
16. [Common messages and what they mean](#16-common-messages-and-what-they-mean)

---

## 1. Signing in for the first time

Your administrator creates your account and gives you your **employee code** and a **temporary password**.

1. Open Nexora and enter your **employee code** (not your email) and the temporary password.
2. **Set up two-factor authentication (2FA).** Every account needs it.
   - Install an authenticator app on your phone, such as Google Authenticator or Microsoft Authenticator.
   - Scan the QR code on screen with the app. If you can't scan it, type in the key shown under the QR code.
   - Enter the 6-digit code the app shows. It submits automatically once all 6 digits are in.
3. **Choose your own password.** It must:
   - be at least 10 characters
   - include upper- and lower-case letters
   - include a number and a symbol (for example `! @ # $`)
   - be different from the temporary password

You're now in.

> Keep your authenticator app on a phone you control. If you change phones, see [section 5](#5-forgot-your-password-or-lost-your-phone).

---

## 2. Signing in day to day

1. Enter your employee code and password.
2. Enter the current 6-digit code from your authenticator app.

Things to know:
- **Keep me signed in on this device** skips the password on that device, but you'll still be asked for a 2FA code in each new session.
- **Passwords expire every 90 days.** When yours expires, Nexora asks you to choose a new one before you can continue.
- **Five wrong passwords in a row lock sign-in** for that employee code for a short time. The message tells you how long to wait.
- **Each 6-digit code works only once.** If a code is rejected, wait for the app to show the next one.
- **All sign-in attempts are recorded,** both successful and failed.

---

## 3. Getting around

- **Sidebar (left):** your menu, grouped into *Main*, *Masters* and *Administration*. It only shows pages you're allowed to open.
  - Collapse it to icons with the button at the top-left of the page, or with the thin rail on its edge.
  - On a phone, the sidebar opens as a slide-out menu.
- **Breadcrumbs (top bar):** show where you are. Click an earlier part to go back.
- **Your name (bottom of the sidebar):** opens your menu: Profile, Light/Dark mode and Log out.
- **Notifications:** appear briefly at the top-right after you save something or when something goes wrong.

---

## 4. Your profile, password and theme

Open **your name → Profile**.

- **Your details:** update your name and work email. Your email must not already be in use by another account.
- **Password:** enter your current password, then the new one twice. The same rules as above apply.
- **Theme:** use **your name → Light mode / Dark mode** to switch. Nexora remembers your choice on any device you sign in from.

Your employee code, roles and department are set by an administrator. You can't change them yourself.

---

## 5. Forgot your password or lost your phone

**Forgot your password**
1. On the sign-in page, click **Forgot password?**
2. Enter your work email. If it belongs to an account, a reset link is emailed to you.
   - For security, the page shows the same message whether or not the email exists.
3. Open the link and choose a new password. Then sign in as normal.

**Lost or replaced your phone (no 2FA codes)**
- Ask an administrator to **reset your 2FA**. At your next sign-in you'll scan a new QR code.

**Account deactivated**
- If you see *"This account has been deactivated"*, contact your administrator.

---

## 6. For administrators: users

**Administration → Users** (needs the *View users* permission; changes need *Create & edit users*).

### Finding people
- **Search** by name, employee code or email.
- **Filter** by status (Active / Inactive) or by role.
- **Sort** by clicking a column heading (User, Emp. code, Last sign-in). Click again to reverse the order.
- The 🛡 icon shows whether the person has set up 2FA.
- Filters live in the page address, so you can bookmark or share a filtered list.

### Adding a user
1. Click **New user**.
2. Fill in:
   - **Identity:** employee code (letters, numbers and hyphens), full name, work email, optional mobile (10–15 digits, optional `+`), date of joining (can't be in the future)
   - **Organisation:** department, designation, *Reports to*, verticals and teams, and products
   - **Access:** at least one **role**, and whether they're an **authorised signatory**
3. Click **Create user**.
4. A dialog shows a **temporary password once**. Copy it and share it with the person securely. It can't be shown again.

The new user sets up 2FA and chooses their own password at first sign-in.

### Editing a user
Open the user from the list, change what you need and click **Save changes**. The button stays disabled until something has changed.

### Security actions (on the user's page)
Every action asks you to confirm, and each one signs the user out on all devices.

| Action | What happens |
|---|---|
| **Reset password** | A new temporary password is generated and shown to you once. The user must change it at next sign-in. |
| **Reset 2FA** | The user must scan a new QR code at next sign-in (for example, after losing their phone). |
| **Deactivate user** | The user can't sign in until reactivated. Their history is kept. |
| **Activate user** | Lets a deactivated user sign in again. |

Built-in safeguards:
- You can't deactivate yourself, or reset your own password or 2FA from this page. Use your **Profile** for your own password.
- Only a **super-admin** can edit another super-admin or give someone the super-admin role.
- A user can't be set to report to themselves.

There's no "delete user". Deactivate people who have left, so their history stays intact.

---

## 7. For administrators: roles and permissions

**Administration → Roles & permissions** (needs *View roles*; changes need *Create & edit roles*).

- A **role** is a named set of permissions, such as `dt-operations`. A user can hold several roles; they get every permission from all of them.
- **super-admin** is fixed. It always has every permission and can't be edited or deleted.

### Creating or editing a role
1. Click **New role**, or click a role's name.
2. Enter a name using lowercase letters, numbers and hyphens. Spaces become hyphens automatically.
3. Tick permissions in each section, or use **All** to select a whole section.
4. Click **Save role**.

Changes apply **immediately** to everyone with that role.

### Deleting a role
Open the role and click **Delete role**. A role that's still assigned to users can't be deleted; move those users to another role first.

---

## 8. Masters (reference lists)

**Masters** in the sidebar (needs *View masters*; changes need *Create & edit masters*).

The Masters page lists every reference list, grouped as:
- **Organisation:** departments, designations, products, verticals, vertical teams
- **Business development:** lead sources, arrangers, banks, contact types, transaction types
- **Reference data:** pincodes
- **Deals:** job sheet activities

Open a list to see its entries. You can search, filter by status, and sort by clicking column headings.

### Adding or editing an entry
1. Click **Add …**, or use **⋯ → Edit** on a row.
2. Fill in the form that slides in from the right. Fields marked **\*** are required.
3. Click **Save**. If something's wrong, the field is highlighted with an explanation.

Nexora tidies up what you type: it trims extra spaces and puts codes in capitals and emails in lower case. It also refuses duplicates, such as two departments with the same name or the same pincode and city twice.

Notes on specific lists:
- **Products:** the code appears in engagement letter numbers (for example `BTL/DEB/EL/26-27/18`). Changing a code only affects new numbers.
- **Verticals / teams:** choose the authorised signatory and the products they handle. Teams also hold the team, legal, compliance and billing emails.
- **Arrangers / banks:** the CIN is optional but must be a valid 21-character CIN if entered.
- **Pincodes:** 6 digits, not starting with 0, plus the city and state.
- **Job sheet activities:** the checklist every deal's job sheet carries. *Applies to* limits an activity to listed or unlisted issues; leave it empty for every deal.
- **Issuing authorities:** who issues a CP/CS document (Issuer, Statutory Auditor, ROC …).
- **Legal documents:** the documents a deal can be executed under (trust deed, deed of hypothecation …), with a category and the products whose deals can use them.
- **CP / CS documents:** the standard conditions precedent and subsequent, with their issuing authority. Tick the kinds of issue (listed/unlisted, secured/unsecured) a document is **suggested** for; any document can still be added to any deal.

### Deactivate vs delete

| Action | Use it when | Effect |
|---|---|---|
| **Deactivate** (⋯ menu) | An entry is no longer used | Hidden from dropdowns in forms; existing records keep it. Can be re-activated any time. |
| **Delete** (⋯ menu) | An entry was created by mistake and never used | Removed. **Blocked if anything uses it**, for example a department that has users. |

When in doubt, **deactivate**.

Every change to a master is recorded: who changed what, and when.

---

## 9. Companies

**Companies** in the sidebar (needs *View companies*; changes need *Create & edit companies, GSTs, addresses, contacts*).

The list shows every client and counterparty. Search by **name, former name, CIN, PAN or any GSTIN**, and filter by entity type or status.

### Adding a company
1. Click **New company** and pick the entity type:
   - **Company:** needs its **CIN** and a company **PAN** (4th character `C`). The class (public, private…) and whether it's listed are **read from the CIN**, so you don't enter them.
   - **LLP:** needs its **LLPIN** (like `AAB-1234`) and a firm PAN (4th character `F` or `E`).
   - **Other entity** (firm, trust, bank, body corporate…): no registration number. PAN is optional.
2. CIN, PAN and GSTIN are converted to capitals and spaces are removed. Mistakes show as you type.
3. If you give a date of incorporation for a company, its year must match the year inside the CIN.
4. **Fetch** (next to the CIN, or next to the PAN for other entities) fills the name, incorporation date and category from the government registry. Check what it filled before saving; nothing is saved until you click **Create company**. If another company in Nexora already has that number, you'll get a link to it instead of creating a duplicate.
5. After saving, you land on the company page to add its GSTINs, addresses and contacts.

### GSTINs
- Click **Add GSTIN** on the GSTINs tab. Nexora checks the **check digit** (so most typing mistakes are caught) and that the GSTIN was issued under **the company's PAN**. The company needs a PAN before you can add a GSTIN.
- The **state is taken from the first two digits**; you don't pick it.
- **Fetch** fills the legal name, trade name and registration date from the GST portal, and warns you if the registration is cancelled or suspended.
- A saved GSTIN number **can't be edited**. If it's wrong, deactivate it and add the correct one. You can still edit its legal name, trade name and registration date.
- A GSTIN that active addresses use can't be deactivated until those addresses are moved to another GSTIN or deactivated.

### Addresses
- Types: **Registered office** (at most one active per company), **Billing** and **Other**.
- Linking a GSTIN sets the address's state to the GSTIN's state. This matters because the state decides CGST + SGST vs IGST on invoices.
- If the pincode is in the pincode master, it must belong to the chosen state.
- **Billing name** is only needed when invoices should carry a different name from the company name.

### Contacts
- Each contact needs an **email or a mobile number** (or both). The same email can't be used twice within one company.

### Deactivating
Companies, GSTINs, addresses and contacts are **deactivated, never deleted**, because deals and invoices refer to them. Deactivated records stay visible (marked *Inactive*) and can be reactivated.

---

## 10. For administrators: tax settings

**Administration → Tax settings** (needs *View tax settings*; changes need *Change GST rates and Beacon's GSTIN*).

- **Beacon's GSTIN:** its state is Beacon's home state. Billing to an address in the same state carries **CGST + SGST**; anywhere else carries **IGST**. GST can't be worked out until this is set.
- **GST rates:** each rate has an *effective from* date, and an invoice uses the rate in force on its date. To change the rate, **add a new rate** with a future date. Rates that have taken effect can't be edited or removed, because invoices already raised depend on them. A scheduled rate can be withdrawn before its date.
- SGST must equal CGST, and IGST must equal CGST + SGST.

---

## 11. Transactions: from draft to engagement letter

**Transactions → New transaction** (needs *Create & edit draft transactions*). Phase 1 covers Debenture Trustee deals.

### The wizard
Work through six steps. Each one saves on its own (**Save and continue**), so you can stop and come back later; drafts are listed under **Transactions → Drafts**. A step opens once the steps before it are complete.

1. **Basics:** the client company, vertical team, relationship manager, signatory and origin. The company must already exist under Companies.
2. **Contacts:** tick the company's people who should receive the letter and mark each **To** or **Cc**. At least one "To" needs an email. Missing someone? Use **Manage contacts** to add them on the company page.
3. **Issue details:** listing, issue type, security, rating, base issue size, green shoe and tenure. Split the issue across instruments (NCD, OCD, CCD, MLD); the split must add up exactly, and the totals turn red until it does.
4. **Fees:** the acceptance fee (one time) and the service fee (per annum), as an amount or a percentage of the issue size, with frequency, start date, advance/arrears and optional escalation.
5. **Schedule:** Nexora builds the billing schedule. Periods follow the financial year, and part-periods are charged by days (pro rata). Check it, then click **verify**. Changing the issue or fees later clears the verification.
6. **Review:** check everything and **Send for approval** (needs *Send transactions for approval*). The transaction can't be edited while approvers decide.

### Approval
- Approvers get an email with a personal link, and also see the request under **Approvals**. Open the transaction to approve or reject it; a rejection needs a reason.
- It's **approved** once a head approver and at least one other approver approve. **Any rejection** sends it back: open it, click **Revise and resubmit**, make the changes and send it again. Every round of votes stays on record.
- You can't vote on a request you submitted. Email links are personal and expire after 7 days.

### Engagement letter
Once approved, someone with *Issue engagement letters* opens the transaction and clicks **Issue letter**. This assigns the next EL number for the financial year (e.g. `BTL/DEB/EL/25-26/14`) and the deal code, creates the PDF, and moves the transaction to **Active**. The deal opens at **Preliminary** in the deal workspace ([section 12](#12-deals-the-deal-workspace)). If a fee runs from the EL date, the EL date is fixed to that fee's approved start date. Every version of the letter is kept; open it with **Open PDF**.

### Lists
**Drafts**, **Pending approval** and **Approved** are under Transactions. Each can be searched and exported to Excel with **Export**. Once the letter is issued, the transaction is listed under **Deals**.

---

## 12. Deals: the deal workspace

**Deals** in the sidebar (needs *View deals*). The list shows every transaction whose engagement letter has been issued. Search by company, CIN, EL number or deal code, and filter by status. Open a deal to see its workspace, one tab per area. Tabs for the later Phase 2 modules (Execution, Security, ISIN, Covenants, Credit rating, Outward, Invoices) are placeholders for now.

### Overview
The EL number, deal code, issue and owners at a glance, and every version of the engagement letter. **Fees, schedule and approval** opens the full transaction record.

### Contacts & billing
Who the deal's invoices go to (needs *Edit deal contacts & billing*). Click **Set up billing** / **Edit** and choose:
- the **billing address**, from the company's active addresses
- the **GSTIN**: an address linked to a GSTIN always bills under it. Otherwise pick a GSTIN registered in the same state, or none.
- the **billing contacts**: at least one needs an email.

The **place of supply** is the GSTIN's state (or the address's state when there's no GSTIN). The same state as Beacon's GSTIN means **CGST + SGST**; any other state means **IGST**. Missing an address or contact? Add it on the company page first.

### Status
A deal moves through **Preliminary → Documentation → Live**, and from Live to **Redeemed**, **Foreclosed**, **Surrendered**, **Transferred**, **Defaulted** or **Closed**. It can be put **On hold** or **Cancelled** before it goes live. Click **Change status** (needs *Request deal status changes*) and give the new status, the date it took effect and a reason. Only the moves allowed from the current status are offered.

| Change | Who must approve |
|---|---|
| Putting a deal **on hold** | Nobody: it applies at once |
| **Cancelling** a Preliminary deal | Management |
| Anything else | **Management and Accounts**, one person each |

- Moving a deal **out of Live** needs the **NOC** uploaded with the request (PDF or image, up to 10 MB).
- A deal on hold resumes only at the status it was put on hold from.
- Approvers (*Approve status changes (Management)* / *(Accounts)*) are emailed, see the request on the dashboard, and vote on the deal's Status tab. One rejection (with a reason) rejects the request. You can't vote on your own request, and one person can't approve for both teams.
- Only one request can be open at a time. The person who raised it can **withdraw** it until it's decided.
- Redeemed, Foreclosed, Surrendered, Transferred, Cancelled and Closed are **final**: the deal closes and can't change again (except through God Mode).

### Documentation
Three lists: the deal's **legal documents**, its **conditions precedent (CP)** and its **conditions subsequent (CS)**. Adding, uploading and removing needs *Documents & CP/CS: maker*; verifying needs *Documents & CP/CS: checker*.

**Legal documents**
- **Add document** and choose the document and how to add it: the **document** itself (once per deal), **another copy**, a **supplement** or an **amendment**. Copies, supplements and amendments are numbered for you (e.g. *Supplement Deed of Hypothecation-2*). Only documents set up for the deal's product are offered.
- **Upload** the execution version (PDF, Word, Excel or image, up to 20 MB). **Replace** uploads a newer one; the earlier files stay under *earlier files*.
- **Remove file** takes the current file off (it stays in the history). A document can be **removed** from the deal only when it has no current file.

**CP / CS**
- **Add CP items** / **Add CS items**: tick documents from the list, or **write your own** for this deal only. Documents *suggested* for this kind of issue (listed/unlisted, secured/unsecured) are at the top. You can give a **due date**; an item past its due date shows **Overdue** until it's verified or marked not applicable.
- **Upload** one or more files (up to 10 at a time). Uploading sends the item **for checking**, with you as the maker.
- A **checker** clicks **Verify**, or **Send back** with what needs fixing. **The checker can never be the person who uploaded the files.** A verified item is final.
- A sent-back item stays sent back while you fix it: remove the wrong file, upload the right one, and it goes for checking again.
- From the **⋯** menu: **Set due date**, **Mark not applicable** (with a reason; the files stay on record), or **Remove**, which is only for an item added by mistake with no files ever uploaded.

### Job sheet
The checklist of activities for the deal (from *Masters → Job sheet activities*).
- The **maker** (*Job sheet: maker*) clicks **Record**, enters the date the item was received and a comment, and sends it for checking.
- A **checker** (*Job sheet: checker*) clicks **Verify**, or **Send back** with what needs fixing. **The checker can never be the maker.**
- A sent-back entry can be corrected and resubmitted. A verified entry is final.

### Activity
Everything that happened on the deal, newest first: status changes, billing changes, job sheet entries, documents and CP/CS items, with who did it and when.

---

## 13. Your dashboard

**Dashboard** is the first page after sign-in.
- **Headline numbers** (if you can see deals or transactions): open deals, live deals, deals opened this financial year, issue size under trusteeship, drafts and transactions pending approval. Click a number to open the matching list.
- **Waiting on you:** transactions to approve, deal status changes waiting for your team, job sheet entries and CP/CS items to check, and your entries and items that were sent back. Oldest first; click one to go straight to it.

---

## 14. For super-admins: God Mode

**Administration → God Mode** (super-admins only). Corrects any business record when the normal screens can't, for example a deal that's already active or a letter that went out with a mistake. If you entered your 2FA code more than 15 minutes ago, you're asked for a fresh one first.

- **Find the record:** search by company name, CIN, PAN, GSTIN, EL number (including old, retired ones) or deal code. A company page lists its GSTINs, addresses, contacts and transactions; a deal page lists its basics, letter contacts, issue details, fees, billing, status, job sheet, legal documents and CP/CS items. For documents you can correct the name; for CP/CS items the name, issuing authority, due date and comments (their status and files stay as they happened).
- **Correct it:** click **Correct**, change the values and give a **reason**. The values are checked exactly as on the normal screen, so God Mode can't save anything the regular form would refuse. If someone else changed the record after you opened the page, reload and try again.
- **Undo:** each correction in the **Change history** has an **Undo** button. It puts the old values back (checked against today's rules) and is refused if the record has changed since. The undo is logged too. Some changes can't be undone this way (a forced status change, the first fees on a deal); make a new correction instead.
- **Follow-ups:** correcting the issue or fees rebuilds the fee schedule. Check it on the normal view, then click **Verify schedule**. If data printed in the letter changed after its latest version, you're told the letter may be out of date.
- **Force a status change:** moves a deal without the Management/Accounts vote, but only to a status its current status allows. A final status closes the deal.
- **Engagement letter:** each correction is saved as a new version and the old versions stay:
  - **Regenerate from data:** re-renders the letter from today's data with the same number and date.
  - **Edit wording:** click into the letter and change the text, for this deal only. Links, scripts and inline styles are removed; the letter's layout is kept.
  - **Number / date:** keep the number, take the next one for the financial year, or enter one (it must match the date's financial year and never have been used). A replaced number is **retired for good**. If a fee runs from the EL date, correct the fee's start date first.
  - **Replace PDF:** upload a signed or corrected PDF.

Every God Mode change is permanent: who made it, when, why, and the values before and after.

---

## 15. Data brought over from Stack

At go-live, users, roles, masters, companies, Debenture Trustee deals and their documentation are copied from Stack (the old system).
- **Your account:** sign in with your usual employee code and your Stack password. You'll be asked to choose a new password and set up 2FA straight away.
- **Imported letters:** older engagement letter versions show **PDF not copied yet** until the files are brought over from Stack. The letter details (number, date, version) are already there.
- **Pending status changes:** deals that were waiting in Stack for redemption, closure or cancellation approval arrive at their current status with the request open. Management and Accounts approve them on the deal's Status tab.
- **Fees and schedules:** billed periods are exactly as Stack billed them. Deals whose fee setup in Stack was incomplete come without fees; set them up through God Mode if needed.
- **Documentation:** each deal's legal documents (with supplements, amendments and copies) and its CP/CS items arrive with their status and files. Files show **(not copied from Stack yet)** until the files are brought over. Items Stack's older system listed twice on a deal are merged into one, keeping all files. Stack's "WIP" items are *Awaiting check*.
- **Placeholders:** a user email like `user-123@legacy.invalid` means Stack had no usable email for that person. Ask an administrator to correct it.

---

## 16. Common messages and what they mean

| Message | Meaning / what to do |
|---|---|
| *These credentials do not match our records.* | Wrong employee code or password. Check Caps Lock. Remember it's your **employee code**, not your email. |
| *Too many login attempts…* | Sign-in is locked for a short time after repeated failures. Wait and try again, or use **Forgot password?** |
| *That code is not valid…* | Use the current code in your app. Codes change every 30 seconds and each one works only once. Check your phone's clock is set automatically. |
| *This account has been deactivated.* | Contact your administrator. |
| *Your password was set by an administrator…* / *Passwords expire every 90 days…* | Choose a new password to continue. |
| *The new password must be different from your current password.* | Pick a password you're not already using. |
| *… has already been taken.* | That name, code or email already exists. Search for it, as it may be deactivated. |
| *This … is in use and can't be deleted.* | Deactivate it instead. |
| *This GSTIN fails its check-digit test.* | A character is mistyped. Copy the GSTIN from the GST certificate or portal. |
| *This GSTIN belongs to PAN …, not the company's PAN …* | Either the GSTIN is another company's, or the company's PAN is wrong. |
| *The incorporation year must match the year in the CIN.* | Check the date, or the CIN (characters 9–12 are the year). |
| *No … record was found.* (after **Fetch**) | The registry doesn't know this number. Check it, or type the details in by hand. |
| *The lookup service is not responding right now.* | Type the details in by hand, or try **Fetch** again later. |
| *Too many lookups in a short time.* | Wait a minute and try again. |
| *The instrument amounts add up to …, not the base issue size.* | Fix the split in the issue details step so it matches exactly. |
| *Complete these steps first: …* | A wizard step is missing, or the schedule hasn't been verified. |
| *You can't vote on a request you submitted.* | Another approver has to vote. |
| *The … runs from the EL date and its approved schedule starts on …* | Issue the letter with that date, or revise the transaction to change the fee start date. |
| *A … deal can't move to …* | That status change isn't allowed from the deal's current status. Only the offered moves are possible. |
| *A status change is already waiting for approval.* | Wait for the open request to be decided, or withdraw it if you raised it. |
| *Upload the NOC to move a deal out of Live.* | Attach the NOC to the request. |
| *A deal on hold resumes at the status it was put on hold from (…).* | Pick that status, or cancel the deal. |
| *You made this entry, so someone else has to check it.* | Another checker has to verify or send it back. |
| *The GSTIN is registered in … but the address is in …* | Choose an address in the GSTIN's state, or a different GSTIN. |
| *This record changed after you opened it…* (God Mode) | Someone else edited it. Reload the page and make the correction again. |
| *The record has changed since this correction…* (God Mode undo) | Undoing would overwrite a later edit. Correct the record by hand instead. |
| *That EL number has already been used.* | Every EL number, including retired ones, can only be used once. |
| *The PDF of … hasn't been copied over from Stack yet.* | The letter came from Stack and its file hasn't been brought over yet. |
| *Tax settings are incomplete.* | Ask an administrator to set Beacon's GSTIN and a GST rate. |
| *403 / This action is unauthorized.* | Your role doesn't allow this. Ask an administrator if you need it. |

---

*Something not working as described? Note the page, what you clicked and the message shown, and send it to the Nexora team.*
