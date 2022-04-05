<?php
declare(strict_types=1);

namespace Downloader\Runner;

final class Response
{
    private string $body = '';
    private array $header =  [];

    public function __construct(array $header, string $body)
    {
        $this->header = $header;
        $this->body = $body;
    }
    public function getHeader($key = null)
    {
        return $this->header[$key] ?? '';
    }

    public function getHeaders() :array
    {
        return $this->header;
    }

    public function getBody():string {
        return $this->body;
    }
}