<?php

namespace App\Http\Controllers;

use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $setting = Setting::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'theme_mode' => 'light',
                'language' => 'Myanmar',
                'shop_type' => 'shop',
            ]
        );

        return response()->json(SettingResource::make($setting));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme_mode' => ['sometimes', 'string', 'in:light,dark'],
            'language' => ['sometimes', 'string', 'max:20'],
            'shop_type' => ['sometimes', 'string', 'in:shop,service,restaurant,store'],
            'shop_image' => ['nullable', 'string'],
        ]);

        $setting = Setting::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'theme_mode' => 'light',
                'language' => 'Myanmar',
                'shop_type' => 'shop',
            ]
        );

        $setting->update($data);

        if (array_key_exists('shop_image', $data)) {
            $shopId = $request->user()->shop_id;
            if ($shopId) {
                $shop = Shop::find($shopId);
                if ($shop) {
                    $shop->update(['shop_image' => $data['shop_image'] ?? null]);
                }
            }
        }

        return response()->json(SettingResource::make($setting->fresh()));
    }
}
