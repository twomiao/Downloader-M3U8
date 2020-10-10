
### Downloader M3U8：
   ![m1905.php 启动UI](https://github.com/twomiao/Downloader-M3U8/blob/main/start.png)

### M3U8简介：
> m3u8准确来说是一种索引文件，使用m3u8文件实际上是通过它来解析对应的放在服务器上的视频网络地址，从而实现在线播放。使用m3u8格式文件主要因为可以实现多码率视频的适配，视频网站可以根据用户的网络带宽情况，自动为客户端匹配一个合适的码率文件进行播放，从而保证视频的流畅度。

### 为何开发 Downloader M3U8下载器？
1. 因为M3U8格式特殊性，普通一个500MB的视频文件，可能切分出上千个视频片段。
2. 这种视频文件，通过迅雷是无法现在下载自动合并。
3. 如果使用一个脚本文件合并，那么下载不同网站规则不用导致管理及其混乱。
### 主要技术

* Downloader M3U8 优点: 
   * 支持批量不同视频网站M3U8视频文件下载
   * 支持自定义不同视频网站规则，重点是解决上面第3点
   * Swoole 协程并发下载，能节省数倍的时间
  
### 环境要求

* PHP
* Swoole
* Linux

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
        |-- Miaotwo 
        |-- Movies  
            |-- m1905.php -> www.1905.com
            |-- ..... 更多脚本文件
            |-- ..... 更多脚本文件
        |-- Parsers 解析ts规则
            |-- M1905.php -> www.1905.com
            |-- ..... 更多脚本文件
            |-- ..... 更多脚本文件
    |-- vendor composer autoload 
```

 启动 Downloader M3U8：

```
  $> cd 磁盘目录/Downloader/Movies/
  $> php m1905.php (通过网站名称定义脚本名称最好), m1905 电影网站
```

如果你足够幸运的话,那么将会看到如下界面：

```
root@LAPTOP-8LA5CDLH:/mnt/c/Users/twomiao/desktop/Downloader/Downloader/Movies# php m1905.php
  ___                          _                   _                 __  __   ____         ___
 |   \   ___  __ __ __  _ _   | |  ___   __ _   __| |  ___   _ _    |  \/  | |__ /  _  _  ( _ )
 | |) | / _ \ \ V  V / | ' \  | | / _ \ / _` | / _` | / -_) | '_|   | |\/| |  |_ \ | || | / _ \
 |___/  \___/  \_/\_/  |_||_| |_| \___/ \__,_| \__,_| \___| |_|     |_|  |_| |___/  \_,_| \___/

 特点：运行平台Linux系统 - 协程并发 - 高速下载M3U8视频 - 自定义并发速度 - 自定义下载任务.

 启动：2020-10-10 17:16:48

 环境：Swoole:4.5.4, PHP: v7.3.20, Os: Linux, Downloader: v1.0
.
 [ Found ] 分析M3U8地址获取到：1339个文件
 [ Downloading ] 正在下载数据: [==================================================] 100%, 网速: 5.4MB/s, 文件数量: 1339/1339个
 [ Saving ] 视频保存进度: 100%
 [ Done ] 下载数据完成: 1339/1339个, 文件大小: 423.8MB.
 [ Done ] 视频下载成功,已保存到本地: /mnt/c/Users/twomiao/desktop/download/1602321536.mp4.
 [ Done ] 下载完成用时：2.28 分钟

```

### 可运行案例：
```
<?php
namespace Downloader\Parsers;

use Downloader\Miaotwo\MovieParserInterface;

/**
 * 网站地址：https://www.1905.com/
 * 1905电影网解析规则
 * 特别说明：这是一个完整可运行的案例
 * Class M1905
 * @package Downloader\Parsers
 */
class M1905 implements MovieParserInterface
{
    /**
     * 返回一个完整的Ts视频片段地址
     * @param $m3u8Url M3U8地址
     * @param $movieTs M3U8文件中ts地址
     * @return string
     */
    public function parsedTsUrl($m3u8Url, $movieTs): string
    {
        // 由于地址不够完整,需要自行处理返回一个完整地址
        $length = strrpos($m3u8Url, '/') + 1;
        return substr($m3u8Url, 0, $length) . $movieTs;
    }
}
```

### License

Apache License Version 2.0, http://www.apache.org/licenses/
