<?php


namespace wuxue107\request_proxy\proxy;


class RequestURI implements \ArrayAccess
{
    protected $uriParts = [
        'scheme' => 'http',
        'user' => '',
        'pass' => '',
        'host' => 'localhost',
        'port' => '',
        'path' => '',
        'query' => '',
        'fragment' => '',
    ];

    private $queryParams = [];

    private $fullUrl;

    public function getQueryParams(): array{
        return $this->queryParams;
    }

    public function getQueryParam($name){
        return $this->queryParams[$name]??null;
    }

    public function setQueryParams(array $params){
        $this->queryParams = $params;
        $this->setPart('query',http_build_query($params));

        return $this;
    }


    public function addQueryParams(array $params){
        $params = $this->getQueryParams();
        foreach ($params as $key => $val)
            $params[$key] = $val;

        $this->setQueryParams($params);
        return $this;
    }

    public function addQueryParam($name,$val){
        $this->addQueryParams([$name => $val]);
        return $this;
    }

    public function removeQueryParam($name){
        return $this->addQueryParam($name,null);
    }

    /**
     * @return array
     */
    public function getUriParts(): array
    {
        return $this->uriParts;
    }

    /**
     * @param array $uriParts
     */
    public function setUriParts(array $uriParts): void
    {
        $this->uriParts = $uriParts;
    }

    static function fromUri($url)
    {
        $uri = new RequestURI();
        $parts = @parse_url($url)?:[];
        $uri->setParts($parts);
        return $uri;
    }


    public function __toString(){
        return $this->getFullUrl();
    }

    public function getFullUrl()
    {
        if(is_null($this->fullUrl)){
            $uriData = $this->uriParts;
            if($uriData['scheme'] == "file"){
                return $uriData['path'];
            }

            $url = $uriData['scheme'] . '://';
            if (!empty($uriData['user'])) {
                $url .= $uriData['user'] . ($uriData['pass'] ? (':' . $uriData['pass']) : '') . '@';
            }

            $url .= $uriData['host'];

            if (!empty($uriData['port'])) {
                $url .= ":" . $uriData['port'];
            }

            $url .= $uriData['path'];

            if (!empty($uriData['query']))
                $url .= "?" . $uriData['query'];

            if (!empty($uriData['fragment']))
                $url .= "#" . $uriData['fragment'];

            $this->fullUrl = $url;
        }

        return $this->fullUrl;
    }

    static function fromLocalFile($file){
        $uri = new RequestURI();
        $uri->setParts([
            'scheme' => 'file',
            'user' => '',
            'pass' => '',
            'host' => '',
            'port' => '',
            'path' => $file,
            'query' => '',
            'fragment' => '',
        ]);

        return $uri;
    }

    static function fromRequest()
    {
        $uri = new RequestURI();
        $path = null;
        if(isset($_SERVER['REQUEST_URI'])){
            $path = strstr($_SERVER['REQUEST_URI'],'?',true);
        }
        if(is_null($path)){
            $path = $_SERVER['PHP_SELF']??$_SERVER['SCRIPT_NAME']??'';
        }

        $uri->setParts([
            'scheme' => isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https' : 'http',
            'path' => $path,
            'host' => $_SERVER['HTTP_HOST']??'',
            'port' => $_SERVER['SERVER_PORT']??''
        ]);

        $uri->setQuery($_SERVER['QUERY_STRING']??'');

        return $uri;
    }


    public function getPart($part){
        return $this->uriParts[$part]??null;
    }

    protected function setPart($part,$value){
        $this->fullUrl = null;
        if(isset($this->uriParts[$part])){
            $this->uriParts[$part] = $value??'';
        }

        return $this;
    }

    public function setParts($uriParts){
        $this->fullUrl = null;
        foreach ($uriParts as $part => $value){
            if($value !== null && isset($this->uriParts[$part])){
                $this->uriParts[$part] = $value;
            }
        }

        return $this;
    }

    public function setScheme($value){
        if(empty($value)){
            $value = 'http';
        }
        return $this->setPart('scheme',$value);
    }

    public function getScheme(){
        return $this->getPart('scheme');
    }

    public function setUser($value){
        return $this->setPart('user',$value);
    }

    public function getUser(){
        return $this->getPart('user');
    }

    public function setPass($value){
        return $this->setPart('pass',$value);
    }

    public function getPass(){
        return $this->getPart('pass');
    }


    public function setHost($value){
        return $this->setPart('host',$value);
    }

    public function getHost(){
        return $this->getPart('host');
    }


    public function setPort($value){
        return $this->setPart('port',$value);
    }

    public function getPort(){
        return $this->getPart('port');
    }


    public function setPath($value){
        return $this->setPart('path',$value);
    }

    public function getPath(){
        return $this->getPart('path');
    }


    public function setQuery($value){
        @parse_str((string) $value,$params);
        $this->queryParams = $params?:[];
        return $this->setPart('query',$value);
    }

    public function getQuery(){
        return $this->getPart('query');
    }


    public function setFragment($value){
        return $this->setPart('fragment',$value);
    }

    public function getFragment(){
        return $this->getPart('fragment');
    }

    /**
     * Whether a offset exists
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return bool true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return !empty($this->uriParts[$offset]);
    }

    /**
     * Offset to retrieve
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->getPart($offset);
    }


    public function offsetSet($offset, $value)
    {
        $this->setPart($offset,$value);
    }

    public function offsetUnset($offset)
    {
    }
}