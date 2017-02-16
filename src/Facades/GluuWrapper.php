<?php

namespace KWRI\LaravelGluuWrapper\Facades;

use KWRI\LaravelGluuWrapper\Contracts\Manager;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Illuminate\Routing\Router
 */
class GluuWrapper extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Manager::class;
    }
}
