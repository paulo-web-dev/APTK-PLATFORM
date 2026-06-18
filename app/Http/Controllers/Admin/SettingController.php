<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $settings = Setting::orderBy('group')->orderBy('key')->get()->groupBy('group');

        return view('admin.settings.index', compact('settings'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key'   => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.]+$/', Rule::unique('settings', 'key')],
            'value' => ['nullable', 'string'],
            'group' => ['nullable', 'string', 'max:50'],
        ]);

        Setting::create([
            'key'   => $data['key'],
            'value' => $data['value'] ?? null,
            'group' => $data['group'] ?: 'general',
        ]);

        return back()->with('status', 'Configuração adicionada.');
    }

    public function update(Request $request, Setting $setting): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['nullable', 'string'],
        ]);

        $setting->update(['value' => $data['value'] ?? null]);

        return back()->with('status', "Configuração '{$setting->key}' salva.");
    }

    public function destroy(Setting $setting): RedirectResponse
    {
        $setting->delete();

        return back()->with('status', 'Configuração removida.');
    }
}
