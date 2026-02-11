<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        // Получение всех настроек пользователя как key-value пары
        $settings = $request->user()
            ->settings()
            ->pluck('value', 'key');
        
        return response()->json($settings);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        // Сохраняем каждую настройку
        foreach ($request->all() as $key => $value) {
            Setting::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'key' => $key
                ],
                [
                    'value' => $value
                ]
            );
        }
        
        // Возвращаем обновлённые настройки
        $settings = $user->settings()->pluck('value', 'key');
        
        return response()->json([
            'message' => 'Settings saved successfully',
            'settings' => $settings
        ]);
    }
}