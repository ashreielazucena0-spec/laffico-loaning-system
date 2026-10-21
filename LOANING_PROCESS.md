# The loan process, step by step

| # | Stage | Who | What the system does |
|---|-------|-----|----------------------|
| 1 | **Membership** | Employee | Client account (acct no. auto-assigned) with contact, occupation, monthly income, co-maker. Portal login is created with a one-time temporary password. Ledgers (Savings, CBU, Mortuary, Insurance) opened as needed. |
| 2 | **Application** | Employee | Pick a loan product. Rate, limits, term cap and fees are fixed by the product (`Config\Loaning::$loanProducts`); staff cannot quote their own. Purpose is required; collateral where the product needs it (Vehicle). The signed Application for Loan is attached. |
| 3 | **Credit evaluation** (automatic) | System | **Hard stops** (application is refused): client has a past-due loan, already has 3 open loans, a duplicate pending application, amount/term outside the product limits, missing collateral/purpose. **Flags** (go to the Manager with the request): no co-maker at or above ₱30,000, monthly dues over 40% of income (or no income on file), restructured-loan history, optional multiple-of-CBU limit. |
| 4 | **Approval** | Manager | The request shows purpose, collateral, net proceeds, debt-service ratio and every flag. A Manager must approve (never the person who filed it) and the signed application must be attached. Rejections need a reason. |
| 5 | **Release** | System | On approval the loan is released: principal goes out; **service fee, insurance and Capital Build Up retention are deducted** (posted as income / the client's Insurance and CBU ledgers). Printable **disbursement voucher** and **amortization schedule / disclosure statement**. |
| 6 | **Repayment** | Employee / client online | Each payment is allocated **penalty first, then interest, then principal**. Official receipt can be printed. Payments cannot exceed what is needed to settle the loan. Clients see balance, penalty, due date and schedule in the portal and can pay online (GCash/Maya/card via PayMongo, or the built-in sandbox for demos). |
| 7 | **Late payment** | System | An installment still unpaid more than 3 days after its due date is charged **3% of the unpaid amount**, and again for every further 30 days. A Manager can waive penalties (reason required, audited). |
| 8 | **Collection** | System | Automatic reminders at 15/30 days, collection letters at 45/60, remedial action at 75. Status: Current (Gold) / Restructured (Bronze) / Past due (Silver). |
| 9 | **Restructuring** | Employee → Manager | Remaining balance is re-spread over a new term after Manager approval; a grace period applies. |
| 10 | **Closure** | System | When principal, interest and penalties are all paid the loan closes (closing date recorded). Cancelling the last payment reopens it. |

Everything is written to the audit trail. A cancelled transaction needs a reason and a supporting document, and
a Manager's approval when requested by an Employee. Release deductions cannot be cancelled separately.

## Where to change the rules
`app/Config/Loaning.php`: products (rate, min/max, max term, fees, collateral), `maxActiveLoans`, `coMakerThreshold`,
`maxDebtServicePct`, `loanToCbuMultiple`, `penaltyRatePct`, `penaltyGraceDays`, reminder days, roles and permissions.
Have your cooperative's policy confirmed before going live; the values shipped are sensible defaults.
