<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in((array) config('school.locales'))],
        ]);

        $locale = (string) $data['locale'];

        $request->session()->put('locale', $locale);

        $user = $request->user();
        $user->forceFill(['locale' => $locale])->save();

        app()->setLocale($locale);

        return redirect()->back(fallback: route('dashboard'));
    }
}
