<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\ZKTeco\ZKTecoBiometricServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ZKTecoBiometricServiceProvider::class,
];
