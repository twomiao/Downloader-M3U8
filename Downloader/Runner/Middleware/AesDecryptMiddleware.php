<?php

namespace Downloader\Runner\Middleware;

use Downloader\Runner\HttpClient;
use Downloader\Runner\Middleware\Data\Mu38Data;
use Downloader\Runner\RetryRequestException;
use League\Pipeline\StageInterface;

/**
 * 解密AES 视频
 * Class AesDecryptMiddleware
 * @package Downloader\Runner\Middleware
 */
class AesDecryptMiddleware implements StageInterface
{
    /**
     * @param Mu38Data $data
     * @return mixed
     */
    public function __invoke($data)
    {

        return $data;
    }

    /**
     * 获取加密KEY
     * @param string $data
     * @return array|null
     */
    protected function getDecryptionParameters(string $data): ?array
    {
        $keyInfo = $this->getParsekey($data);
        if ($keyInfo) {
            /**
             * @var $client HttpClient
             */
            $client = $this->container->get('client');

            try {
                $client->get()->request($keyInfo['keyUri']);
                if ($client->isSucceed()) {
                    $keyInfo['key'] = $client->getBody();
                    return $keyInfo;
                }
            } catch (RetryRequestException $e) {
                throw $e;
            }
        }

        return [];
    }

    protected function getParsekey($data)
    {
        $doesIt = preg_match("#\#EXT-X-KEY:METHOD=(.*?)\#EXTINF#is", $data, $matches);

        if ($doesIt) {
            $line = trim($matches[1]);
            $result = explode(',', $line);
            $method = $result[0];
            preg_match('/URI="(.*?)"/is', $result[1], $keyUri);
            $keyUri = $keyUri[1];

            switch (count($result)) {
                case 2:
                    return compact('method', 'keyUri');
                case 3:
                    $vi = $result[2];
                    return compact('method', 'keyUri', 'vi');
                default:
                    break;
            }
        }
        return [];
    }
}
