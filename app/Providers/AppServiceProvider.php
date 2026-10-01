<?php

namespace App\Providers;

use App\Repositories\Contracts\AuditRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\ExportRepositoryInterface;
use App\Repositories\Contracts\FeedbackRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseItemRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\StockAlertRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Eloquent\EloquentAuditRepository;
use App\Repositories\Eloquent\EloquentCategoryRepository;
use App\Repositories\Eloquent\EloquentCustomerRepository;
use App\Repositories\Eloquent\EloquentDashboardRepository;
use App\Repositories\Eloquent\EloquentExportRepository;
use App\Repositories\Eloquent\EloquentFeedbackRepository;
use App\Repositories\Eloquent\EloquentInventoryRepository;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\Eloquent\EloquentPackageRepository;
use App\Repositories\Eloquent\EloquentProductRepository;
use App\Repositories\Eloquent\EloquentPurchaseItemRepository;
use App\Repositories\Eloquent\EloquentSaleRepository;
use App\Repositories\Eloquent\EloquentStockAlertRepository;
use App\Repositories\Eloquent\EloquentStockRepository;
use App\Repositories\Eloquent\EloquentSupplierRepository;
use App\Services\AdminDashboardService;
use App\Services\AuditService;
use App\Services\CategoryService;
use App\Services\CustomerService;
use App\Services\DashboardService;
use App\Services\ExportService;
use App\Services\FeedbackService;
use App\Services\InventoryService;
use App\Services\OrderItemService;
use App\Services\OrderNumberService;
use App\Services\OrderService;
use App\Services\PackageService;
use App\Services\ProductService;
use App\Services\PurchaseItemService;
use App\Services\SaleService;
use App\Services\StockAlertService;
use App\Services\SupplierService;
use App\Services\UserResolutionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repositories
        $this->app->bind(SaleRepositoryInterface::class, EloquentSaleRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(StockRepositoryInterface::class, EloquentStockRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
        $this->app->bind(PackageRepositoryInterface::class, EloquentPackageRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, EloquentCustomerRepository::class);
        $this->app->bind(SupplierRepositoryInterface::class, EloquentSupplierRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, EloquentDashboardRepository::class);
        $this->app->bind(ExportRepositoryInterface::class, EloquentExportRepository::class);
        $this->app->bind(StockAlertRepositoryInterface::class, EloquentStockAlertRepository::class);
        $this->app->bind(InventoryRepositoryInterface::class, EloquentInventoryRepository::class);
        $this->app->bind(PurchaseItemRepositoryInterface::class, EloquentPurchaseItemRepository::class);
        $this->app->bind(AuditRepositoryInterface::class, EloquentAuditRepository::class);
        $this->app->bind(FeedbackRepositoryInterface::class, EloquentFeedbackRepository::class);

        // Services
        $this->app->singleton(UserResolutionService::class);
        $this->app->singleton(OrderNumberService::class);
        $this->app->singleton(OrderItemService::class);
        $this->app->singleton(SupplierService::class);
        $this->app->singleton(InventoryService::class);
        $this->app->singleton(ProductService::class);
        $this->app->singleton(SaleService::class);
        $this->app->singleton(OrderService::class);
        $this->app->singleton(CategoryService::class);
        $this->app->singleton(PackageService::class);
        $this->app->singleton(StockAlertService::class);
        $this->app->singleton(CustomerService::class);
        $this->app->singleton(AuditService::class);
        $this->app->singleton(ExportService::class);
        $this->app->singleton(DashboardService::class);
        $this->app->singleton(AdminDashboardService::class);
        $this->app->singleton(PurchaseItemService::class);
        $this->app->singleton(FeedbackService::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * Named rate limiters, referenced from the route middleware.
     *
     * The numbers are deliberately asymmetric. Reads get a wide window because
     * opening a screen legitimately fans out several GETs at once and a till
     * sitting idle still polls; auth gets a tight one because that is the only
     * group where the traffic pattern itself is the attack.
     *
     * Both limiters emit Laravel's standard `Retry-After` header on 429, which
     * the Flutter client parses for `TooManyRequestsException.retryAfterSeconds`
     * and for the retry delay. Nothing custom is needed on either side.
     */
    protected function configureRateLimiters(): void
    {
        // Authenticated traffic, keyed by user when there is a session and by
        // IP when there is not. Keying on the user stops one till behind a
        // shared shop NAT from consuming another's quota.
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(120)->by($user?->id ?: $request->ip());
        });

        // Credential endpoints: login, register, OTP send and verify, password
        // reset. Tight, and keyed on IP alone because there is no user yet --
        // which is precisely why this is the group worth throttling.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
