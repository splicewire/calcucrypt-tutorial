<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\TeamApprovalWalletServiceProvider::class,
    App\Providers\LocalMarketplaceServiceProvider::class,
    FortifyServiceProvider::class,
    TypeScriptTransformerServiceProvider::class,
];
