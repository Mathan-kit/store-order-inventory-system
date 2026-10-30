<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CustomerNotFoundException;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    /**
     * Find a customer by email (case-insensitive and trimmed) or throw an exception.
     *
     * @throws CustomerNotFoundException
     */
    public function findByEmail(string $email): Customer
    {
        $normalizedEmail = strtolower(trim($email));
        $customer = Customer::where('email', $normalizedEmail)->first();

        if (! $customer) {
            throw new CustomerNotFoundException('Customer not found.');
        }

        return $customer;
    }

    /**
     * Get paginated orders for a customer by email.
     *
     * @throws CustomerNotFoundException
     */
    public function getCustomerOrders(string $email, int $perPage = 15): LengthAwarePaginator
    {
        $customer = $this->findByEmail($email);

        return $customer->orders()
            ->with(['customer', 'items.product'])
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);
    }
}

