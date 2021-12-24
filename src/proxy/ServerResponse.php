<?php

namespace wuxue107\request_proxy\proxy;

/**
 * Class ServerResponse
 */
class ServerResponse
{
    /** @var int 请求响应 HTTP CODE */
    private $code = 0;
    /** @var string 如果请求结果没有读取或设置过，直接通过fpassthru函数拷贝到输出 */
    public $outputWay = 'echo';
    /** @var string 相应内容 */
    private $body;
    /** @var resource 请求打开远程URL的文件句柄 */
    public $fp = false;

    public $headers = [];

    public function setResource($fp){
        $this->outputWay = 'copy';
        $this->fp = $fp;
        $this->removeResponseHeadersUseRegx('/^Content-Length:/i');
    }

    public function addHeader($header){
        $this->headers[] = $header;
    }

    public function addHeaders($headers){
        foreach($headers as $index => $header) {
            $this->addHeader($header);
        }
    }

    public function clearAllHeaders(){
        $this->headers = [];
    }

    public function removeResponseHeadersUseRegx(string $headerRegx)
    {
        foreach ($this->headers as $index => $header) {
            if (preg_match($headerRegx, $header)) {
                unset($this->headers[$index]);
            }
        }
    }

    public function setHeader($headerName, $value){
        $headerName = ServerRequest::normalizeHeaderName($headerName);
        $this->removeResponseHeadersUseRegx('#^'.preg_quote($headerName,'#').':#i');
        $this->addHeader("$headerName: $value");
    }

    public function setHeaders($headers){
        foreach ($headers as $headerName => $value){
            $this->setHeader($headerName,$value);
        }
    }

    public function setDownloadHeader($fileName){
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
        if($fileName == ''){
            $fileName = 'download';
        }

        $this->setHeader('Content-Type','application/octet-stream');
        $this->setHeader('Content-Transfer-Encoding','binary');

        // $response->addHeader('Content-Type: application/force-download');
        // $response->addHeader('Content-Type: application/download');

        //处理中文文件名
        $ua = $_SERVER["HTTP_USER_AGENT"] ?? '';
        $encodedFileName = str_replace("+", "%20", urlencode($fileName));
        if(preg_match("/Firefox/i", $ua)) {
            $this->addHeader('Content-Disposition: attachment; filename*="utf8\'\'' . $fileName . '"');
        }
        else if(preg_match("/MSIE|Edge/i", $ua)) {
            $this->addHeader('Content-Disposition: attachment; filename="' . $encodedFileName . '"');
        }
        else {
            $this->addHeader('Content-Disposition: attachment; filename="' . $fileName . '"');
        }
    }
    public function getBody()
    {
        $this->outputWay = 'echo';
        if(is_string($this->body)) {
            return $this->body;
        }

        if($this->fp !== false) {
            $this->body = @stream_get_contents($this->fp);
            if($this->body === false) {
                $this->body = '';
            }

            @fclose($this->fp);
        }
        else {
            $this->body = '';
        }

        return $this->body;
    }

    public function setBody(string $body)
    {
        $this->outputWay = 'echo';
        $this->body = $body;
        foreach($this->headers as $index => $header){
            if(preg_match('/^Content-Length:/i',$header)){
                unset($this->headers[$index]);
            }
        }
    }

    public function getCode(){
        return $this->code;
    }

    public function setCode(int $code){
        $this->code = $code;
    }

    public function render()
    {
        foreach($this->headers as $header) {
            @header($header, true);
        }

        if($this->outputWay === 'copy') {
            @fpassthru($this->fp);
        }
        else {
            echo $this->getBody();
        }

        if(!$this->fp) {
            @fclose($this->fp);
        }
    }

    public function saveToFile($fileName){
        $wf = @fopen($fileName,'w+');
        if($wf !== false){
            if($this->outputWay === 'copy'){
                $wlen = stream_copy_to_stream($this->fp,$wf);
            }else{
                $wlen = fwrite($wf,$this->getBody());
            }
            @fclose($wf);

            return $wlen;
        }

        return false;
    }
}
