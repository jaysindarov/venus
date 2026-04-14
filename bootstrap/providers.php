<?php

use App\Providers\AIServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    AIServiceProvider::class,
    HorizonServiceProvider::class,
    TelescopeServiceProvider::class,
];
