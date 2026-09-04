<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EloquentCustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(
        protected Customer $model,
    ) {}

    public function listForShop(Request $request): LengthAwarePaginator
    {
        $user = $request->user();
        $query = $this->model->where('shop_id', $user->shop_id);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate(20);
    }

    public function findById(int $id): ?Customer
    {
        return $this->model->find($id);
    }

    public function create(array $data): Customer
    {
        return $this->model->create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
    }

    public function delete(Customer $customer): bool
    {
        return $customer->delete();
    }

    public function findOrCreateByName(string $name, int $shopId, ?string $phone = null): Customer
    {
        return $this->model->firstOrCreate(
            ['shop_id' => $shopId, 'name' => $name],
            ['phone' => $phone]
        );
    }

    public function incrementStats(int $customerId, int $orderTotal): void
    {
        $this->model->where('id', $customerId)->increment([
            'total_purchases' => 1,
            'total_spent' => $orderTotal,
        ]);
    }
}
