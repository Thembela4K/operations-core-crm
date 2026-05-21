<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'sppraUrl' => AppSetting::valueFor('sppra_url', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sppra_url' => ['nullable', 'url', 'max:2048'],
        ]);

        AppSetting::setValue('sppra_url', $data['sppra_url'] ?? '');

        return back()->with('success', 'Settings updated.');
    }
}
