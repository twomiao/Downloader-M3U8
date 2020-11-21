
##### Downloader M3U8：
   ![start.php 启动UI](https://img-blog.csdnimg.cn/2020112111430761.png?x-oss-process=image/watermark,type_ZmFuZ3poZW5naGVpdGk,shadow_10,text_aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L20wXzM3MDgyOTYy,size_16,color_FFFFFF,t_70)

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
        |-- Runner  实现代码 
        |-- Parsers 解析ts规则
            |-- M1905.php -> www.1905.com
            |-- ..... 更多脚本文件
            |-- ..... 更多脚本文件
    |-- vendor composer autoload 
```

 启动 Downloader M3U8：

```
  $> cd /mnt/c/Users/twomiao/desktop/Downloader-M3U8/Downloader/start.php
  $> php start.php 启动下载任务
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

    protected function getParsekey($data)
    {
        $data = parent::getParsekey($data);

        /**
         * array(2) {
         *["method"]=>
         *string(7) "AES-128"
         *["keyUri"]=>
         *string(7) "key.key"
         *}
         */
//        $keyUri = $data['keyUri'];
//        $data['keyUri'] =  "https://.......com/81820200424/GC0229379/1000kb/hls/{$keyUri}";

        return $data;
    }


}
```

#### 启动代码：
```
<?php declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Downloader\Runner\Downloader;
use Downloader\Runner\Decrypt\Aes128;
use Downloader\Parsers\YouKu;
use Downloader\Parsers\Hua;

\Co\run(function () {

    \Swoole\Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);

    $downloader = new Downloader(
        $container = require __DIR__ . '/Runner/Container.php',
        $config = [
            'output' => dirname(__DIR__) . '/../output2',
            'concurrent' => 25,
        ]
    );

    $downloader
        ->setMovieParser(new YouKu(), [
            "https://xigua-cdn.haima-zuida.com/20201024/16083_77f06fd4/1000k/hls/index.m3u8",
            "https://dalao.wahaha-kuyun.com/20201114/259_7e8e3c78/1000k/hls/index.m3u8"
        ], new Aes128())
        ->setMovieParser(new Hua(), [
            "https://m3u8i.vodfile.m1905.com/202011220309/972a4a041420ecca90901d33fa2086ee/movie/2017/06/15/m201706152917FI77DD7VW2PA/AF9889E7AAB81F8C1AE5615AD.m3u8"
        ], new Aes128())
        ->run();
});


```

### License

Apache License Version 2.0, http://www.apache.org/licenses/
