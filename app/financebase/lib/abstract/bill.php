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

abstract class financebase_abstract_bill{

	public $task_num = 500;// 任务分块
	public $exec_sql_num = 50;//执行sql分块
	public $rules = array();//单据规则
    public $fee_item_rules = array();//单据类型
    public $shop_list = array();//店铺信息列表
    public $order_bn_date = array();//订单时间
    public static $ar_verify_rules = array();//AR核对规则缓存

	abstract public function getTitle();// 获取导入文件标题
    abstract public function getBillCategory($params);// 获取具体类别


    /**
     * 处理任务
     * @Author YangYiChao
     * @Date   2019-05-31
     * 从rpc获取下载链接，并进行下载，完成后进行异步处理
     */
    public function process($cursor_id,$params,&$errmsg)
    {
        @ini_set('memory_limit','128M');
        $oFunc = kernel::single('financebase_func');

        $oFunc->writelog('支付单对账单-处理任务-开始','settlement',$params);

        $mdlSettlement = app::get('financebase')->model('bill');

        $data = $params['data'];
       
        $sdf = array();

        $basic_sdf = array();

        $insert_sql = "";

        $insert_basic_sql = "insert into sdb_financebase_bill_base(shop_id,unique_id,content,create_time)values";

        $offset = intval($data['offset']) + 1;//文件行数 行数默认从1开始

        if($data){

            if(!isset($this->shop_list[$params['shop_id']]))
            {
                $shop_info = app::get("ome")->model('shop')->getList('name,shop_type,business_type,shop_bn',array('shop_id'=>$params['shop_id']));
                if($shop_info) $this->shop_list[$params['shop_id']] = $shop_info[0];
            }

            //单据类型
            if(!$this->fee_item_rules){
                $this->fee_item_rules = app::get('financebase')->getConf('bill.rules.ref');
            }
            $data = $this->batchTransferData($data, $params['title']);
            // 组织数据
            foreach ($data as $row) {

                $res = $this->getSdf($row,$offset,$params['title']);
                if($res['status'] and $res['data'])
                {
                    $tmpData = $res['data'];
                
                    $this->_formatData($tmpData);
                
                    $base_content = addslashes(json_encode($tmpData,JSON_UNESCAPED_UNICODE));

                    $bill_category = $this->getBillCategory($tmpData);

                    // 重置 $tmpData
                    $tmp = $this->_filterData($tmpData);

                    $shop_id = $tmpData['shop_id'] ?: $params['shop_id'];

                    $tmp['remarks']         = str_replace('\'', '', $tmp['remarks']);
                    $tmp['shop_id']         = $shop_id;
                    $tmp['create_time']     = time();
                    $tmp['bill_category']   = $bill_category;
                    $tmp['disabled']        = $tmp['bill_category'] ? 'false' : 'true';
                    $tmp['order_create_date'] = $this->getOrderBnDate($tmp['order_bn']);
                    $tmp['ar_verify_status'] = $this->getArVerifyStatus($tmp['bill_category']);
                 
                    $sdf[$tmp['unique_id']] = $tmp;

                    $basic_sdf[$tmp['unique_id']] = array('shop_id'=>$params['shop_id'],'unique_id'=>$tmp['unique_id'],'content'=>$base_content,'create_time' => $tmp['create_time']);
    
                    // if(!$insert_sql) $insert_sql = sprintf("insert into sdb_financebase_bill(%s)values",implode(',', array_keys($tmp)));
                    if(!$insert_sql) $insert_sql = sprintf("insert into sdb_financebase_bill(%s)values",implode(',', array_keys($tmp)));
                }elseif (!$res['status']) {
                    array_push($errmsg, $res['msg']);
                }
                
                $offset ++;
            }
        }
        unset($data);

        // 判断 unique_id 是否有重复
        $check_exist_data = $mdlSettlement->getList('unique_id',array('shop_id'=>$params['shop_id'],'unique_id|in'=>array_keys($sdf)));
        if($check_exist_data){
            foreach ($check_exist_data as $v)
            {
                unset($sdf[$v['unique_id']]);
                unset($basic_sdf[$v['unique_id']]);
            }
        }
        unset($check_exist_data);

        if($sdf){
            $sdf = array_chunk($sdf, $this->exec_sql_num);
            foreach ($sdf as $row) {
                $arr_sql = array();
                $arr_basic_sql = array();
                $arr_unique_id = array();
                $arr_ar_verify_status = array();
                foreach ($row as $v) {
                    // $v = array_map('trim',$v);
                    $arr_sql[] = sprintf("('%s')",implode("','", array_values($v)));
                    $arr_basic_sql[] = sprintf("('%s')",implode("','", array_values($basic_sdf[$v['unique_id']])));
                    $arr_unique_id[$v['unique_id']] = $v['bill_category'];
                    
                    // ar_verify_status是3的，汇总出来，数据丢给统一app处理
                    if($v['ar_verify_status'] == 3){
                        $arr_ar_verify_status[] = $v['unique_id'];
                    }
                }
                // 插入bill表
                $sql = $insert_sql.implode(',', $arr_sql);
                if($mdlSettlement->db->exec($sql))
                {
                    // 插入bill基础表
                    $sql = $insert_basic_sql.implode(',', $arr_basic_sql);
                    $mdlSettlement->db->exec($sql);

                    foreach ($arr_unique_id as $unique_id=>$bill_category) 
                    {
                        // 同步bill
                        $this->syncToBill($basic_sdf[$unique_id],$bill_category);
                    }

                    if($arr_ar_verify_status){
                        $servicelist = kernel::servicelist('financebase.service.financebase_abstract_bill.process.after');
                        foreach ($servicelist as $instance) {
                            if (method_exists($instance, 'handleNoArVerify')){
                                $instance->handleNoArVerify($arr_ar_verify_status);
                            }
                        }
                    }
            
                }
                else{
                    $errmsg[] = '支付单对账单-处理任务-插入SQL错误'.$sql;
                    $oFunc->writelog('支付单对账单-处理任务-插入SQL错误','settlement',$sql);
                }
            }
        }
  
        $oFunc->writelog('支付单对账单-处理任务-完成','settlement','Done');
        return $sdf;
    }

    /**
     * batchTransferData
     * @param mixed $data 数据
     * @param mixed $title title
     * @return mixed 返回值
     */
    public function batchTransferData($data, $title) {
        return $data;
    }
    /**
     * 重新匹配对账单
     * @Author YangYiChao
     * @Date   2019-06-03
     */
    public function rematch($cursor_id,$params,&$errmsg)
    {
        // @ini_set('memory_limit','128M');
        
        $oFunc = kernel::single('financebase_func');

        $oFunc->writelog('具体类别-重新匹配-开始','settlement',$params);

        if($params['ids'])
        {
            $mdlBill = app::get('financebase')->model('bill');
            $mdlBillBase = app::get('financebase')->model('bill_base');
            $mdlFinanceBill = app::get('finance')->model('bill');

            $list = $mdlBillBase->getList('shop_id,unique_id,content,create_time',array('shop_id'=>$params['shop_id'],'unique_id|in'=>$params['ids']));

            $class_key = $params['shop_type'];
            
            if($list)
            {
                foreach ($list as $v) {
      
                    $data = json_decode($v['content'],1);

                    $bill_category = $this->getBillCategory($data);
                    $orderBn = $this->_getOrderBn($data);

                    $shop_id = $data['shop_id'] ?: $v['shop_id'];

                    if($bill_category)
                    {
                        $old = $mdlBill->db_dump(['shop_id'=>$shop_id,'unique_id'=>$v['unique_id']], 'id,bill_category,ar_verify_status');
                        $upData = [];
                        if($bill_category != $old['bill_category']) {
                            $upData = ['disabled'=>'false','split_status'=>'0','bill_category'=>$bill_category];
                        }
                        if($orderBn) {
                            $upData['order_bn'] = $orderBn;
                        }
                        $upData['ar_verify_status'] = $this->getArVerifyStatus($bill_category);

                        if($upData){
                            $mdlBill->update($upData,['id'=>$old['id']]);
                        }

                        $mdlFinanceBill->update(['fee_item'=>$bill_category],['channel_id'=>$shop_id,'unique_id'=>$v['unique_id']]);

                        if (!$mdlFinanceBill->count(['channel_id'=>$shop_id,'unique_id'=>$v['unique_id']])) {
                            $this->syncToBill($v, $bill_category);
                        }
                        
                        // ar_verify_status是3的，汇总出来，数据丢给统一app处理
                        if($upData['ar_verify_status'] == 3 && $old['ar_verify_status'] == 2){
                            $servicelist = kernel::servicelist('financebase.service.financebase_abstract_bill.process.after');
                            foreach ($servicelist as $instance) {
                                if (method_exists($instance, 'handleNoArVerify')){
                                    $instance->handleNoArVerify(array($v['unique_id']));
                                }
                            }
                        }
                    } else {
                        $errmsg = '未匹配到具体类别:'.json_encode($data, JSON_UNESCAPED_UNICODE);
                    }
                }
            } else {
                $errmsg = '未查询到单据';
            }
        } else {
            $errmsg = '缺少ids';
        }
        
        $oFunc->writelog('具体类别-重新匹配-完成','settlement','Done');

        return true;
    }

    /**
     * 分派重新匹配任务
     * @Author YangYiChao
     * @Date   2019-06-09
     */
    public function doRematchTask($cursor_id,$params,&$errmsg)
    {

        $oFunc = kernel::single('financebase_func');
        $mdlShopSettlement = app::get('financebase')->model('bill');

        $page_size = $oFunc->getConfig('page_size');

        $platform_type = $params['platform_type'] ? $params['platform_type'] : 'alipay';
        $worker = "financebase_data_bill_".$platform_type.".rematch";
        $filter = [
            'shop_id'=>$params['shop_id'],
            'trade_time|bthan' => strtotime($params['rematch_time_from']),
            'trade_time|sthan' => strtotime($params['rematch_time_to'].' 23:59:59')
        ];
        if('nomatch' == $params['rematch_mode'])
        {
            $filter['disabled'] = 'true';
        } elseif ('bill_category' == $params['rematch_mode']) {
            $filter['bill_category'] = $params['bill_category'];
        }

        $total_num = $mdlShopSettlement->count($filter);

        if($total_num > 0 ){
            $last_id = 0;

            $total_page = ceil($total_num/$page_size);

            for ($i=0; $i < $total_page; $i++) { 
                $filter['id|than'] = $last_id;
                $unique_id = array();
                $list = $mdlShopSettlement->getList('unique_id,id',$filter,0,$page_size,'id');
                if($list){
                    foreach ($list as $v) {
                        array_push($unique_id, $v['unique_id']);
                        $last_id = $v['id'];
                    }

                    $data = array();
                    $data['ids'] = $unique_id;
                    $data['shop_id'] = $params['shop_id'];
                    $oFunc->addTask('重新匹配对账单',$worker,$data);

                }
            }

        }
    }

    /**
     * 获取分块导出数据
     * @Author YangYiChao
     * @Date   2019-06-07
     */
    public function getExportData($filter=array(),$page_size=500,&$id){
        $mdlShopSettlement = app::get('financebase')->model('bill');
        $mdlShopSettlementBasic = app::get('financebase')->model('bill_base');

        $res = array();

        $filter['id|than'] = $id;
        $data = $mdlShopSettlement->getList('unique_id,id,bill_category,shop_id,order_bn,order_create_date',$filter,0,$page_size,'id');
        if($data){
            $array_bill_category = array();
            foreach ($data as $v) {
                $array_bill_category[$v['unique_id']] = array('bill_category'=>$v['bill_category'],'order_bn'=>$v['order_bn']);
                $id = $v['id'];
            }
            unset($data);


            $base_filter = array('unique_id|in'=>array_keys($array_bill_category));
            isset($filter['shop_id']) and $base_filter['shop_id'] = $filter['shop_id'];
            $data = $mdlShopSettlementBasic->getList('unique_id,content,shop_id',$base_filter);

            foreach ($data as &$v) {
                $v['content'] = json_decode($v['content'],1);
                foreach ($v['content'] as $k2 => $v2) {
                    if( is_numeric($v2) and !in_array($k2, array('income_amount','outcome_amount','amount')) ) $v['content'][$k2] = "\t".$v2;
                }
                $v['content']['shop_id'] = $v['shop_id'];
                $v['content']['order_bn'] = $array_bill_category[$v['unique_id']]['order_bn'];
                $v['content']['bill_category'] = $array_bill_category[$v['unique_id']]['bill_category'];
                $v['content']['order_create_date'] = $v['order_create_date'];
                array_push($res, $v['content']);
            }
        }

        return $res;
    }


    /**
     * 检查File
     * @param mixed $file_name file_name
     * @param mixed $file_type file_type
     * @return mixed 返回验证结果
     */
    public function checkFile($file_name,$file_type){
        return true;
    }

	// 读取规则 
    /**
     * 获取Rules
     * @param mixed $type type
     * @return mixed 返回结果
     */
    public function getRules($type='alipay')
    {

        if(!$this->rules){
           $this->rules = kernel::single('financebase_data_bill_category_rules')->getRules($type);
        }
        return $this->rules;
    }

    

    /**
     * 检查Rule
     * @param mixed $rule rule
     * @return mixed 返回验证结果
     */
    public function checkRule(&$rule)
    {
       
        $is_and_ok = true;
        $is_or_ok  = true;

        if(isset($rule['and']) and $rule['and'])
        {
            foreach ($rule['and'] as $v) 
            {
                if(!$this->_checkData($v['rule_value'], $this->verified_data[$v['rule_type']], $v['rule_filter']))
                {
                    $is_and_ok = false;
                    break;
                }
            }
        }

        if($is_and_ok and isset($rule['or']) and $rule['or'])
        {
            $is_or_ok = false;
            foreach ($rule['or'] as $v) 
            {

                if($this->_checkData($v['rule_value'], $this->verified_data[$v['rule_type']], $v['rule_filter']))
                {
                    $is_or_ok = true;
                    break;
                }
            }
        }


        if($is_and_ok and $is_or_ok){
            return true;
        }else{
            return false;
        }
         
    }

    /**
     * 检查规则
     * @Author YangYiChao
     * @Date   2019-06-03
     * @param  [String]     $search  搜索词
     * @param  [String]     $content 内容
     * @param  [String]     $mode    contain:包含  nocontain:不包含  equal:相等
     * @return [Bool]       
     */
    public function _checkData($search, $content,$mode='contain')
    {
    	switch ($mode) {
    		case 'contain':
    			// 使用 strpos 进行字符串包含检查，避免正则表达式特殊字符问题
    			if(strpos($content, $search) !== false) return true;
    			break;
    		case 'nocontain':
    			// 使用 strpos 进行字符串不包含检查，避免正则表达式特殊字符问题
    			if(strpos($content, $search) === false) return true;
    			break;
    		default:
    			return $content == $search;
    			break;
    	}

    	return false;
    }

    public function _formatData(&$data){
        foreach ($data as $k=>$str) {
            $data[$k] = str_replace(array("\r\n", "\r", "\n","\t"), "", $str);
        }
    }

    /**
     * 获取BillType
     * @param mixed $params 参数
     * @param mixed $shop_id ID
     * @return mixed 返回结果
     */
    public function getBillType($params,$shop_id)
    {
        if(!$this->array_fee_type)
        {
            $list = app::get('financebase')->model('bill_fee_type')->getList('*',array());

            foreach ($list as $key => $value) {
                if ($value['rule_id']) $list[$key]['bill_category'] = &$bill_category[$value['rule_id']];
            }

            if ($rule_id = array_keys( (array) $bill_category)) {
                $ruleMdl = app::get('financebase')->model('bill_category_rules');
                foreach ($ruleMdl->getList('rule_id,bill_category', array ('rule_id' => $rule_id)) as $value) {
                    $bill_category[$value['rule_id']] = $value['bill_category'];
                }
            }

            foreach ($list as $v) 
            {
                $this->array_fee_type[$v['shop_id']][$v['bill_category']] = $v;
            }
        }

        // bill_type  0:表示收入 1：表示支出 
        $res = array('status'=>true,'bill_type'=>0,'fee_type'=>'收入','fee_type_id'=>0);

        $fee_type_exist = false;
        if ( isset($this->array_fee_type[$shop_id][$params['fee_item']]) ) $fee_type_exist = true;

        if( $fee_type_exist === true )
        {
            if('taobao' == $this->shop_list[$shop_id]['shop_type'] and 'fx' == $this->shop_list[$shop_id]['business_type'] )
            {
                $whitelist = $this->array_fee_type[$shop_id][$params['fee_item']]['whitelist'];

                if(!$this->checkWhiteList($params['member'],$whitelist))
                {
                    $res['status'] = false;
                    return $res;
                }
            }

            //改成金额来判断实收实退
            if(in_array($params['fee_obj'],['淘宝', '有赞','拼多多']))
            {
                $amount = $params['outcome_amount'] < 0 ? $params['outcome_amount'] : $params['income_amount'];
            }else{
                $amount = $params['amount'];
            }

            //改成金额来判断实收实退
            if($amount < 0 )
            {
                $res['bill_type'] = 1;
            }
            elseif ($amount > 0) 
            {
                $res['bill_type'] = 0;
            }
            else
            {
                $res['bill_type'] = $this->array_fee_type[$shop_id][$params['fee_item']]['bill_type'];
            }

            $res['fee_type']  = $res['bill_type'] ? '支出' : '收入';
            $res['fee_type_id'] = $this->array_fee_type[$shop_id][$params['fee_item']]['fee_type_id'];
        }
        else
        {
            $res['status'] = false;
        }

        return $res;
    }

    /**
     * 检查WhiteList
     * @param mixed $member member
     * @param mixed $whitelist whitelist
     * @return mixed 返回验证结果
     */
    public function checkWhiteList($member,$whitelist)
    {
 
        $whitelist = explode("\n", $whitelist);
        foreach ($whitelist as $v) 
        {
            if(preg_match("/$v/", $member)) return true;
        }
        return false;
    }

    /**
     * 获取OrderBnDate
     * @param mixed $order_bn order_bn
     * @return mixed 返回结果
     */
    public function getOrderBnDate($order_bn)
    {
        if(!$order_bn) return "";
        if(!$this->mdlOmeOrder)
        {
            $this->mdlOmeOrder = app::get('ome')->model('orders');
        }

        if(!isset($this->order_bn_date[$order_bn]))
        {
            $row = $this->mdlOmeOrder->getList('createtime',array('order_bn'=>$order_bn),0,1);
            $this->order_bn_date[$order_bn] = $row ? date('Y-m-d H:i:s',$row[0]['createtime']) : '';
        }

        return $this->order_bn_date[$order_bn];
    }

    /**
     * 获取AR核对状态
     * @param string $bill_category 具体类别
     * @return int 返回状态值：1-已核对AR，2-需核对AR，3-无需核对AR
     */
    public function getArVerifyStatus($bill_category = '')
    {
        // 如果bill_category为空，返回2（需核对AR）
        if(empty($bill_category)) {
            return 2;
        }

        // 如果静态缓存为空，一次性查询所有规则
        if(empty(self::$ar_verify_rules)) {
            $mdlRules = app::get('financebase')->model('bill_category_rules');
            $rules = $mdlRules->getList('bill_category,need_ar_verify', array('disabled' => 'false'));
            
            self::$ar_verify_rules = array_column($rules, 'need_ar_verify', 'bill_category');
        }

        // 根据bill_category查找对应的need_ar_verify值
        $need_ar_verify = isset(self::$ar_verify_rules[$bill_category]) ? self::$ar_verify_rules[$bill_category] : 1;

        // 根据need_ar_verify值返回对应的状态
        if($need_ar_verify == 0) {
            return 3; // 无需核对AR
        } else {
            return 2; // 需核对AR
        }
    }
}
