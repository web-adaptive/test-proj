<?php

namespace App\Http\Controllers;

use App\Models\YandexSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $settings = YandexSettings::where('user_id', Auth::id())->first();
        
        return Inertia::render('Settings', [
            'yandexUrl' => $settings?->yandex_url ?? '',
            'success' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'yandex_url' => ['required', 'url'],
        ]);

        YandexSettings::updateOrCreate(
            ['user_id' => Auth::id()],
            ['yandex_url' => $validated['yandex_url']]
        );

        return redirect()->route('settings')->with('success', 'Настройки сохранены');
    }
}
