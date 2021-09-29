<?php declare(strict_types=1);

namespace Downloader\Command;

use Downloader\Parsers\M1905;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Downloader\Runner\Downloader;
use Pimple\Container as PimpleContainer;
use Downloader\Runner\DownloaderServiceProvider;

class M1905Command extends Command
{
    protected PimpleContainer $container;

    protected function configure()
    {
        $this->setName('m1905')
            ->addOption('max_workers', 'M', InputArgument::OPTIONAL, '下载任务，使用的协程池数量', 35)
            ->setDescription('Downloader-M3U8 极速下载程序.')
            ->setHelp('php downloader start');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->container = new PimpleContainer();
        $this->container->register(new DownloaderServiceProvider());
    }

    protected function execute(InputInterface $io_input, OutputInterface $io_out)
    {
        $m3u8s = [
            'https://m3u8i.vodfile.m1905.com/202109291620/56d27c18db09c845c9baa017512e06b5/movie/2021/02/23/m20210223ABRH3LJ64UX37YDH/FBAFFDC7DA5B968DCEA5E4A62.m3u8',
            'https://m3u8i.vodfile.m1905.com/202109291620/56d27c18db09c845c9baa017512e06b5/movie/2021/02/23/m20210223ABRH3LJ64UX37YDH/FBAFFDC7DA5B968DCEA5E4A62.m3u8'
        ];

        $max_workers = (int)$io_input->getOption('max_workers');
        $downloader = new Downloader($this->container, $io_input, $io_out, $max_workers);
        $downloader->addParser(new M1905())->addTasks($m3u8s);
        $downloader->start();
        return Command::SUCCESS;
    }
}