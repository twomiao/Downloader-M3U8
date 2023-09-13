<?php declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use Downloader\Command\M1905;
use Downloader\Command\M1906;
use Symfony\Component\Console\Application;
use Swoole\Coroutine;

ini_set('memory_limit', '4048M');

Swoole\Coroutine\run(function() {
    Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL | SWOOLE_HOOK_CURL]);
    $application = new Application();
    $application->setAutoExit(false);
    $application->add(new M1906());
    $application->add(new M1905());
    $application->run();
});
