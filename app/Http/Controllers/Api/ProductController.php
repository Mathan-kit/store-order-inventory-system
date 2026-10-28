<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * List product catalog for billing selection.
     */
    public function index(): AnonymousResourceCollection
    {
        $products = Product::orderBy('name', 'asc')->get();

        return ProductResource::collection($products);
    }

    /**
     * List products at or below the configured low-stock threshold.
     */
    public function lowStock(): AnonymousResourceCollection
    {
        $threshold = (int) config('inventory.low_stock_threshold', 10);

        $products = Product::where('stock_on_hand', '<=', $threshold)
            ->orderBy('stock_on_hand', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return ProductResource::collection($products);
    }
}

