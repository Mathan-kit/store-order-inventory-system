# Prompt 01: Full Build (Store Order & Inventory Mini-System)

| | |
|---|---|
| **Project** | Store Order & Inventory Mini-System (Mallow Technologies take-home) |
| **AI tool** | Claude |
| **Stack** | Laravel, MySQL, Pest, Blade + vanilla JS |
| **Approach** | One structured prompt that covers the whole build in ordered stages |

## Prompting approach

I used a single structured, stage-ordered prompt instead of many small ones. It combines these frameworks:

| Framework | How I applied it |
|---|---|
| **RTCF** (Role, Task, Context, Format) | Described the project state, the task, and the exact way I want files delivered |
| **RISEN** (Role, Instructions, Steps, End goal, Narrowing) | Fixed the build order, set a final checklist, and constrained packages and code style |
| **Specification-driven prompting** | Gave the exact schema, endpoints, validation rules and error JSON so nothing is guessed |
| **Self-verification** | Asked Claude to confirm migrations, tests, stock integrity and job dispatch before finishing |

I reviewed and edited all generated code before committing it.

---

## The prompt

Hi Claude, I'm building a take-home assignment for Mallow Technologies: a "Store Order & Inventory Mini-System" in Laravel. I want you to build the whole thing for me, working like a senior Laravel developer would.

### Where I am right now

I've already created the Laravel project (`composer create-project laravel/laravel store-order-system`), installed Pest with the Laravel plugin, and set up my `.env` for MySQL (database `store_orders`, 127.0.0.1:3306, root, no password, `APP_NAME="Store Billing"`). Please check `composer.json` for the exact Laravel version and use idioms that fit it. Everything else is still to do, including `php artisan install:api`, the queue tables and the config file.

### What the app should do

A retail counter records customer orders against a product catalog and keeps stock in sync.

### Database (normalized, MySQL/InnoDB)

- **customers**: id, name, email (unique), timestamps
- **products**: id, name, code (unique), price decimal(10,2), tax_percentage decimal(5,2), stock_on_hand unsigned integer (indexed), timestamps
- **orders**: id, order_number (unique), customer_id FK, subtotal, tax_amount, grand_total, status (default `confirmed`), timestamps, plus an index on (customer_id, created_at)
- **order_items**: id, order_id FK (cascade on delete), product_id FK, quantity, unit_price, tax_percentage, line_subtotal, line_tax, line_total, timestamps. These columns are a snapshot of price and tax at purchase time.

Please add Eloquent models with relationships, casts and fillable, a `Product::isLowStock()` helper, an `OrderStatus` enum (Pending, Confirmed, Cancelled), and factories for Customer and Product.

### API

1. **POST /api/orders** takes `customer_email`, `customer_name` and `items[{product_id, quantity}]`. It validates stock, computes subtotal, tax and grand total, deducts stock, and returns the created order with a 201. Use a `StoreOrderRequest` form request (email rfc, items min 1 max 50, product must exist, quantity integer 1 to 10000).
2. **GET /api/customers/{email}/orders** returns the customer's order history, paginated 15 per page, newest first, with items. Return a JSON 404 if the customer doesn't exist.
3. **GET /api/customers/{email}** returns name and email, or a 404. My UI uses it to auto-fill the customer name.
4. **GET /api/products** returns the product catalog for the billing dropdown.
5. **GET /api/products/low-stock** returns products with `stock_on_hand` at or below a configurable threshold, lowest stock first. Put the threshold in `config/inventory.php` (env `INVENTORY_LOW_STOCK_THRESHOLD`, default 10 so Bread, Milk and Eggs show up). Only read it via `config()`, never `env()` in application code.

### Architecture rules

- Thin controllers: they only validate, call a service and return a Resource.
- All business logic goes in `App\Services\OrderService::createOrder(array $customer, array $items): Order`.
- A custom `App\Exceptions\InsufficientStockException` carrying product id, name, code, requested and available. Render it as a 422 JSON response and register the rendering in `bootstrap/app.php`. For example:

```json
{
  "message": "Insufficient stock for Bread",
  "errors": {
    "items": [
      { "product_id": 1, "code": "BRD-001", "requested": 3, "available": 1 }
    ]
  }
}
```

- API Resources for Order, OrderItem, Product and Customer.
- Use bcmath or integer cents for money so there is no float drift. Round tax per line to 2 decimals (half-up) and make the order totals the sum of the lines.

### Concurrency safety (the most important part)

If two orders hit the same product at the same time and only one unit is left, exactly one must succeed and the other must fail cleanly, with no overselling. In `OrderService`, inside `DB::transaction()`:

1. Merge duplicate `product_id`s in the request (sum the quantities) before locking.
2. Sort the unique product ids ascending and load them with `whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()`. A consistent lock order avoids deadlocks on multi-product orders.
3. Check stock for every line. On the first shortfall, throw `InsufficientStockException` so the whole transaction rolls back and nothing is partially deducted.
4. Deduct stock, create the Order and OrderItems, then dispatch the job.

Add a short code comment explaining why I chose pessimistic locking over optimistic locking. For the customer, use `firstOrCreate` on the trimmed, lowercased email, and update the name if it is different.

### Queued job

`SendOrderConfirmationJob` (implements `ShouldQueue`). It takes the order ID, reloads the order with customer and items, and simulates the confirmation email with a structured `Log::info` (order number, customer email, grand total, item count). Set `tries = 3` with a backoff, add a `failed()` method that logs, and make it run after commit so it never fires for a rolled-back order. Use `QUEUE_CONNECTION=database`.

### Seed data

About 12 realistic Indian retail products with INR prices and tax of 5, 12 or 18 percent. Include Bread (stock 4), Milk 1L (stock 9), Eggs (stock 2), Colgate Toothpaste and Parle-G Biscuit, plus some well-stocked items. Also seed about 5 customers, including `thomas@example.com`.

### Tests (Pest)

**Feature/OrderCreationTest**
- creates the order and deducts stock
- exact totals with mixed tax rates and a rounding case
- insufficient stock returns 422, stock is unchanged and no order is created
- a multi-product order where the second product fails does not deduct the first
- duplicate lines are merged and checked against the combined quantity
- the job is dispatched on success and not on failure (`Queue::fake`)
- validation errors (missing fields, zero or negative quantity, empty items, unknown product)
- a customer is reused case-insensitively and their name is updated, with no duplicate rows
- the order snapshot keeps the old `unit_price` after the product price changes

**Feature/CustomerOrderHistoryTest**: history by email, pagination, 404 for unknown customers, isolation between customers.

**Feature/LowStockEndpointTest**: only products at or below the threshold, honours a config override, sorted by stock.

**Unit/OrderServiceTest**: two sequential orders on a product with stock 1, where the second throws `InsufficientStockException` with the right requested and available values.

**Feature/ConcurrentOrderStockTest**: a real concurrency test.
- Do not use `RefreshDatabase` here, because its wrapping transaction hides data from other processes. Use `DatabaseMigrations` instead, and skip the test when the driver is not MySQL.
- Create a small dev-only artisan command (`orders:place {email} {product_id} {qty}`) that calls `OrderService` and prints JSON.
- Start two of them in parallel with Symfony Process on a product with stock 1, then assert that exactly one succeeded, one failed on stock, the final stock is 0 and only one order exists. Repeat the race a few times.

Set up a separate MySQL test database called `store_orders_test` in `phpunit.xml` / `.env.testing`.

### UI

`GET /` renders `resources/views/billing.blade.php` with `public/css/billing.css` and `public/js/billing.js`. It should match the "Store Billing: New Order" wireframe, in plain Blade, vanilla CSS and vanilla JS with no CDN or heavy libraries:

- Dark header and a customer section (email input with a 400ms debounced lookup that auto-fills the name, plus a name input).
- Products table with product dropdown, qty, unit price and line total, a "+ Add Product" button and a remove-row button. Stop the quantity going above available stock on the client side.
- Low stock alert card on the right, loaded from the API and refreshed after each bill.
- Payment box with subtotal, tax, grand total, "Amount Given by Customer" and a live "Balance to Return" with an INR denomination breakdown (500, 200, 100, 50, 20, 10, 5, 2, 1, greedy).
- "Generate Bill" posts to `/api/orders` and shows a printable receipt modal plus a note that the confirmation was queued. It also shows validation and insufficient-stock errors inline.

The UI totals are only previews, so the server response is the source of truth.

### README.md

It should cover: overview and architecture, setup (PHP and Composer versions, creating the MySQL database, `.env`, `migrate --seed`, `queue:work`, serve), API docs with cURL and JSON samples (success, validation error, insufficient stock), the concurrency strategy, the low-stock config, how to run the tests and why the concurrency test needs MySQL, and an **Assumptions** section. The assumptions I want listed:

- low-stock means "at or below" the threshold
- guest checkout creates the customer by email
- email is case-insensitive
- the name is updated if it is different
- tax is rounded per line, half-up
- prices are tax-exclusive
- duplicate lines are merged
- no PDF is generated (the confirmation is a logged simulation)
- amount given and balance are UI-only and not saved
- wireframe numbers are illustrative
- currency is INR
- the prompt log is in `/prompts`

### How I want you to work

- Go in this order: setup (`install:api`, queue tables, `config/inventory.php`, `.env.example` and `.env.testing`), then migrations, models, enum, factories and seeders, then the exception, `OrderService` and job, then the form request, controllers, resources, routes and exception rendering, then the artisan command, tests, UI and README.
- Every file must be complete. No placeholders, TODOs or "..." and no extra packages beyond Laravel and Pest. Follow PSR-12, use strict types and return types, and run `./vendor/bin/pint` at the end.
- If you can touch my filesystem, create the files directly, run `php artisan migrate:fresh --seed` and `php artisan test`, and fix any failures. If you can't, give each file as `### path/to/file` followed by one code block with the full contents, with only a one-line reason between files. If you are running out of space, stop at a file boundary and write `CONTINUE FROM: <next file>` so I can reply "continue".

### Before you finish

Please confirm each of these:

- [ ] `migrate:fresh --seed` runs clean
- [ ] all tests pass, including the concurrency test on MySQL
- [ ] stock never goes negative
- [ ] the job only dispatches after commit and never on failure
- [ ] controllers contain no business logic
- [ ] all errors return consistent JSON

Then give me a 6-line summary, the exact commands to run the demo, and anything you weren't sure about.

---

## Follow-up prompts

Screenshots of any follow-ups (for example "continue" or bug-fix requests) are saved next to this file in `/prompts`.
