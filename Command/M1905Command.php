<?php declare(strict_types=1);

namespace Downloader\Command;

use Downloader\Files\M1905File;
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
class M1905Command extends Command
{
    protected PimpleContainer $container;

    protected function configure()
    {
        $this->setName('m1905')
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
           '黄金大劫案' => "https://m3u8i.vodfile.m1905.com/202204061603/c9b4b805a5148ce77e6a5895ffaf8166/movie/2019/10/22/m201910227KZFWKWLUKB73EXO/10A994B56920FEEAC04EB5799.m3u8"
//           '无人区' => "https://m3u8i.vodfile.m1905.com/202204021350/0062d1437e77ebde0ceedd6ab7022532/movie/2014/07/08/m2014070882MYZ4QYL20IY6US/m2014070882MYZ4QYL20IY6US.m3u8"
        ];

        // 推荐安装 FFMPEG 生成指定视频格式文件
        // 1. Downloader\Runner\CreateBinaryVideoListener::class 二进制文件格式
        //    不需要安装任何程序，自动生成二进制文件
        // 2. Downloader\Runner\CreateFFmpegVideoListener::class 指定生成文件格式
        //    此监听器必须安装FFMPEG 程序，才可以正常使用 [推荐的方式]
        // 3. 默认使用 [二进制文件格式] 创建视频文件
        // 4. 用户自行安装FFMPEG 程序，直接把下面这段注释去掉，自动改为FFMPEG 生成视频文件格式
//       $this->container['dispatcher']->addListener(CreateVideoFileEvent::NAME, [new CreateFFmpegVideoListener(), CreateBinaryVideoListener::METHOD_NAME]);

        $downloader  = new Downloader($this->container, $input, $output);
        $downloader->setConcurrencyValue(15);
        $downloader->setQueueValue(30);

        foreach ($files as $name => $url)
        {
            try
            {
                // 创建视频为mp4格式
                $file = new M1905File($url, DOWNLOAD_DIR.'/黄金大劫案', $name, 'mp4');
                $downloader->addFile($file);
            }catch (\Exception $e) {
                var_dump($e->getMessage());
            }
        }
        $downloader->start();
        return Command::SUCCESS;
    }
}