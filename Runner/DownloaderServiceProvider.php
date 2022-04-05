<?php
declare(strict_types=1);
namespace Downloader\Runner;

use Katzgrau\KLogger\Logger;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
        // 日志服务
        $container['logger'] = fn($c) => new Logger(getcwd() . '/logs');
        // 事件服务
        $container['dispatcher'] = fn($c) => new EventDispatcher();
    }
}