<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Throwable;

class OrderController extends Controller
{
    /**
     * Store a new customer order.
     */
    public function store(StoreOrderRequest $request, OrderService $orderService): JsonResponse
    {
        try {
            $order = $orderService->createOrder(
                customer: [
                    'email' => $request->validated('customer_email'),
                    'name' => $request->validated('customer_name'),
                ],
                items: $request->validated('items'),
            );

            return (new OrderResource($order))
                ->response()
                ->setStatusCode(201);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'items' => [
                        [
                            'product_id' => $e->getProductId(),
                            'code' => $e->getProductCode(),
                            'requested' => $e->getRequested(),
                            'available' => $e->getAvailable(),
                        ],
                    ],
                ],
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'An error occurred while creating the order: '.$e->getMessage(),
            ], 500);
        }
    }
}
