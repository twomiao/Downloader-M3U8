##### dl-m3u8：
<img src="https://img-blog.csdnimg.cn/d4bb29b0301341cc9739500f62b6fb60.png"  height="250" width="980"/>
<br/>

#### dl-m3u8 简介：
> m3u8准确来说是一种索引文件，使用m3u8文件实际上是通过它来解析对应的放在服务器上的视频网络地址，从而实现在线播放。主要还是复用，类似于一个下载框架；用户自己定义命令，一个命令对应一个下载网站；协程配合Channel进行通信，性能很好。

#### 为何实现 dl-m3u8？
1. 因为M3U8格式特殊性，普通一个500MB的视频文件可能切分出上千个视频片段。
2. 这种视频文件，通过迅雷是无法现在下载全部ts文件自动合并。
3. 如果使用脚本文件下载合并，网站不同规则不同导致管理及其混乱。

#### dl-m3u8 优点: 
   * 自定义下载规则也可方便后续复用。[√]
   * 协程并发下载，能节省许多时间。[√]
   * Ctrl+c安全停止下载程序，避免强制停止丢失数据。[√]
   * 增加JSON文件模板下载文件。[√]
   * 可定义一套CLI UI界面方便用户下载交互。[√]
   * 可自行改造源码，实现Sqlite本地数据库保存下载数据信息。[x]
  
### 环境要求

* PHP 8.1.13
* Swoole 5.0.1
* /usr/bin/ffmpeg (转换视频格式)

### Doc

 推荐使用 [composer](https://www.phpcomposer.com/) 安装。

```
  git clone https://github.com/twomiao/Downloader-M3U8.git
  composer install (依赖PSR4)
```
 启动 dl-m3u8：

```
  $> cd /Your path/Download-M3u8/
  $> ./dl-m3u8 m1905 /dl-m3u8/download/files
```

#### 加密文件实现加密接口，进行解密下载文件：
```
<?php declare(strict_types=1);
namespace Downloader\Files;

use Downloader\Runner\Contracts\EncryptedFileInterface;
use Downloader\Runner\FileM3u8;
use Downloader\Runner\FileSlice;

/**
 * @final
 * Class TestFile
 * @package Downloader\Files
 */
final class M1905File extends FileM3u8 implements EncryptedFileInterface
{
    /**
     * 下载域名
     * @var string $downloadDomainName
     */
    public static string $downloadDomainName = "www.1905.com";

    public function decrypt(string $data): string
    {
        // return openssl_decrypt($fileData, 'aes-128-cbc', '6a1177f9ceedcdcf', OPENSSL_RAW_DATA);
//        return openssl_decrypt($fileData, 'aes-128-cbc', '6a28df8dbb9eacfd', OPENSSL_RAW_DATA);
        return $data;
    }
    
    public function downloadCdnUrl(FileSlice $fileSlice) : string 
    {
        return "{$this->cdnUrl}/".$fileSlice->getPath();
    }
}
```

#### php dl-m3u8 m1905：
```
<?php declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use Downloader\Command\M1905;
use Downloader\Command\M1906;
use Symfony\Component\Console\Application;
use Swoole\Coroutine;

ini_set('memory_limit', '4048M');

Swoole\Coroutine\run(function() {
    Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL]);
    $application = new Application();
    $application->setAutoExit(false);
    $application->add(new M1906());
    $application->add(new M1905());
    $application->run();
});

```

#### M1905Command：
```
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
        $data = [];
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
         RUN: 

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

```

### License

Apache License Version 2.0, http://www.apache.org/licenses/
