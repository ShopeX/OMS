<?php

class openapi_entrance extends openapi_response{

    /**
     *
     * 类常量缓存过期时间
     * @var string
     */
    const _EXPIRETIME  = 3600;

    /**
     *
     * 类常量当前脚本执行
     * @var string
     */
    static private $_nowTime = '';

    /**
     *
     * 类常量误差时间
     * @var string
     */
    const _TIME = 3600;

    /**
     *
     * 静态私有变量系统级参数
     * @var array
     */
    static private $_sysParams = array();

    /**
     *
     * 静态私有变量应用级参数
     * @var array
     */
    static private $_appParams = array();

    /**
     *
     * 开放数据接口入口函数
     * @param array $params
     */
    public function service($params){
        ini_set('memory_limit','256M');

        //接收所有参数
        $this->setParams($params);

        //检查系统级参数
        if(!$this->checkSysParams($params)){
            $this->send_error('e000001',self::$_sysParams['charset'],self::$_sysParams['type']);
        }
        
        //签名验证
        $error_code = '';
        $errorData = array();
        if(!$this->validate($params, $error_code, $errorData)){
            $sub_msg = $error_code === 'e000002' ? '签名验证失败' : '';
            $this->send_error($error_code, self::$_sysParams['charset'], self::$_sysParams['type'], $sub_msg);
        }
        
        //检查接口是否存在
        $allow_methods = openapi_conf::getMethods();
        if(!isset($allow_methods[self::$_sysParams['class']]) || !isset($allow_methods[self::$_sysParams['class']]['methods'][self::$_sysParams['method']])){
            $this->send_error('e000003',self::$_sysParams['charset'],self::$_sysParams['type']);
        }

        //检查权限
        if(!openapi_privilege::checkAccess(self::$_sysParams['flag'],self::$_sysParams['class'],self::$_sysParams['method'])){
            $this->send_error('e000004',self::$_sysParams['charset'],self::$_sysParams['type']);
        }

        //监控统计
        $statisticsLib = kernel::single('openapi_statistics');
        $statisticsLib->set(self::$_sysParams['flag'],self::$_sysParams['class'],self::$_sysParams['method']);

        //实例化接口对象
        $dataObjectLib = kernel::single('openapi_object');
        $code = '';
        $sub_msg = '';
        if(!$dataObjectLib->instance(self::$_sysParams,self::$_appParams,$code,$sub_msg)){
            $this->send_error($code,self::$_sysParams['charset'],self::$_sysParams['type'],$sub_msg);
        }

        //执行接口调用处理
        $result = array();
        if($dataObjectLib->process($result,$code,$sub_msg)){
            $this->send_result($result,self::$_sysParams['charset'],self::$_sysParams['type']);
        }else{
            $this->send_error($code,self::$_sysParams['charset'],self::$_sysParams['type'],$sub_msg);
        }

    }

    /**
     *
     * 接收传入参数兼容post数据
     * @param unknown_type $params
     */
    private function setParams(&$params){
        // 如果content_type为application/json，则将body内容转换为json
        if (stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            // 需要判断php://input是否为空
            if ($input = file_get_contents('php://input')) {
                $input = json_decode($input, true);
                $_POST = array_merge($_POST, $input?:[]);
            }
        }
        if(empty($params)){
           $params = array();
        }
        
        self::$_nowTime = time();

        foreach($params as &$v){
            $v = urldecode($v);
        }
        return $params = array_merge($params , $_POST);
    }

    /**
     *
     * 检查系统级参数函数
     * @param array $params
     */
    private function checkSysParams($params){
        self::$_sysParams = array(
            'ver' => $params['ver'] ? $params['ver'] : 1,
            'charset' => $this->getFormatCharset($params['charset']) ? $params['charset'] : 'utf-8',
            'type' => $this->getFormatType($params['type']) ? $params['type'] : 'json',
        );

        if (!empty($params['method'])) {
            $methodAlias = $this->resolveMethodAlias($params);
        }

        if(empty($params['method']) || empty($params['flag']) || empty($_POST['sign']) || empty($_POST['timestamp'])){
            return false;
        }

        $args = explode('.',$methodAlias);
        $method = array_pop($args);
        $class = array_pop($args);

        if(empty($class) || empty($method)){
            return false;
        }

        $path ='';
        if(count($args)>0){
            $path = implode('_', $args);
            $class = implode('.', $args).'.'.$class;
        }

        self::$_sysParams['path'] = $path;
        self::$_sysParams['flag'] = $params['flag'];
        self::$_sysParams['class'] = $class;
        self::$_sysParams['method'] = $method;
        return true;
    }

    private function resolveMethodAlias($params){
        $method = isset($params['method']) ? $params['method'] : '';
        if (empty($method)) {
            return '';
        }

        foreach(kernel::servicelist('openapi.conf') as $item){
            if(!is_object($item)){
                continue;
            }
            if(!method_exists($item, 'getMethodAlias')){
                continue;
            }
            $aliasList = $item->getMethodAlias($params);
            if(isset($aliasList[$method])){
                return $aliasList[$method];
            }
        }
        return $method;
    }
    
    /**
     * 验证签名函数
     *
     * @param $params
     * @param $error_code
     * @param $errorData 签名详细错误信息
     * @return bool
     */
    private function validate($params, &$error_code=null, &$errorData=null){
        $sign = $_POST['sign']; //$params['sign'];
        unset($params['sign']);

        $assemble_str = '';
        $local_sign = '';
        foreach(kernel::servicelist('openapi.conf') as $item){
            if(!is_object($item)){
                continue;
            }
            if(!method_exists($item, 'verifySign')){
                continue;
            }

            $local_sign = $item->verifySign($params, $assemble_str);
            if($local_sign){
                break;
            }
        }

        //sign
        if (!$local_sign) {
            $local_sign = $this->gen_sign($params, $assemble_str);
        }
        
        //check
        if($_POST['timestamp'] < self::$_nowTime-self::_TIME || $_POST['timestamp'] > self::$_nowTime+self::_TIME ){
            $error_code = 'e000008';
            return false;
        }elseif(!$local_sign || $sign != $local_sign){
            $error_code = 'e000002';
            
            //签名详细错误信息
            $errorData = array(
                'request_sign' => $sign,
                'assemble_str' => $assemble_str,
            );
            
            return false;
        }elseif($has_done = cachecore::fetch($local_sign)){
            $error_code = 'e000009';
            return false;
        }else{
            unset($params['method']);
            unset($params['flag']);
            unset($params['ver']);
            unset($params['charset']);
            unset($params['type']);
            unset($params['timestamp']);

            self::$_appParams = $params;
            cachecore::store($local_sign, 'done', self::_EXPIRETIME);
            return true;
        }
    }
    
    /**
     * 生成签名算法函数
     *
     * @param $params
     * @param $assemble_str 拼接集合字符参数
     * @return string
     */
    private function gen_sign($params, &$assemble_str='')
    {
        $token = openapi_setting::getConf(self::$_sysParams['flag'],'interfacekey');
        if(!$token){
            return false;
        }
        
        //拼接集合字符参数（JSON 参数统一规范化，中文与 \uXXXX 等价）
        $assemble_str = openapi_sign::assembleForSign($params);

        return strtoupper(md5(strtoupper(md5($assemble_str)).$token));
    }
}
