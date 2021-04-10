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
                "https://m3u8i.vodfile.m1905.com/202104111303/5750d10b976913b603178623791ce8b2/movie/2019/11/01/m201911013H75YYU0X8TGQ5XV/31F7C75013716C57F444253B3.m3u8"
            ], array(
                /***
                 *  视频本身未加密，因此无需中间件
                 */
//                new AesDecryptMiddleware,
            ))
            ->start();

        return Command::SUCCESS;
    }
}