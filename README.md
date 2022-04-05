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

#### 加密视频代码,额外实现DecryptFileInterface 解密接口：
```
<?php declare(strict_types=1);
namespace Downloader\Files;

use Downloader\Runner\Contracts\DecryptFileInterface;
use Downloader\Runner\Contracts\GenerateUrlInterface;
use Downloader\Runner\FileM3u8;
use Downloader\Runner\TransportStreamFile;

/**
 * 加密视频必须实现 DecryptFileInterface 解密接口
 * 未加密视频，只需实现 GenerateUrlInterface 接口
 * @final
 * Class TestFile
 * @package Downloader\Files
 */
final class TestFile extends FileM3u8 implements GenerateUrlInterface,DecryptFileInterface
{
    public static function generateUrl(TransportStreamFile $file): string
    {
       // return 'https://cdn.com/'.trim($file->getUrl());
       $url = $file->getFileM3u8()->getUrl();

       return dirname($url).'/'.trim($file->getUrl());
    }

    public function decrypt(string $fileData, FileM3u8 $fileM3u8): string
    {
        return openssl_decrypt($fileData, 'aes-128-cbc', '0547f389e9d8babb', OPENSSL_RAW_DATA);
    }
}

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
```

### License

Apache License Version 2.0, http://www.apache.org/licenses/
