<?php
namespace Downloader\Runner;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class ExceptionHandler
{
    protected $container;

    /**
     * @var LoggerInterface $logger
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        set_exception_handler([$this, 'record']);
        $this->container = $container;
        $this->logger = $this->container->get(LoggerInterface::class);
    }

    /**
     * @param $e \Throwable
     */
    public function record($e)
    {
        // 具体根据异常分类记录不同类型
        if ($e instanceof \Error) {
            $this->logger->error($e->getMessage());
        } elseif ($e instanceof DownloaderException) {
            $this->logger->debug($e->getMessage());
        } else {
            $this->logger->notice($e->getMessage());
        }
    }
}