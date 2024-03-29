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
            // ->addArgument("save_path", InputArgument::REQUIRED, "视频文件存储路径.")
            // ->addOption('corrents', 'req', InputArgument::OPTIONAL, '并发请求数', 35)
            ->setDescription("dl-m3u8 并发下载M3U8视频")
            ->setHelp('php dl-m3u8 /home/m3u8');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        Container::register(new DownloaderServiceProvider($output, $input));
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
        // $data = [];
        // while(1) {
        //     $option = select(
        //         label: '请按以下选项运行程序?',
        //         options: [
        //             self::OPTION_ADD => '添加下载任务',
        //             self::OPTION_DELETE => '删除下载任务',
        //             self::OPTION_RUN => '运行程序',
        //             self::OPTION_QUIT => '退出程序'
        //         ],
        //         default: self::OPTION_ADD,
        //     );
        //     switch($option) {
        //         case self::OPTION_ADD:
        //             $name  = $this->textFilename("请填写文件名称：");
        //             $url   = $this->textDownloadUrl("请填写下载地址：");
        //             array_push($data, [ 'name' => $name, 'm3u8_url' => $url]);
        //             $key  = \key($data);
        //             array_unshift($data[$key], $key);
        //             \next($data);
        //             break;
        //         case self::OPTION_DELETE:
        //             $id = $this->textFilename("请输入要删除的ID：");
        //             unset($data[(int)$id]);
        //             break;
        //         case self::OPTION_RUN:
        //             goto RUN;
        //         case self::OPTION_QUIT:
        //             return static::SUCCESS;
        //     }

        //     if (\count($data) > 0) {
        //         $this->drawUiTable($output, 'www.baidu.com', ['ID', '文件名', '下载地址'], $data);
        //     }
        // }
        // RUN:

        $files = [
        ];

        $videos = [];
        foreach ($files as $file) {
            $videos[] = new M1905File($file['video_url'], $file['name'], $file['cdn']);
        }

        $dl = new Downloader("www.baidu.com");
        $dl->download(...$videos);
        $dl->start();

        return static::SUCCESS;
    }
}