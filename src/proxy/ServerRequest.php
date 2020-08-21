<?php

namespace wuxue107\request_proxy\proxy;


class ServerRequest
{
    public $url;
    public $method;
    public $headers = [];
    public $body;
    public $options;

    public function __construct()
    {
        $timeout = (float) ini_get('default_socket_timeout');
        $timeout = ($timeout < 10.0) ? 10.0 : $timeout;
        $this->options =  [
            'http' => [
                'max_redirects'    => 3,
                'follow_location'  => 0,
                'ignore_errors'    => true,
                'protocol_version' => 1.1,
                'request_fulluri' => false,
                'timeout'          => $timeout,
            ],
            "ssl"  => [
                "verify_peer"      => false,
                "verify_peer_name" => false,
                "allow_self_signed" => true,
            ],
        ];
    }

    static function createFromGlobal()
    {
        $request = new ServerRequest();
        if(!empty($request->body)) {
            $request->method = 'POST';
        }
        else {
            $request->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        }

        $request->url = self::getFullUrl();
        $headers = self::getHeaders();
        unset($headers['Host'], $headers['Connection']);
        $request->headers = $headers;

        if($request->method == 'POST') {
            $request->body = file_get_contents('php://input');
        }
        else {
            $request->body = null;
        }

        return $request;
    }

    static function getHeaders()
    {
        $headers = [];
        foreach($_SERVER as $name => $value) {
            if(substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }

    static function getFullUrl(){
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }
}
