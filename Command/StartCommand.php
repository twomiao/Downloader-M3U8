<?php
namespace Downloader\Command;

use Downloader\Parsers\Hua;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Downloader\Runner\Downloader;
use Pimple\Container as PimpleContainer;
use Downloader\Runner\ServiceProvider;

class StartCommand extends Command
{
    protected function configure()
    {
        $this->setName('start')
            ->setDescription('PHP 协程池超速下载M3U8视频.')
            ->setHelp('php downloader start');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = new PimpleContainer();
        $container->register(new ServiceProvider());

        $task_list = array(
            Hua::class => [
//                'https://video.com/m3u8/3278/m3u8.m3u8',
//                'https://video.com/m3u8/3342/m3u8.m3u8'
            ],
//            YouKu::class => [
//                'https://video.com/m3u8/3278/m3u8.m3u8',
//                'https://video.com/m3u8/3342/m3u8.m3u8'
//            ],
        );

        $downloader = new Downloader($container, $input, $output);
        try {
            $downloader->addParsers($task_list);
        } catch (\ReflectionException $e) {}
        $downloader->start();

        return Command::SUCCESS;
    }
}