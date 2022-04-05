<?php declare(strict_types=1);
namespace Downloader\Runner\Contracts;

use Downloader\Runner\Response;

interface HttpRequestInterface
{
    public function send(array $data = [], $method = 'GET'): Response;
}