# User Stories & Workflow Review — SmartStock ERP
Generated: 2026-06-10

---

## USER STORY REVIEW

```
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│ USER STORY REVIEW                                                                               │
├────────────────────────────────────┬────────────┬────────────────────────────────────────────────┤
│ User Story                         │ Status     │ Gap                                            │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ BO-1  Consolidated dashboard       │ ⚠ PARTIAL  │ DashboardMetricsService queries sales_orders   │
│                                    │            │ (legacy), not sales (POS). No branches_summary │
│                                    │            │ card showing per-branch today_sales/low_stock.  │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ BO-2  Low stock alert → PO         │ ⚠ PARTIAL  │ LowStockDetected event fires in InventoryService│
│                                    │            │ but LowStockNotification class is missing;      │
│                                    │            │ HandleLowStockDetected listener is missing;     │
│                                    │            │ nothing registered in AppServiceProvider.        │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ BO-3  Monthly P&L email            │ ❌ MISSING  │ No SendMonthlyPnlReportJob, no                 │
│                                    │            │ MonthlyPnlReportMail, no                        │
│                                    │            │ SendMonthlyPnlReportsCommand, no scheduler       │
│                                    │            │ registration in bootstrap/app.php.               │
│                                    │            │ P&L PDF export itself exists.                   │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ BM-1  Mobile requisition approve   │ ⚠ PARTIAL  │ Routes + backend logic (approve/reject/revise)  │
│                                    │            │ fully implemented with status guards. Missing:   │
│                                    │            │ resources/views/requisitions/show.blade.php     │
│                                    │            │ (only create/index exist). Notifications are     │
│                                    │            │ database-channel only — no mail with direct      │
│                                    │            │ action links.                                    │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ BM-2  Stock transfer 3 clicks      │ ⚠ PARTIAL  │ StockTransferController@store exists and works. │
│                                    │            │ transfers/create.blade.php is a multi-item form, │
│                                    │            │ not a 3-click flow. No quick-create.blade.php.  │
│                                    │            │ from_branch_id is a required POST field (not     │
│                                    │            │ auto-derived from auth user's branch).           │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ CA-1  Sale < 30s barcode scan      │ ❌ MISSING  │ /pos/products/search and /pos/sales JSON         │
│                                    │            │ endpoints exist. However there is NO POS terminal│
│                                    │            │ Blade view (resources/views/pos/ doesn't exist). │
│                                    │            │ No barcode cache. Returns up to 30 results       │
│                                    │            │ (spec: max 10). No keyboard shortcuts.           │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ CA-2  Change calculation display   │ ❌ MISSING  │ No POS terminal Blade view. POSService does not │
│                                    │            │ accept amount_tendered. Receipts (thermal/A4)    │
│                                    │            │ don't include Amount Paid or Change Given rows.  │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ SK-1  Mobile GRN scanning          │ ⚠ PARTIAL  │ purchases/receive.blade.php exists (57 lines)   │
│                                    │            │ but is a plain desktop form. Missing: barcode    │
│                                    │            │ scan input, auto-focus, ZXing camera support,    │
│                                    │            │ scan-to-highlight product row, mobile-first CSS, │
│                                    │            │ sticky CONFIRM footer.                           │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ SK-2  Expiring in 30 days view     │ ⚠ PARTIAL  │ reports/expiry.blade.php exists with day filter │
│                                    │            │ (7/14/30/60/90) under /reports/expiry route.     │
│                                    │            │ Missing: colour-coded rows (≤7d red/≤14d orange/ │
│                                    │            │ ≤30d yellow), "Flag for Promotion" action button, │
│                                    │            │ category filter, /inventory/expiring-soon URL,   │
│                                    │            │ and storekeeper dashboard widget card.           │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ AC-1  VAT report for TRA           │ ⚠ PARTIAL  │ FinancialController@vatReport exists; TIN shown  │
│                                    │            │ in PDF (tenant->tin ?? config['tin']); PDF export│
│                                    │            │ works; sequential ref generated. Missing:        │
│                                    │            │ breakdown by tax rate (currently aggregate only, │
│                                    │            │ no GROUP BY tax_rate). Ref format is             │
│                                    │            │ VAT-YYYYMMDD-{tenantId}, spec wants VAT-YYYY-NNN.│
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ AC-2  Expense approval timestamp   │ ⚠ PARTIAL  │ approved_by + approved_at columns exist in DB.  │
│                                    │            │ ExpenseController@approve sets both correctly.   │
│                                    │            │ Missing: ExpenseController returns JSON only —   │
│                                    │            │ no Blade detail/show view displaying approval     │
│                                    │            │ metadata. No PDF/Excel expense export with       │
│                                    │            │ audit trail fields.                              │
└────────────────────────────────────┴────────────┴────────────────────────────────────────────────┘
```

---

## WORKFLOW REVIEW

```
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│ WORKFLOW REVIEW                                                                                 │
├────────────────────────────────────┬────────────┬────────────────────────────────────────────────┤
│ Workflow                           │ Status     │ Gap                                            │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ WF-1  Purchase Workflow            │ ⚠ PARTIAL  │ Core path (draft→pending→approved/rejected/     │
│                                    │            │ revision) implemented with status guards and     │
│                                    │            │ audit logs. Missing: (1) resubmit action         │
│                                    │            │ (revision_requested → pending) — no method or   │
│                                    │            │ route exists. (2) "Generate PO from requisition" │
│                                    │            │ action (approved → PO draft). PO→sent→received  │
│                                    │            │ is a separate controller (PurchaseOrderController│
│                                    │            │ + GoodsReceivedNoteController) but there is no  │
│                                    │            │ link that creates a PO from an approved req.    │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ WF-2  POS Sales Workflow           │ ⚠ PARTIAL  │ PosSession model + migration exist. open/close  │
│                                    │            │ routes exist. Missing: (1) openSession does NOT │
│                                    │            │ check for an existing open session before        │
│                                    │            │ creating a new one. (2) pos_sessions migration   │
│                                    │            │ lacks opening_cash, closing_cash, total_sales,  │
│                                    │            │ total_transactions columns. (3) sales table has  │
│                                    │            │ no pos_session_id FK. (4) POST /pos/sales does   │
│                                    │            │ not validate an open session exists. (5)         │
│                                    │            │ closeSession returns a plain 200 {"message":     │
│                                    │            │ "Session closed."} with no shift summary.        │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ WF-3  Stock Transfer Workflow      │ ✅ DONE     │ Full state machine: pending→approved→            │
│                                    │            │ dispatched→received. Status guards on every      │
│                                    │            │ action. stockOut at dispatch, stockIn at receive. │
│                                    │            │ Discrepancy detection + dual-branch notification. │
│                                    │            │ All writes in DB::transaction.                   │
├────────────────────────────────────┼────────────┼────────────────────────────────────────────────┤
│ WF-4  Inventory Audit Workflow     │ ✅ DONE     │ States: initiated→counting→completed→posted.    │
│                                    │            │ Warehouse lock via InventoryService::            │
│                                    │            │ assertNoActiveAudit() called from stockIn,       │
│                                    │            │ stockOut, and adjust. post() applies variance     │
│                                    │            │ adjustments inside DB::transaction and writes     │
│                                    │            │ InventoryMovement records. Audit logs on all     │
│                                    │            │ transitions.                                     │
└────────────────────────────────────┴────────────┴────────────────────────────────────────────────┘
```

---

## SUMMARY

| Status    | Count | Items |
|-----------|-------|-------|
| ✅ DONE   | 2     | WF-3, WF-4 |
| ⚠ PARTIAL | 9     | BO-1, BO-2, BM-1, BM-2, SK-1, SK-2, AC-1, AC-2, WF-1, WF-2 |
| ❌ MISSING | 3     | BO-3, CA-1, CA-2 |

**Items requiring work for Phase 2:**
- BO-1: Fix dashboard to query `sales` (POS) table; add branches_summary widget
- BO-2: Create `LowStockNotification` + `HandleLowStockDetected` listener; register event
- BO-3: Create job, mailable, command, register scheduler
- BM-1: Create `requisitions/show.blade.php` (mobile-first); add mail channel to notifications
- BM-2: Create `transfers/quick-create.blade.php`; auto-set `from_branch_id` from auth user
- CA-1: Create POS terminal Blade view (`resources/views/pos/terminal.blade.php`)
- CA-2: Add `amount_tendered` to POSService; update receipts; add change display in POS UI
- SK-1: Upgrade `purchases/receive.blade.php` with barcode scan + ZXing camera + mobile-first layout
- SK-2: Add colour coding + action button + dashboard widget to expiry view
- AC-1: Add tax-rate breakdown to `computeVat()`
- AC-2: Create expense detail Blade view showing approval timestamps; add PDF/Excel export
- WF-1: Add `resubmit()` method + route; add "Generate PO" action from approved requisition
- WF-2: Block duplicate open sessions; add session columns to migration; link sales to session; shift summary on close
