<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\CustomerNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Throwable;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    /**
     * Retrieve customer basic profile by email for auto-fill.
     */
    public function show(string $email): JsonResponse
    {
        try {
            $customer = $this->customerService->findByEmail($email);

            return response()->json([
                'data' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                ],
            ]);
        } catch (CustomerNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (Throwable) {
            return response()->json([
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    /**
     * Retrieve paginated order history for a customer.
     */
    public function orders(string $email): JsonResponse
    {
        try {
            $orders = $this->customerService->getCustomerOrders($email, 15);

            return OrderResource::collection($orders)->response();
        } catch (CustomerNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        } catch (Throwable) {
            return response()->json([
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }
}
