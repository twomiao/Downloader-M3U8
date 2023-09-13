<?php
declare(strict_types=1);

namespace Downloader\Runner;

final class Response
{
    private string $body;
    private array $headers;
    private int $statusCode;

    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusCode() : int {
        return $this->statusCode;
    }

    public function getHeader($key = null) : string
    {
        return $this->headers[$key] ?? '';
    }

    public function getHeaders() :array
    {
        return $this->headers;
    }

    public function getBody() : string {
        return $this->body;
    }
}