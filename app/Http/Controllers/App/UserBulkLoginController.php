<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserBulkLoginController extends Controller
{
    /**
     * Enable login for several users at once. All or nothing: one user the actor may
     * not manage rejects the whole request. No password is set and no mail is sent.
     */
    public function enable(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid', 'exists:users,id'],
        ]);
        $actor = $request->user();

        $changed = DB::transaction(function () use ($data, $actor): int {
            $users = User::query()->whereKey($data['ids'])->lockForUpdate()->get();

            $blocked = $users->reject(fn (User $user) => $actor->can('manage', $user))
                ->map(fn (User $user) => $user->name ?? $user->email)->values()->all();
            if ($blocked !== []) {
                throw ValidationException::withMessages(['ids' => 'You may not manage: '.implode(', ', $blocked).'.']);
            }

            $count = 0;
            foreach ($users as $user) {
                if (! $user->login_enabled) {
                    $user->login_enabled = true;
                    $user->save();
                    $count++;
                }
            }

            return $count;
        });

        return back()->with('success', 'Login enabled for '.$changed.' '.($changed === 1 ? 'user' : 'users').'.');
    }
}
