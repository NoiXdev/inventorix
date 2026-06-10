<?php

namespace App\Providers\Filament;

use App\Http\Middleware\ApplyRuntimeSettings;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        FilamentAsset::register([
            Js::make('scanner', \Vite::asset('resources/js/plugins/scanner.ts'))->module(),
            Js::make('qr-print', \Vite::asset('resources/js/plugins/qr-print/index.ts'))->module(),
            Css::make('overrides', Vite::asset('resources/css/app.css')),
        ]);

        if (config('auth.multi_factor_auth.enabled') === true) {
            $panel->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable(config('auth.multi_factor_auth.recoverable')),
            ], isRequired: config('auth.multi_factor_auth.force'));
        }

        return $panel
            ->id('app')
            ->path('app')
            ->profile()
            ->default()
            ->maxContentWidth(Width::Full)
            ->login()
            ->spa()
            ->brandLogo('/asset/logo/header.png')
            ->brandLogoHeight('3rem')
            ->brandName('Inventorix')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverClusters(in: app_path('Filament/App/Clusters'), for: 'App\Filament\App\Clusters')
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => \Blade::render('@livewire("scanner")'))
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('qr-print.modal'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.app.auth.entra-button'),
            )
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                ApplyRuntimeSettings::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
