# Mallow Technologies - Take-Home Build Prompt

## Assignment: Store Order & Inventory Mini-System
A retail counter application that records customer orders against a product catalog and keeps inventory in sync.

### Core Objectives:
1. Normalized schema for products, customers, orders, and order_items.
2. `POST /api/orders` - creates orders, validates stock, computes subtotal/tax/grand total, deducts stock atomically.
3. `GET /api/customers/{email}/orders` - paginated order history (15 per page).
4. `GET /api/products/low-stock` - products at or below the configured threshold.
5. Queued job simulating order confirmation email (`SendOrderConfirmationJob`).
6. Comprehensive Pest test suite including real multi-process concurrency safety.
7. Vanilla CSS/JS wireframe UI for "Store Billing - New Order".

