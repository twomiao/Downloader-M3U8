##### Downloader M3U8：
<img src="https://img-blog.csdnimg.cn/20210912201957312.png?x-oss-process=image/watermark,type_ZHJvaWRzYW5zZmFsbGJhY2s,shadow_50,text_Q1NETiBAdHdvbWlhbw==,size_20,color_FFFFFF,t_70,g_se,x_16" height="400" alt="正常下载完成"/>
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
<?php
namespace Downloader\Parsers;

use Downloader\Runner\Parser;

class Hua extends Parser
{
    static function tsUrl(string $m3u8FileUrl, string $partTsUrl): string
    {
        return dirname($m3u8FileUrl) . '/' . $partTsUrl;
    }

    static function fileName(string $m3u8FileUrl): string
    {
        return basename(dirname($m3u8FileUrl, 1));
    }  

    /**
     * 默认解密当前视频文件
     * @param string $data
     * @throws FileException
     * @return string
     */
    public static function decodeData(string $data): string
    {
        /**
         ********************************
         * 默认解密算法  aes-128-cbc
         ********************************
         *
         * 下载网络数据进行尝试解密   
         * 不满足需求 可以选择重写此方法
         *
         * ******************************
         */
        if (FileM3u8::$decryptKey && FileM3u8::$decryptMethod) {
            $data = \openssl_decrypt($data, FileM3u8::$decryptMethod, FileM3u8::$decryptKey, OPENSSL_RAW_DATA);
            if ($data === false) {
                throw new FileException(
                    // 尝试解密方式和秘钥 [aes-128-cbc] - [3423123ew12312]
                    sprintf("尝试解密方式和秘钥 [%s] - [%s] 解密失败!", FileM3u8::$decryptMethod, FileM3u8::$decryptKey)
                );
            }
        }
        return $data;
    }
}

```

#### php Downloader.php start：
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

#### StartCommand：
```
<?php
namespace Downloader\Command;

use Downloader\Parsers\Hua;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Downloader\Runner\Downloader;
use Pimple\Container as PimpleContainer;
use Downloader\Runner\ServiceProvider;

class StartCommand extends Command
{
    protected function configure()
    {
        $this->setName('start')
            ->setDescription('PHP 协程池超速下载M3U8视频.')
            ->setHelp('php downloader start');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = new PimpleContainer();
        $container->register(new ServiceProvider());

        $task_list = array(
            Hua::class => [    
//                'https://video.com/m3u8/3278/m3u8.m3u8',
//                'https://video.com/m3u8/3342/m3u8.m3u8'
            ],
//          YouKu::class => [
//                'https://video.com/m3u8/3278/m3u8.m3u8',
//                'https://video.com/m3u8/3342/m3u8.m3u8'
//           ],
        );

        $downloader = new Downloader($container, $input, $output);
        try {
            $downloader->addParsers($task_list);
        } catch (\ReflectionException $e) {}
        $downloader->start();

        return Command::SUCCESS;
    }
}
```

### License

Apache License Version 2.0, http://www.apache.org/licenses/
