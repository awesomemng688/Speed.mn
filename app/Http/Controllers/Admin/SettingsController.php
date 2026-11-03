<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AdminAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $values = [];
        foreach (array_keys(Setting::DEFAULTS) as $key) {
            $values[$key] = Setting::value($key);
        }

        return view('admin.settings', ['values' => $values]);
    }

    public function update(Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'discord_url' => ['required', 'url', 'max:255', 'starts_with:https://'],
            'hero_title' => ['required', 'string', 'max:60'],
            'hero_accent' => ['required', 'string', 'max:60'],
            'hero_lead' => ['required', 'string', 'max:300'],
            'banner_text' => ['nullable', 'string', 'max:200'],
            'banner_url' => ['nullable', 'url', 'max:255', 'starts_with:https://,http://'],
        ]);
        $data['banner_enabled'] = $request->boolean('banner_enabled') ? '1' : '0';
        $data['banner_text'] = $data['banner_text'] ?? '';
        $data['banner_url'] = $data['banner_url'] ?? '';

        Setting::put($data);
        $audit->record($request, 'settings.updated', null, ['keys' => array_keys($data)], 'Site settings');

        return back()->with('status', 'Тохиргоо хадгалагдлаа.');
    }
}
