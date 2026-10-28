<?php

declare(strict_types=1);

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sequential double-order on stock 1 causes second order to throw InsufficientStockException', function () {
    $product = Product::factory()->create([
        'name' => 'Single Stock Item',
        'code' => 'SS-001',
        'price' => 50.00,
        'tax_percentage' => 5.00,
        'stock_on_hand' => 1,
    ]);

    $orderService = app(OrderService::class);

    // First order: succeeds and brings stock from 1 to 0
    $firstOrder = $orderService->createOrder(
        customer: [
            'name' => 'First Buyer',
            'email' => 'buyer1@example.com',
        ],
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 1,
            ],
        ]
    );

    expect($firstOrder)->toBeInstanceOf(Order::class);
    expect($product->fresh()->stock_on_hand)->toBe(0);
    expect(Order::count())->toBe(1);

    // Second order: fails cleanly because stock is now 0
    try {
        $orderService->createOrder(
            customer: [
                'name' => 'Second Buyer',
                'email' => 'buyer2@example.com',
            ],
            items: [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ]
        );

        $this->fail('Expected InsufficientStockException was not thrown.');
    } catch (InsufficientStockException $e) {
        expect($e->getProductId())->toBe($product->id);
        expect($e->getProductCode())->toBe('SS-001');
        expect($e->getRequested())->toBe(1);
        expect($e->getAvailable())->toBe(0);
        expect($e->getMessage())->toBe('Insufficient stock for Single Stock Item');
    }

    // Verify database state remains uncorrupted
    expect($product->fresh()->stock_on_hand)->toBe(0);
    expect(Order::count())->toBe(1);
    expect(Customer::where('email', 'buyer2@example.com')->exists())->toBeFalse();
});

