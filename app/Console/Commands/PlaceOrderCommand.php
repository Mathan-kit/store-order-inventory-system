<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\InsufficientStockException;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Throwable;

class PlaceOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:place 
                            {email : Customer email address} 
                            {product_id : Product ID to order} 
                            {quantity=1 : Quantity to order} 
                            {--name=Concurrency Tester : Customer name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dev-only command to place an order via OrderService for concurrency testing';

    /**
     * Execute the console command.
     */
    public function handle(OrderService $orderService): int
    {
        $email = (string) $this->argument('email');
        $productId = (int) $this->argument('product_id');
        $quantity = (int) $this->argument('quantity');
        $name = (string) $this->option('name');

        try {
            $order = $orderService->createOrder(
                customer: [
                    'email' => $email,
                    'name' => $name,
                ],
                items: [
                    [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ],
                ]
            );

            $this->line((string) json_encode([
                'success' => true,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
            ]));

            return self::SUCCESS;
        } catch (InsufficientStockException $e) {
            $this->line((string) json_encode([
                'success' => false,
                'error' => 'insufficient_stock',
                'message' => $e->getMessage(),
                'product_id' => $e->getProductId(),
                'available' => $e->getAvailable(),
                'requested' => $e->getRequested(),
            ]));

            return 2;
        } catch (Throwable $e) {
            $this->line((string) json_encode([
                'success' => false,
                'error' => 'exception',
                'message' => $e->getMessage(),
            ]));

            return self::FAILURE;
        }
    }
}
