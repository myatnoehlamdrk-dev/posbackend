<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveUserRequest;
use App\Http\Resources\ShopResource;
use App\Http\Resources\UserResource;
use App\Models\Shop;
use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
    ) {}

    // ── Dashboard ──

    public function dashboard(): JsonResponse
    {
        return response()->json($this->dashboardService->getStats());
    }

    public function salesChart(Request $request): JsonResponse
    {
        $days = $request->integer('days', 7);
        return response()->json($this->dashboardService->getSalesChart($days));
    }

    public function topProducts(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 10);
        return response()->json($this->dashboardService->getTopProducts($limit));
    }

    // ── Shops ──

    public function shops(Request $request): JsonResponse
    {
        $query = Shop::withCount('users');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('shop_name', 'like', "%{$search}%")
                  ->orWhere('owner_name', 'like', "%{$search}%")
                  ->orWhere('owner_email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('shop_type', $request->input('type'));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $perPage = $request->integer('per_page', 20);
        $shops = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'shops' => $shops->getCollection()->map(fn ($shop) => [
                'id' => (string) $shop->id,
                'shopName' => $shop->shop_name,
                'shopType' => $shop->shop_type,
                'shopImage' => $shop->shop_image,
                'shopPhysicalAddress' => $shop->shop_physical_address,
                'ownerName' => $shop->owner_name,
                'ownerEmail' => $shop->owner_email,
                'ownerPhone' => $shop->owner_phone,
                'isActive' => $shop->is_active,
                'usersCount' => $shop->users_count,
                'createdAt' => $shop->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'total' => $shops->total(),
                'per_page' => $shops->perPage(),
                'current_page' => $shops->currentPage(),
                'last_page' => $shops->lastPage(),
            ],
        ]);
    }

    public function toggleShopActive(Shop $shop): JsonResponse
    {
        $shop->update(['is_active' => ! $shop->is_active]);

        return response()->json(new ShopResource($shop->fresh()));
    }

    public function destroyShop(Shop $shop): JsonResponse
    {
        $shop->users()->update(['shop_id' => null]);
        $shop->delete();

        return $this->deleted();
    }

    // ── Users ──

    public function users(Request $request): JsonResponse
    {
        $query = User::with('shop');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('active')) {
            $query->where('active_status', $request->boolean('active'));
        }

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->input('shop_id'));
        }

        $perPage = $request->integer('per_page', 20);
        $users = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'users' => $users->getCollection()->map(fn ($user) => UserResource::make($user)->resolve($request)),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function pendingUsers(): JsonResponse
    {
        $users = User::with('shop')
            ->where('active_status', false)
            ->where('is_verified', true)
            ->orderByDesc('id')
            ->get();

        return response()->json(
            $users->map(fn ($user) => UserResource::make($user)->resolve(request()))
        );
    }

    public function approveUser(ApproveUserRequest $request, User $user): JsonResponse
    {
        $user->update([
            'active_status' => true,
            'shop_id' => $request->input('shop_id'),
        ]);

        return response()->json(UserResource::make($user->fresh()->load('shop'))->resolve($request));
    }

    public function toggleUserActive(User $user): JsonResponse
    {
        $user->update(['active_status' => ! $user->active_status]);

        return response()->json(UserResource::make($user->fresh()->load('shop'))->resolve(request()));
    }

    public function destroyUser(User $user): JsonResponse
    {
        $user->delete();

        return $this->deleted();
    }
}
