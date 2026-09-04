<?php

namespace App\Repositories\Eloquent;

use App\Models\Supplier;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentSupplierRepository implements SupplierRepositoryInterface
{
    public function __construct(
        protected Supplier $model,
    ) {}

    public function list(): Collection
    {
        return $this->model->latest()->get();
    }

    public function findById(int $id): ?Supplier
    {
        return $this->model->find($id);
    }

    public function findByName(string $name): ?Supplier
    {
        return $this->model->where('name', $name)->first();
    }

    public function create(array $data): Supplier
    {
        return $this->model->create($data);
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): bool
    {
        return $supplier->delete();
    }

    public function resolveOrCreate(?int $supplierId, ?string $supplierName): ?int
    {
        if ($supplierId) {
            return $supplierId;
        }

        if ($supplierName) {
            $supplier = $this->findByName($supplierName);
            if ($supplier) {
                return $supplier->id;
            }
            $new = $this->create(['name' => $supplierName]);
            return $new->id;
        }

        return null;
    }
}
