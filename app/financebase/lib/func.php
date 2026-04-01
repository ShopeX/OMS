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
/**
 * 公共函数类
 *
 * @author 334395174@qq.com
 * @version 0.1
 */
class financebase_func
{

    /**
     * 添加队列
     * @param String $title 队列标题
     * @param String $worker 队列执行类方法
     * @param mixed $params 队列参数
     * @param String $type 队列类型
     * @return bool
     */
    public function addTask($title, $worker, $params, $type = 'slow')
    {
        if (empty($params)) {
            return false;
        }

        $oQueue    = app::get('base')->model('queue');
        $queueData = array(
            'queue_title' => $title,
            'start_time'  => time(),
            'params'      => $params,
            'worker'      => $worker,
        );

        $result = $oQueue->save($queueData);

        return $result;
    }

    /**
     * 写入日志
     * @param String $log_title 日志标题
     * @param String $log_type  日志类型
     * @param mixed   $params 参数
     */
    public function writelog($log_title, $log_type, $params)
    {
        if (empty($params)) {
            return null;
        }

        if (empty($log_type)) {
            return null;
        }

        if (app::get('financebase')->getConf('bill.logs.is_open') == 'false') {
            return null;
        }

        $log_dir = DATA_DIR . '/financebase/logs/' . date('Ym');
        if (!is_dir($log_dir)) {
            utils::mkdir_p($log_dir);
        }

        $log_file = $log_dir . '/financebase_' . $log_type . '.log';

        if (is_array($params)) {
            $params = json_encode($params, JSON_UNESCAPED_UNICODE);
        }

        $log_msg = sprintf("%s [ %s ] : %s \n", date('c'), $log_title, $params);

        error_log($log_msg, 3, $log_file);
    }

    /**
     * 获取csv文件总行数
     * @param String $file    文件地址
     * @param Int    $length  读取长度
     * @param Int    $start   开始行数
     */
    public function getCsvData($file = '', $length = 0, $start = 0)
    {
        if (!file_exists($file)) {
            return array();
        }

        $splObject = new SplFileObject($file, 'rb');
        $length    = $length ? $length : $this->getCsvTotalLines($file) - $start;
        $start     = ($start < 0) ? 0 : $start;
        $data      = array();
        $splObject->seek($start);
        while ($length-- && !$splObject->eof()) {
            $current = $splObject->current(); //current方法不会跳行

            $encode = mb_detect_encoding($current, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ('UTF-8' != $encode) {
                $current = mb_convert_encoding($current, 'UTF-8', $encode);
            }

            $data[] = str_getcsv($current); //再转成数组
            $splObject->next();
        }
        return $data;
    }

    /**
     * 获取csv文件总行数
     * @param String $file 文件地址
     */
    public function getCsvTotalLines($file = '')
    {
        if (!file_exists($file)) {
            return 0;
        }

        $splObject = new SplFileObject($file, 'rb');
        $splObject->seek(filesize($file));
        return $splObject->key() + 1;
    }

    public function strIconv($str, $from = 'gbk', $to = 'utf-8')
    {
        return iconv("$from", "$to//TRANSLIT", $str);
    }

    /**
     * 下载
     * @return bool
     */
    public function download($url, $write_file)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $fp = fopen($write_file, 'w+');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return true;
    }

    /**
     * 解压
     * @return bool
     */
    public function unZip($zip_file, $unzip_dir, $is_delete = 0)
    {
        if (!is_dir($unzip_dir)) {
            utils::mkdir_p($unzip_dir);
        }

        $zip = new ZipArchive;
        if ($zip->open($zip_file) === true) {
            $zip->extractTo($unzip_dir);
            $zip->close();
        }
        if ($is_delete) {
            unlink($zip_file);
        }

        return true;
    }

    /**
     * 接口请求
     * @return bool
     */
    public function request($method, $params, $callback, $log_title, $shop_id, $time_out = 5, $queue = false, $addon = '', $write_log = '', $mode = 'sync')
    {
        $rpcObj = kernel::single('ome_rpc_request');

        if ($mode == 'sync') {
            $rs = $rpcObj->rpc_request($method, $params, null, $time_out);
            $rs = (array) $rs;
            if (isset($rs['data'])) {
                $rs['data'] = json_decode($rs['data'], true);
            }
        } elseif ($mode == 'async') {

            $rs = $rpcObj->request($method, $params, $callback, $log_title, $shop_id, $time_out, $queue, $addon, $write_log);
        } else {
            $rs = array('rsp' => 'fail', 'err_msg' => '请求类型错误！');
        }

        return $rs;
    }


    /**
     * node_type中key为店铺类型，value为账单类型
     * 
     * @param mixed $key
     */
    public function getConfig($key = '')
    {
        $config = array(
            'node_type' => array(
                'taobao' => 'alipay', 
                'tmall' => 'alipay', 
                '360buy' => '360buy', 
                'antopen' => 'alipay', 
                'luban'=>'luban', 
                'youzan'=>'youzan',
                'wx' => 'wxpay',
                'weixinshop' => 'wechatpay',
                'wxshipin' => 'wxpay',
                'website' => 'wechatpay',
                'pinduoduo' => 'pinduoduo',
                'vop' => 'vop',

            ),
            'page_size' => 500,
        );
        return $key ? $config[$key] : $config;
    }

    // 获取店铺平台
    public function getShopPlatform($column_platform = false)
    {
        $data = array(
            'alipay' => '支付宝',
            '360buy' => '京东',
            'luban' => '抖音',
            'youzan' => '有赞',
            // 'vip'    => '唯品会',
            'pinduoduo' => '拼多多',
            'wechatpay'    => '微信',
            'wx'    => '微信小店',
            'vop'    => '唯品会',
            'miaosuda' => '喵速达', // shop_type='taobao' and business_type='maochao'
            'weimobr' => '微盟零售',
        );
        if (!$column_platform){
            $data = array_merge($data, array(
                'jdwallet' => '京东钱包',
                'jdecard' => '京东E卡',
                'jdguobu' => '京东国补',
                'tmyp' => '天猫优品',
            ));
        }
        return $data;
    }
    public static function getShopType()
    {
        $data = array ('taobao','360buy','luban','youzan','pinduoduo','wx','weixinshop','wxshipin','website','vop','xhs','miaosuda','weimobr');
        return $data;
    }

    public static function getShopList($shop_type='', $s_type = '', $filter_config = array())
    {
        $filter = array('active' => 'true','delivery_mode'=>'self');
        if ($shop_type) {
            if (is_array($shop_type)) {
                $filter['shop_type|in'] = $shop_type;
            } else {
                $filter['shop_type'] = $shop_type;
            }
        } else {
            $filter['shop_type|noequal'] = '';
        }

        if ($s_type){
            $filter['s_type'] = $s_type;
        }

        // 支持灵活的筛选条件（支持 business_type, tbbusiness_type 等扩展字段）
        if ($filter_config && is_array($filter_config)) {
            foreach ($filter_config as $field => $value) {
                // 跳过 shop_type（已在上面处理）
                if ($field === 'shop_type') {
                    continue;
                }
                
                // 处理各种筛选字段
                if ($value) {
                    if (is_array($value)) {
                        $filter[$field . '|in'] = $value;
                    } else {
                        $filter[$field] = $value;
                    }
                }
            }
        }

        return app::get('ome')->model('shop')->getList('shop_id,name,shop_type,node_type,business_type,config', $filter);
    }
    
    public static function getQueueConfig($shop_type)
    {
        $data = [
            'luban' => [
                'interval_time' => 3600,//秒 间隔时间
                'specific_time' => strtotime(date("Y-m-d") . " 10:30:00"),//时间点
            ],
            'default' => [
                'interval_time' => 0,//0 表示一天执行一次
                'specific_time' => strtotime(date("Y-m-d") . " 10:30:00"),//时间点
            ]
        ];
        
        return $data[$shop_type] ?? $data['default'];
    }

    /**
     *  数据保存到存储空间 返回远程数据
     *
     * @param      string   $file_name  文件名
     * @param      array    $data       数据
     *
     * @return     Mix
     */
    public static function storeStorageData($file_name, $data = array())
    {
        $storageLib = kernel::single('taskmgr_interface_storage');
        $local_file = DATA_DIR . '/financebase/tmp_local/' . $file_name;
        if (!is_dir(dirname($local_file))) {
            utils::mkdir_p(dirname($local_file));
        }

        file_put_contents($local_file, json_encode($data, JSON_UNESCAPED_UNICODE));

        $move_res = $storageLib->save($local_file, $file_name, $remote_url);
        unlink($local_file);

        if ($move_res) {
            return $remote_url;
        }

        return false;
    }

    public static function getStorageData($remote_url, $local_file)
    {
        $storageLib  = kernel::single('taskmgr_interface_storage');
        $getfile_res = $storageLib->get($remote_url, $local_file);
        if ($getfile_res) {
            return json_decode(file_get_contents($local_file), 1);
        }
        return array();
    }

    public static function deleteStorageData($remote_url)
    {
        $storageLib = kernel::single('taskmgr_interface_storage');
        $storageLib->delete($remote_url);
    }

    public static function addTaskQueue($params, $task_type = 'verificationprocess')
    {
        $params['task_type'] = $task_type;
        $push_params         = array(
            'data' => $params,
            'url'  => kernel::openapi_url('openapi.autotask', 'service'),
        );
        return kernel::single('taskmgr_interface_connecter')->push($push_params);
    }

    public static function getFeeItem()
    {
        return array(
            1 => array('platform_type' => '淘宝', 'fee_type' => '交易付款'),
            2 => array('platform_type' => '淘宝', 'fee_type' => '在线支付'),
            3 => array('platform_type' => '淘宝', 'fee_type' => '交易退款'),
            4 => array('platform_type' => '淘宝', 'fee_type' => '交易分账'),
            5 => array('platform_type' => '淘宝', 'fee_type' => '转账'),
            6 => array('platform_type' => '淘宝', 'fee_type' => '其它'),
            7 => array('platform_type' => '京东', 'fee_type' => '货款'),
            8 => array('platform_type' => '京东', 'fee_type' => '售后卖家赔付费'),
            9 => array('platform_type' => '京东', 'fee_type' => '代收配送费'),
            10 => array('platform_type' => '京东', 'fee_type' => '定金货款'),
            11 => array('platform_type' => '京东', 'fee_type' => '尾款货款'),
            12 => array('platform_type' => '京东', 'fee_type' => '价保扣款'),
        );

    }

    public static function getShopExtends()
    {
        $extends_list = app::get('channel')->model('channel')->getList('channel_bn', array('channel_type' => 'ipay', 'filter_sql' => 'node_id is not null AND node_id!=""'));
        if ($extends_list) {
            $tmp_shop_list = financebase_func::getShopList(financebase_func::getShopType());
            if(empty($tmp_shop_list)) {
                return [];
            }
            $shopId = array_column($tmp_shop_list, 'shop_id');
            $shop_id = array();
            foreach ($extends_list as $key => $value) {
                if(in_array($value['channel_bn'] , $shopId)) {
                    $shop_id[] = $value['channel_bn'];
                }
            }

            return app::get('ome')->model('shop')->getList('name,shop_id', array('shop_id|in' => $shop_id));
        } else {
            return array();
        }

    }

    public static function dd($data)
    {
        echo '<pre>';
        print_r($data);exit;
    }
    
    /**
     * 辅助函数：检测文件是否为压缩文件
     * @param $file_path 文件路径
     * @return bool
     * @date 2024-10-24 4:41 下午
     */
    public function isCompressedFile($file_path) {
        $file_info = finfo_open(FILEINFO_MIME_TYPE); // 返回MIME类型
        $mime_type = finfo_file($file_info, $file_path);
        finfo_close($file_info);
        
        // 检查MIME类型是否为压缩文件
        $compressed_mime_types = ['application/zip', 'application/x-zip-compressed', 'application/x-tar', 'application/gzip', 'application/x-gzip', 'application/x-bzip2', 'application/x-bzip'];
        
        return in_array($mime_type, $compressed_mime_types);
    }


    public static function getorgShopList($shop_type='', $s_type = '')
    {
        $filter = array('active' => 'true','delivery_mode'=>'self');
        if ($shop_type) {
            if (is_array($shop_type)) {
                $filter['shop_type|in'] = $shop_type;
            } else {
                $filter['shop_type'] = $shop_type;
            }
        } else {
            $filter['shop_type|noequal'] = '';
        }

        if ($s_type){
            $filter['s_type'] = $s_type;
        }

        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $filter['org_id'] = $organization_permissions;
        }
        return app::get('ome')->model('shop')->getList('shop_id,name,shop_type,node_type,business_type,config', $filter);
    }
}
