<?php
declare(strict_types=1);
namespace Downloader\Runner;

use Downloader\Runner\Contracts\HttpRequestInterface;
use Katzgrau\KLogger\Logger;
use League\Pipeline\Pipeline;
use League\Pipeline\StageInterface;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Psr\Log\LoggerInterface;

class DownloaderServiceProvider implements ServiceProviderInterface
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
        $container[HttpRequestInterface::class] = function () {
            $httpRequest = new HttpRequest(['CURLOPT_HEADER' => true, 'CURLOPT_NOBODY' => false]);
            return $httpRequest;
        };

        $container[StageInterface::class] = function () {
            $pipeline = new Pipeline();
            return $pipeline;
        };
    }
}