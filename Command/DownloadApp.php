<?php

namespace Downloader\Command;

use Downloader\Runner\Downloader;
use Pimple\Container;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadApp extends Downloader
{
    public function __construct(Container $container, InputInterface $input, OutputInterface $output, int $poolCount = 35)
    {
        parent::__construct($container, $input, $output, $poolCount);
    }
}