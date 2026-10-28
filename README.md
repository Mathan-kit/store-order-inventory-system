# Store Order & Inventory Mini-System

A production-grade retail counter billing and inventory management application built with **Laravel 12**, **MySQL (InnoDB)**, **Pest PHP**, and vanilla CSS/JavaScript.

Designed for retail POS counters, this system allows operators to record customer orders against a product catalog, perform real-time price & tax calculations, prevent overselling under high concurrency via pessimistic locking, and simulate queued background notifications.

---

## 1. Architecture Overview

This project adheres strictly to production Laravel architecture patterns:

- **Thin Controllers**: Controllers contain zero business logic or query operations. They validate via Form Requests, delegate execution to domain services, and return API Resources or JSON responses.
- **Service Layer (`App\Services\OrderService`)**: Encapsulates transactional order placement, stock verification, atomic deduction, monetary calculations via `bcmath`, duplicate line merging, customer resolution, and queued event triggering.
- **Custom Exceptions (`App\Exceptions\InsufficientStockException`)**: Carries detailed domain context (product ID, name, code, requested quantity, and available stock), rendered directly as HTTP 422 JSON.
- **Strict Monetary Precision**: Decimals stored in database columns (`decimal:10,2` for prices, `decimal:5,2` for taxes, `decimal:12,2` for totals). All line and order totals are computed using `bcmath` and half-up rounding (`PHP_ROUND_HALF_UP`) to prevent floating-point drift.
- **Asynchronous Queue Jobs (`App\Jobs\SendOrderConfirmationJob`)**: Simulates asynchronous email confirmation logging, tied to database transactions with `$afterCommit` guarantees.
- **Enums (`App\Enums\OrderStatus`)**: Backed string enum (`pending`, `confirmed`, `cancelled`) cast on Eloquent models.
- **Frontend**: Lightweight, dependency-free vanilla CSS & JavaScript single-page interface matching the "Store Billing - New Order" wireframe with 400ms debounced customer lookup, dynamic row management, client-side stock safeguards, live greedy denomination calculation, and printable receipts.

---

## 2. Environment & System Requirements

- **PHP**: `^8.2` (tested on PHP 8.2+) with `pdo_mysql`, `bcmath`, and `ctype` extensions.
- **Composer**: `^2.2+`
- **Database**: **MySQL 8.0+** or **MariaDB 10.4+** (InnoDB storage engine is mandatory for row-level pessimistic locking).
- **Queue Connection**: `database`

---

## 3. Installation & Setup

### 3.1. Clone & Install Dependencies

```bash
cd store-order-system
composer install
```

### 3.2. Create MySQL Databases

Create both the primary and test databases:

```sql
CREATE DATABASE IF NOT EXISTS store_orders;
CREATE DATABASE IF NOT EXISTS store_orders_test;
```

### 3.3. Environment Configuration

Copy `.env.example` to `.env` and verify your MySQL credentials:

```bash
cp .env.example .env
php artisan key:generate
```

Verify your `.env` contains:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=store_orders
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
INVENTORY_LOW_STOCK_THRESHOLD=10
```

### 3.4. Run Migrations & Seed Data

Run fresh database migrations and populate the seed catalog (~12 retail products including Bread, Milk 1L, Eggs, Colgate Toothpaste, Parle-G Biscuit, and ~5 customers including `thomas@example.com`):

```bash
php artisan migrate:fresh --seed
```

### 3.5. Start Background Queue Worker

Open a dedicated terminal to process queued confirmation jobs:

```bash
php artisan queue:work --queue=default --tries=3
```

### 3.6. Run Local Development Server

```bash
php artisan serve
```

Visit the POS interface in your browser at: **`http://localhost:8000`**

---

## 4. API Documentation

### 4.1. Create Order

- **Endpoint**: `POST /api/orders`
- **Headers**: `Content-Type: application/json`, `Accept: application/json`

#### Validation Rules (`StoreOrderRequest`)
- `customer_email`: `required|email:rfc|max:255`
- `customer_name`: `required|string|max:255`
- `items`: `required|array|min:1|max:50`
- `items.*.product_id`: `required|integer|exists:products,id`
- `items.*.quantity`: `required|integer|min:1|max:10000`

#### Success Sample (HTTP 201 Created)
```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "customer_email": "thomas@example.com",
    "customer_name": "Thomas Mathew",
    "items": [
      {"product_id": 1, "quantity": 2},
      {"product_id": 4, "quantity": 1}
    ]
  }'
```

```json
{
  "data": {
    "id": 1,
    "order_number": "ORD-20261028-A1B2C3",
    "status": "confirmed",
    "subtotal": "190.00",
    "tax_amount": "23.80",
    "grand_total": "213.80",
    "customer": {
      "id": 1,
      "name": "Thomas Mathew",
      "email": "thomas@example.com",
      "created_at": "2026-10-28T15:30:00.000000Z",
      "updated_at": "2026-10-28T15:30:00.000000Z"
    },
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "product_name": "Bread",
        "product_code": "BRD-001",
        "quantity": 2,
        "unit_price": "40.00",
        "tax_percentage": "5.00",
        "line_subtotal": "80.00",
        "line_tax": "4.00",
        "line_total": "84.00"
      },
      {
        "id": 2,
        "product_id": 4,
        "product_name": "Colgate Toothpaste",
        "product_code": "CLG-001",
        "quantity": 1,
        "unit_price": "110.00",
        "tax_percentage": "18.00",
        "line_subtotal": "110.00",
        "line_tax": "19.80",
        "line_total": "129.80"
      }
    ],
    "created_at": "2026-10-28T15:30:00.000000Z",
    "updated_at": "2026-10-28T15:30:00.000000Z"
  }
}
```

#### Validation Error Sample (HTTP 422 Unprocessable Content)
```json
{
  "message": "The customer email field must be a valid email address.",
  "errors": {
    "customer_email": [
      "The customer email field must be a valid email address."
    ]
  }
}
```

#### Insufficient Stock Error Sample (HTTP 422 Unprocessable Content)
```json
{
  "message": "Insufficient stock for Bread",
  "errors": {
    "items": [
      {
        "product_id": 1,
        "code": "BRD-001",
        "requested": 10,
        "available": 4
      }
    ]
  }
}
```

---

### 4.2. Customer Order History

- **Endpoint**: `GET /api/customers/{email}/orders`
- **Query Params**: `page` (optional, default 1)
- **Pagination**: 15 orders per page, newest first (`latest('created_at')`).

```bash
curl -X GET http://localhost:8000/api/customers/thomas@example.com/orders \
  -H "Accept: application/json"
```

```json
{
  "data": [
    {
      "id": 1,
      "order_number": "ORD-20261028-A1B2C3",
      "status": "confirmed",
      "subtotal": "190.00",
      "tax_amount": "23.80",
      "grand_total": "213.80",
      "customer": {
        "id": 1,
        "name": "Thomas Mathew",
        "email": "thomas@example.com"
      },
      "items": [ ... ]
    }
  ],
  "links": { ... },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

If the customer is not found:
```json
{
  "message": "Customer not found."
}
```
*(HTTP 404)*

---

### 4.3. Customer Profile Lookup (Auto-Fill)

- **Endpoint**: `GET /api/customers/{email}`

```bash
curl -X GET http://localhost:8000/api/customers/thomas@example.com \
  -H "Accept: application/json"
```

```json
{
  "data": {
    "name": "Thomas Mathew",
    "email": "thomas@example.com"
  }
}
```

---

### 4.4. Product Catalog

- **Endpoint**: `GET /api/products`

Returns the full product catalog ordered alphabetically by name for counter selection.

---

### 4.5. Low Stock Products

- **Endpoint**: `GET /api/products/low-stock`

Returns all products whose `stock_on_hand` is **at or below** the configured threshold (`config('inventory.low_stock_threshold')`), ordered ascending by stock quantity.

```bash
curl -X GET http://localhost:8000/api/products/low-stock \
  -H "Accept: application/json"
```

```json
{
  "data": [
    {
      "id": 3,
      "name": "Eggs",
      "code": "EGG-001",
      "price": "80.00",
      "tax_percentage": "5.00",
      "stock_on_hand": 2,
      "is_low_stock": true
    },
    {
      "id": 1,
      "name": "Bread",
      "code": "BRD-001",
      "price": "40.00",
      "tax_percentage": "5.00",
      "stock_on_hand": 4,
      "is_low_stock": true
    },
    {
      "id": 2,
      "name": "Milk 1L",
      "code": "MLK-001",
      "price": "65.00",
      "tax_percentage": "5.00",
      "stock_on_hand": 9,
      "is_low_stock": true
    }
  ]
}
```

---

## 5. Concurrency Strategy & Pessimistic Locking

### 5.1. Concurrency Architecture
Inventory decrement in retail counter environments suffers from high contention on hot rows (e.g. promotional items or the last item in stock). To guarantee that overselling is physically impossible, the application employs **Pessimistic Locking (`SELECT ... FOR UPDATE`)** inside an ACID database transaction:

```php
DB::transaction(function () use ($customer, $items) {
    // 1. Merge duplicate product IDs in the request by summing quantities
    ...
    // 2. Sort unique product IDs ascending to prevent cross-transaction deadlocks
    $sortedProductIds = array_keys($mergedQuantities);
    sort($sortedProductIds, SORT_NUMERIC);

    // 3. Acquire exclusive row-level locks in deterministic order
    $products = Product::whereIn('id', $sortedProductIds)
        ->orderBy('id', 'asc')
        ->lockForUpdate()
        ->get()
        ->keyBy('id');

    // 4. Verify stock_on_hand >= quantity for EVERY line item before deducting
    ...
    // 5. Deduct inventory & persist Order and OrderItems
    ...
    // 6. Dispatch background job with afterCommit guarantees
    SendOrderConfirmationJob::dispatch($order->id)->afterCommit();
});
```

### 5.2. Deadlock Prevention via Sorted Lock Ordering
When multiple concurrent requests attempt to lock multiple rows (e.g., Transaction A locks Product 1 then Product 2; Transaction B locks Product 2 then Product 1), an engine deadlock occurs.
By sorting the target product IDs in ascending numerical order (`sort($sortedProductIds, SORT_NUMERIC)`) prior to locking, all concurrent transactions request locks in the exact same sequence, eliminating circular lock wait graphs.

### 5.3. Why Pessimistic Locking over Optimistic Locking?
1. **High Contention on Hot Rows**: In retail checkout, multiple registers compete for the same fast-selling stock. Optimistic locking (e.g., version checking) results in frequent transaction conflicts, high abort rates, and cascading retries.
2. **Correctness over Raw Throughput**: Over-allocating stock at a checkout counter is a critical business failure. Pessimistic locking guarantees serialization on contested inventory.
3. **Simpler Failure Semantics**: The failing transaction encounters an immediate, clean rejection (`InsufficientStockException`) rather than retrying stale reads.

### 5.4. Transactional Job Safety (`afterCommit`)
The queued confirmation job (`SendOrderConfirmationJob`) is configured with `$afterCommit` behavior. The queue worker never pulls the job from the database queue before the parent transaction commits, preventing race conditions where the worker attempts to read uncommitted orders.

---

## 6. Low-Stock Threshold Configuration

The low-stock threshold is configured through `config/inventory.php` using the `INVENTORY_LOW_STOCK_THRESHOLD` environment variable:

```php
// config/inventory.php
return [
    'low_stock_threshold' => (int) env('INVENTORY_LOW_STOCK_THRESHOLD', 10),
];
```

The application strictly queries `config('inventory.low_stock_threshold')` and never invokes `env()` directly in domain logic.

---

## 7. Running Tests

The test suite is written in **Pest PHP** covering Feature and Unit tests:

```bash
php artisan test
```

### Concurrency Test Requirements
- **Real Multi-Process Testing (`Tests\Feature\ConcurrentOrderStockTest`)**: Spawns two independent parallel OS processes (`Symfony\Component\Process\Process`) executing the artisan command `orders:place` targeting a single remaining stock unit (`stock_on_hand = 1`).
- **Why MySQL is Required**: SQLite does not implement true `lockForUpdate()` row locks (SQLite uses database-level locks and ignores row locking clauses). The concurrency test explicitly asserts MySQL / InnoDB driver availability and skips itself if run on SQLite.
- **Dedicated Test Database**: Tests run against `store_orders_test` configured in `.env.testing` and `phpunit.xml`.

Run only the concurrency test:
```bash
php artisan test tests/Feature/ConcurrentOrderStockTest.php
```

---

## 8. Assumptions & Decisions

1. **Low-Stock Rule is "At or Below"**: A product is classified as low stock when `stock_on_hand <= threshold`. With the default threshold of 10, items with stock 10, 9, 4, 2, etc. are included in alerts.
2. **Guest Checkout & Customer Creation**: Customers are identified by their email address. If an email does not exist, a new customer record is automatically created (`Customer::firstOrCreate`).
3. **Case-Insensitive Email Matching**: Emails are trimmed and lowercased (`strtolower(trim($email))`) to prevent duplicate customer records due to casing differences (`Thomas@Example.COM` vs `thomas@example.com`).
4. **Customer Name Updates**: If an existing customer places an order with a newly specified name, the customer's `name` attribute is updated in place.
5. **Tax & Monetary Rounding**: Tax is calculated per line item and rounded to 2 decimal places using half-up rounding (`round($rawTax, 2, PHP_ROUND_HALF_UP)`). Order-level totals (`subtotal`, `tax_amount`, `grand_total`) are the exact sum of individual line items (`order totals = sum of lines`) to avoid rounding inconsistencies.
6. **Tax-Exclusive Catalog Prices**: Catalog product prices are treated as tax-exclusive base prices. Tax is computed and added on top per line item according to the product's `tax_percentage`.
7. **Duplicate Request Lines Merged**: If an order request contains duplicate entries for the same `product_id`, their quantities are merged and summed prior to locking and stock checking. The order will persist a single consolidated line item.
8. **Email Confirmation is a Logged Simulation**: In line with requirements, no SMTP delivery or PDF generation takes place. `SendOrderConfirmationJob` writes structured JSON context to the application log (`Log::info`).
9. **Amount Tendered / Change is UI-Only**: "Amount Given by Customer" and the greedy denomination breakdown are real-time cashier UI conveniences and are not stored in the database schema.
10. **Illustrative Wireframe Numbers**: Product prices and sample totals in the wireframe were treated as illustrative; the database catalog values and server-calculated totals serve as the authoritative source of truth.
11. **Currency**: All amounts are denominated in Indian Rupees (INR, ₹).
12. **Default Order Status**: New orders default to status `confirmed` (`App\Enums\OrderStatus::Confirmed`).
13. **Prompt Log Location**: Detailed prompt instructions and specifications are preserved in `/prompts` (`prompts/takehome_build_prompt.md`).
