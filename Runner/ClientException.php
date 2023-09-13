<?php
namespace Downloader\Runner;

use Exception;
use Swoole\Coroutine\Http\Client as SwooleClient;

class ClientException extends Exception {
    protected SwooleClient $client;

    public function __construct(SwooleClient $client)
    {
        parent::__construct(socket_strerror($client->errCode), $client->errCode);
    }
}