<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a signed-in user whose login has been turned off
 * (e.g. by offboarding or the user form), on their next request.
 */
class EnsureLoginEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->login_enabled) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('app.login')->with('error', __('This account is disabled.'));
        }

        return $next($request);
    }
}
