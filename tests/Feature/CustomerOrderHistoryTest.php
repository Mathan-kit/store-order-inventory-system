<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('returns order history for a customer ordered newest first with line items', function () {
    $customer = Customer::factory()->create(['email' => 'thomas@example.com']);
    $product = Product::factory()->create(['name' => 'Bread']);

    $order1 = Order::forceCreate([
        'order_number' => 'ORD-OLD',
        'customer_id' => $customer->id,
        'subtotal' => '40.00',
        'tax_amount' => '2.00',
        'grand_total' => '42.00',
        'status' => OrderStatus::Confirmed,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    OrderItem::create([
        'order_id' => $order1->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => '40.00',
        'tax_percentage' => '5.00',
        'line_subtotal' => '40.00',
        'line_tax' => '2.00',
        'line_total' => '42.00',
    ]);

    $order2 = Order::forceCreate([
        'order_number' => 'ORD-NEW',
        'customer_id' => $customer->id,
        'subtotal' => '80.00',
        'tax_amount' => '4.00',
        'grand_total' => '84.00',
        'status' => OrderStatus::Confirmed,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    OrderItem::create([
        'order_id' => $order2->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => '40.00',
        'tax_percentage' => '5.00',
        'line_subtotal' => '80.00',
        'line_tax' => '4.00',
        'line_total' => '84.00',
    ]);

    $response = $this->getJson('/api/customers/thomas@example.com/orders');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.order_number', 'ORD-NEW')
        ->assertJsonPath('data.1.order_number', 'ORD-OLD')
        ->assertJsonPath('data.0.items.0.product_name', 'Bread');
});

test('paginates customer orders with 15 per page', function () {
    $customer = Customer::factory()->create(['email' => 'heavy.buyer@example.com']);

    for ($i = 1; $i <= 20; $i++) {
        Order::forceCreate([
            'order_number' => "ORD-PAG-{$i}",
            'customer_id' => $customer->id,
            'subtotal' => '100.00',
            'tax_amount' => '5.00',
            'grand_total' => '105.00',
            'status' => OrderStatus::Confirmed,
            'created_at' => now()->subMinutes(25 - $i),
            'updated_at' => now()->subMinutes(25 - $i),
        ]);
    }

    $responsePage1 = $this->getJson('/api/customers/heavy.buyer@example.com/orders');
    $responsePage1->assertStatus(200)
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.total', 20)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.current_page', 1);

    $responsePage2 = $this->getJson('/api/customers/heavy.buyer@example.com/orders?page=2');
    $responsePage2->assertStatus(200)
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.current_page', 2);
});

test('returns 404 when customer email does not exist', function () {
    $response = $this->getJson('/api/customers/nonexistent@example.com/orders');

    $response->assertStatus(404)
        ->assertJson(['message' => 'Customer not found.']);
});

test('ensures isolation between orders of different customers', function () {
    $customerA = Customer::factory()->create(['email' => 'customer.a@example.com']);
    $customerB = Customer::factory()->create(['email' => 'customer.b@example.com']);

    Order::create([
        'order_number' => 'ORD-AAA-1',
        'customer_id' => $customerA->id,
        'subtotal' => '50.00',
        'tax_amount' => '2.50',
        'grand_total' => '52.50',
        'status' => OrderStatus::Confirmed,
    ]);

    Order::create([
        'order_number' => 'ORD-BBB-1',
        'customer_id' => $customerB->id,
        'subtotal' => '100.00',
        'tax_amount' => '5.00',
        'grand_total' => '105.00',
        'status' => OrderStatus::Confirmed,
    ]);

    $responseA = $this->getJson('/api/customers/customer.a@example.com/orders');
    $responseA->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', 'ORD-AAA-1');

    $responseB = $this->getJson('/api/customers/customer.b@example.com/orders');
    $responseB->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', 'ORD-BBB-1');
});

test('retrieves customer by email for auto-fill or returns 404', function () {
    Customer::factory()->create([
        'name' => 'Existing Customer',
        'email' => 'autofill@example.com',
    ]);

    $success = $this->getJson('/api/customers/autofill@example.com');
    $success->assertStatus(200)
        ->assertJson([
            'data' => [
                'name' => 'Existing Customer',
                'email' => 'autofill@example.com',
            ],
        ]);

    $notFound = $this->getJson('/api/customers/unknown@example.com');
    $notFound->assertStatus(404)
        ->assertJson(['message' => 'Customer not found.']);
});
