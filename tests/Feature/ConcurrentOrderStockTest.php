<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        test()->markTestSkipped('Concurrent lockForUpdate test requires MySQL (InnoDB).');
    }

    // Manual cleanup without wrapping transaction so external OS processes share DB state
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    OrderItem::truncate();
    Order::truncate();
    Customer::truncate();
    Product::truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
});

afterEach(function () {
    if (DB::getDriverName() === 'mysql') {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        OrderItem::truncate();
        Order::truncate();
        Customer::truncate();
        Product::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
});

test('concurrent order placement on 1 unit of stock allows exactly one order and prevents overselling', function () {
    // Run the concurrent race 3 times with fresh products to verify determinism and race resilience
    for ($race = 1; $race <= 3; $race++) {
        $product = Product::create([
            'name' => "Race Item {$race}",
            'code' => "RACE-{$race}",
            'price' => 50.00,
            'tax_percentage' => 5.00,
            'stock_on_hand' => 1,
        ]);

        $cmd1 = [
            PHP_BINARY,
            'artisan',
            'orders:place',
            "runner_a_{$race}@example.com",
            (string) $product->id,
            '1',
            '--name=Runner A',
            '--env=testing',
        ];

        $cmd2 = [
            PHP_BINARY,
            'artisan',
            'orders:place',
            "runner_b_{$race}@example.com",
            (string) $product->id,
            '1',
            '--name=Runner B',
            '--env=testing',
        ];

        $process1 = new Process($cmd1, base_path());
        $process2 = new Process($cmd2, base_path());

        // Launch both OS processes in parallel
        $process1->start();
        $process2->start();

        // Wait for both concurrent processes to finish
        $process1->wait();
        $process2->wait();

        $parseJson = function (string $output): ?array {
            $lines = array_filter(array_map('trim', explode("\n", $output)));
            foreach (array_reverse($lines) as $line) {
                $decoded = json_decode($line, true);
                if (is_array($decoded) && array_key_exists('success', $decoded)) {
                    return $decoded;
                }
            }

            return null;
        };

        $result1 = $parseJson($process1->getOutput());
        $result2 = $parseJson($process2->getOutput());

        expect($result1)->not->toBeNull('Process 1 did not output valid JSON: '.$process1->getOutput().$process1->getErrorOutput());
        expect($result2)->not->toBeNull('Process 2 did not output valid JSON: '.$process2->getOutput().$process2->getErrorOutput());

        $successCount = ($result1['success'] ? 1 : 0) + ($result2['success'] ? 1 : 0);
        $failureCount = (! $result1['success'] ? 1 : 0) + (! $result2['success'] ? 1 : 0);

        // Exactly one process succeeds and exactly one fails
        expect($successCount)->toBe(1, "Expected exactly 1 success in race {$race}, got {$successCount}. Results: ".json_encode([$result1, $result2]));
        expect($failureCount)->toBe(1, "Expected exactly 1 failure in race {$race}, got {$failureCount}");

        // The failed process must report insufficient_stock
        $failedResult = ! $result1['success'] ? $result1 : $result2;
        expect($failedResult['error'])->toBe('insufficient_stock');
        expect($failedResult['available'])->toBe(0);

        // Final inventory stock must be exactly 0 (never negative)
        $freshProduct = $product->fresh();
        expect($freshProduct->stock_on_hand)->toBe(0);

        // Exactly one order and one order item exists for this product
        $orderItems = OrderItem::where('product_id', $product->id)->get();
        expect($orderItems->count())->toBe(1);
        expect($orderItems->first()->quantity)->toBe(1);
    }
});
