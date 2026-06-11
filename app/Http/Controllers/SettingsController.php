<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index');
    }

    public function update(Request $request): RedirectResponse
    {
        return back()->with('success', 'Settings are controlled by application configuration.');
    }
}
