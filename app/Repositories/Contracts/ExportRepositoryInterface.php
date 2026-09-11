<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ExportRepositoryInterface
{
    public function getSalesForExport(int $shopId, ?string $startDate = null, ?string $endDate = null): Collection;
    public function getOrdersForExport(int $shopId, ?string $startDate = null, ?string $endDate = null): Collection;
}
