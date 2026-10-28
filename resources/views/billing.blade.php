<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Store Billing - New Order</title>
    <link rel="stylesheet" href="{{ asset('css/billing.css') }}">
</head>
<body>
    <header class="app-header">
        <div class="header-container">
            <div class="brand">
                <span class="brand-icon">🛒</span>
                <div>
                    <h1 class="brand-title">Store Billing</h1>
                    <p class="brand-subtitle">Retail POS &amp; Inventory Management</p>
                </div>
            </div>
            <div class="header-meta">
                <span class="badge badge-online">● POS Counter #1</span>
                <span id="currentClock" class="clock-display">--:--:--</span>
            </div>
        </div>
    </header>

    <main class="app-main">
        <div class="billing-grid">
            <!-- Left Column: Customer & Items -->
            <section class="left-column">
                <!-- Customer Card -->
                <div class="card customer-card">
                    <div class="card-header">
                        <h2 class="card-title">Customer Information</h2>
                        <span id="customerStatusBadge" class="badge badge-neutral">Guest / New</span>
                    </div>
                    <div class="card-body form-row">
                        <div class="form-group flex-1">
                            <label for="customerEmail">Customer Email <span class="required">*</span></label>
                            <div class="input-with-indicator">
                                <input type="email" id="customerEmail" class="form-control" placeholder="e.g. thomas@example.com" autocomplete="off" required>
                                <span id="emailLookupSpinner" class="input-spinner hidden">⟳</span>
                            </div>
                            <small class="form-hint">Type email to auto-fill existing customer records (400ms debounce).</small>
                        </div>
                        <div class="form-group flex-1">
                            <label for="customerName">Customer Name <span class="required">*</span></label>
                            <input type="text" id="customerName" class="form-control" placeholder="e.g. Thomas Mathew" required>
                            <small class="form-hint">Name will be updated if changed for existing customer.</small>
                        </div>
                    </div>
                </div>

                <!-- Products Card -->
                <div class="card products-card">
                    <div class="card-header">
                        <h2 class="card-title">Order Items</h2>
                        <button type="button" id="btnAddRow" class="btn btn-secondary btn-sm">+ Add Product</button>
                    </div>
                    <div class="card-body">
                        <!-- Server-side / validation inline error alert -->
                        <div id="inlineErrorAlert" class="alert alert-danger hidden" role="alert">
                            <div class="alert-icon">⚠</div>
                            <div class="alert-content">
                                <strong id="errorAlertTitle">Error placing order</strong>
                                <ul id="errorAlertList"></ul>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 32%;">Product</th>
                                        <th style="width: 12%;">Stock</th>
                                        <th style="width: 14%;">Qty</th>
                                        <th style="width: 14%;">Unit Price (₹)</th>
                                        <th style="width: 10%;">Tax</th>
                                        <th style="width: 14%; text-align: right;">Total (₹)</th>
                                        <th style="width: 4%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody">
                                    <!-- Dynamic Rows Rendered by JS -->
                                </tbody>
                            </table>
                        </div>

                        <div class="table-actions">
                            <button type="button" id="btnAddRowSecondary" class="btn btn-outline btn-sm">+ Add Another Item</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Right Column: Low Stock Alerts & Payment Box -->
            <aside class="right-column">
                <!-- Low Stock Alert Card -->
                <div class="card low-stock-card">
                    <div class="card-header">
                        <h2 class="card-title">Low Stock Alerts</h2>
                        <span id="lowStockCountBadge" class="badge badge-warning">0 items</span>
                    </div>
                    <div class="card-body">
                        <div id="lowStockContainer" class="low-stock-list">
                            <p class="empty-text">Loading inventory alerts...</p>
                        </div>
                    </div>
                </div>

                <!-- Payment Box Card -->
                <div class="card payment-card">
                    <div class="card-header">
                        <h2 class="card-title">Payment Summary</h2>
                    </div>
                    <div class="card-body">
                        <div class="summary-line">
                            <span>Subtotal:</span>
                            <span id="previewSubtotal">₹0.00</span>
                        </div>
                        <div class="summary-line">
                            <span>Tax Amount:</span>
                            <span id="previewTax">₹0.00</span>
                        </div>
                        <div class="summary-line grand-total-line">
                            <span>Grand Total:</span>
                            <span id="previewGrandTotal" class="grand-total-amount">₹0.00</span>
                        </div>

                        <hr class="divider">

                        <div class="form-group">
                            <label for="amountGiven">Amount Given by Customer (₹)</label>
                            <input type="number" id="amountGiven" class="form-control form-control-lg" step="0.01" min="0" placeholder="0.00">
                        </div>

                        <div class="summary-line balance-line">
                            <span>Balance to Return:</span>
                            <span id="balanceToReturn" class="balance-amount">₹0.00</span>
                        </div>

                        <div id="denominationSection" class="denomination-section hidden">
                            <label class="denomination-title">Suggested Return Denominations:</label>
                            <div id="denominationChips" class="denomination-chips">
                                <!-- Computed greedy denomination breakdown -->
                            </div>
                        </div>

                        <div class="action-section">
                            <button type="button" id="btnGenerateBill" class="btn btn-primary btn-block btn-lg">
                                <span id="generateButtonText">Generate Bill</span>
                                <span id="generateButtonSpinner" class="button-spinner hidden">⟳</span>
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <!-- Receipt Modal -->
    <div id="receiptModal" class="modal-backdrop hidden">
        <div class="modal-dialog">
            <div class="receipt-paper" id="receiptContent">
                <div class="receipt-header">
                    <h2 class="receipt-store-name">STORE BILLING COUNTER</h2>
                    <p class="receipt-store-address">Main Street Retail Outlet, Counter #1</p>
                    <p class="receipt-title">TAX INVOICE / CASH RECEIPT</p>
                </div>

                <div class="receipt-meta">
                    <div><strong>Order #:</strong> <span id="rcptOrderNumber"></span></div>
                    <div><strong>Date:</strong> <span id="rcptDate"></span></div>
                    <div><strong>Customer:</strong> <span id="rcptCustomerName"></span></div>
                    <div><strong>Email:</strong> <span id="rcptCustomerEmail"></span></div>
                </div>

                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Item</th>
                            <th style="text-align: center;">Qty</th>
                            <th style="text-align: right;">Price</th>
                            <th style="text-align: right;">Tax</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody id="rcptItemsBody">
                        <!-- Items rendered by JS -->
                    </tbody>
                </table>

                <div class="receipt-totals">
                    <div class="receipt-line"><span>Subtotal:</span> <span id="rcptSubtotal"></span></div>
                    <div class="receipt-line"><span>Tax Amount:</span> <span id="rcptTax"></span></div>
                    <div class="receipt-line receipt-grand"><span>Grand Total:</span> <span id="rcptGrandTotal"></span></div>
                    <div class="receipt-line"><span>Amount Tendered:</span> <span id="rcptAmountGiven"></span></div>
                    <div class="receipt-line"><span>Change Returned:</span> <span id="rcptChangeReturned"></span></div>
                </div>

                <div class="receipt-notice">
                    <p class="notice-badge">✉ Order confirmation email simulation queued for background dispatch.</p>
                    <p class="thank-you">Thank you for shopping with us!</p>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" id="btnPrintReceipt" class="btn btn-secondary">Print Receipt</button>
                <button type="button" id="btnNewOrder" class="btn btn-primary">Start New Order</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/billing.js') }}"></script>
</body>
</html>

