<?php
namespace Downloader\Runner;

use Katzgrau\KLogger\Logger;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use ProgressBar\Manager;
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
        $container['log.dir'] = function ($c) {
            return __DIR__ . '/../logs/';
        };

        $container['config'] = function ($c) {
            return [
                'output'      => '',
                'concurrent'  => 25,
            ];
        };

        $container[LoggerInterface::class] = function (Container $container) {
            return new Logger($container['log.dir']);
        };

        $container['bar'] = $container->factory(function (Container $container) {
            return new Manager(0, 100, 100);
        });

        $container['log'] = $container->factory(function (Container $container) {
            $c = new \Pimple\Psr11\Container($container);
            return new Log($c);
        });

        $container['client'] = $container->factory(function (Container $container) {
            $c = new \Pimple\Psr11\Container($container);

            $client = new HttpClient();
            $client->setContainer($c);
            return $client;
        });
    }
}