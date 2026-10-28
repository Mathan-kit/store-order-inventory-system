<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * Store a new customer order.
     */
    public function store(StoreOrderRequest $request, OrderService $orderService): JsonResponse
    {
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
    }
}

