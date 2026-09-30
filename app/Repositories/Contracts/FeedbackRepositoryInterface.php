<?php

namespace App\Repositories\Contracts;

use App\Models\Feedback;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface FeedbackRepositoryInterface
{
    public function listForShop(Request $request): LengthAwarePaginator;

    public function findForShop(int $id, int $shopId): ?Feedback;

    public function create(array $data): Feedback;

    public function delete(Feedback $feedback): bool;
}
