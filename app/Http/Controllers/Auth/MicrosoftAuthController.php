<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\EntraAuthException;
use App\Services\Auth\EntraIdAuthService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class MicrosoftAuthController
{
    public function redirect(): RedirectResponse
    {
        abort_unless(config('services.microsoft-azure.enabled'), 404);

        return Socialite::driver('microsoft-azure')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(EntraIdAuthService $auth): RedirectResponse
    {
        abort_unless(config('services.microsoft-azure.enabled'), 404);

        try {
            $msUser = Socialite::driver('microsoft-azure')->user();
            $auth->assertTenantMatches($msUser);
            $user = $auth->resolveUser($msUser);
            $auth->syncAttributes($user, $msUser);

            Auth::guard('web')->login($user, remember: true);
            request()->session()->regenerate();

            return redirect()->intended(Filament::getUrl());
        } catch (EntraAuthException $e) {
            return redirect()->route('filament.app.auth.login')
                ->with('entra_error', $e->getUserMessage());
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('filament.app.auth.login')
                ->with('entra_error', __('Microsoft sign-in failed. Please try again.'));
        }
    }
}
