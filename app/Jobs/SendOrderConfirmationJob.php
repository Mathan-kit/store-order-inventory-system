<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOrderConfirmationJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(public int $orderId)
    {
        $this->afterCommit();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $order = Order::with(['customer', 'items'])->find($this->orderId);

        if (! $order) {
            Log::warning("SendOrderConfirmationJob: Order {$this->orderId} not found.");

            return;
        }

        Log::info("Order confirmation email simulated for order #{$order->order_number}", [
            'order_number' => $order->order_number,
            'customer_email' => $order->customer?->email,
            'grand_total' => $order->grand_total,
            'item_count' => $order->items->count(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("SendOrderConfirmationJob failed for order {$this->orderId}", [
            'order_id' => $this->orderId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
