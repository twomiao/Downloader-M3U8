<?php

use Downloader\Runner\Log;
use Psr\Log\LoggerInterface;

$container = new \League\Container\Container();
$container->add(
    'color',
    \Colors\Color::class
);
$container->add(
    LoggerInterface::class,
    \Katzgrau\KLogger\Logger::class
)->addArgument(dirname(__DIR__) . '/../logs');

$container->add(
    'bar',
    \ProgressBar\Manager::class,
    false
)->addArguments([0,100,100]);

$container->add(
    'log',
    Log::class,
    false
)->addArgument($container);

$container->add('client',
    \Downloader\Runner\HttpClient::class,
    false
)->addMethodCall('setContianer', [$container]);

return $container;
