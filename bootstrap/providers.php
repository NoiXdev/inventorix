<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminTestPanelProvider;
use App\Providers\Filament\AppPanelProvider;

return [
    AppServiceProvider::class,
    AdminTestPanelProvider::class,
    AppPanelProvider::class,
];
