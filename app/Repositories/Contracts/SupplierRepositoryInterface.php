<?php

namespace App\Repositories\Contracts;

use App\Models\Supplier;
use Illuminate\Support\Collection;

interface SupplierRepositoryInterface
{
    public function list(): Collection;
    public function findById(int $id): ?Supplier;
    public function findByName(string $name): ?Supplier;
    public function create(array $data): Supplier;
    public function update(Supplier $supplier, array $data): Supplier;
    public function delete(Supplier $supplier): bool;
    public function resolveOrCreate(?int $supplierId, ?string $supplierName): ?int;
}
