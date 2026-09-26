<?php

abstract class openapi_api_function_abstract{
    const API_LOG_MSG_MAX_BYTES = 60000;

    public function charFilter($str){
        if(strpos($str, kernel::single('ome_security_hash')->get_code())) {
            //return kernel::single('ome_func')->getEncryptText($str);
            
            //加密信息也有包含\反斜杠的情况
            $str = kernel::single('ome_func')->getEncryptText($str);
        }
        return str_replace(array("\t","\r","\n",'"',"\\",''),array(" "," "," ",'“',"/",''),$str);
    }
    #获取所有仓库,type等于1是自建仓库
    public function get_all_branchs($type = '1',$branch_bn='',$page_no=0,$page_size=-1){
        #基础条件，必须是自建仓库
        $filter = array('owner'=>'1');
        $is_super = kernel::single('desktop_user')->is_super();
        #按操作员
        if(!$is_super){
            $opInfo = kernel::single('ome_func')->getDesktopUser(1);
            $op_id = $opInfo['op_id'];
            $filter['op_id']= $op_id;
        }
        #按仓库编号
        if($branch_bn){
            $filter['branch_bn'] = $branch_bn;
        }
        $branchObj = app::get('ome')->model('branch');
        $branch_arr = $branchObj->getList('branch_id,branch_bn,name', $filter, $page_no, $page_size);
        return $branch_arr;
    }

    /**
     * 日志
     *
     * @return void
     * @author 
     **/
    public function _write_log($title, $original_bn, $status = 'success', $params = array(), $result=array(), $convert_params = array())
    {
        $apilogModel = app::get('ome')->model('api_log');
        $log_id = $apilogModel->gen_id();

        if (!empty($params['task']) && !empty($result['rsp']) && $result['rsp'] === 'succ') {
            $apilogModel->set_repeat($params['task'], $log_id);
        }

        $paramsForMsg = self::summarizeApiLogPayload($params);
        $resultForMsg = self::summarizeApiLogPayload($result);
        $msg = '接收参数：' . var_export($paramsForMsg, true)
            . '<hr/>返回结果：' . var_export($resultForMsg, true);
        $msg = self::truncateApiLogMsg($msg);

        $logsdf = array(
            'log_id'        => $log_id,
            'task_name'     => $title,
            'status'        => $status,
            'worker'        => '',
            'params'        => serialize(array($_REQUEST['method'], $_REQUEST)),
            'msg'           => $msg,
            'log_type'      => '',
            'api_type'      => 'response',
            'memo'          => '',
            'original_bn'   => $original_bn,
            'createtime'    => time(),
            'last_modified' => time(),
        );

        $apilogModel->insert($logsdf);
    }

    protected static function summarizeApiLogPayload($data)
    {
        if (!is_array($data)) {
            if (is_string($data) && strlen($data) > 2000) {
                return sprintf('[string, %d bytes]', strlen($data));
            }
            return $data;
        }

        $summary = array();
        foreach ($data as $key => $val) {
            if (in_array($key, array('materials', 'stocks', 'stores', 'metadatas', 'prices'), true)) {
                $summary[$key] = self::summarizeApiLogBatchField($val);
                continue;
            }
            if (in_array($key, array('fail_detail', 'unique_codes', 'raw_ids'), true) && is_array($val)) {
                $summary[$key] = self::summarizeApiLogList($val, 20, $key);
                continue;
            }
            if ($key === 'items' && is_array($val)) {
                $summary[$key] = self::summarizeApiLogList($val, 10, 'items');
                continue;
            }
            if (is_array($val)) {
                if (self::isApiLogListArray($val) && count($val) > 20) {
                    $summary[$key] = self::summarizeApiLogList($val, 20, $key);
                    continue;
                }
                $summary[$key] = self::summarizeApiLogPayload($val);
                continue;
            }
            if (is_string($val) && strlen($val) > 2000) {
                $summary[$key] = sprintf('[string, %d bytes]', strlen($val));
                continue;
            }
            $summary[$key] = $val;
        }
        return $summary;
    }

    protected static function summarizeApiLogBatchField($val)
    {
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (is_array($decoded)) {
                return sprintf('[JSON, %d items, %d bytes]', count($decoded), strlen($val));
            }
            return sprintf('[string, %d bytes]', strlen($val));
        }
        if (is_array($val)) {
            return sprintf('[array, %d items]', count($val));
        }
        return $val;
    }

    protected static function summarizeApiLogList(array $list, $limit = 20, $label = 'items')
    {
        $total = count($list);
        if ($total <= $limit) {
            return $list;
        }
        $sample = array_slice($list, 0, $limit);
        $sample[] = sprintf('...(+%d %s omitted, total %d)', $total - $limit, $label, $total);
        return $sample;
    }

    protected static function isApiLogListArray(array $arr)
    {
        if (!$arr) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    protected static function truncateApiLogMsg($msg)
    {
        if (strlen($msg) <= self::API_LOG_MSG_MAX_BYTES) {
            return $msg;
        }
        $suffix = sprintf("\n...(truncated, total %d bytes)", strlen($msg));
        return substr($msg, 0, self::API_LOG_MSG_MAX_BYTES - strlen($suffix)) . $suffix;
    }
}
