##### Downloader M3U8：
<img src="https://img-blog.csdnimg.cn/20210912201957312.png?x-oss-process=image/watermark,type_ZHJvaWRzYW5zZmFsbGJhY2s,shadow_50,text_Q1NETiBAdHdvbWlhbw==,size_20,color_FFFFFF,t_70,g_se,x_16" height="255" width="1200"/>
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
   * Pcntl+MultiCurl 也可以实现。
  
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
    |-- Runner  Downloader-M3U8 实现代码 
    |-- Command  启动命令 
    |-- Parsers 存放解析规则类
        |-- M1905.php -> www.1905.com
        |-- ..... 更多脚本文件
        |-- ..... 更多脚本文件
    |-- vendor composer autoload 
```

 启动 Downloader M3U8：

```
  $> cd 工作目录
  $> php Downloader.php start
```

#### 自定义解析规则：
```
<?php declare(strict_types=1);
namespace Downloader\Parsers;

use Downloader\Runner\Contracts\DecodeVideoInterface;
use Downloader\Runner\Downloader;
use Downloader\Runner\FileException;
use Downloader\Runner\FileM3u8;
use Downloader\Runner\VideoParser;
use Psr\Log\LoggerInterface;

class M1905 extends VideoParser implements DecodeVideoInterface
{
    /**
     * 下载文件命名
     * @param string $m3u8Url
     * @return string
     */
    protected function filename(string $m3u8Url): string
    {
        return basename(dirname($m3u8Url, 3));
    }

    /**
     * 获取完整ts 地址
     * @param string $m3u8FileUrl
     * @param string $partTsUrl
     * @return string
     */
    public function tsUrl(string $m3u8FileUrl, string $partTsUrl): string
    {
        return dirname($m3u8FileUrl) . '/' . $partTsUrl;
    }

    /**
     * 解码视频
     * @param FileM3u8 $fileM3u8
     * @param string $data
     * @param string $remoteTsUrl
     * @return string
     * @throws FileException
     */
    public static function decode(FileM3u8 $fileM3u8, string $data, string $remoteTsUrl): string
    {
        $method = $fileM3u8->getDecryptMethod();
        $key = $fileM3u8->getKey();

        $data = \openssl_decrypt($data, $method, $key, OPENSSL_RAW_DATA);
        if ($data === false) {
            Downloader::getContainer(LoggerInterface::class)->error(
                sprintf("网络地址 %s, 尝试解密方式和秘钥 [%s] - [%s] 解密失败!", $remoteTsUrl, $method, $key)
            );
            throw new FileException(
                sprintf("失败网络地址 %s, 尝试解密方式和秘钥 [%s] - [%s] 解密失败!", $remoteTsUrl, $method, $key)
            );
        }
        return $data;
    }

    /**
     * 秘钥KEY
     * @param FileM3u8 $fileM3u8
     * @return string
     */
    public static function key(FileM3u8 $fileM3u8): string
    {
        return file_get_contents(dirname((string)$fileM3u8) . '/' . $fileM3u8->getKeyFile());
    }

    /**
     * 加密方式
     * @param FileM3u8 $fileM3u8
     * @return string
     */
    public static function method(FileM3u8 $fileM3u8): string
    {
        return 'aes-128-cbc';
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
use Downloader\Command\StartCommand;
use function Swoole\Coroutine\run;

// 下载根目录
define('DOWNLOAD_DIR', __DIR__ . '/../Downloader');

run(function () {
    Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);

    $application = new Application(Downloader::APP_NAME, Downloader::VERSION);
    $application->setAutoExit(false);

    $application->add(new StartCommand());
    $application->run();
});
```

#### M1905Command：
```
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
            ->addOption('max_workers', 'M', InputArgument::OPTIONAL, '下载任务，使用的协程池数量', 45)
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
            // ......... 
        ];

        $max_workers = (int)$io_input->getOption('max_workers');
        $downloader = new Downloader($this->container, $io_input, $io_out, $max_workers);
        $downloader->addParser(new M1905())->addTasks($m3u8s);
        $downloader->start();
        return Command::SUCCESS;
    }
}
```

### License

Apache License Version 2.0, http://www.apache.org/licenses/
