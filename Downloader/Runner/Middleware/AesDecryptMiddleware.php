<?php

namespace Downloader\Runner\Middleware;

use Downloader\Runner\HttpClient;
use Downloader\Runner\Middleware\Data\Mu38Data;
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
        return $this->decrypt($data->getAuthkeyUrl(), $data->getRawData());
    }

    protected function decrypt($authKeyUrl, $rawData)
    {
        [$authKey, $authMethod] = $this->parseKey($authKeyUrl);
        $data = openssl_decrypt($rawData, $authMethod, $authKey, OPENSSL_RAW_DATA);
        return $data;
    }

    protected function parseKey($authKeyUrl): ?array
    {
        preg_match('@(.*?),URI=\"(.*?)\"@Ui', $authKeyUrl, $res);

        $client = new HttpClient();

        $client->get()->request($res[2]);

        $authKey = $client->getBody();

        $result = array(
            $authKey,
            'aes-128-cbc'
        );

        return $result;
    }
}
