<?php

namespace wuxue107\request_proxy;

use wuxue107\request_proxy\proxy\RequestURI;
use wuxue107\request_proxy\proxy\ServerRequest;
use wuxue107\request_proxy\proxy\ServerResponse;


/**
 * Class RequestProxy
 * @package wuxue107\request_proxy
 *
 * @method static RequestProxy relativePath($pathPrefix, $remoteUrlPrefix,$hitHeader = true)
 * @method static RequestProxy setUrl(string $remoteUrl)
 * @method static RequestProxy setUserAgent(string $userAgent)
 * @method static RequestProxy noCache()
 * @method static RequestProxy noCompress($force = false)
 * @method static RequestProxy addRequestHeaders($headers)
 * @method static RequestProxy addRequestHeader($headerName,$value)
 * @method static RequestProxy download($fileName,$timeout = 7200)
 */
class RequestProxy
{
    private $debug = false;
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

    private function execute(ServerRequest $request,ServerResponse $response){
        $stack = $this->stack;
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
     * 转发请求到指定连接
     *
     * @param ServerRequest $request
     *
     * @return ServerResponse
     */
    public function forward(ServerRequest $request = null)
    {
        if(is_null($request)) {
            $request = ServerRequest::createFromGlobal();
        }

        $sendRequest = function(ServerRequest $request, ServerResponse $response) {
            // 务必传绝度路径，容易疏忽造成安全问题
            $fullUrl = $request->getUri()->getFullUrl();
            if(strpos($fullUrl,'..') !== false){
                $response->setCode(403);
            }else{
                $response = $request->send($response);
            }

            if($this->debug){
                $response->addHeader('ProxyUrl: ' . $fullUrl);
            }
        };

        $this->stack[] = $sendRequest;

        $response = new ServerResponse();
        return $this->execute($request,$response);
    }

    /**
     * 根据相对路径转发请求
     *
     * @param $pathPrefix
     * @param $remoteUrlPrefix
     * @param $hitHeader
     * @return $this
     */
    public function filterRelativePath($pathPrefix, $remoteUrlPrefix,$hitHeader = true){
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($pathPrefix, $remoteUrlPrefix,$hitHeader) {
            // 示例： http://app.com/manager/user/query?role=admin&age=20#target
            $uri = $request->getUri();
            // manager
            $remoteUri = parse_url($remoteUrlPrefix);

            $currentPath = $uri->getPath();
            $uri->setScheme($remoteUri['scheme']??'');
            $uri->setUser($remoteUri['user']??'');
            $uri->setHost($remoteUri['host']??'');
            $uri->setPass($remoteUri['pass']??'');
            $uri->setPort($remoteUri['port']??'');

            $path = $remoteUri['path']??'';
            $pathPrefixLen = strlen($pathPrefix);

            if(!$hitHeader){
                $currentPath = strstr($currentPath,$pathPrefix);
            }

            if(substr($currentPath,0,$pathPrefixLen) !== $pathPrefix){
                if($hitHeader){
                    $path = rtrim($path,'/') . $currentPath;
                }else{
                    header('HTTP/1.1 404 Proxy Missed');
                    exit();
                }
            }

            $appendPath = substr($currentPath,$pathPrefixLen);

            $targetPath = $path . $appendPath;
            $uri->setPath($targetPath);

            $next($request, $response);
        });
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
            $request->setUri(RequestURI::fromUri($remoteUrl));
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
            $request->setHeader('User-Agent',$userAgent);
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
                 $request->removeHeaders([
                     'If-None-Match','If-Modified-Since'
                 ]);

                $request->setHeader('Cache-Control','no-cache');
                $next($request, $response);
            });
    }

    public function filterNoCompress(){
        return $this->filterRemoveRequestHeader('Accept-Encoding');
    }

    /**
     * 设置相应内容的 MIME类型
     * @param $mimeType
     *
     * @return $this
     */
    public function filterSetResponseContentType(string $mimeType){
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($mimeType) {
                $next($request, $response);
                $response->setHeader('Content-Type',$mimeType);
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
    public function filterDownload(string $fileName = '', int $timeout = 7200)
    {
        return $this->filterNoCache()
        ->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($fileName, $timeout) {
            set_time_limit($timeout);
            $request->setTimeout($timeout);
            $next($request, $response);

            if($response->getCode() === 200) {
                if($fileName === '') {
                    $fileName = basename($request->getUri()->getPath());
                }

                $response->setDownloadHeader($fileName);
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
            $request->setTimeout($timeout);
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
                $response->clearAllHeaders();
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
            $request->removeHeadersWithoutWhileList($headerNames);

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
            $request->setHeader($name,$value);
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
            $request->setHeaders($headers);
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
            $request->removeHeader($name);
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
            $request->removeHeaders($headerNames);

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
            $response->removeResponseHeadersUseRegx($headerRegx);
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
            $response->addHeaders($headers);
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
            $response->addHeader($header);
        });
    }

    public function filterMatchUrl($urlRegx , callable $filter){
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($urlRegx,$filter) {
            if(preg_match($urlRegx,$request->getUri()->getFullUrl(),$matches)){
                $filter($request,$response,$next,$matches);
            }else{
                $next($request, $response);
            }
        });
    }

    public function filterMatchPath($pathRegx , callable $filter){
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($pathRegx,$filter) {
            if(preg_match($pathRegx,$request->getUri()->getPath(),$matches)){
                $filter($request,$response,$next,$matches);
            }else{
                $next($request, $response);
            }
        });
    }

    /**
     * @param string $type  json | html | javascript | image | css
     * @param callable $filter
     * @return $this
     */
    public function filterMatchResponseContentType($type , callable $filter ){
        return $this->filterMatchResponseHeader('#^Content-Type\s*?:.*?'.preg_quote($type).'#',$filter,false);
    }

    public function filterMatchResponseHeader($responseHeaderRegx , callable $filter , $matchRemove = false){
        return $this->addFilter(function(ServerRequest $request, ServerResponse $response, $next) use ($responseHeaderRegx,$filter,$matchRemove) {
            $next($request, $response);
            $headers = $response->getHeaders();
            foreach ($headers as $index => $header){
                if(preg_match($responseHeaderRegx,$header,$matches)){
                    if($matchRemove){
                        $response->removeHeaderByIndex($index);
                    }
                    $filter($request, $response,$matches);
                    break;
                }
            }
        });
    }

    public static function instance(){
        return new static();
    }

    public static function filter($filter){
        return self::instance()->addFilter($filter);
    }

    public static function __callStatic($name, $arguments)
    {
        $proxy = self::instance();

        $methodFunc = 'filter' . ucfirst($name);
        if(!method_exists($proxy,$methodFunc)){
            throw new \ErrorException("call undefined static method" . $methodFunc);
        }

        return call_user_func_array([$proxy,$methodFunc],$arguments);
    }

}
