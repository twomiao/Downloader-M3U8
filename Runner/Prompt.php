<?php
declare(strict_types=1);
namespace Downloader\Runner;

use Laravel\Prompts\Note;
use Symfony\Component\Console\Output\StreamOutput;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

final class Loading
{
    private string $msg;
    private int $num = 0;
    // "加载用时(%d)秒 %s\r"
    public function __construct(string $msg)
    {
        $this->msg = $msg;
        Note::setOutput(new StreamOutput(fopen("php://output", "r")));
    }
    public function display(): void
    {
        $num = $this->num++;
        ob_start();
        info(sprintf(date("Y-m-d H:i:s") . " " . $this->msg, $num,
        $num % 2==0 ? "卍卍卍":"卐卐卐"));
        $msg = ob_get_contents();
        ob_end_clean();
        echo str_replace("\n", "", $msg)."\r";
    }
}

final class Prompt
{
    private static array $map = [];

    public static function loading(string $msg)
    {
        $loading = new Loading($msg);
        $tid = \Swoole\Timer::tick(1000, static fn () => $loading->display());
        static::$map[$tid] = $tid;
        return $tid;
    }

    public static function stop(int $tid): bool
    {
        if (!isset(static::$map[$tid])) {
            return true;
        }
        unset(static::$map[$tid]);
        return \Swoole\Timer::clear($tid);
    }
}
