<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function log(Model $model, string $event, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => request()->user()?->id,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function getForModel(Model $model)
    {
        return AuditLog::where('auditable_type', get_class($model))
            ->where('auditable_id', $model->id)
            ->with('user')
            ->latest()
            ->get();
    }

    public function getForShop(int $shopId, ?string $modelType = null)
    {
        $query = AuditLog::whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->with('user');

        if ($modelType) {
            $query->where('auditable_type', $modelType);
        }

        return $query->latest()->paginate(50);
    }

    public function getRecentActivity(int $shopId, int $limit = 10)
    {
        return AuditLog::whereHas('user', fn ($q) => $q->where('shop_id', $shopId))
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }
}
