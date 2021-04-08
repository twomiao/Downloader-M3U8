<?php
namespace Downloader\Command;

use Downloader\Parsers\Hua;
use Downloader\Parsers\YouKu;
use Downloader\Runner\Middleware\AesDecryptMiddleware;
use Downloader\Runner\Middleware\RsaDecryptMiddleware;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Downloader\Runner\Downloader;
use Pimple\Container as PimpleContainer;
use Downloader\Runner\ServiceProvider;
use Pimple\Psr11\Container as Psr11Container;

class StartCommand extends Command
{
    protected function configure()
    {
        $this->setName('start')
            ->setDescription('Download M3U8 network video concurrently.')
            ->setHelp('php downloader start');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = new PimpleContainer();
        $container->register(new ServiceProvider());
        $container['config'] = $container->extend('config', function ($config, $c) use ($output, $input) {
            return [
                'output' => __DIR__ . '/../../../output/',
                'concurrent' => 25,
                'outputConsole' => $output,
                'inputConsole' => $input
            ];
        });
        $c = new Psr11Container($container);

        $downloader = new Downloader($c, $c->get('config'));
        $downloader
            ->setMovieParser(new YouKu(), [
                "https://vod.xxx.com/20210322/RIn5ERXl/1000kb/hls/index.m3u8"
//                "https://youku.com-movie-youku.com/20181028/1275_c4fb695f/1000k/hls/index.m3u8",
            ], array(
                new AesDecryptMiddleware,
//                new RsaDecryptMiddleware
            ))
            ->setMovieParser(new Hua(), [
//                "https://m3u8i.vodfile.m1905.com/202011220309/972a4a041420ecca90901d33fa2086ee/movie/2017/06/15/m201706152917FI77DD7VW2PA/AF9889E7AAB81F8C1AE5615AD.m3u8"
            ])
            ->start();

        return Command::SUCCESS;
    }
}