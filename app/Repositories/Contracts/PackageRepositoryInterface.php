<?php

namespace App\Repositories\Contracts;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface PackageRepositoryInterface
{
    public function listForShop(Request $request): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function findById(int $id): ?Package;
    public function create(array $data): Package;
    public function update(Package $package, array $data): Package;
    public function delete(Package $package): bool;
    public function countProducts(Package $package): int;
}
