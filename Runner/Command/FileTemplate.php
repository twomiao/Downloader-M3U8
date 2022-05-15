<?php
namespace Downloader\Runner\Command;

use Downloader\Runner\Downloader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FileTemplate extends Command
{
    /**
     * 模板文件名称
     * @var string
     */
   public const FILENAME = 'download-m3u8.json';

    protected function configure()
    {
        $this->setName('file-tpl')
            ->addArgument('path', InputArgument::OPTIONAL, '文件下载模板', \getcwd())
            ->addOption('count', 'c', InputArgument::OPTIONAL,'模板中文件数量',2)
            ->setDescription('文件下载模板')
            ->setHelp('php Downloader.php [Command] [/mnt/c/]');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // 模板数量
        $count     = $input->getOption('count');
        // 目录
        $path      = $input->getArgument('path');

        if (!is_int($count) && $count < 1) {
            $output->writeln("<error>类型不合法:{$count}</error>");
            return Command::FAILURE;
        }

        if (!is_dir($path) && !mkdir($path, true, 0777)) {
            $output->writeln("<error>创建目录失败:{$path}</error>");
            return Command::FAILURE;
        }

        // 文件名称
        $localFile = rtrim($path, '\/').DIRECTORY_SEPARATOR.self::FILENAME;
        if(is_file($localFile))  {
            $output->writeln("<error>文件已经存在:{$localFile}</error>");
            return Command::FAILURE;
        }

        $data =  \json_encode(
            $templates = self::fileTemplate($count),
            JSON_UNESCAPED_UNICODE);
        if($data === false) {
            $php_errormsg = \json_last_error_msg();
            $output->writeln("<error>json 语法错误: {$php_errormsg}</error>");
            return Command::FAILURE;
        }

        \file_put_contents($localFile, $data);
        return Command::SUCCESS;
    }

    protected static function fileTemplate($count = 3) {
        $template = [
            'name'    => Downloader::PROGRAM_NAME,
            'version' => Downloader::VERSION,
//            'files' => []
        ];
        $files = [];
        while($count-- > 0) {
            $files['files'][] =   [
                'filename' => '',
                'm3u8_url' => '',
                'url_prefix' => '',
                'suffix' => 'mp4',
                'key' => '',
                'method' => '',
                'put_path' => '',
                'decrypt_class' => ''
            ];
        }
        return array_merge($template,$files);
    }
}