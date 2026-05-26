<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminTestPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AdminTestPanelProvider::class,
    AppPanelProvider::class,
    HorizonServiceProvider::class,
];
