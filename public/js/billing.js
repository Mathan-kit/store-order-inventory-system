/**
 * Store Billing POS JavaScript
 * Vanilla JS implementation for POS order management
 */

document.addEventListener('DOMContentLoaded', () => {
    // State
    let catalog = [];
    let lowStockProducts = [];
    let rowIdCounter = 0;
    let emailLookupTimer = null;

    // DOM Elements
    const customerEmailInput = document.getElementById('customerEmail');
    const customerNameInput = document.getElementById('customerName');
    const customerStatusBadge = document.getElementById('customerStatusBadge');
    const emailLookupSpinner = document.getElementById('emailLookupSpinner');

    const itemsTableBody = document.getElementById('itemsTableBody');
    const btnAddRow = document.getElementById('btnAddRow');
    const btnAddRowSecondary = document.getElementById('btnAddRowSecondary');

    const previewSubtotalEl = document.getElementById('previewSubtotal');
    const previewTaxEl = document.getElementById('previewTax');
    const previewGrandTotalEl = document.getElementById('previewGrandTotal');

    const amountGivenInput = document.getElementById('amountGiven');
    const balanceToReturnEl = document.getElementById('balanceToReturn');
    const denominationSection = document.getElementById('denominationSection');
    const denominationChips = document.getElementById('denominationChips');

    const btnGenerateBill = document.getElementById('btnGenerateBill');
    const generateButtonText = document.getElementById('generateButtonText');
    const generateButtonSpinner = document.getElementById('generateButtonSpinner');

    const inlineErrorAlert = document.getElementById('inlineErrorAlert');
    const errorAlertTitle = document.getElementById('errorAlertTitle');
    const errorAlertList = document.getElementById('errorAlertList');

    const lowStockContainer = document.getElementById('lowStockContainer');
    const lowStockCountBadge = document.getElementById('lowStockCountBadge');

    // Receipt Modal Elements
    const receiptModal = document.getElementById('receiptModal');
    const rcptOrderNumber = document.getElementById('rcptOrderNumber');
    const rcptDate = document.getElementById('rcptDate');
    const rcptCustomerName = document.getElementById('rcptCustomerName');
    const rcptCustomerEmail = document.getElementById('rcptCustomerEmail');
    const rcptItemsBody = document.getElementById('rcptItemsBody');
    const rcptSubtotal = document.getElementById('rcptSubtotal');
    const rcptTax = document.getElementById('rcptTax');
    const rcptGrandTotal = document.getElementById('rcptGrandTotal');
    const rcptAmountGiven = document.getElementById('rcptAmountGiven');
    const rcptChangeReturned = document.getElementById('rcptChangeReturned');
    const btnPrintReceipt = document.getElementById('btnPrintReceipt');
    const btnNewOrder = document.getElementById('btnNewOrder');

    // Clock
    const clockEl = document.getElementById('currentClock');
    function updateClock() {
        const now = new Date();
        clockEl.textContent = now.toLocaleTimeString();
    }
    setInterval(updateClock, 1000);
    updateClock();

    // 1. Initial Data Fetching
    async function loadCatalog() {
        try {
            const res = await fetch('/api/products');
            if (res.ok) {
                const json = await res.json();
                catalog = json.data || [];
                // If table is empty, add first row
                if (itemsTableBody.children.length === 0) {
                    addItemRow();
                }
            }
        } catch (e) {
            console.error('Failed to load products catalog:', e);
        }
    }

    async function loadLowStockAlerts() {
        try {
            const res = await fetch('/api/products/low-stock');
            if (res.ok) {
                const json = await res.json();
                lowStockProducts = json.data || [];
                renderLowStockAlerts();
            }
        } catch (e) {
            console.error('Failed to load low-stock alerts:', e);
        }
    }

    function renderLowStockAlerts() {
        lowStockContainer.innerHTML = '';
        lowStockCountBadge.textContent = `${lowStockProducts.length} items`;

        if (lowStockProducts.length === 0) {
            lowStockContainer.innerHTML = '<p class="empty-text">All items are well stocked (above threshold).</p>';
            return;
        }

        lowStockProducts.forEach(item => {
            const div = document.createElement('div');
            div.className = 'low-stock-item';
            div.innerHTML = `
                <div>
                    <div class="low-stock-name">${escapeHtml(item.name)}</div>
                    <div class="low-stock-code">${escapeHtml(item.code)}</div>
                </div>
                <div>
                    <span class="badge ${item.stock_on_hand <= 3 ? 'badge-danger' : 'badge-warning'}">
                        ${item.stock_on_hand} left
                    </span>
                </div>
            `;
            lowStockContainer.appendChild(div);
        });
    }

    // 2. Debounced Customer Email Lookup (400ms)
    customerEmailInput.addEventListener('input', () => {
        clearTimeout(emailLookupTimer);
        const email = customerEmailInput.value.trim();

        if (!email || !email.includes('@')) {
            customerStatusBadge.className = 'badge badge-neutral';
            customerStatusBadge.textContent = 'Guest / New';
            emailLookupSpinner.classList.add('hidden');
            return;
        }

        emailLookupSpinner.classList.remove('hidden');

        emailLookupTimer = setTimeout(async () => {
            try {
                const res = await fetch(`/api/customers/${encodeURIComponent(email)}`);
                if (res.ok) {
                    const data = await res.json();
                    if (data.data && data.data.name) {
                        customerNameInput.value = data.data.name;
                        customerStatusBadge.className = 'badge badge-success';
                        customerStatusBadge.textContent = 'Existing Customer';
                    }
                } else if (res.status === 404) {
                    customerStatusBadge.className = 'badge badge-neutral';
                    customerStatusBadge.textContent = 'New Customer';
                }
            } catch (err) {
                console.error('Error during customer lookup:', err);
            } finally {
                emailLookupSpinner.classList.add('hidden');
            }
        }, 400);
    });

    // 3. Dynamic Product Rows
    function addItemRow() {
        rowIdCounter++;
        const rowId = `item-row-${rowIdCounter}`;

        const tr = document.createElement('tr');
        tr.id = rowId;
        tr.className = 'product-row';

        let optionsHtml = '<option value="">-- Select Product --</option>';
        catalog.forEach(p => {
            const isOutOfStock = p.stock_on_hand <= 0;
            const stockLabel = isOutOfStock ? 'OUT OF STOCK' : `${p.stock_on_hand} in stock`;
            optionsHtml += `<option value="${p.id}" ${isOutOfStock ? 'disabled' : ''}>
                ${escapeHtml(p.name)} (${p.code}) - ₹${parseFloat(p.price).toFixed(2)} [${stockLabel}]
            </option>`;
        });

        tr.innerHTML = `
            <td>
                <select class="form-control select-product" required>
                    ${optionsHtml}
                </select>
            </td>
            <td>
                <span class="stock-badge badge badge-neutral">-</span>
            </td>
            <td>
                <input type="number" class="form-control input-qty" min="1" max="10000" value="1" disabled required>
            </td>
            <td>
                <span class="unit-price-display">₹0.00</span>
            </td>
            <td>
                <span class="tax-rate-display">0%</span>
            </td>
            <td style="text-align: right; font-weight: 600;">
                <span class="line-total-display">₹0.00</span>
            </td>
            <td>
                <button type="button" class="btn-remove-row" title="Remove Item">&times;</button>
            </td>
        `;

        itemsTableBody.appendChild(tr);

        // Bind Row Events
        const selectProduct = tr.querySelector('.select-product');
        const inputQty = tr.querySelector('.input-qty');
        const btnRemove = tr.querySelector('.btn-remove-row');

        selectProduct.addEventListener('change', () => {
            onProductSelected(tr);
            recalculateTotals();
        });

        inputQty.addEventListener('input', () => {
            onQuantityChanged(tr);
            recalculateTotals();
        });

        btnRemove.addEventListener('click', () => {
            tr.remove();
            if (itemsTableBody.children.length === 0) {
                addItemRow();
            }
            recalculateTotals();
        });
    }

    function onProductSelected(tr) {
        const select = tr.querySelector('.select-product');
        const stockBadge = tr.querySelector('.stock-badge');
        const inputQty = tr.querySelector('.input-qty');
        const priceDisplay = tr.querySelector('.unit-price-display');
        const taxDisplay = tr.querySelector('.tax-rate-display');

        const productId = parseInt(select.value, 10);
        const product = catalog.find(p => p.id === productId);

        if (!product) {
            stockBadge.className = 'stock-badge badge badge-neutral';
            stockBadge.textContent = '-';
            inputQty.disabled = true;
            inputQty.value = 1;
            priceDisplay.textContent = '₹0.00';
            taxDisplay.textContent = '0%';
            return;
        }

        // Configure Stock & Constraints
        const available = product.stock_on_hand;
        stockBadge.textContent = `${available} avail`;
        if (available <= 3) {
            stockBadge.className = 'stock-badge badge badge-danger';
        } else if (product.is_low_stock) {
            stockBadge.className = 'stock-badge badge badge-warning';
        } else {
            stockBadge.className = 'stock-badge badge badge-online';
        }

        inputQty.disabled = false;
        inputQty.max = available;
        if (parseInt(inputQty.value, 10) > available) {
            inputQty.value = available;
        }
        if (parseInt(inputQty.value, 10) < 1) {
            inputQty.value = 1;
        }

        priceDisplay.textContent = `₹${parseFloat(product.price).toFixed(2)}`;
        taxDisplay.textContent = `${parseFloat(product.tax_percentage).toFixed(0)}%`;
    }

    function onQuantityChanged(tr) {
        const select = tr.querySelector('.select-product');
        const inputQty = tr.querySelector('.input-qty');
        const productId = parseInt(select.value, 10);
        const product = catalog.find(p => p.id === productId);

        if (!product) return;

        let qty = parseInt(inputQty.value, 10);
        if (isNaN(qty) || qty < 1) {
            inputQty.value = 1;
            qty = 1;
        }

        // Prevent client-side selection beyond available stock
        if (qty > product.stock_on_hand) {
            inputQty.value = product.stock_on_hand;
            alert(`Maximum available stock for ${product.name} is ${product.stock_on_hand}.`);
        }
    }

    btnAddRow.addEventListener('click', addItemRow);
    btnAddRowSecondary.addEventListener('click', addItemRow);

    // 4. Monetary Calculations (Preview)
    function recalculateTotals() {
        let subtotal = 0;
        let totalTax = 0;
        let grandTotal = 0;

        const rows = itemsTableBody.querySelectorAll('.product-row');
        rows.forEach(tr => {
            const select = tr.querySelector('.select-product');
            const inputQty = tr.querySelector('.input-qty');
            const lineTotalDisplay = tr.querySelector('.line-total-display');

            const productId = parseInt(select.value, 10);
            const product = catalog.find(p => p.id === productId);

            if (product && !inputQty.disabled) {
                const qty = parseInt(inputQty.value, 10) || 0;
                const unitPrice = parseFloat(product.price);
                const taxPercent = parseFloat(product.tax_percentage);

                const lineSubtotal = Math.round(qty * unitPrice * 100) / 100;
                const rawTax = lineSubtotal * (taxPercent / 100);
                const lineTax = Math.round(rawTax * 100) / 100; // Half up
                const lineTotal = Math.round((lineSubtotal + lineTax) * 100) / 100;

                subtotal += lineSubtotal;
                totalTax += lineTax;
                grandTotal += lineTotal;

                lineTotalDisplay.textContent = `₹${lineTotal.toFixed(2)}`;
            } else {
                lineTotalDisplay.textContent = '₹0.00';
            }
        });

        previewSubtotalEl.textContent = `₹${subtotal.toFixed(2)}`;
        previewTaxEl.textContent = `₹${totalTax.toFixed(2)}`;
        previewGrandTotalEl.textContent = `₹${grandTotal.toFixed(2)}`;
        previewGrandTotalEl.dataset.grandTotal = grandTotal.toFixed(2);

        updateBalanceAndDenominations(grandTotal);
    }

    // 5. Amount Given, Balance & Greedy INR Denomination Breakdown
    amountGivenInput.addEventListener('input', () => {
        const grandTotal = parseFloat(previewGrandTotalEl.dataset.grandTotal || '0');
        updateBalanceAndDenominations(grandTotal);
    });

    function updateBalanceAndDenominations(grandTotal) {
        const amountGiven = parseFloat(amountGivenInput.value) || 0;
        const balance = amountGiven - grandTotal;

        if (balance >= 0 && grandTotal > 0 && amountGiven > 0) {
            balanceToReturnEl.textContent = `₹${balance.toFixed(2)}`;
            balanceToReturnEl.style.color = '#16a34a';

            computeDenominations(balance);
        } else if (amountGiven > 0 && balance < 0) {
            balanceToReturnEl.textContent = `Short by ₹${Math.abs(balance).toFixed(2)}`;
            balanceToReturnEl.style.color = '#dc2626';
            denominationSection.classList.add('hidden');
        } else {
            balanceToReturnEl.textContent = '₹0.00';
            balanceToReturnEl.style.color = '#16a34a';
            denominationSection.classList.add('hidden');
        }
    }

    function computeDenominations(balance) {
        // Greedy INR denomination breakdown: 500, 200, 100, 50, 20, 10, 5, 2, 1
        const denominations = [500, 200, 100, 50, 20, 10, 5, 2, 1];
        let remaining = Math.floor(balance);
        const breakdown = [];

        for (const denom of denominations) {
            if (remaining >= denom) {
                const count = Math.floor(remaining / denom);
                breakdown.push({ denom, count });
                remaining %= denom;
            }
        }

        denominationChips.innerHTML = '';
        if (breakdown.length > 0) {
            breakdown.forEach(item => {
                const span = document.createElement('span');
                span.className = 'denom-chip';
                span.textContent = `₹${item.denom} × ${item.count}`;
                denominationChips.appendChild(span);
            });
            denominationSection.classList.remove('hidden');
        } else {
            denominationSection.classList.add('hidden');
        }
    }

    // 6. Generate Bill (POST /api/orders)
    btnGenerateBill.addEventListener('click', async () => {
        hideInlineErrors();

        const customerEmail = customerEmailInput.value.trim();
        const customerName = customerNameInput.value.trim();

        if (!customerEmail || !customerName) {
            showInlineError('Missing Customer Details', ['Please provide both customer email and customer name.']);
            return;
        }

        const items = [];
        const rows = itemsTableBody.querySelectorAll('.product-row');
        rows.forEach(tr => {
            const select = tr.querySelector('.select-product');
            const inputQty = tr.querySelector('.input-qty');
            const productId = parseInt(select.value, 10);
            const quantity = parseInt(inputQty.value, 10);

            if (productId && quantity > 0) {
                items.push({ product_id: productId, quantity: quantity });
            }
        });

        if (items.length === 0) {
            showInlineError('Empty Order', ['Please select at least one valid product.']);
            return;
        }

        // Submitting State
        btnGenerateBill.disabled = true;
        generateButtonText.textContent = 'Processing Bill...';
        generateButtonSpinner.classList.remove('hidden');

        try {
            const res = await fetch('/api/orders', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    customer_email: customerEmail,
                    customer_name: customerName,
                    items: items,
                }),
            });

            const data = await res.json();

            if (res.status === 201) {
                // Success: Server returns created Order Resource
                displayReceipt(data.data);
                // Refresh catalog and low stock list
                await loadCatalog();
                await loadLowStockAlerts();
            } else if (res.status === 422) {
                // Validation error or InsufficientStockException
                const errorMessages = [];
                if (data.errors) {
                    if (Array.isArray(data.errors.items)) {
                        data.errors.items.forEach(errItem => {
                            errorMessages.push(`Product #${errItem.product_id} (${errItem.code}): Requested ${errItem.requested}, but only ${errItem.available} available in stock.`);
                        });
                    } else {
                        Object.keys(data.errors).forEach(key => {
                            data.errors[key].forEach(msg => errorMessages.push(msg));
                        });
                    }
                }
                showInlineError(data.message || 'Validation Error', errorMessages);
            } else {
                showInlineError('Order Failed', [data.message || 'An unexpected error occurred while placing the order.']);
            }
        } catch (err) {
            console.error('Order submission error:', err);
            showInlineError('Network Error', ['Could not contact the store billing API. Please try again.']);
        } finally {
            btnGenerateBill.disabled = false;
            generateButtonText.textContent = 'Generate Bill';
            generateButtonSpinner.classList.add('hidden');
        }
    });

    function showInlineError(title, errors) {
        errorAlertTitle.textContent = title;
        errorAlertList.innerHTML = '';
        errors.forEach(err => {
            const li = document.createElement('li');
            li.textContent = err;
            errorAlertList.appendChild(li);
        });
        inlineErrorAlert.classList.remove('hidden');
        inlineErrorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideInlineErrors() {
        inlineErrorAlert.classList.add('hidden');
        errorAlertList.innerHTML = '';
    }

    // 7. Receipt Modal Display
    function displayReceipt(order) {
        rcptOrderNumber.textContent = order.order_number;
        rcptDate.textContent = new Date(order.created_at).toLocaleString();
        rcptCustomerName.textContent = order.customer.name;
        rcptCustomerEmail.textContent = order.customer.email;

        rcptItemsBody.innerHTML = '';
        order.items.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(item.product_name)}</td>
                <td style="text-align: center;">${item.quantity}</td>
                <td style="text-align: right;">₹${parseFloat(item.unit_price).toFixed(2)}</td>
                <td style="text-align: right;">₹${parseFloat(item.line_tax).toFixed(2)}</td>
                <td style="text-align: right; font-weight: bold;">₹${parseFloat(item.line_total).toFixed(2)}</td>
            `;
            rcptItemsBody.appendChild(tr);
        });

        const amountTendered = parseFloat(amountGivenInput.value) || parseFloat(order.grand_total);
        const change = amountTendered - parseFloat(order.grand_total);

        rcptSubtotal.textContent = `₹${parseFloat(order.subtotal).toFixed(2)}`;
        rcptTax.textContent = `₹${parseFloat(order.tax_amount).toFixed(2)}`;
        rcptGrandTotal.textContent = `₹${parseFloat(order.grand_total).toFixed(2)}`;
        rcptAmountGiven.textContent = `₹${amountTendered.toFixed(2)}`;
        rcptChangeReturned.textContent = `₹${(change > 0 ? change : 0).toFixed(2)}`;

        receiptModal.classList.remove('hidden');
    }

    btnPrintReceipt.addEventListener('click', () => {
        window.print();
    });

    btnNewOrder.addEventListener('click', () => {
        receiptModal.classList.add('hidden');
        resetBillingForm();
    });

    function resetBillingForm() {
        customerEmailInput.value = '';
        customerNameInput.value = '';
        customerStatusBadge.className = 'badge badge-neutral';
        customerStatusBadge.textContent = 'Guest / New';
        amountGivenInput.value = '';
        itemsTableBody.innerHTML = '';
        addItemRow();
        recalculateTotals();
        hideInlineErrors();
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // Initialize Page
    loadCatalog();
    loadLowStockAlerts();
});

