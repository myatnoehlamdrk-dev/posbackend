<?php

namespace App\Providers;

use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Eloquent\EloquentCategoryRepository;
use App\Repositories\Eloquent\EloquentCustomerRepository;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\Eloquent\EloquentPackageRepository;
use App\Repositories\Eloquent\EloquentProductRepository;
use App\Repositories\Eloquent\EloquentSaleRepository;
use App\Repositories\Eloquent\EloquentStockRepository;
use App\Repositories\Eloquent\EloquentSupplierRepository;
use App\Services\AuditService;
use App\Services\CategoryService;
use App\Services\CustomerService;
use App\Services\DashboardService;
use App\Services\ExportService;
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
        $this->app->singleton(PurchaseItemService::class);
    }

    public function boot(): void
    {
        //
    }
}
