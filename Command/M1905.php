<?php
declare(strict_types=1);
namespace Downloader\Command;

use Downloader\Files\M1905File;
use Downloader\Runner\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Downloader\Runner\Downloader;
use Pimple\Container as PimpleContainer;
use Downloader\Runner\DownloaderServiceProvider;
use Symfony\Component\Console\Helper\Table;

use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

/**
 * Class M1905Command
 * @package Downloader\Command
 */
class M1905 extends Command
{
    private const OPTION_ADD    = 1;
    private const OPTION_DELETE = 2;
    private const OPTION_RUN    = 3;
    private const OPTION_QUIT   = 4;

    protected function configure()
    {
        $this->setName('m1905')
            ->addArgument("save", InputArgument::REQUIRED, "下载完成视频文件保存磁盘路径")
            ->addArgument("load-file", InputArgument::REQUIRED, "下载网站驱动类")
            ->addOption('concurrent-requests', 'req', InputArgument::OPTIONAL, '并发请求数', 20)
            ->addOption('suffix-name', 'suffix', InputArgument::OPTIONAL, '视频文件后缀名', "mp4")
            ->setDescription("dl-m3u8 并发下载M3U8视频")
            ->setHelp('php dl-m3u8 save [/home/m3u8] --req [20] --suffix [mp4]');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        Container::register(new DownloaderServiceProvider($output, $input));
        Downloader::$savePath = "/mnt/c/users/twomiao/desktop/downloader/test";
    }

    protected function drawUiTable(
        OutputInterface $out,
        string $title,
        array $headers,
        array $data = []
    ): void {
        $table = new Table($out);
        $table->setHeaderTitle($title);
        $table->setVertical(true);
        $table
            ->setHeaders($headers)
            ->setRows($data);
        $table->setStyle("box");
        $table->render();
        echo "\n";
    }

    protected function textFilename(string $lable, string $default = ""): string
    {
        return text(label: $lable, default: $default, required: true);
    }

    protected function textDownloadUrl(string $lable, string $default = ""): string
    {
        $url = text(
            label: $lable,
            validate: fn (string $value) => match (true) {
                $value === "" => '请填写视频在线地址.',
                !filter_var($value, FILTER_VALIDATE_URL) ||
                    !\str_ends_with($value, ".m3u8") => "{$value} 是一个无效下载地址.",
                default => $default
            }
        );
        return $url;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /*  $data = [];
         while(1)
         {
             $option = select(
                 label: '请按以下选项运行程序?',
                 options: [
                     self::OPTION_ADD => '添加下载任务',
                     self::OPTION_DELETE => '删除下载任务',
                     self::OPTION_RUN => '运行程序',
                     self::OPTION_QUIT => '退出程序'
                 ],
                 default: self::OPTION_ADD,
             );
             switch($option) {
                 case self::OPTION_ADD:
                     $name  = $this->textFilename("请填写文件名称：");
                     $url   = $this->textDownloadUrl("请填写下载地址：");
                     array_push($data, [ 'name' => $name, 'm3u8_url' => $url]);
                     $key  = \key($data);
                     array_unshift($data[$key], $key);
                     \next($data);
                     break;
                 case self::OPTION_DELETE:
                     $id = $this->textFilename("请输入要删除的ID：");
                     unset($data[(int)$id]);
                     break;
                 case self::OPTION_RUN:
                     goto RUN;
                 case self::OPTION_QUIT:
                     return static::SUCCESS;
             }

            if (\count($data)>0) {
                 $this->drawUiTable($output, 'www.baidu.com', ['ID', '文件名', '下载地址'], $data);
            }
         }
         RUN: */

        $files = [
            [
                'name' => '变形金刚3',
                'video_url' => 'https://m3u8i.vodfile.m1905.com/202309122237/78cadc30724606747f22859630c88730/movie/2015/11/30/m20151130ACC8WYILBOGQG8IP/AEC06BAE912E0862B4F7B1B22.m3u8',
                'cdn' => 'https://m3u8i.vodfile.m1905.com/202309122237/78cadc30724606747f22859630c88730/movie/2015/11/30/m20151130ACC8WYILBOGQG8IP', 
                 'ext' => 'mp4'
            ],
             [
                 'name' => '阿凡达2',
                 'video_url' => 'https://m3u8i.vodfile.m1905.com/202309122237/78cadc30724606747f22859630c88730/movie/2015/11/30/m20151130ACC8WYILBOGQG8IP/AEC06BAE912E0862B4F7B1B22.m3u8',
                  'cdn' => 'https://m3u8i.vodfile.m1905.com/202309122237/78cadc30724606747f22859630c88730/movie/2015/11/30/m20151130ACC8WYILBOGQG8IP', 
                  'ext' => 'mp4'
            ],
            [
                'name' => '八角笼中',
                'video_url' => 'https://m3u8i.vodfile.m1905.com/202309122237/78cadc30724606747f22859630c88730/movie/2015/11/30/m20151130ACC8WYILBOGQG8IP/AEC06BAE912E0862B4F7B1B22.m3u8',
                'cdn' => 'https://m3u8i.vodfile.m1905.com/202309122237/78cadc30724606747f22859630c88730/movie/2015/11/30/m20151130ACC8WYILBOGQG8IP', 
                 'ext' => 'mp4'
            ]
        ];

        $videos = [];
        foreach ($files as $file) {
            $videos[] = new M1905File($file['video_url'], $file['name'], $file['cdn'], $file['ext']);
        }

        $dl = new Downloader(__DIR__ . "/../../videos");
        $dl->download(...$videos);
        $dl->start();

        return static::SUCCESS;
    }
}
