# request proxy 

- A simple http request proxy，request forward
- 一个简单的Http请求代理,请求转发
- By default, all headers and bodies of the request and the headers and bodies that return the target request will be forwarded
- 默认会转发请求的所有header、body和返回目标请求的header、body
- You can filter requests and responses through the filter method provided by RequestProxy, and you can add custom processing methods
- 可以通过RequestProxy提供的filter方法，进行请求和响应的过滤处理，可以添加自定义处理方法
- Relative path forwarding request
- 相对路径转发请求

# Install 

```bash
composer install wuxue107/request-proxy
```

# Quick Usage 

## forwards a picture 代理请求一张图片
```php
    use wuxue107\request_proxy\RequestProxy;
    
    RequestProxy::setUrl('https://www.baidu.com/img/PCtm_d9c8750bed0b3c7d089fa7d55720d6cf.png')->forward()->render();
```
## forwards a picture 代理下载一张图片
```php
    use wuxue107\request_proxy\RequestProxy;
    RequestProxy::toUrl('https://www.baidu.com/img/PCtm_d9c8750bed0b3c7d089fa7d55720d6cf.png')->filterDownload()->forward()->render();
```

##  Proxy the third-party API and automatically handle the access token 代理第三方API并处理ACCESS_TOKEN 相对路径的转发请求，
```php
use wuxue107\request_proxy\RequestProxy;
use \wuxue107\request_proxy\proxy\ServerRequest;
use \wuxue107\request_proxy\proxy\ServerResponse;

$app = WeWork::instance()->app;
RequestProxy::toRelativePath('/cgi-bin','https://qyapi.weixin.qq.com/cgi-bin')
    ->addFilter(function(ServerRequest $request,ServerResponse $response, $next) use ($app){
        // Before send request run
        // 请求支持执行
        $agentId = $app->config->get('agent_id');
        $corpId = $app->config->get('corp_id');
        
        /** Replace parameter variable in request URL parameter*/
        /** 请求URL参数中替换参数变量 */
        $request->url = str_replace(['AGENT_ID','CORP_ID'],[$agentId,$corpId],$request->url);


        /** 请求参数中注入参数ACCESS_TOKEN */
        /** The parameter is injected into the request parameter ACCESS_TOKEN */
        /** 请求参数中注入参数ACCESS_TOKEN */
        $token = $app->access_token->getToken()['access_token'];
        $request->url = addUrlParam($request->url,['access_token' => $token]);

        $next($request,$response); // Do forgot call this
        
        // After send request run
        // 请求之后执行
    })->forward()->render();
```

## All filter inner method  所有的内置过滤函数

```php
    use wuxue107\request_proxy\RequestProxy;
    RequestProxy::setUrl('https://www.baidu.com/img/PCtm_d9c8750bed0b3c7d089fa7d55720d6cf.png')
                ->filterSetUserAgent('Chrome')
                ->filterNoCache()
                ->filterSetResponseContentType('application/json')
                ->filterDownload('some.tar')
                ->filterSetTimeout(3600)
                ->filterRequestHeaderWhileList(['Cookie'])
                ->filterAddRequestHeader('X-AUTH','xxxx')
                ->filterAddRequestHeaders(['X-AUTH' => 'xxx','X-Content-Type' => 'json'])
                ->filterRemoveRequestHeader('Cache-Control')
                ->filterRemoveRequestHeaders(['Cache-Control','Expire'])
                ->filterRemoveAllRequestHeader()
                ->filterRemoveResponseHeadersUseRegx('/Cache-Control|Expire/i')
                ->filterAddResponseHeaders(['Server: SOME'])
                ->filterAddResponseHeader('Server: SOME')
                ->filterSaveToFile('/tmp/aaaa.tar')
                ->forward()->render();
```
