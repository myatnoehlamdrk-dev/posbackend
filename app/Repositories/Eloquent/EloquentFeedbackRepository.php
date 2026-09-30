<?php

namespace App\Repositories\Eloquent;

use App\Models\Feedback;
use App\Repositories\Contracts\FeedbackRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EloquentFeedbackRepository implements FeedbackRepositoryInterface
{
    public function __construct(
        protected Feedback $model,
    ) {}

    public function listForShop(Request $request): LengthAwarePaginator
    {
        $query = $this->model->where('shop_id', $request->user()->shop_id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('message', 'like', "%{$search}%");
        }

        return $query->with('user:id,name,email')
            ->latest()
            ->paginate((int) $request->input('per_page', 20));
    }

    public function findForShop(int $id, int $shopId): ?Feedback
    {
        return $this->model->where('shop_id', $shopId)
            ->with('user:id,name,email')
            ->find($id);
    }

    public function create(array $data): Feedback
    {
        return $this->model->create($data);
    }

    public function delete(Feedback $feedback): bool
    {
        return (bool) $feedback->delete();
    }
}
