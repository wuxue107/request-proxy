<?php


namespace wuxue107\request_proxy\proxy;


class RequestBody
{
    private $content = false;

    private $data = [];

    private $files = [];

    private ServerRequest $request;

    public static function createFromServerRequest(ServerRequest $request){
        if($request->getMethod() != 'POST'){
            return null;
        }

        $body = new self();
        $body->request = $request;

        $contentType = $request->getHeader('Content-Type');
        if(strpos($contentType,'form') != false){
            $body->data = $_POST;
            if(preg_match('#boundary=(.*)#',$contentType,$matches)){
                $body->files = self::initFiles();
            }
        }else{
            $body->content = file_get_contents('php://input');
            if(strpos('json') != false){
                $body->data = @json_decode($body->content,true);
            }
        }

        return $body;
    }

    public function setData($data){
        $this->content = false;
        $this->data = $data;
        return $this;
    }

    public function setContent($content){
        $this->content = $content;
        return $this;
    }

    public function getFiles(){
        return $this->files;
    }

    /**
     * 设置上传文件
     *
     * @param string $inputKey PS: avatar
     * @param array $file PS: ['name' => 'aa.php','type' => 'application/octet-stream','size'=>11,'tmp_file' => '/tmp/aa.png']
     *
     * @return RequestBody
     */
    public function addFile($inputKey,$file){
        $this->files[$inputKey] = $file;

        return $this;
    }

    public function addLocalFile($inputKey,$file,$type = null){
        return $this->addFile($inputKey,[
            'name' => basename($file),
            'type' => $type?:'application/octet-stream',
            'size'=> null,
            'tmp_file' => $file
        ]);
    }

    public function getNormalFormContent(){
        return http_build_query($this->data);
    }

    public function getMultipartFormContent(){
        $boundary = '--' . md5(uniqid());
        $items = [];
        $items[] = "Content-type: multipart/form-data, boundary={$boundary}\r\n";
        self::processMultipartFormPost($this->data,$boundary,'',$items);
        self::processMultipartFormFile($this->files,$boundary,$items);
        $items[] = "\r\n--{$boundary}--";

        return implode('',$items);
    }

    private static function processMultipartFormPost($post,$boundary,$prefix = '', &$items = []){
        foreach ($post as $key => $value){
            if($prefix !== '') {
                $key = "{$prefix}[{$key}]";
            }

            if(is_array($value)){
                self::processMultipartFormPost($value,$boundary,$key,$items);
            }else{
                $items[] = "\r\n--{$boundary}\r\nContent-Disposition: form-data;name=\"{$key}\"\r\n\r\n{$value}";
            }
        }
    }

    private static function iterFileKey($fileName,$prefix = null){
        if(!is_array($fileName)){
            yield $prefix;
        }else{
            if(!is_null($prefix)){
                $prefix = $prefix . '.';
            }
            foreach ($fileName as $k => $group){
                yield from self::iterFileKey($group, $prefix . $k);
            }
        }
    }

    private static function initFiles(){

        $selfFiles = [];
        $files = $_FILES??[];
        foreach ($files as $key => $value){
            foreach (self::iterFileKey($files[$key]['name']) as $path){
                $name = $key . '[' . str_replace('.','][',$path) . ']';
                //$name = preg_replace('#\[\d+\]#','[]',$name);

                $fileName = self::pathGet($files[$key]['name'],$path);
                $type = self::pathGet($files[$key]['type'],$path);
                $tmpName = self::pathGet($files[$key]['tmp_name'],$path);
                $size = self::pathGet($files[$key]['size'],$path);
                $error = self::pathGet($files[$key]['error'],$path);

                $selfFiles[$name] = [
                    'name' => $fileName,
                    'type' => $type,
                    'tmp_name' => $tmpName,
                    'size' => $size,
                    'error' => $error,
                ];
            }
        }

        return $selfFiles;
    }

    private static function pathGet($value,$path){
        if(is_null($value)){
            return null;
        }
        if(is_null($path)){
            return $value;
        }

        $res = explode('.',$path,2);
        if(isset($res[1])){
            return self::pathGet($value[$res[0]]??null,$res[1]);
        }else{
            return $value[$res[0]]??null;
        }
    }

    private static function processMultipartFormFile($files,$boundary, &$items = []){
        foreach ($files as $inputName => $file){

            $fileName = $file['name'];
            $type = $file['type'];
            $tmpName = $file['tmp_name'];
            $items[] = "\r\n--$boundary";
            $items[] = "\r\nContent-Disposition: form-data;name=\"{$inputName}\"; filename=\"$fileName\"";
            $items[] = "\r\nContent-Type: " . $type . "\r\n\r\n";
            $items[] = file_get_contents($tmpName);
        }
    }


    public function getContent(){
        if($this->content != false){
            return $this->content;
        }

        $contentType = $this->request->getHeader('Content-Type');
        if(strpos($contentType,'json')){
            $this->content = json_encode($this->data,JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }else if(strpos($contentType,'multipart/form-data') != false){
            $this->content = $this->getMultipartFormContent();
        }else if(strpos($contentType,'form') != false){
            $this->content = $this->getNormalFormContent();
        }else{
            $this->content = strval($this->data);
        }

        return $this->content;
    }


    public function __toString()
    {
        return $this->getContent();
    }
}