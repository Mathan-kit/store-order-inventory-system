<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * List product catalog for billing selection.
     */
    public function index(): AnonymousResourceCollection|JsonResponse
    {
        try {
            $products = $this->productService->getAllProducts();

            return ProductResource::collection($products);
        } catch (Throwable) {
            return response()->json([
                'message' => 'An error occurred while fetching products.',
            ], 500);
        }
    }

    /**
     * List products at or below the configured low-stock threshold.
     */
    public function lowStock(): AnonymousResourceCollection|JsonResponse
    {
        try {
            $products = $this->productService->getLowStockProducts();

            return ProductResource::collection($products);
        } catch (Throwable) {
            return response()->json([
                'message' => 'An error occurred while fetching low-stock products.',
            ], 500);
        }
    }
}
