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
            ->setDescription('PHP 协程池极速下载M3U8视频.')
            ->setHelp('php downloader start');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
//        $max_workers = $input->getOption('max_workers');
//        $task_list   = $input->getArgument('本地文件下载记录');
        $max_workers = 45;
        $task_list = array(
            M1905::class => [
                
            ],
        );

        $container = new PimpleContainer();
        $container->register(new ServiceProvider());

        $container[Downloader::class] = function ($container) use ($task_list, $input, $output, $max_workers) {
            $downloader = new DownloadApp($container, $input, $output, $max_workers);
            try {
                $downloader->addParsers($task_list);
            } catch (\ReflectionException $e) {}
            return $downloader;
        };

        $container[Downloader::class]->start();

        return Command::SUCCESS;
    }
}