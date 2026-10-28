<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Jobs\SendOrderConfirmationJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OrderService
{
    /**
     * Create a customer order atomically with pessimistic stock locking.
     *
     * @param  array{name: string, email: string}  $customer
     * @param  list<array{product_id: int, quantity: int}>  $items
     *
     * @throws InsufficientStockException
     */
    public function createOrder(array $customer, array $items): Order
    {
        return DB::transaction(function () use ($customer, $items): Order {
            // 1. Merge duplicate product IDs in the request by summing quantities
            $mergedQuantities = [];
            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];

                $mergedQuantities[$productId] = ($mergedQuantities[$productId] ?? 0) + $quantity;
            }

            // 2. Sort unique product IDs in ascending order to prevent deadlocks across concurrent multi-product orders
            $sortedProductIds = array_keys($mergedQuantities);
            sort($sortedProductIds, SORT_NUMERIC);

            // Pessimistic locking (lockForUpdate) with ascending ID ordering prevents deadlocks on multi-product orders.
            // Chosen over optimistic locking because retail counter inventory suffers high contention on hot rows;
            // pessimistic locking guarantees strict correctness over throughput and provides clean rollback semantics.
            $products = Product::whereIn('id', $sortedProductIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($sortedProductIds as $productId) {
                if (! $products->has($productId)) {
                    throw new InvalidArgumentException("Product with ID {$productId} does not exist.");
                }
            }

            // 3. Verify stock availability for every line item prior to deducting any inventory
            foreach ($mergedQuantities as $productId => $quantity) {
                /** @var Product $product */
                $product = $products->get($productId);

                if ($product->stock_on_hand < $quantity) {
                    throw new InsufficientStockException(
                        productId: $product->id,
                        productName: $product->name,
                        productCode: $product->code,
                        requested: $quantity,
                        available: $product->stock_on_hand,
                    );
                }
            }

            // 4. Resolve or update customer (case-insensitive email matching)
            $email = strtolower(trim((string) $customer['email']));
            $name = trim((string) $customer['name']);

            $customerRecord = Customer::firstOrCreate(
                ['email' => $email],
                ['name' => $name]
            );

            if ($customerRecord->name !== $name) {
                $customerRecord->update(['name' => $name]);
            }

            // 5. Deduct stock, calculate monetary snapshots (bcmath / half-up rounding), and assemble line items
            $subtotal = '0.00';
            $taxAmount = '0.00';
            $grandTotal = '0.00';
            $lineItemsData = [];

            foreach ($mergedQuantities as $productId => $quantity) {
                /** @var Product $product */
                $product = $products->get($productId);

                $product->decrement('stock_on_hand', $quantity);

                $unitPrice = number_format((float) $product->price, 2, '.', '');
                $taxPercentage = number_format((float) $product->tax_percentage, 2, '.', '');

                $lineSubtotal = bcmul((string) $quantity, $unitPrice, 2);
                $rawTax = bcmul($lineSubtotal, bcdiv($taxPercentage, '100', 6), 6);
                $lineTax = number_format(round((float) $rawTax, 2, PHP_ROUND_HALF_UP), 2, '.', '');
                $lineTotal = bcadd($lineSubtotal, $lineTax, 2);

                $subtotal = bcadd($subtotal, $lineSubtotal, 2);
                $taxAmount = bcadd($taxAmount, $lineTax, 2);
                $grandTotal = bcadd($grandTotal, $lineTotal, 2);

                $lineItemsData[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_percentage' => $taxPercentage,
                    'line_subtotal' => $lineSubtotal,
                    'line_tax' => $lineTax,
                    'line_total' => $lineTotal,
                ];
            }

            // 6. Persist Order and OrderItems
            $orderNumber = $this->generateUniqueOrderNumber();

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $customerRecord->id,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'status' => OrderStatus::Confirmed,
            ]);

            foreach ($lineItemsData as $itemData) {
                $order->items()->create($itemData);
            }

            // 7. Dispatch confirmation email job with afterCommit guarantees
            SendOrderConfirmationJob::dispatch($order->id)->afterCommit();

            return $order->load(['customer', 'items.product']);
        });
    }

    /**
     * Generate a unique order tracking number.
     */
    protected function generateUniqueOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
