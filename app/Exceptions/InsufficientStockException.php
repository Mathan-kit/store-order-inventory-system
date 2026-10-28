<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsufficientStockException extends Exception
{
    public function __construct(
        protected int $productId,
        protected string $productName,
        protected string $productCode,
        protected int $requested,
        protected int $available,
        string $message = '',
        int $code = 422,
        ?\Throwable $previous = null,
    ) {
        if ($message === '') {
            $message = "Insufficient stock for {$productName}";
        }

        parent::__construct($message, $code, $previous);
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getProductCode(): string
    {
        return $this->productCode;
    }

    public function getRequested(): int
    {
        return $this->requested;
    }

    public function getAvailable(): int
    {
        return $this->available;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'items' => [
                    [
                        'product_id' => $this->productId,
                        'code' => $this->productCode,
                        'requested' => $this->requested,
                        'available' => $this->available,
                    ],
                ],
            ],
        ], 422);
    }
}

