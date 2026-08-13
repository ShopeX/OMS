<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class erpapi_hchsafe_matrix_taobao_request_hchsafe extends erpapi_hchsafe_request_hchsafe{

    /**
     * 登录日志
     * 
     * @return void
     * @author 
     * */
    public function login($sdf)
    {
        if (!$this->__is_config_mq() || !$this->__ati || !trim($sdf['uname'])) return $this->succ();

        $account_type = pam_account::get_account_type('desktop');
        $shopName = app::get('ome')->model('shop')->get_taobao_name();
        $taobao_shop = app::get('ome')->model('shop')->dump(array('node_type'=>'taobao','filter_sql'=>'(node_id!="" AND node_id is not null)'),'node_id');
        if (!$taobao_shop['node_id']) return $this->succ();
        if($account_type === $sdf['type'] && $shopName) {
            $pushData = array(
                'userId'       => $this->__prefixUname.(string)$sdf['uname'],
                'userIp'       => $this->__remote_addr,
                'ati'          => (string)$this->__ati,
                'topAppKey'    => TOP_APP_KEY,
                'appName'      => $this->__host,
                'tid'          => $shopName,
                'loginResult'  => $_SESSION['error'] ? 'fail' : 'success',
                'loginMessage' => $_SESSION['error'] ? $_SESSION['error'] : '登录成功',
                'time'         => $_SESSION['login_time'],
                'to_node_id'    =>$taobao_shop['node_id'],
            );
            $title = '淘宝风控登录日志';
            $rs = $this->__caller->call(HCHSAFE_UPLOAD_LOGIN_LOG,$pushData,array(),$title,10);
        }

        return $this->succ();
    }

    /**
     * 订单访问数据
     * 
     * @return void
     * @author 
     * */
    public function orderdata($sdf)
    {
        if (!$this->__is_config_mq() || !$this->__ati || !$sdf['tradeIds']) return $this->succ();

        // 过滤掉非淘宝订单
        foreach ((array)$sdf['tradeIds'] as $key => $value) {
            if (!is_numeric($value) || strlen($value) != '16') unset($sdf['tradeIds'][$key]);
        }
        $taobao_shop = app::get('ome')->model('shop')->dump(array('node_type'=>'taobao','filter_sql'=>'(node_id!="" AND node_id is not null)'),'node_id');
        if (!$taobao_shop['node_id']) return $this->succ();
        if (!$sdf['tradeIds']) return $this->succ();

        $uname = kernel::single('desktop_user')->get_login_name();
        $title = '淘宝风控订单访问数据';
        foreach (array_chunk((array)$sdf['tradeIds'], 100) as $value) {
            $pushData = array(
                'userId'    => $this->__prefixUname.$uname,
                'userIp'    => $this->__remote_addr,
                'ati'       => (string)$this->__ati,
                'topAppKey' => TOP_APP_KEY,
                'appName'   => $this->__host,
                'url'       => $this->__url,
                'tradeIds'  => implode(',',$value),
                'operation' => $sdf['operation'],
                'time'      => time(),
                'to_node_id'    =>$taobao_shop['node_id'],
            );

            $rs = $this->__caller->call(HCHSAFE_UPLOAD_ORDER_LOG,$pushData,array(),$title,10);
            
        }

       

        return $this->succ();
    }

    /**
     * SQL
     * 
     * @return void
     * @author 
     * */
    public function sql($sdf)
    {
        if (!$this->__is_config_mq() || !$this->__ati || !$sdf['sqls']) return $this->succ();
        $taobao_shop = app::get('ome')->model('shop')->dump(array('node_type'=>'taobao','filter_sql'=>'(node_id!="" AND node_id is not null)'),'node_id');
        if (!$taobao_shop['node_id']) return $this->succ();
        $uname = kernel::single('desktop_user')->get_login_name();

        $pushData = array(
            'userId'    => $this->__prefixUname.$uname,
            'userIp'    => $this->__remote_addr,
            'ati'       => (string) $this->__ati,
            'appName'   => (string) $this->__host,
            'url'       => $this->__url,
            'db'        => DB_HOST,
            'sql'       => implode(';',$sdf['sqls']),
            'to_node_id'    =>$taobao_shop['node_id'],
        );
        $title = '淘宝风控SQL';
        $rs = $this->__caller->call(HCHSAFE_UPLOAD_SQL_LOG,$pushData,array(),$title,10);
        
        return $this->succ();
    }

    /**
     * 订单推送第三方
     * 
     * @return void
     * @author 
     * */
    public function orderpush($sdf)
    {
        if (!$this->__is_config_mq() || !$this->__ati || !$sdf['tradeIds']) return $this->succ();
        $taobao_shop = app::get('ome')->model('shop')->dump(array('node_type'=>'taobao','filter_sql'=>'(node_id!="" AND node_id is not null)'),'node_id');
        if (!$taobao_shop['node_id']) return $this->succ();
        // 过滤掉非淘宝订单
        foreach ((array)$sdf['tradeIds'] as $key => $value) {
            if (!is_numeric($value) || strlen($value) != '16') unset($sdf['tradeIds'][$key]);
        }

        if (!$sdf['tradeIds']) return $this->succ();

        $uname = kernel::single('desktop_user')->get_login_name();


        foreach (array_chunk((array)$sdf['tradeIds'], 100) as $value) {
            $pushData = array(
                'userId'    => $this->__prefixUname.$uname,
                'userIp'    => $this->__remote_addr,
                'ati'       => (string)$this->__ati,
                'appName'   => $this->__host,
                'url'       => $this->__url,
                'tradeIds'  => implode(',',(array)$value),
                'sendTo'    => '',
                'node_id'   => $sdf['to_node_id'],
                'to_node_id'   => $taobao_shop['node_id'],
            );
            $title = '淘宝风控订单推送第三方';
            $rs = $this->__caller->call(HCHSAFE_UPLOAD_ORDERSEND_LOG,$pushData,array(),$title,10);
            
        }



        return $this->succ();
    }

    /**
     * 风控
     * 
     * @return void
     * @author 
     * */
    public function computerisk()
    {

        if (!$this->__is_config_mq() || !$this->__ati) return $this->succ();

        $taobao_shop = app::get('ome')->model('shop')->dump(array('node_type'=>'taobao','filter_sql'=>'(node_id!="" AND node_id is not null)'),'node_id');

        if (!$taobao_shop['node_id']) return $this->succ();

        $uname = kernel::single('desktop_user')->get_login_name();

        $title = '淘宝风控';
        
        $params = array(
            'userId'     => $this->__prefixUname.$uname,
            'userIp'     => $this->__remote_addr,
            'ati'        => $this->__ati,
            'topAppKey'  => TOP_APP_KEY,
            'appName'    => $this->__host,
            'time'       => time(),
            'to_node_id' => $taobao_shop['node_id'],
        );

        $rs = $this->__caller->call(HCHSAFE_UPLOAD_COMPUTE_RISK,$params,array(),$title,10);

        if ($rs['rsp'] == 'fail') return $this->succ();

         $data = json_decode($rs['data'],true);
        // 必须短信验证
        if ('true' == app::get('ome')->getConf('desktop.account.mobile.verify') ) {
            $data['risk'] = 1;
        }
        if ($data['result'] == 'success' && $data['risk'] > 0.5) {
            $urlRs = $this->getVerifyUrl(array('to_node_id'=>$taobao_shop['node_id']));

            $msg = '获取短信验证页面失败，' . $urlRs['msg'];
            return $this->error($msg);
        }

        return $this->succ();
    }

        /**
     * 获取VerifyUrl
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function getVerifyUrl($sdf) {
        if (!$this->__ati) return $this->error('缺少孔明锁');
        $objDesktopUser = kernel::single('desktop_user');
        $uname = $objDesktopUser->get_login_name();
        $mobile = $objDesktopUser->get_mobile();
        if(empty($mobile)) {
            return $this->error('缺少手机号,请联系主账号添加手机号');
        }
        $objSession = kernel::single('base_session');
        $sessionId = $objSession->sess_id();
        $url = 'http://erp-redirect.shopex.cn/index.php?url=' . base64_encode(kernel::base_url(true) . '?ctl=passport&act=login_verify&sess_id='.$sessionId.'&to_node_id='.$sdf['to_node_id']);
        $title = '淘宝获取二次验证url';
        $params = array(
            "sessionId" => $sessionId,
            "mobile" => $mobile,
            "redirectURL" => urlencode($url),
            "userId" => $this->__prefixUname.$uname,
            "userIp" => $this->__remote_addr,
            "ati" => $this->__ati,
            "appId" => TOP_APP_KEY,
            "appName" => $this->__host,
            "time" => time(),
            "to_node_id" => $sdf['to_node_id']
        );

        $rs = $this->__caller->call(HCHSAFE_VERIFY_URL,$params,array(),$title,10);
        
        if ($rs['rsp'] == 'succ') {
            $data = json_decode($rs['data'], true);
            if($data['verifyUrl']) {
                $objSession->set_cookie_expires(0);
                header('Location:'.$data['verifyUrl']);
                exit();
            }
        }
        return $rs;
    }

    /**
     * isVerifyPassed
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function isVerifyPassed($sdf) {
        $title = '淘宝二次验证是否成功';
        $params = array(
            'token' => $sdf['token'],
            'time' => time(),
            'to_node_id' => $sdf['to_node_id']
        );
        $rs = $this->__caller->call(HCHSAFE_VERIFY_PASSED,$params,array(),$title,10);
        if($rs['data']) {
            $data = json_decode($rs['data'], true);
            if($data['verifyResult'] == 'fail') {
                $this->verifyLog(false);
                return $this->error($data['errMsg']);
            }
        }
        $this->verifyLog(true);
        return $rs;
    }
    
    
    protected function verifyLog($verifyResult) {
        if (!$this->__is_config_mq()) return $this->succ();
        $shopName = app::get('ome')->model('shop')->get_taobao_name();
        if(!$shopName) {
            return $this->succ();
        }
        $bqq = kernel::single('base_queue_mq');
        $accountType = pam_account::get_account_type('desktop');
        
        if($_SESSION['account'][$accountType]) {
            $user = app::get('desktop')->model('users')->db_dump(['user_id'=>$_SESSION['account'][$accountType]], 'mobile,name');
        }
        $shopRows = app::get('ome')->model('shop')->getList('addon', ['node_type'=>'taobao']);
        $seller_nick = [];
        foreach ($shopRows as $v) {
            $addon = $v['addon'];
            $seller_nick[] = $addon['nickname'];
        }
        //idaas登录日志
        $pushData = array(
            'topAppKey'    => TOP_APP_KEY,
            'loginFrom'    => kernel::this_url(1).'?'.$_SERVER['QUERY_STRING'],
            'loginId' => base_shopnode::node_id('ome') . '.' . (string)$user['name'],
            'clientIp' => $_SERVER['REMOTE_ADDR'],
            'clientPc' => $_SERVER['HTTP_USER_AGENT'],
            'requestTime' => $_SESSION['login_time'] ? : time(),
            'requestId' => $this->uniqid(),
            'loginResult' => $verifyResult ? 'success' : 'fail',
            'phone' => $user ? (string) $user['mobile'] : '',
            'time'  => $_SESSION['login_time'],
            'sellerNick' => (string) implode(',', $seller_nick)
        );
        $this->__mq_config['routerkey'] = 'tb.verify.login.log';
        $bqq->connect($this->__mq_config, $this->__mq_config['exchange'], 'tb.verify.login.log');
        $flag = $bqq->publish(json_encode($pushData),$this->__mq_config['routerkey']);
        $bqq->disconnect();
        return $this->succ();
    }
}
