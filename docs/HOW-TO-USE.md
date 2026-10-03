# How to use Nexora

This guide covers what you can do in Nexora **today**. Transactions, deals, approvals and God Mode are still being built. They'll be added here as they go live (see [PLAN.md](PLAN.md)).

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
10. [Common messages and what they mean](#10-common-messages-and-what-they-mean)

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
4. After saving, you land on the company page to add its GSTINs, addresses and contacts.

### GSTINs
- Click **Add GSTIN** on the GSTINs tab. Nexora checks the **check digit** (so most typing mistakes are caught) and that the GSTIN was issued under **the company's PAN**. The company needs a PAN before you can add a GSTIN.
- The **state is taken from the first two digits**; you don't pick it.
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

## 10. Common messages and what they mean

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
| *403 / This action is unauthorized.* | Your role doesn't allow this. Ask an administrator if you need it. |

---

*Something not working as described? Note the page, what you clicked and the message shown, and send it to the Nexora team.*
