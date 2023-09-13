<?php
namespace Downloader\Runner;

use RuntimeException;
use Pimple\Container as PimpleContainer;
use Pimple\ServiceProviderInterface;

final class Container
{
    private static ?PimpleContainer $container = null;

    public static function register(ServiceProviderInterface $provider) : void {
        if (is_null(static::$container))
        {
            static::$container = new PimpleContainer();
            static::$container->register( $provider );
        }
    }

    public static function make($service) : mixed 
    {
        if (is_null(static::$container)) {
            throw new RuntimeException("Container not initialized.");
        }
       return static::$container[$service];
    }

    private function __clone(){}
   
}