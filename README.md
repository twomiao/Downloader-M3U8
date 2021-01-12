##### Downloader M3U8：
<img src="https://img-blog.csdnimg.cn/20201213145124851.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L20wXzM3MDgyOTYy,size_16,color_FFFFFF,t_70" width="750" height="500" alt="downloading"/>

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

* PHP
* Swoole

### Doc

 推荐使用 [composer](https://www.phpcomposer.com/) 安装。

```
  git clone https://github.com/twomiao/Downloader-M3U8.git
  composer install (依赖PSR4)
```

Downloader M3U8目录结构：
```
|-- Downloader-M3U8
    |-- Downloader 
        |-- Runner  库代码 
        |-- Command  自定义命令 
        |-- Parsers 解析ts规则
            |-- M1905.php -> www.1905.com
            |-- ..... 更多脚本文件
            |-- ..... 更多脚本文件
    |-- vendor composer autoload 
```

 启动 Downloader M3U8：

```
  $> cd ***/Downloader-M3U8/Downloader
  $> php Downloader.php start
```

#### 自定义规则：
```
<?php
namespace Downloader\Parsers;

use Downloader\Runner\MovieParser;

class YouKu extends MovieParser
{
    /**
     * @param $m3u8Url string 视频文件信息
     * @param $movieTs  string ts文件名称
     * @return string  返回完整ts视频地址
     */
    protected function parsedTsUrl(string $m3u8Url, string $movieTs): string
    {
        $url = str_replace("index.m3u8", "", $m3u8Url);

        return "{$url}{$movieTs}";
    }
}
```
#### 解密视频：
```
<?php

namespace Downloader\Runner\Middleware;

use Downloader\Runner\HttpClient;
use Downloader\Runner\Middleware\Data\Mu38Data;
use Downloader\Runner\RetryRequestException;
use League\Pipeline\StageInterface;

/**
 * 解密AES 视频
 * Class AesDecryptMiddleware
 * @package Downloader\Runner\Middleware
 */
class AesDecryptMiddleware implements StageInterface
{
    /**
     * @param Mu38Data $data
     * @return mixed
     */
    public function __invoke($data)
    {
        return $data->getRawData();
    }
}



use Downloader\Runner\Middleware\Data\Mu38Data;
use League\Pipeline\StageInterface;

/**
 * 解密RSA视频
 * Class RsaDecryptMiddleware
 * @package Downloader\Runner\Middleware
 */
class RsaDecryptMiddleware implements StageInterface
{
    /**
     * Process the payload.
     *
     * @param Mu38Data $data
     *
     * @return mixed
     */
    public function __invoke($data)
    {
        return $data;
    }
}
```

#### php Downloader.php start：
```
<?php declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Swoole\Runtime;
use Downloader\Runner\Downloader as Downloader;
use Symfony\Component\Console\Application;
use Downloader\Command\StartCommand;

\Co\run(function () {
    Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);

    $application = new Application('Downloader-M3u8', Downloader::VERSION);
    $application->setAutoExit(false);

    $application->add(new StartCommand());
    $application->run();
});
```

#### StartCommand：
```
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
                "https://m3u8i.vodfile.m1905.com/202101121627/67b3778169a648f8ef1b83f26832470a/movie/2014/07/08/m2014070882MYZ4QYL20IY6US/m2014070882MYZ4QYL20IY6US-535k.m3u8",
//                "https://youku.com-movie-youku.com/20181028/1275_c4fb695f/1000k/hls/index.m3u8",
            ], array(
                new AesDecryptMiddleware,
                new RsaDecryptMiddleware
            ))
            ->setMovieParser(new Hua(), [
//                "https://m3u8i.vodfile.m1905.com/202011220309/972a4a041420ecca90901d33fa2086ee/movie/2017/06/15/m201706152917FI77DD7VW2PA/AF9889E7AAB81F8C1AE5615AD.m3u8"
            ])
            ->start();

        return Command::SUCCESS;
    }
}
```

### License

Apache License Version 2.0, http://www.apache.org/licenses/
