<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function __construct(
        protected AuditRepositoryInterface $auditRepository,
    ) {}

    public function log(Model $model, string $event, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        return $this->auditRepository->log($model, $event, $oldValues, $newValues);
    }

    public function getForModel(Model $model)
    {
        return $this->auditRepository->getForModel($model);
    }

    public function getForShop(int $shopId, ?string $modelType = null)
    {
        return $this->auditRepository->getForShop($shopId, $modelType);
    }

    public function getRecentActivity(int $shopId, int $limit = 10)
    {
        return $this->auditRepository->getRecentActivity($shopId, $limit);
    }
}
