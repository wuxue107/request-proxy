<?php


use wuxue107\request_proxy\proxy\RequestURI;
use wuxue107\request_proxy\proxy\ServerRequest;
use wuxue107\request_proxy\proxy\ServerResponse;
use wuxue107\request_proxy\RequestProxy;

require __DIR__ . '/src/RequestProxy.php';
require __DIR__ . '/src/proxy/ServerRequest.php';
require __DIR__ . '/src/proxy/ServerResponse.php';
require __DIR__ . '/src/proxy/RequestURI.php';
require __DIR__ . '/src/proxy/RequestBody.php';

//$item = [
//        "name" => [
//                "avatar" => [
//                        "aaaa.jpg",
//                        "bbbb.jpg",
//                ]
//        ]
//];
//foreach (ServerRequest::iterFileKey($item['name']) as $value){
//    var_dump($value);
//}
//exit;

if(isset($_REQUEST['a'])){
    ini_set('display_errors','On');
    error_reporting(E_ALL);


    RequestProxy::relativePath('','http://127.0.0.1:3001')
        ->forward()->render();
}else{
    ?>

<form method="post" action="?a=1" enctype="multipart/form-data">
    <input type="text" name="aa" value="aa" />
    <input type="text" name="bb" value="bb" />
    <input type="file" name="pic[avatar][]" multiple>
    <button type="submit">submit</button>
</form>
    <?php

}
