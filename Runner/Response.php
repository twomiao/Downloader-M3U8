<?php
declare(strict_types=1);

namespace Downloader\Runner;

final class Response
{
    private array $resp_header = [];
    private string $resp_data = '';
    private array $options = [];

    public function __construct(array $resp_header, string $resp_data, array $options)
    {
        $this->resp_header = $resp_header;
        $this->resp_data = $resp_data;
        $this->options = $options;

        // 开启header
        $openHeader = $this->options['CURLOPT_HEADER'];
        if ($openHeader) {
            // header+bpdy
            if ($this->options['CURLOPT_NOBODY'] === false) {
                [$resp_headers, $this->resp_data] = explode("\r\n\r\n", $resp_data, 2);
            } else {
                // no body
                $resp_headers = $resp_data;
                $this->resp_data = '';
            }
            $this->resp_header = static::parserRespHeader($resp_headers);
        }
    }

    private static function parserRespHeader(string $resp_headers)
    {
        $headers = explode("\r\n", $resp_headers);

        $header_arr = [];
        foreach ($headers as $idx => $header) {
            if (empty($header)) {
                continue;
            }

            // HTTP 1.1 200 OK
            if (!$idx) {
                // status_code = 200,
                //status_msg = ok
                //http_protocol = 1.1
                [$protocol, $protocol_version, $status_code, $status_msg] =
                    explode(' ', str_replace('/', ' ', $header));

                $header = sprintf(
                    "http_protocol=%s&protocol_version=%s&status_code=%s&status_msg=%s",
                    $protocol, $protocol_version, $status_code, $status_msg
                );
            }

            $header = str_replace([": ", '"'], ["=", ""], $header);

            $header_arr[] = strtolower($header);
        }

        parse_str(implode('&', $header_arr), $header_arr);
        return $header_arr;
    }

    public function getData()
    {
        return $this->resp_data;
    }

    public function getHeaders(string $name = '')
    {
        if ($name) {
            return $this->resp_header[$name] ?? '';
        }
        return $this->resp_header;
    }
}