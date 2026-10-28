<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_code' => $this->product?->code,
            'quantity' => (int) $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'tax_percentage' => (string) $this->tax_percentage,
            'line_subtotal' => (string) $this->line_subtotal,
            'line_tax' => (string) $this->line_tax,
            'line_total' => (string) $this->line_total,
        ];
    }
}

