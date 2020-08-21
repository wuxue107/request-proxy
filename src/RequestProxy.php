<?php

namespace wuxue107\request_proxy;

use wuxue107\request_proxy\proxy\ServerRequest;
use wuxue107\request_proxy\proxy\ServerResponse;

class RequestProxy
{
    private $stack = [];

    /**
     * @param callable $filter
     *
     * @return $this
     */
    public function addFilter(callable $filter)
    {
        $this->stack[] = $filter;
        return $this;
    }

    /**
     * 转发请求到指定连接
     *
     * @param ServerRequest $request
     *
     * @return ServerResponse
     */
    public function forward(ServerRequest $request = null)
    {

        $response = $this->send($request);
        $response->render();
        return $response;
    }

    /**
     * 发送请求
     *
     * @param ServerRequest|null $request
     *
     * @return ServerResponse
     */
    public function send(ServerRequest $request = null){
        if(is_null($request)) {
            $request = ServerRequest::createFromGlobal();
        }
        $response = new ServerResponse();
        unset($http_response_header);
        $sendRequest = function(ServerRequest $request, ServerResponse $response) {
            $headerLines = [
                'Connection: close',
            ];
            foreach($request->headers as $name => $value) {
                $headerLines[] = $name . ': ' . $value;
            }

            $request->options = array_replace_recursive($request->options ?? [], [
                'http' => [
                    'method'  => $request->method,
                    'header'  => $headerLines,
                    'content' => $request->body,
                ],
            ]);

            $response->fp = @fopen($request->url, 'r', false, stream_context_create($request->options));
            if($response->fp !== false) {
                stream_set_timeout($response->fp, 30);
                $response->outputWay = 'copy';

                $meta = stream_get_meta_data($response->fp);
                $headers = $meta['wrapper_data'] ?? [];
                if(isset($headers[0])) {
                    $httpCode = explode(' ', $headers[0])[1] ?? '299';
                    $response->code = (int) $httpCode;
                }
                $response->headers = $headers;
            }
        };

        $stack = $this->stack;
        $stack[] = $sendRequest;
        @ob_clean();

        $noop = function(){};
        $stackRunner = array_reduce(array_reverse($stack),function($next,$prev){
            return function () use ($prev,$next){
                $args = func_get_args();
                $args[] = $next;
                return call_user_func_array($prev,$args);
            };
        },$noop);
        $stackRunner($request, $response);

        return $response;
    }

    /**
     * Http 请求代理转发
     *
     * @param string $targetUrl
     *
     * @return $this
     */
    static function toUrl(string $targetUrl)
    {
        $proxy = new self();
        $proxy->filterSetUrl($targetUrl);

        return $proxy;
    }

    /**
     * 根据相对路径转发请求
     *
     * @param $pathPrefix
     * @param $remoteUrlPrefix
     *
     * @return $this
     */
    static function toRelativePath(string $pathPrefix, string $remoteUrlPrefix)
    {
        $proxy = new self();
        $proxy->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($pathPrefix, $remoteUrlPrefix) {
            // 示例： http://app.com/manager/user/query?role=admin&age=20#target
            $urlInfo = parse_url($request->url);
            // manager
            $pathPrefix = '/' . ltrim($pathPrefix, '/');
            // user/query
            $path = ltrim($urlInfo['path'], $pathPrefix);
            // ?role=admin&age=20
            $query = (isset($urlInfo['query']) ? ('?' . $urlInfo['query']) : '');
            //  #target_hash
            $fragment = (isset($urlInfo['fragment']) ? ('#' . $urlInfo['fragment']) : '');

            $request->url = $remoteUrlPrefix . $path . $query . $fragment;
            $next($request, $response);
        });

        return $proxy;
    }

    /**
     * 指定请求URL
     *
     * @param $remoteUrl
     *
     * @return $this
     */
    public function filterSetUrl(string $remoteUrl)
    {
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($remoteUrl) {
            $request->url = $remoteUrl;
            $next($request, $response);
        });
    }

    /**
     * 设置请求UserAgent
     *
     * @param $userAgent
     *
     * @return $this
     */
    public function filterSetUserAgent(string $userAgent)
    {
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($userAgent) {
            $request->headers['User-Agent'] = $userAgent;
            $next($request, $response);
        });
    }

    /**
     * 不使用HTTP缓存
     *
     * @return $this
     */
    public function filterNoCache()
    {
        return $this->filterRemoveResponseHeadersUseRegx('/Last-Modified|ETag|Cache-Control|Expires/i')
             ->addFilter(function(ServerRequest $request, ServerResponse $response, $next) {
                unset($request->headers['If-None-Match'], $request->headers['If-Modified-Since']);
                $request->headers['Cache-Control'] = 'no-cache';
                $next($request, $response);
            });
    }

    /**
     * 设置相应内容的 MIME类型
     * @param $mimeType
     *
     * @return $this
     */
    public function filterSetResponseContentType(string $mimeType){
        return $this->filterRemoveResponseHeadersUseRegx('/^Content-Type:/i')
            ->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($mimeType) {
                $next($request, $response);
                $response->headers[] = "Content-Type: " . $mimeType;
            });
    }

    /**
     * 下载文件
     *
     * @param $fileName
     * @param $timeout
     *
     * @return $this
     */
    public function filterDownload(string $fileName = '', int $timeout = 3600)
    {
        return $this->filterNoCache()
        ->filterSetTimeout($timeout)
        ->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($fileName, $timeout) {
            set_time_limit(0);
            $next($request, $response);
            if($response->code === 200) {
                if($fileName === '') {
                    $fileName = basename($request->url);
                }
                $fileName = strtr($fileName, [
                    "\r" => '',
                    "\n" => '',
                    "<"  => '',
                    ">"  => '',
                    '\\' => '',
                    '/'  => '',
                    '|'  => '',
                    ':'  => '',
                    '"'  => '',
                    '*'  => '',
                    '?'  => '',
                ]);

                $response->headers[] = "Content-Type: application/octet-stream";
                $response->headers[] = "Content-Transfer-Encoding: binary";
                //$response->headers[] = 'Content-Type: application/force-download';
                //$response->headers[] = 'Content-Type: application/download';

                //处理中文文件名
                $ua = $_SERVER["HTTP_USER_AGENT"] ?? '';
                $encodedFileName = str_replace("+", "%20", urlencode($fileName));
                if(preg_match("/Firefox/i", $ua)) {
                    $response->headers[] = 'Content-Disposition: attachment; filename*="utf8\'\'' . $fileName . '"';
                }
                else if(preg_match("/MSIE|Edge/i", $ua)) {
                    $response->headers[] = 'Content-Disposition: attachment; filename="' . $encodedFileName . '"';
                }
                else {
                    $response->headers[] = 'Content-Disposition: attachment; filename="' . $fileName . '"';
                }
            }
        });
    }

    /**
     * 设置请求超时时间
     *
     * @param $timeout
     *
     * @return $this
     */
    public function filterSetTimeout(int $timeout)
    {
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($timeout) {
            $request->options['http']['timeout'] = $timeout;
            $next($request, $response);
        });
    }

    /**
     * 保存到文件
     *
     * @param     $fileName
     * @param int $timeout
     *
     * @return $this
     */
    public function filterSaveToFile(string $fileName, int $timeout = 3600)
    {
        return $this->filterSetTimeout($timeout)
            ->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($timeout, $fileName) {
                $next($request, $response);

                $response->headers = [];
                return $response->saveToFile($fileName);
            });
    }

    /**
     * 请求头白名单，其他的将被删除
     *
     * @param array $headerNames
     *
     * @return $this
     */
    public function filterRequestHeaderWhileList(array $headerNames = [])
    {
        $headerNames[] = 'Accept';
        $headerNames[] = 'Content-Type';

        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($headerNames) {
            foreach(array_keys($request->headers) as $name) {
                if(!in_array($name, $headerNames)) {
                    unset($request->headers[$name]);
                }
            }

            $next($request, $response);
        });
    }

    /**
     * 添加单个请求头
     *
     * @param $name
     * @param $value
     *
     * @return $this
     */
    public function filterAddRequestHeader(string $name, string $value)
    {
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($name, $value) {
            $request->headers[$name] = $value;
            $next($request, $response);
        });
    }

    /**
     * 添加多个请求头，键值对
     *
     * @param $headers
     *
     * @return $this
     */
    public function filterAddRequestHeaders(array $headers)
    {
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($headers) {
            foreach($headers as $name => $value) {
                $request->headers[$name] = $value;
            }
            $next($request, $response);
        });
    }

    /**
     * 删除单个请求头
     *
     * @param $name
     *
     * @return $this
     */
    public function filterRemoveRequestHeader(string $name)
    {
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($name) {
            unset($request->headers[$name]);
            $next($request, $response);
        });
    }

    /**
     * 删除指定的多个请求头
     *
     * @param $headerNames
     *
     * @return $this
     */
    public function filterRemoveRequestHeaders(array $headerNames)
    {
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($headerNames) {
            foreach($headerNames as $name) {
                unset($request->headers[$name]);
            }
            $next($request, $response);
        });
    }

    /**
     * 删除所有请求头
     *
     * @return $this
     */
    public function filterRemoveAllRequestHeader()
    {
        return $this->filterRequestHeaderWhileList([]);
    }

    /**
     * 根据正则删除相应头
     *
     * @param string $headerRegx
     *
     * @return $this
     */
    public function filterRemoveResponseHeadersUseRegx(string $headerRegx)
    {
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($headerRegx) {
            $next($request, $response);
            foreach($response->headers as $index => $header) {
                if(preg_match($headerRegx, $header)) {
                    unset($request->headers[$index]);
                }
            }
        });
    }

    /**
     * 添加多个相应头
     *
     * @param $headers
     *
     * @return $this
     */
    public function filterAddResponseHeaders(array $headers)
    {
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($headers) {
            $next($request, $response);
            foreach($headers as $index => $header) {
                $response->headers[] = $header;
            }
        });
    }

    /**
     * 添加相应头
     *
     * @param $header
     *
     * @return $this
     */
    public function filterAddResponseHeader(string $header)
    {
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($header) {
            $next($request, $response);
            $response->headers[] = $header;
        });
    }
}
