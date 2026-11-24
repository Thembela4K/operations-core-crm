# Architecture Decision Log

## ADR-001: Rebuild From Streamlit To Laravel + MySQL

**Decision:** Replace the original Streamlit implementation with a Laravel + MySQL/MariaDB app.

**Why:** The app needs to be hosted on Hostinger Custom PHP/HTML hosting, support persistent relational data, authentication, roles, document upload, email, reminders, and structured workflows.

**Alternatives considered:** Keep Streamlit; build with another stack. Streamlit was rejected because it was not appropriate for the desired hosted business portal and maintainable multi-module architecture.

**Tradeoffs:** Laravel is more setup-heavy than Streamlit but fits Hostinger/PHP hosting and long-term module growth.

**Do not reverse casually.**

## ADR-002: Use Blade + Tailwind, Not A SPA

**Decision:** Use Laravel Blade pages with Tailwind/Vite.

**Why:** Server-rendered pages are simpler for Hostinger shared PHP hosting, easier to maintain, and sufficient for a corporate internal tool.

**Alternatives considered:** SPA frameworks. Not chosen because there was no strong need and it would add deployment/runtime complexity.

**Tradeoffs:** Some interactivity is more manual, but the app remains straightforward to deploy and debug.

## ADR-003: Public Repo With Private Runtime Data

**Decision:** Keep repository public but exclude sensitive runtime data.

**Why:** The user explicitly wants the project public and safe from credential leakage.

**Rules:** Do not commit `.env`, passwords, API keys, real SMTP app passwords, private database exports, uploaded documents, logs, production secrets, or private files.

**Tradeoffs:** Seeders and docs must stay generic; real organization setup happens in the private database/admin UI.

**Do not reverse casually.**

## ADR-004: Rename Projects To Tender Proposals

**Decision:** Replace the “Project” domain with “Tender Proposal” under Operations.

**Why:** The business flow is about tenders/proposals received from tender portals, not generic project management.

**Alternatives considered:** Keep “Projects” and relabel in UI. Rejected because it would mislead users and the public.

**Tradeoffs:** Older migration names still show project history, but user-facing domain is tender proposals.

## ADR-005: Separate Quotation Requests From Sales Quotations

**Decision:** Keep incoming quotation requests in Operations and client-facing sales quotations in Finance.

**Why:** The business uses both terms differently. Quotation requests are assigned to departments; sales quotations are created for clients.

**Tradeoffs:** More modules/routes, but avoids confusion.

**Do not merge casually.**

## ADR-006: Department Assignment Workflow

**Decision:** Tenders and quotation requests are assigned to departments/users with due dates, instructions, unread state, documents, and submissions.

**Why:** The original core problem is that departments forget assigned tenders/quotations. The system exists to make assignments trackable.

**Tradeoffs:** More workflow tables (`assignments`, `submissions`, `documents`, `reminder_logs`) but better accountability.

## ADR-007: Reviewer Inbox For Returned Work

**Decision:** Department submissions remain tied to the original tender/quotation request and appear for reviewers; they do not become new requests.

**Why:** The user explicitly said returned work should not come back as a new submission disconnected from the original assignment.

**Tradeoffs:** Requires polymorphic submissions and document links, but preserves traceability.

## ADR-008: Remove Scores

**Decision:** Remove system emphasis on scores/ratings in the workflow UI.

**Why:** The system is for reminders, assignments, and tracking; the user did not want scoring language.

**Tradeoffs:** Some legacy DB fields may remain for migration/history compatibility, but UI should not center scoring.

## ADR-009: Roles Without Manager

**Decision:** Supported roles are `super_admin`, `director`, `reception`, `department_user`, and `business_admin`. No `manager`.

**Why:** The business clarified there is no manager/operational management role needed.

**Tradeoffs:** Permissions are role-method based; future role changes must be deliberate.

**Do not reintroduce `manager` casually.**

## ADR-010: Invitation-Only Users

**Decision:** No public registration. Super admin invites users by email; invited users set passwords through an invitation link.

**Why:** The portal is internal and staff access must be controlled.

**Tradeoffs:** Requires email to onboard users. Local Mailpit mirror was added to make local demos reliable.

**Do not add public registration without approval.**

## ADR-011: OTP Password Reset For Invited Users Only

**Decision:** Forgot password sends a 6-digit OTP only to active users who accepted invitations.

**Why:** Keeps reset flow scoped to legitimate staff and avoids public enumeration.

**Tradeoffs:** Users without accepted invitations cannot use forgot password; admin must invite/resend first.

## ADR-012: One System Mailbox

**Decision:** One configured mailbox sends all CRM notifications, reminders, invitations, reset OTPs, and future system emails.

**Why:** The user asked whether all department mailboxes were needed and chose one sender mailbox. Department mailboxes are recipients only.

**Tradeoffs:** SMTP setup is simpler. Sender reputation/delivery depends on that mailbox.

## ADR-013: Local Mailpit Mirror For Demos

**Decision:** In `APP_ENV=local`, mirror outgoing system emails to Laragon Mailpit when available.

**Why:** Gmail can accept SMTP but delay/filter inbox delivery. The user needs reliable local demonstrations.

**Tradeoffs:** Local sends may appear both in Mailpit and external Gmail. Production is unaffected.

**Do not remove without replacing local demo email strategy.**

## ADR-014: Fixed VAT At 15%

**Decision:** VAT is fixed at 15% in V1, read from `app_settings` default/fallback but not exposed for normal user editing.

**Why:** The user stated VAT is always 15%.

**Tradeoffs:** Easy and consistent. Future VAT changes require a deliberate settings/admin decision.

## ADR-015: Operational Finance, Not Full Accounting

**Decision:** The system replaces QuickBooks-style operational document work, not formal accounting.

**Scope:** Clients, quotations, invoices, payments, expenses, receipts, requisitions, PDFs, reports.

**Out of scope:** Chart of accounts, journals, ledgers, payroll, tax filing, trial balance.

**Do not expand into full accounting without a new plan.**

> **Amended by ADR-027.** The out-of-scope list above no longer applies: chart of accounts,
> the ledger, VAT filing and financial statements are now in scope. Payroll, inventory and
> multi-currency remain excluded.

## ADR-016: Departments Own Client Quotations; Reception Owns Invoices

**Decision:** Departments create/download client sales quotations and send via their own email outside CRM. Reception creates invoices from quotations/job cards.

**Why:** This matches current business workflow. Reception currently uses QuickBooks for invoices; CRM replaces that operational invoicing work.

**Tradeoffs:** CRM tracks quotation documents/statuses but does not send them to clients directly.

**Do not make reception send client quotations or departments create invoices without approval.**

## ADR-017: Job Cards Between Quotation And Invoice

**Decision:** Departments create job cards instead of invoices. Job cards can be marked ready for invoice.

**Why:** The business clarified departments should not invoice; job cards represent department work readiness.

**Tradeoffs:** Adds another workflow entity but models reality better.

## ADR-018: Delivery Notes For IT/Device Delivery

**Decision:** Add delivery notes linked to job cards/sales quotations.

**Why:** IT sometimes delivers consumables/devices and requires reception-issued delivery notes.

**Tradeoffs:** Extra module and numbering (`DN-{YEAR}-{0001}`), but clearer operations trail.

## ADR-019: Requisitions As Approval Workflow

**Decision:** Add requisitions for departments requesting funds from directors.

**Why:** Requisitions were previously sent by email and needed tracking/approval in CRM.

**Tradeoffs:** Requires approval, release, item totals, supplier/document links, and email notifications.

## ADR-020: Use Existing Old CRM Only As Import Reference

**Decision:** Do not reuse the old CodeIgniter/Rise CRM codebase. Import useful data only.

**Why:** The old CRM had useful features but too much generic jargon and poor adoption fit.

**Tradeoffs:** Import command maps selected records only; not every old CRM feature is recreated.

## ADR-021: MIS Assistant Uses Hybrid AI

**Decision:** MIS assistant uses local responder/navigation logic first and remote NVIDIA OpenAI-compatible AI when configured.

**Why:** The user wanted human-like conversation and CRM-aware answers, but the app also needs predictable local behavior and clear API failure messages.

**Alternatives considered:** Fully local hard-coded bot; Gemini free tier; full remote AI takeover.

**Tradeoffs:** More complex pipeline, but safer and more reliable. Remote AI can answer naturally; local logic protects common CRM actions and fallbacks.

## ADR-022: Assistant Actions Are Vetted Navigation, Not Mutations

**Decision:** Assistant can answer and navigate but should not create/edit/delete/approve/send/release funds.

**Why:** Earlier assistant behavior was too eager to act. Mutation workflows need explicit safe UX and permission checks.

**Tradeoffs:** Users may ask it to perform actions; it should open the correct module/workflow rather than silently changing data.

**Do not grant mutation authority casually.**

## ADR-023: Hostinger Custom PHP/HTML Deployment

**Decision:** Use Hostinger Custom PHP/HTML website option.

**Why:** Laravel is a PHP app. WordPress/Website Builder/Node.js options are not appropriate for this codebase.

**Tradeoffs:** Manual upload requires careful public folder mapping and writable `storage`/`bootstrap/cache`.

## ADR-024: Commit And Push After Each Completed Change

**Decision:** Future agents should commit and push completed changes to `main`.

**Why:** The user explicitly requested commits after every change.

**Tradeoffs:** Requires discipline to verify before committing and avoid bundling unrelated changes.
## ADR-025: Module Access Control Map

**Decision:** Module entry is governed by a single map, `User::MODULE_ACCESS`, enforced by the `module:` route middleware (`EnsureModuleAccess`) and mirrored in the sidebar and dashboard.

- `finance` (invoices, payments, expenses, suppliers, purchases) and `approvals` are **closed to department users**.
- `approvals` is director, super admin and business admin only. Reception does not see the approval queue, but keeps requisition funds release from the requisition screen.
- `clients`, `operations`, `catalog` and `documents` remain open to department users, scoped to their department.
- `business_admin` reads every gated module, may write only inside the `users` module, and cannot delete accounts or assign the super admin role.
- `admin` (users, departments, settings) stays super-admin only.

**Why:** A department-level user could previously read the whole client register, the company approval queue and every document title, and the dashboard showed company finance to every role. Access was enforced only by coarse route groups and a handful of controller checks.

**Tradeoffs:** Two policy axes now exist — `role` for capability and department scope for reach. Any new module must be added to `MODULE_ACCESS` (or deliberately left out for personal modules) or it inherits "open to everyone". Client billing fields (VAT/tax, billing mailbox) and invoice sections are also hidden from department users.

**Related:** ADR-009 still stands — there is no `manager` role. Department heads are a future *level*, not a sixth role.

## ADR-026: Business Analyst Renamed To Business Admin

**Decision:** The role `business_analyst` was renamed to `business_admin`. A migration renames existing rows. The role gained a `users` module grant: business admins can **see all users and add staff accounts**.

**Why:** The business clarified that this seat administers staff accounts; it was never a pure reporting analyst. The old name implied read-only analytics and the role was given no account rights.

**Constraints kept:** business admins cannot delete accounts (deletion stays super-admin only), cannot view or edit a super admin account, and cannot assign the `super_admin` role — adding staff must not become a privilege-escalation path. Every other gated module stays read-only for them.

**Tradeoffs:** Two rename touchpoints: the stored `users.role` value and the `ROLE_*` constant. Any external report or export filtering on `business_analyst` must be updated. ADR-009's "no manager role" is unaffected.

## ADR-027: Full Accounting In Scope

**Decision:** The portal becomes the company's accounting system. Chart of accounts, a double-entry ledger, VAT return generation, bank reconciliation, trial balance, P&L and balance sheet are **in scope**. QuickBooks Desktop data will be migrated in and QuickBooks then retired and uninstalled.

**Why:** The business confirmed that once the CRM is live and the data migrated, QuickBooks will be "rendered useless and possibly uninstalled". VAT returns are prepared and filed internally, so an operational-only system cannot replace QuickBooks.

**Scope boundaries still excluded:** payroll, inventory/stock, fixed assets and depreciation, multi-currency. None has been confirmed as in use; each is a large addition requiring its own decision.

**Supersedes:** the out-of-scope list in ADR-015 (chart of accounts, journals, ledgers, tax filing, trial balance).

**Non-negotiables this creates:**
- Every posted journal entry balances; posted entries are immutable and corrected by reversal.
- A document posts exactly once (idempotent by source).
- VAT figures on the return are read from the ledger, not recomputed from invoices.
- Periods are locked after a VAT return is filed.
- Historical periods are locked after migration.

**Tradeoffs:** This is a system build, not a feature. Ledger and VAT correctness carry legal exposure; the first generated VAT return must be reviewed by whoever signs the filing. The design is specified separately and must be approved before implementation.

**Related:** ADR-014 (VAT fixed at 15%), ADR-015 (amended), ADR-026 (business admin).

## ADR-028: Invoices may be created directly, QuickBooks style

**Status:** Accepted — 2026-11-24

**Context:** Staff are migrating off QuickBooks. In QuickBooks, reception types an invoice from scratch. The CRM previously forced invoices to originate from a completed job card (ADR-016), which added a step the finance user does not expect.

**Decision:**

1. Reception (and other finance-capable roles) may create an invoice directly, without a job card.
2. The invoice form mirrors the QuickBooks Desktop layout: `Customer:Job`, `Class`, `Date`, `Invoice #`, `P.O. No.`, `Terms`, `Due Date`, `Ship Date`, `Ship Via`, `Bill To`, `Ship To`, line grid (`Item / Description / Qty / Rate / Amount`), `Customer Message` and `Memo`.
3. Picking `Terms` recalculates `Due Date` from the invoice date.
4. Job-card and quotation linkage is retained when available, but is no longer required.
5. The printed invoice follows the QuickBooks form layout rather than the previous custom template.

**Consequences:** ADR-016's job-card-gating is relaxed for invoice creation; reception may still generate an invoice from a job card. Totals and VAT continue to come from the shared calculator, and an invoice only posts to the ledger when its status is `Issued`.

**Related:** ADR-016 (relaxed), ADR-014 (VAT), ADR-027 (accounting in scope).

## Unconfirmed / Needs Verification

- Whether production should keep remote MIS AI enabled long term.
- Whether DomPDF should replace current `SimplePdfService`.
- Whether Hostinger will provide cron/SSH or require phpMyAdmin/manual scheduler alternatives.
