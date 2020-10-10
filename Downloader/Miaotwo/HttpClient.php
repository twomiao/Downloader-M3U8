<?php
namespace Downloader\Miaotwo;

/************************************
 * 有时间在替换这破CURL吧
 ************************************/
class HttpClient
{
    public static function get($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        $respCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $output = curl_exec($ch);
        if ($output === false) {
            $curlError = curl_error($ch);
//            Logger::create()->error("{$curlError}, resp code:{$respCode} url addr：{$url}", "[ Error ] ");
        }
        curl_close($ch);
        return $output;
    }
}