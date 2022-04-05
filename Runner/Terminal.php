<?php
declare(strict_types=1);
namespace Downloader\Runner;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Terminal
{
    private string $level;

    private ?OutputInterface $output = null;
    private ?InputInterface $input = null;

    public function __construct(InputInterface $input,
                                OutputInterface $output, string $level) {
        $this->level = $level;
        $this->output = $output;
        $this->input = $input;
    }

    public function level(string $level) {
        $this->level = $level;
        return $this;
    }

    public function getOutput() : OutputInterface {
        return $this->output;
    }

    public function input(): InputInterface {
        return $this->input;
    }

    public function print(string $message): void
    {
        $format = [
            'date' => date('Y-m-d H:i:s'),
            'app_name' => Downloader::PROGRAM_NAME,
            'level' => strtoupper($this->level),
            'message' => $message
        ];
        $message = sprintf(self::format(), $format['date'], $format['app_name'], $format['level'], $format['message']);

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

    public function message(): void
    {
        $row = [
            'name' => Downloader::PROGRAM_NAME,
            'version' => Downloader::VERSION,
            'php' => phpversion(),
            'swoole' => swoole_version(),
            'platform' => 'linux',
        ];

        $tableStatistics = new Table($this->output);
        $tableStatistics->setHeaders(['程序名称', '当前版本','PHP 版本', 'Swoole 版本', '运行平台']);
        $tableStatistics->setRows([$row]);
        $tableStatistics->render();
    }

    private static function format(): string
    {
        // 2021-09-21 | Downloader | INFO |  start request url .....
        return "%s | %s | %s | %s";
    }
}