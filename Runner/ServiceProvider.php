<?php
declare(strict_types=1);
namespace Downloader\Runner;

use Katzgrau\KLogger\Logger;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Log\LoggerInterface;

class ServiceProvider implements ServiceProviderInterface
{

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $container A container instance
     */
    public function register(Container $container)
    {
        $container['log.dir'] = function () {
            return __DIR__ . '/../logs/';
        };

        $container[LoggerInterface::class] = function (Container $container) {
            return new Logger($container['log.dir']);
        };
    }
}