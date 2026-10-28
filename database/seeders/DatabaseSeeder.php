<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $products = [
            [
                'name' => 'Bread',
                'code' => 'BRD-001',
                'price' => 40.00,
                'tax_percentage' => 5.00,
                'stock_on_hand' => 4,
            ],
            [
                'name' => 'Milk 1L',
                'code' => 'MLK-001',
                'price' => 65.00,
                'tax_percentage' => 5.00,
                'stock_on_hand' => 9,
            ],
            [
                'name' => 'Eggs',
                'code' => 'EGG-001',
                'price' => 80.00,
                'tax_percentage' => 5.00,
                'stock_on_hand' => 2,
            ],
            [
                'name' => 'Colgate Toothpaste',
                'code' => 'CLG-001',
                'price' => 110.00,
                'tax_percentage' => 18.00,
                'stock_on_hand' => 25,
            ],
            [
                'name' => 'Parle-G Biscuit',
                'code' => 'PAR-001',
                'price' => 20.00,
                'tax_percentage' => 5.00,
                'stock_on_hand' => 50,
            ],
            [
                'name' => 'Tata Salt 1kg',
                'code' => 'SLT-001',
                'price' => 28.00,
                'tax_percentage' => 5.00,
                'stock_on_hand' => 60,
            ],
            [
                'name' => 'Fortune Sunflower Oil 1L',
                'code' => 'OIL-001',
                'price' => 155.00,
                'tax_percentage' => 5.00,
                'stock_on_hand' => 30,
            ],
            [
                'name' => 'Maggi 2-Minute Noodles',
                'code' => 'MAG-001',
                'price' => 14.00,
                'tax_percentage' => 12.00,
                'stock_on_hand' => 45,
            ],
            [
                'name' => 'Dettol Bathing Soap',
                'code' => 'DET-001',
                'price' => 55.00,
                'tax_percentage' => 18.00,
                'stock_on_hand' => 35,
            ],
            [
                'name' => 'Aashirvaad Atta 5kg',
                'code' => 'ATT-001',
                'price' => 260.00,
                'tax_percentage' => 5.00,
                'stock_on_hand' => 20,
            ],
            [
                'name' => 'Surf Excel Detergent 1kg',
                'code' => 'SRF-001',
                'price' => 140.00,
                'tax_percentage' => 18.00,
                'stock_on_hand' => 18,
            ],
            [
                'name' => 'Amul Butter 500g',
                'code' => 'BUT-001',
                'price' => 275.00,
                'tax_percentage' => 12.00,
                'stock_on_hand' => 15,
            ],
        ];

        foreach ($products as $productData) {
            Product::updateOrCreate(
                ['code' => $productData['code']],
                $productData
            );
        }

        $customers = [
            ['name' => 'Thomas Mathew', 'email' => 'thomas@example.com'],
            ['name' => 'Priya Sharma', 'email' => 'priya@example.com'],
            ['name' => 'Rahul Verma', 'email' => 'rahul@example.com'],
            ['name' => 'Anita Desai', 'email' => 'anita@example.com'],
            ['name' => 'Vikram Singh', 'email' => 'vikram@example.com'],
        ];

        foreach ($customers as $customerData) {
            Customer::updateOrCreate(
                ['email' => $customerData['email']],
                $customerData
            );
        }
    }
}
