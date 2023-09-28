<?php
declare(strict_types=1);
namespace Downloader\Runner;

use Katzgrau\KLogger\Logger;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class DownloaderServiceProvider implements ServiceProviderInterface
{
    public function __construct(public OutputInterface $out) {}
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
        $container['log'] = fn () => new Logger(getcwd() . '/logs');
        // 配置文件
        $container['config'] = fn () => new ConfigFile();

        // 视频保存路径
        $container['video_save_path'] = "/mnt/c/users/twomiao/desktop/downloader/videos";

        // 事件服务
        $container['event'] = fn () => new EventDispatcher();
        $container->extend('event', function ($dispatcher, $container) {
            $dispatcher->addListener(SaveFile::NAME, [new SaveFileListener(),"onSaveFile"]);
            return $dispatcher;
        });

        $container[OutputInterface::class] = fn () => $this->out;
    }
}
