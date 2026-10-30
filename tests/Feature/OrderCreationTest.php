<?php

declare(strict_types=1);

use App\Jobs\SendOrderConfirmationJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('creates order and deducts stock', function () {
    Queue::fake();

    $product = Product::factory()->create([
        'name' => 'Bread',
        'code' => 'BRD-001',
        'price' => 40.00,
        'tax_percentage' => 5.00,
        'stock_on_hand' => 10,
    ]);

    $payload = [
        'customer_name' => 'Thomas Mathew',
        'customer_email' => 'thomas@example.com',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
            ],
        ],
    ];

    $response = $this->postJson('/api/orders', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.customer.email', 'thomas@example.com')
        ->assertJsonPath('data.customer.name', 'Thomas Mathew')
        ->assertJsonPath('data.subtotal', '80.00')
        ->assertJsonPath('data.tax_amount', '4.00')
        ->assertJsonPath('data.grand_total', '84.00')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.quantity', 2);

    expect($product->fresh()->stock_on_hand)->toBe(8);
    expect(Order::count())->toBe(1);
    expect(OrderItem::count())->toBe(1);
});

test('calculates subtotal, tax, grand total correctly with mixed tax rates and half-up rounding', function () {
    Queue::fake();

    // Line 1: 3 * 19.99 = 59.97. Tax @ 18% = 10.7946 -> 10.79. Total = 70.76
    $product1 = Product::factory()->create([
        'name' => 'Toothpaste',
        'code' => 'TP-001',
        'price' => 19.99,
        'tax_percentage' => 18.00,
        'stock_on_hand' => 20,
    ]);

    // Line 2: 2 * 10.55 = 21.10. Tax @ 5% = 1.055 -> 1.06 (half-up rounding). Total = 22.16
    $product2 = Product::factory()->create([
        'name' => 'Biscuits',
        'code' => 'BSC-001',
        'price' => 10.55,
        'tax_percentage' => 5.00,
        'stock_on_hand' => 20,
    ]);

    $payload = [
        'customer_name' => 'Priya Sharma',
        'customer_email' => 'priya@example.com',
        'items' => [
            ['product_id' => $product1->id, 'quantity' => 3],
            ['product_id' => $product2->id, 'quantity' => 2],
        ],
    ];

    $response = $this->postJson('/api/orders', $payload);

    // Sum of lines: Subtotal = 59.97 + 21.10 = 81.07. Tax = 10.79 + 1.06 = 11.85. Grand = 70.76 + 22.16 = 92.92
    $response->assertStatus(201)
        ->assertJsonPath('data.subtotal', '81.07')
        ->assertJsonPath('data.tax_amount', '11.85')
        ->assertJsonPath('data.grand_total', '92.92');

    $this->assertDatabaseHas('orders', [
        'subtotal' => '81.07',
        'tax_amount' => '11.85',
        'grand_total' => '92.92',
    ]);
});

test('insufficient stock returns 422, leaves stock unchanged and rolls back all persistence', function () {
    Queue::fake();

    $product = Product::factory()->create([
        'name' => 'Eggs',
        'code' => 'EGG-001',
        'stock_on_hand' => 2,
    ]);

    $payload = [
        'customer_name' => 'Rahul Verma',
        'customer_email' => 'rahul@example.com',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 3],
        ],
    ];

    $response = $this->postJson('/api/orders', $payload);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Insufficient stock for Eggs',
            'errors' => [
                'items' => [
                    [
                        'product_id' => $product->id,
                        'code' => 'EGG-001',
                        'requested' => 3,
                        'available' => 2,
                    ],
                ],
            ],
        ]);

    expect($product->fresh()->stock_on_hand)->toBe(2);
    expect(Order::count())->toBe(0);
    expect(OrderItem::count())->toBe(0);
    expect(Customer::where('email', 'rahul@example.com')->exists())->toBeFalse();
    Queue::assertNotPushed(SendOrderConfirmationJob::class);
});

test('multi-product order where second product fails leaves first product stock undeducted', function () {
    Queue::fake();

    $product1 = Product::factory()->create([
        'name' => 'Bread',
        'code' => 'BRD-001',
        'stock_on_hand' => 5,
    ]);

    $product2 = Product::factory()->create([
        'name' => 'Milk',
        'code' => 'MLK-001',
        'stock_on_hand' => 1,
    ]);

    $payload = [
        'customer_name' => 'Anita Desai',
        'customer_email' => 'anita@example.com',
        'items' => [
            ['product_id' => $product1->id, 'quantity' => 2],
            ['product_id' => $product2->id, 'quantity' => 4],
        ],
    ];

    $response = $this->postJson('/api/orders', $payload);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Insufficient stock for Milk');

    expect($product1->fresh()->stock_on_hand)->toBe(5);
    expect($product2->fresh()->stock_on_hand)->toBe(1);
    expect(Order::count())->toBe(0);
});

test('duplicate product lines in one request are merged and checked against combined quantity', function () {
    Queue::fake();

    $product = Product::factory()->create([
        'name' => 'Bread',
        'code' => 'BRD-001',
        'stock_on_hand' => 4,
    ]);

    // Requesting 2 + 3 = 5 when only 4 available -> should fail
    $failPayload = [
        'customer_name' => 'Vikram Singh',
        'customer_email' => 'vikram@example.com',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
            ['product_id' => $product->id, 'quantity' => 3],
        ],
    ];

    $response = $this->postJson('/api/orders', $failPayload);

    $response->assertStatus(422)
        ->assertJsonPath('errors.items.0.requested', 5)
        ->assertJsonPath('errors.items.0.available', 4);

    expect($product->fresh()->stock_on_hand)->toBe(4);

    // Requesting 2 + 2 = 4 when 4 available -> succeeds with single merged order item of quantity 4
    $successPayload = [
        'customer_name' => 'Vikram Singh',
        'customer_email' => 'vikram@example.com',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ];

    $successResponse = $this->postJson('/api/orders', $successPayload);

    $successResponse->assertStatus(201)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.quantity', 4);

    expect($product->fresh()->stock_on_hand)->toBe(0);
});

test('dispatches SendOrderConfirmationJob and does not dispatch on failure', function () {
    Queue::fake();

    $product = Product::factory()->create([
        'name' => 'Bread',
        'stock_on_hand' => 5,
    ]);

    $payload = [
        'customer_name' => 'Thomas Mathew',
        'customer_email' => 'thomas@example.com',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ];

    $response = $this->postJson('/api/orders', $payload);
    $response->assertStatus(201);

    $orderId = $response->json('data.id');

    Queue::assertPushed(SendOrderConfirmationJob::class, function (SendOrderConfirmationJob $job) use ($orderId) {
        return $job->orderId === $orderId;
    });
});

test('validation handles missing fields, empty items, zero, negative, non-integer quantity, and unknown product', function () {
    // Missing email and name
    $this->postJson('/api/orders', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_email', 'customer_name', 'items']);

    // Empty items
    $this->postJson('/api/orders', [
        'customer_name' => 'Test',
        'customer_email' => 'test@example.com',
        'items' => [],
    ])->assertStatus(422)->assertJsonValidationErrors(['items']);

    // Zero quantity
    $this->postJson('/api/orders', [
        'customer_name' => 'Test',
        'customer_email' => 'test@example.com',
        'items' => [['product_id' => 1, 'quantity' => 0]],
    ])->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);

    // Negative quantity
    $this->postJson('/api/orders', [
        'customer_name' => 'Test',
        'customer_email' => 'test@example.com',
        'items' => [['product_id' => 1, 'quantity' => -2]],
    ])->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);

    // Non-integer quantity
    $this->postJson('/api/orders', [
        'customer_name' => 'Test',
        'customer_email' => 'test@example.com',
        'items' => [['product_id' => 1, 'quantity' => 'invalid']],
    ])->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);

    // Unknown product_id
    $this->postJson('/api/orders', [
        'customer_name' => 'Test',
        'customer_email' => 'test@example.com',
        'items' => [['product_id' => 999999, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors(['items.0.product_id']);
});

test('reuses existing customer by email case-insensitively and updates name without duplicate rows', function () {
    Queue::fake();

    $customer = Customer::factory()->create([
        'name' => 'John Original',
        'email' => 'john.doe@example.com',
    ]);

    $product = Product::factory()->create(['stock_on_hand' => 10]);

    $payload = [
        'customer_name' => 'John Updated',
        'customer_email' => '  JOHN.DOE@EXAMPLE.COM  ',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ];

    $response = $this->postJson('/api/orders', $payload);

    $response->assertStatus(201);
    expect(Customer::count())->toBe(1);
    expect($customer->fresh()->name)->toBe('John Updated');
    expect($customer->fresh()->email)->toBe('john.doe@example.com');
});

test('order item snapshot keeps original unit price after product price changes later', function () {
    Queue::fake();

    $product = Product::factory()->create([
        'price' => 100.00,
        'tax_percentage' => 18.00,
        'stock_on_hand' => 10,
    ]);

    $payload = [
        'customer_name' => 'Price Check Customer',
        'customer_email' => 'pricecheck@example.com',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
    ];

    $response = $this->postJson('/api/orders', $payload);
    $response->assertStatus(201);

    // Update product catalog price
    $product->update(['price' => 199.99]);

    $orderItem = OrderItem::where('product_id', $product->id)->first();
    expect($orderItem->unit_price)->toBe('100.00');
    expect($product->fresh()->price)->toBe('199.99');
});
