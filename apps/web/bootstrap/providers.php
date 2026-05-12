<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    AdminPanelProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
