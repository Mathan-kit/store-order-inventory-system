<?php

declare(strict_types=1);

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('returns only products at or below the default low-stock threshold ordered by stock ascending', function () {
    config(['inventory.low_stock_threshold' => 10]);

    $pEggs = Product::factory()->create(['name' => 'Eggs', 'stock_on_hand' => 2]);
    $pBread = Product::factory()->create(['name' => 'Bread', 'stock_on_hand' => 4]);
    $pMilk = Product::factory()->create(['name' => 'Milk 1L', 'stock_on_hand' => 9]);
    $pThreshold = Product::factory()->create(['name' => 'At Boundary', 'stock_on_hand' => 10]);
    $pSoap = Product::factory()->create(['name' => 'Soap', 'stock_on_hand' => 11]);
    $pToothpaste = Product::factory()->create(['name' => 'Toothpaste', 'stock_on_hand' => 25]);

    $response = $this->getJson('/api/products/low-stock');

    $response->assertStatus(200)
        ->assertJsonCount(4, 'data');

    $stocks = collect($response->json('data'))->pluck('stock_on_hand')->all();
    expect($stocks)->toBe([2, 4, 9, 10]);

    // Product with stock 10 is included (at or below)
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain($pThreshold->id)
        ->not->toContain($pSoap->id)
        ->not->toContain($pToothpaste->id);

    // Verify model helper consistency
    expect($pEggs->isLowStock())->toBeTrue();
    expect($pBread->isLowStock())->toBeTrue();
    expect($pMilk->isLowStock())->toBeTrue();
    expect($pThreshold->isLowStock())->toBeTrue();
    expect($pSoap->isLowStock())->toBeFalse();
});

test('honours dynamic config override for low-stock threshold', function () {
    config(['inventory.low_stock_threshold' => 5]);

    $pLow = Product::factory()->create(['name' => 'Low Item', 'stock_on_hand' => 5]);
    $pMid = Product::factory()->create(['name' => 'Mid Item', 'stock_on_hand' => 7]);
    $pHigh = Product::factory()->create(['name' => 'High Item', 'stock_on_hand' => 15]);

    $response = $this->getJson('/api/products/low-stock');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pLow->id);

    expect($pLow->isLowStock())->toBeTrue();
    expect($pMid->isLowStock())->toBeFalse();
    expect($pHigh->isLowStock())->toBeFalse();
});

