<?php
class openapi_ctl_admin_test extends desktop_controller{
    
    public function test(){
        $conf = openapi_conf::getMethods();
        
        foreach($conf as $k => $v){
            try{
                $paramsObj = kernel::single('openapi_api_params_v1_'.$k);
            } catch (Throwable $e) {
                list($app, $class) = explode('.', $k);
                $paramsObj = kernel::single($app.'_openapi_params_v1_'.$class);
            }
            foreach ($v['methods'] as $method => $vv){
                if( $paramsObj->getAppParams($method) ){
                    $list[] = $k.'.'.$method;
                }
            }
        }
        
        $this->pagedata['list'] = $list;
        $this->page('admin/test/test.html');
    }
    
    public function ajaxResult(){
        if(!$_POST['apiName']) return;
        $k = substr($_POST['apiName'], 0, strrpos($_POST['apiName'], '.'));
        $function = substr($_POST['apiName'], strrpos($_POST['apiName'], '.')+1);
        try{
            $obj = kernel::single('openapi_api_params_v1_'.$k);
        } catch (Throwable $e) {
            list($app, $class) = explode('.', $k);
            $obj = kernel::single($app.'_openapi_params_v1_'.$class);
        }
        $list = $obj->getAppParams($function);
        $description = $obj->description($function);
        

        $this->pagedata['list'] = $list;
        $this->pagedata['post'] = $_POST;
        $this->pagedata['description'] = $description;
        
        //display
        $this->display('admin/test/apiForm.html');
    }
    
    public function result(){
        $url =kernel::base_url(1).kernel::url_prefix().'/openapi/rpc/service/';
        $token = $_POST['token'];
        $method = $_POST['method1'];
        unset($_POST['token']);
        unset($_POST['method1']);
        
        $params = $_POST;
        $params['ver'] = 1;
        $params['method'] = $method;
        
        $params['type'] = $_POST['data_format'];
        $params['charset'] = 'utf-8';
        
        //$params['page_no'] = 1;
        //$params['page_size'] = 100;
        if($params['items']){
            $items = explode(";", $params['items']);
            foreach ($items as $item){
                if($item){
                    $tempa=explode(",", $item);
                    foreach ($tempa as $tempb){
                        $tempc=explode(":", $tempb);
                        $tempd[$tempc[0]]=$tempc[1];
                    }
                    $tempe[]=$tempd;
                }
            }
            $params['items']=  json_encode($tempe);
        }
        $sign = $this->gen_sign($params ,$token);
        $params['sign'] = $sign;
        $http = kernel::single('base_httpclient');
        $response = $http->set_timeout($time_out)->post($url,$params ,$headers);
        echo $response;
    }
    
    private function gen_sign($params,$token){
        return openapi_sign::genSign($params, $token);
    }
}