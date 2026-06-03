<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use FilamentAdmin\FilamentAdminServiceProvider;

return [
    AppServiceProvider::class,
    FilamentAdminServiceProvider::class,
    AdminPanelProvider::class,
];
