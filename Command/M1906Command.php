<?php declare(strict_types=1);

namespace Downloader\Command;

use Downloader\Files\M1905File;
use Downloader\Files\TestFile;
use Downloader\Runner\CreateBinaryVideoListener;
use Downloader\Runner\CreateFFmpegVideoListener;
use Downloader\Runner\CreateVideoFileEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Downloader\Runner\Downloader;
use Pimple\Container as PimpleContainer;
use Downloader\Runner\DownloaderServiceProvider;

/**
 * Class M1905Command
 * @package Downloader\Command
 */
class M1906Command extends Command
{
    protected PimpleContainer $container;

    protected function configure()
    {
        $this->setName('m1906')
            ->addOption('max_workers', 'M', InputArgument::OPTIONAL, '下载任务，使用的协程池数量', 35)
            ->setDescription('Downloader-M3U8 并发下载程序.')
            ->setHelp('php Downloader.php [Command] -M [workers]');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->container = new PimpleContainer();
        $this->container->register(new DownloaderServiceProvider());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $files = [
            '信箱E-mail' => 'https://m3u8i.vodfile.m1905.com/202204260052/d92679a1b901eed8925454bccb7ee781/movie/2018/05/08/m20180508SE2ARG3KGXHQBBI2/669E1732364FBF72253C66547.m3u8',
            '绣春刀' =>'https://m3u8i.vodfile.m1905.com/202204260054/64ab7256564627bee96504ea6d3b8e39/movie/2014/08/27/m20140827JS2X55SR00OY3A16/m20140827JS2X55SR00OY3A16.m3u8'
        ];

        // 推荐安装 FFMPEG 生成指定视频格式文件
        // 1. Downloader\Runner\CreateBinaryVideoListener::class 二进制文件格式
        //    不需要安装任何程序，自动生成二进制文件
        // 2. Downloader\Runner\CreateFFmpegVideoListener::class 指定生成文件格式
        //    此监听器必须安装FFMPEG 程序，才可以正常使用 [推荐的方式]
        // 3. 默认使用 [二进制文件格式] 创建视频文件
        // 4. 用户自行安装FFMPEG 程序，直接把下面这段注释去掉，自动改为FFMPEG 生成视频文件格式
       $this->container['dispatcher']->addListener(CreateVideoFileEvent::NAME, [new CreateFFmpegVideoListener(), CreateFFmpegVideoListener::METHOD_NAME]);

        $downloader  = new Downloader($this->container, $input, $output);
        $downloader->setConcurrencyValue(25);

        foreach ($files as $name => $url)
        {
            try
            {
                // 创建视频为mp4格式
                $file = new M1905File($url, DOWNLOAD_DIR.'/test', $name, 'mp4');
                $downloader->addFile($file);
            }catch (\Exception $e) {
                var_dump($e->getMessage());
            }
        }
        $downloader->start();
        return Command::SUCCESS;
    }
}