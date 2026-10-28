<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    /**
     * Retrieve customer basic profile by email for auto-fill.
     */
    public function show(string $email): JsonResponse
    {
        $normalizedEmail = strtolower(trim($email));
        $customer = Customer::where('email', $normalizedEmail)->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'name' => $customer->name,
                'email' => $customer->email,
            ],
        ]);
    }

    /**
     * Retrieve paginated order history for a customer.
     */
    public function orders(string $email): JsonResponse
    {
        $normalizedEmail = strtolower(trim($email));
        $customer = Customer::where('email', $normalizedEmail)->first();

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found.',
            ], 404);
        }

        $orders = $customer->orders()
            ->with(['customer', 'items.product'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);

        return OrderResource::collection($orders)->response();
    }
}
