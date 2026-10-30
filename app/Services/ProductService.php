<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    /**
     * List all products ordered alphabetically.
     *
     * @return Collection<int, Product>
     */
    public function getAllProducts(): Collection
    {
        return Product::orderBy('name', 'asc')->get();
    }

    /**
     * List products at or below the configured low-stock threshold.
     *
     * @return Collection<int, Product>
     */
    public function getLowStockProducts(): Collection
    {
        $threshold = (int) config('inventory.low_stock_threshold', 10);

        return Product::where('stock_on_hand', '<=', $threshold)
            ->orderBy('stock_on_hand', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }
}

