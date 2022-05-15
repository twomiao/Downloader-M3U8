##### Downloader M3U8：
<img src="https://img-blog.csdnimg.cn/20210928125137100.png?x-oss-process=image/watermark,type_ZHJvaWRzYW5zZmFsbGJhY2s,shadow_50,text_Q1NETiBAdHdvbWlhbw==,size_20,color_FFFFFF,t_70,g_se,x_16" height="255" width="1200"/>
<br/>

#### M3U8简介：
> m3u8准确来说是一种索引文件，使用m3u8文件实际上是通过它来解析对应的放在服务器上的视频网络地址，从而实现在线播放。使用m3u8格式文件主要因为可以实现多码率视频的适配，视频网站可以根据用户的网络带宽情况，自动为客户端匹配一个合适的码率文件进行播放，从而保证视频的流畅度。

#### 为何实现 Downloader M3U8？
1. 因为M3U8格式特殊性，普通一个500MB的视频文件可能切分出上千个视频片段。
2. 这种视频文件，通过迅雷是无法现在下载全部ts文件自动合并。
3. 如果使用脚本文件下载合并，网站不同规则不同导致管理及其混乱。

#### Downloader M3U8 优点: 
   * 支持批量不同视频网站M3U8视频文件下载。
   * 支持自定义不同视频网站规则，重点是解决上面第3点。
   * 协程并发下载，能节省数倍的时间。
   * Ctrl+C 暂停下载进程 再按Ctrl+C 恢复下载进程。
   * SIGTERM 信号完成平滑停止下载进程，防止SIGKILL导致数据丢失。
   * Pcntl+MultiCurl 也可以实现。
   * 增加JSON文件模板下载文件。
  
### 环境要求

* PHP 7.4
* Swoole 4

### Doc

 推荐使用 [composer](https://www.phpcomposer.com/) 安装。

```
  git clone https://github.com/twomiao/Downloader-M3U8.git
  composer install (依赖PSR4)
```

Downloader M3U8目录结构：
```
|-- Downloader-M3U8
    |-- Runner   Downloader-M3U8 核心实现 
    |-- Command  添加自定义启动命令 
    |-- Files    文件解析规则
        |-- M1905.php -> www.1905.com
        |-- ..... 更多脚本文件
        |-- ..... 更多脚本文件
    |-- vendor composer autoload 
    |-- Downloader.php 注册启动命令
```

 启动 Downloader M3U8：

```
  $> cd 工作目录
  $> php Downloader.php start
```

#### 生成下载模板, 运行命令 php Downloader.php file-tpl：

```
{
  "name": "Downloader-M3u8",
  "version": "3.0",
  "files": [
    {
      "filename": "花与棋",
      "m3u8_url": "https://m3u8i.vodfile.m1905.com/202205160334/ad0f002d1da17bf8a582c246b3ef29eb/movie/2018/10/25/m201810250GDNYALQQX19HR1P/145502A2CA0ADBA349064BD2E.m3u8",
      "url_prefix": "https://m3u8i.vodfile.m1905.com/202205160334/ad0f002d1da17bf8a582c246b3ef29eb/movie/2018/10/25/m201810250GDNYALQQX19HR1P",
      "put_path": "/mnt/c/users/twomiao/desktop/Downloader",
      "suffix": "mkv"
    },
    {
      "filename": "把妈妈嫁出去",
      "m3u8_url": "https://m3u8i.vodfile.m1905.com/202205160333/0579c3c3d34210270eb46594f08f4f39/movie/2017/10/11/m20171011INJ4QIAI1GJIZ18D/2C50A79A9292E6D423E015FFE.m3u8",
      "url_prefix": "https://m3u8i.vodfile.m1905.com/202205160333/0579c3c3d34210270eb46594f08f4f39/movie/2017/10/11/m20171011INJ4QIAI1GJIZ18D",
      "put_path": "/mnt/c/users/twomiao/desktop/Downloader",
      "suffix": "mp4"
    },
    {
      "filename": "举起手来",
      "m3u8_url": "https://m3u8i.vodfile.m1905.com/202205160332/dd304687cf6546cf4baadc1725690777/movie/2014/07/10/m20140710LP24JW3CN8IB86H6/m20140710LP24JW3CN8IB86H6.m3u8",
      "url_prefix": "https://m3u8i.vodfile.m1905.com/202205160332/dd304687cf6546cf4baadc1725690777/movie/2014/07/10/m20140710LP24JW3CN8IB86H6",
      "put_path": "/mnt/c/users/twomiao/desktop/Downloader",
      "suffix": "mkv"
    }
  ]
}
```

```
<?php
namespace Downloader\Files\Decrypt;

use Downloader\Runner\Contracts\DecryptFileInterface;
use Downloader\Runner\TransportStreamFile;

class M1906DecryptFile implements DecryptFileInterface
{
    /**
     * 6a1177f9ceedcdcf
     * @var string $key
     */
    private string $key;

    /**
     * aes-128-cbc
     * @var string $method
     */
    private string $method;

    /**
     * 初始化完成
     * M1906 constructor.
     * @param string $key
     * @param string $method
     */
    public function __construct(string $key, string $method)
    {
        $this->key = $key;
        $this->method = $method;
    }

    public function decrypt(string $fileData, TransportStreamFile $transportStreamFile): string
    {
        return openssl_decrypt($fileData, $this->method, $this->key, OPENSSL_RAW_DATA);
    }
}
```

#### 加密视频代码,额外实现DecryptFileInterface 解密接口：
```
<?php
namespace Downloader\Files\Decrypt;

use Downloader\Runner\Contracts\DecryptFileInterface;
use Downloader\Runner\TransportStreamFile;

class M1906DecryptFile implements DecryptFileInterface
{
    /**
     * 6a1177f9ceedcdcf
     * @var string $key
     */
    private string $key;

    /**
     * aes-128-cbc
     * @var string $method
     */
    private string $method;

    /**
     * 初始化完成
     * M1906 constructor.
     * @param string $key
     * @param string $method
     */
    public function __construct(string $key, string $method)
    {
        $this->key = $key;
        $this->method = $method;
    }

    public function decrypt(string $fileData, TransportStreamFile $transportStreamFile): string
    {
        return openssl_decrypt($fileData, $this->method, $this->key, OPENSSL_RAW_DATA);
    }
}
```

#### 生成Url实例,额外实现GenerateUrlInterface接口：
```
<?php
namespace Downloader\Files\Url;

use Downloader\Runner\Contracts\GenerateUrlInterface;
use Downloader\Runner\TransportStreamFile;

class UrlGenerate implements GenerateUrlInterface
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function generateUrl(TransportStreamFile $file): string
    {
        return ltrim($this->url, '\/').'/'.$file->getUrl();
    }
}
```

#### php Downloader.php m1905：
```
<?php declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use Swoole\Runtime;
use Downloader\Runner\Downloader;
use Symfony\Component\Console\Application;
use Downloader\Command\M1905Command;
use function Swoole\Coroutine\run;

define('DOWNLOAD_DIR', __DIR__ . '/../Downloader');

run(function () {
    Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);

    $application = new Application(Downloader::PROGRAM_NAME, Downloader::VERSION);
    $application->setAutoExit(false);
    $application->add(new M1905Command()); // 下载 https://www.1905.com/ 视频
//    $application->add(new OtherCommand()); // 其它网站视频

    $application->run();
});
```

#### M1905Command：
```
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
        $downloader->setConcurrencyValue(12);
        foreach ($files as $jsonFile)
        {
            try
            {
                // 创建视频为mp4格式
                $file = new FileM3u8($jsonFile['m3u8_url'], $jsonFile['put_path']);
                $file->saveAs($jsonFile['filename'], $jsonFile['suffix']);
//                $file->setDecryptFile(new M1906DecryptFile($jsonFile['key'], $jsonFile['method']));
                $file->setGenerateUrl(new UrlGenerate($jsonFile['url_prefix']));
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
```

### License

Apache License Version 2.0, http://www.apache.org/licenses/
