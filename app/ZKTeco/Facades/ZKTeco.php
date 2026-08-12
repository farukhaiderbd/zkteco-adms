<?php

namespace App\ZKTeco\Facades;

use Illuminate\Support\Facades\Facade;

class ZKTeco extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'zkteco-biometric';
    }
}
