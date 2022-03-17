<?php

namespace wuxue107\request_proxy\proxy;

class ServerRequest
{
    /** @var RequestURI $uri */
    private $uri;
    private $method;
    private $headers = [];

    /** @var RequestBody */
    private $body;
    private $options;

    /**
     * @return RequestURI
     */
    public function getUri(): RequestURI
    {
        return $this->uri;
    }

    /**
     * @param RequestURI $uri
     */
    public function setUri(RequestURI $uri): void
    {
        $this->uri = $uri;
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param mixed $method
     */
    public function setMethod($method): void
    {
        $this->method = strtoupper($method);
    }

    public function isMethod($method){
        return $this->method = strtoupper($method);
    }

    public function isPost(){
        return $this->isMethod('POST');
    }

    public function isGet(){
        return $this->isMethod('GET');
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param mixed $body
     */
    public function setBody($body): void
    {
        $this->body = $body;
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }


    public function __construct()
    {
        $timeout = (float) ini_get('default_socket_timeout');
        $timeout = ($timeout < 10.0) ? 10.0 : $timeout;
        $this->options = [
            'http' => [
                'max_redirects' => 3,
                'follow_location' => 0,
                'ignore_errors' => true,
                'protocol_version' => 1.1,
                'request_fulluri' => false,
                'timeout' => $timeout,
            ],
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
                "allow_self_signed" => true,
            ],
        ];
    }

    public function setOptionHttpMaxRedirects($times = 3)
    {
        $this->options['http']['max_redirects'] = $times;
    }

    public function getOptionHttpMaxRedirects(){
        return $this->options['http']['max_redirects'];
    }

    public function setOptionHttpFollowLocation(bool $value = true)
    {
        $this->options['http']['follow_location'] = $value ? 1 : 0;
    }

    public function getOptionHttpFollowLocation()
    {
        return $this->options['http']['follow_location'] == 1;
    }

    public function setOptionsHttpTimeout($timeout = 10)
    {
        $this->options['http']['timeout'] = $timeout;
    }

    public function getOptionsHttpTimeout()
    {
        return $this->options['http']['timeout'];
    }

    public function setTimeout($timeout = 10){
        $this->setOptionsHttpTimeout($timeout);
    }

    public function getTimeout($timeout = 10){
        return $this->getOptionsHttpTimeout();
    }

    public function removeHeader($headerName)
    {
        $headerNames = func_get_args();
        $this->removeHeaders($headerNames);
    }

    public function removeHeadersWithoutWhileList($headerNames){

        foreach ($headerNames as &$name){
            $name = self::normalizeHeaderName($name);
        }
        unset($name);

        foreach(array_keys($this->headers) as $name) {
            if(!in_array($name, $headerNames)) {
                unset($this->headers[$name]);
            }
        }
    }

    public function removeHeaders(array $headerNames){
        foreach ($headerNames as $name){
            unset($this->headers[self::normalizeHeaderName($name)]);
        }
    }

    public function setHeaders(array $headers){
        foreach ($headers as $headerName => $headerValue){
            $this->setHeader($headerName,$headerValue);
        }
    }

    public function setHeader($name, $value)
    {
        $this->headers[self::normalizeHeaderName($name)] = $value;
    }

    public function getHeader($name){
        return $this->headers[self::normalizeHeaderName($name)]??null;
    }

    static function createFromGlobal()
    {
        $request = new ServerRequest();
        if (!empty($request->body)) {
            $request->method = 'POST';
        } else {
            $request->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        }

        $request->uri = RequestURI::fromRequest();


        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[self::normalizeHeaderName(substr($name, 5))] = $value;
            }
        }

        unset($headers['Host'], $headers['Connection']);
        $request->headers = $headers;

        if ($request->method == 'POST') {
            $request->body = RequestBody::createFromServerRequest($request);
        } else {
            $request->body = null;
        }

        return $request;
    }



    static function normalizeHeaderName($name)
    {
        return str_replace(' ', '-', ucwords(strtolower(str_replace(['_','-'], [' ',' '], $name))));
    }


    public function send(ServerResponse $response = null){
        if($response == null){
            $response = new ServerResponse();
        }

        if(in_array($this->getUri()->getScheme(),['http','https'])) {
            unset($http_response_header);
            $headerLines = [
                'Connection: close',
            ];

            $body = '';
            if($this->isPost() || $this->isMethod('PUT')){
                $body = (string) $this->getBody();
                $this->setHeader('Content-Length',strlen($body));
            }

            foreach($this->headers as $name => $value) {
                $headerLines[] = $name . ': ' . $value;
            }

            $this->setOptions(array_replace_recursive($this->getOptions() ?? [], [
                'http' => [
                    'method'  => $this->getMethod(),
                    'header'  => $headerLines,
                    'content' => $body,
                ],
            ]));

            $fullUrl = $this->getUri()->getFullUrl();
            $fp = @fopen($fullUrl, 'r', false, stream_context_create($this->getOptions()));

            if($fp !== false) {
                stream_set_timeout($fp, $this->getOptionsHttpTimeout());
                $response->setResource($fp);
                $meta = stream_get_meta_data($fp);
                $headers = $meta['wrapper_data'] ?? [];
                if(isset($headers[0])) {
                    $httpCode = explode(' ', $headers[0])[1] ?? '299';
                    $response->setCode((int) $httpCode);
                }
                $response->addHeaders($headers);
            }
        }else{
            $fullUrl = $this->getUri()->getFullUrl();
            $fp = @fopen($fullUrl, 'r', false);
            if($fp !== false) {
                $response->setResource($fp);
                $response->setCode(200);
            }else{
                $response->setCode(404);
            }
        }

        return $response;
    }

}
