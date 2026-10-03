<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateThemeRequest;
use Illuminate\Http\RedirectResponse;

class ThemePreferenceController extends Controller
{
    public function __invoke(UpdateThemeRequest $request): RedirectResponse
    {
        $request->user()->forceFill(['theme' => $request->string('theme')->toString()])->save();

        return back();
    }
}
