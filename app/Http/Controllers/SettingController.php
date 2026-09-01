<?php

namespace App\Http\Controllers;

use App\Http\Resources\SettingResource;
use App\Models\Setting;
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

        return response()->json(SettingResource::make($setting->fresh()));
    }
}
