<?php
declare(strict_types=1);
namespace Downloader\Runner;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Cmd
{
    private string $level;

    private OutputInterface $output;
    private InputInterface $input;

    public function __construct(InputInterface $input, OutputInterface $output, string $level)
    {
        $this->level = $level;
        $this->output = $output;
        $this->input = $input;
    }

    public function level(string $level)
    {
        $this->level = $level;
        return $this;
    }

    public function print(string $message): void
    {
        $format = [
            'date' => date('Y-m-d H:i:s'),
            'app_name' => Downloader::APP_NAME,
            'level' => strtoupper($this->level),
            'message' => $message
        ];
        $message = sprintf(static::format(), $format['date'], $format['app_name'], $format['level'], $format['message']);

        $level = strtoupper($this->level);
        switch ($level) {
            case 'INFO':
                $message = "<fg=green>{$message}</>";
                break;
            case 'WARN':
                $message = "<fg=yellow>{$message}</>";
                break;
            case 'ERROR':
                $message = "<fg=red>{$message}</>";
                break;
        }
        $this->output->writeln($message);
    }

    public function env(): void
    {
        $format = [
            'php' => phpversion(),
            'swoole' => swoole_version(),
            'os' => 'linux',
            'app_name' => Downloader::appName(Downloader::VERSION)
        ];

        $message = sprintf(static::formatEnv(), $format['php'], $format['swoole'], $format['os'], $format['app_name']);
        $this->output->writeln($message);
    }

    private static function format(): string
    {
        // 2021-09-21 | Downloader | INFO |  start request url .....
        return "%s | %s | %s | %s";
    }

    private static function formatEnv(): string
    {
        // Swoole:5.1 | PHP:7.4 | Downloader-M3U8 VERSION 201
        return "PHP:%s | Swoole:%s | OS:%s | %s";
    }
}