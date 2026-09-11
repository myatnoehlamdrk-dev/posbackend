<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AuditRepositoryInterface
{
    public function log(Model $model, string $event, ?array $oldValues = null, ?array $newValues = null): AuditLog;
    public function getForModel(Model $model): Collection;
    public function getForShop(int $shopId, ?string $modelType = null): LengthAwarePaginator;
    public function getRecentActivity(int $shopId, int $limit = 10): Collection;
}
