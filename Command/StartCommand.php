<?php

namespace Downloader\Command;

use Downloader\Parsers\M1905;
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
            M1905::class => [
                'https://m3u8ipay.vodfile.m1905.com/202109140913/9968a16b69438ff5f34cc8d91b372cf5/movie/2018/12/12/m20181212OX4884YQ8ZK2QNKD/91E9F9FABB4F66B358CFF7AA7.m3u8'
            ],
        );

        $downloader = new Downloader($container, $input, $output, 45);
        try {
            $downloader->addParsers($task_list);
        } catch (\ReflectionException $e) {
        }
        $downloader->start();

        return Command::SUCCESS;
    }
}