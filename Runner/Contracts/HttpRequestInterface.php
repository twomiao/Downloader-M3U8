<?php declare(strict_types=1);
namespace Downloader\Runner\Contracts;

use Downloader\Runner\Response;

interface HttpRequestInterface
{
    /**
     * @param string $url  目标主机
     * @param array $data  客户端发送的数据包
     * @param string $method  请求方式
     * @return Response|null 服务器返回的数据包
     */
    public function send(string $url, array $data = [], $method = 'GET'): ?Response;
}