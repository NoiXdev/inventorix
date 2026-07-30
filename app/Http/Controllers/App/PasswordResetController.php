<?php

// app/Http/Controllers/App/PasswordResetController.php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('auth/reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // The `hashed` cast on User::password hashes the assigned plaintext.
                $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $user->save();
            },
        );

        return $status === Password::PasswordReset
            ? redirect('/app/login')->with('success', __($status))
            : back()->withErrors(['email' => __($status)]);
    }
}
