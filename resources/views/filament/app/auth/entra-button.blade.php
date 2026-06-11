@php
    // The login page and the multi-factor challenge step share the same render
    // hook. Only show the Entra login button on the initial login form.
    $isMultiFactorChallenge = filled(\Livewire\Livewire::current()?->userUndertakingMultiFactorAuthentication ?? null);
@endphp

@if (! $isMultiFactorChallenge && config('services.microsoft-azure.enabled'))
    @if (session('entra_error'))
        <div class="mb-4 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">
            {{ session('entra_error') }}
        </div>
    @endif

    <div class="text-center text-sm text-gray-500 border-b border-white leading-0.5 mt-2.5 mb-5">
        <span class="bg-gray-900 px-3">{{ __('or sign in with social login') }}</span>
    </div>

    <a
        href="{{ route('auth.microsoft.redirect') }}"
        class="fi-btn fi-btn-color-gray fi-btn-outlined fi-btn-size-md fi-btn-fullwidth mb-4 inline-flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-white/5"
    >
        {{ __('Login via Entra ID') }}
    </a>
@endif
