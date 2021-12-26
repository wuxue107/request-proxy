<?php

require __DIR__ . '/src/RequestProxy.php';
require __DIR__ . '/src/proxy/ServerRequest.php';
require __DIR__ . '/src/proxy/ServerResponse.php';
require __DIR__ . '/src/proxy/RequestURI.php';

use wuxue107\request_proxy\proxy\RequestURI;
use wuxue107\request_proxy\proxy\ServerRequest;
use wuxue107\request_proxy\proxy\ServerResponse;
use wuxue107\request_proxy\RequestProxy;

ini_set('display_errors','On');
error_reporting(E_ALL);


RequestProxy::relativePath('/cgi-bin','https://www.baidu.com')
    ->forward()->render();