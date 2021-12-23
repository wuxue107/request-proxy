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

    static function fromUri($url)
    {
        $uri = new RequestURI();
        $parts = @parse_url($url)?:[];
        $uri->setParts($parts);
    }


    public function __toString()
    {
        $uriData = $this->uriParts;
        if($uriData['scheme'] == ""){
            return $uriData['path'];
        }

        $url = $uriData['scheme'];
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

        return $url;
    }

    static function fromLocalFile($file){
        $uri = new RequestURI();
        $uri->setParts([
            'scheme' => '',
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

        $uri->setParts([
            'scheme' => isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https' : 'http',
            'path' =>   $_SERVER['REQUEST_URI']??$_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? null,
            'query' =>   $_SERVER['QUERY_STRING']??null,
            'host' => $_SERVER['HTTP_HOST']??null,
            'port' => $_SERVER['SERVER_PORT']??''
        ]);

        return $uri;
    }


    public function getPart($part){
        return $this->uriParts[$part]??null;
    }

    public function setPart($part,$value){
        if(isset($this->uriParts[$part])){
            $this->uriParts[$part] = $value??'';
        }

        return $this;
    }

    public function setParts($uriParts){
        foreach ($uriParts as $part => $value){
            if($value !== null && isset($this->uriParts[$part])){
                $this->uriParts[$part] = $value;
            }
        }

        return $this;
    }

    public function setSchema($value){
        return $this->setPart('schema',$value);
    }

    public function getSchema(){
        return $this->getPart('schema');
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