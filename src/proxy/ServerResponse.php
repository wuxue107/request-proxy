<?php

namespace wuxue107\request_proxy\proxy;

/**
 * Class ServerResponse
 */
class ServerResponse
{
    /** @var int 请求响应 HTTP CODE */
    public $code = 0;
    /** @var string 如果请求结果没有读取或设置过，直接通过fpassthru函数拷贝到输出 */
    public $outputWay = 'echo';
    /** @var string 相应内容 */
    private $body;
    /** @var resource 请求打开远程URL的文件句柄 */
    public $fp = false;

    public $headers = [];

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

    public function setFileHandle($fp){
        if($fp){
            $this->fp = $fp;
            $this->outputWay = 'copy';
            foreach($this->headers as $index => $header){
                if(preg_match('/^Content-Length:/i',$header)){
                    unset($this->headers[$index]);
                }
            }
        }
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
