<?php declare(strict_types=1);

namespace Downloader\Command;

use Downloader\Files\Decrypt\M1906DecryptFile;
use Downloader\Files\M1905File;
use Downloader\Files\Url\UrlGenerate;
use Downloader\Runner\Command\FileTemplate;
use Downloader\Runner\CreateFFmpegVideoListener;
use Downloader\Runner\CreateVideoFileEvent;
use Downloader\Runner\FileM3u8;
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
//        $files = [
//            "花与棋" => 'https://m3u8i.vodfile.m1905.com/202205150225/4da2def47f866367838ba6f3e9d55303/movie/2018/10/25/m201810250GDNYALQQX19HR1P/145502A2CA0ADBA349064BD2E.m3u8',
//            '把妈妈嫁出去' => 'https://m3u8i.vodfile.m1905.com/202205150225/4da2def47f866367838ba6f3e9d55303/movie/2017/10/11/m20171011INJ4QIAI1GJIZ18D/2C50A79A9292E6D423E015FFE.m3u8'
//        ];

        $files = self::readTemplateJson(
            $templateFilePath = self::templateFilePath()
        );
        // 推荐安装 FFMPEG 生成指定视频格式文件
        // 1. Downloader\Runner\CreateBinaryVideoListener::class 二进制文件格式
        //    不需要安装任何程序，自动生成二进制文件
        // 2. Downloader\Runner\CreateFFmpegVideoListener::class 指定生成文件格式
        //    此监听器必须安装FFMPEG 程序，才可以正常使用 [推荐的方式]
        // 3. 默认使用 [二进制文件格式] 创建视频文件
        // 4. 用户自行安装FFMPEG 程序，直接把下面这段注释去掉，自动改为FFMPEG 生成视频文件格式
       $this->container['dispatcher']->addListener(CreateVideoFileEvent::NAME, [new CreateFFmpegVideoListener(), CreateFFmpegVideoListener::METHOD_NAME]);

        $downloader  = new Downloader($this->container, $input, $output);
        $downloader->setConcurrentRequestsNumber(20);
        foreach ($files as $jsonFile)
        {
            try
            {
                // 创建视频为mp4格式
                $save_video = $jsonFile['put_path'] . DIRECTORY_SEPARATOR. $jsonFile['filename'];
                $file = new FileM3u8($jsonFile['m3u8_url'], $save_video);
                $file->saveAs($jsonFile['filename'], $jsonFile['suffix']);
//                $file->setDecryptFile(new M1906DecryptFile($jsonFile['key'], $jsonFile['method']));
                $url_prefix = $jsonFile['url_prefix'] ?: dirname($jsonFile['m3u8_url']);
                $file->setGenerateUrl(new UrlGenerate($url_prefix));
                $file->loadJsonFile($jsonFile);
                // 添加下载文件任务
                $downloader->addFile($file);
            } catch (\Exception $e) {
                var_dump($e->getMessage());
            }
        }
        $downloader->start();
        return Command::SUCCESS;
    }

    protected static function readTemplateJson(string $templateFile) : array {
        if (!\file_exists($templateFile)) {
            throw new \RuntimeException('加载模板文件失败:'.$templateFile);
        }

        $template = \file_get_contents($templateFile);
        if (!$template) {
            throw new \RuntimeException('模板Json文件读取失败:'.$template);
        }
        $data =  \json_decode($template, true);
        if($data === false) {
            throw new \RuntimeException('模板Json文件内容解析错误:'.\json_last_error_msg());
        }

        return $data['files'];
    }


    /**
     * 加载模板文件
     * @return string
     */
    protected static function templateFilePath() : string
    {
        return \getcwd().DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.FileTemplate::FILENAME;
    }
}