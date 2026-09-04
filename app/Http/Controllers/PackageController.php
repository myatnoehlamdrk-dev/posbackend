<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\UpdatePackageRequest;
use App\Models\Package;
use App\Services\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function __construct(
        private readonly PackageService $packageService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->packageService->listForShop($request);
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        return $this->packageService->create($request->validated(), $request->user()->shop_id);
    }

    public function show(Package $package): JsonResponse
    {
        return $this->packageService->show($package);
    }

    public function update(UpdatePackageRequest $request, Package $package): JsonResponse
    {
        return $this->packageService->update($request->validated(), $package, $request->user()->shop_id);
    }

    public function destroy(Package $package): JsonResponse
    {
        return $this->packageService->delete($package);
    }
}
