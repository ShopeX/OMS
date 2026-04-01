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


class finance_mdl_ar extends dbeav_model{
    var $defaultOrder = array('create_time',' desc');
    //public $has_many = array('items'=>'ar_items');
    var $has_export_cnf = true;
    var $export_name = '应收应退报表';
    
    /**
     * import_params
     * @param mixed $public public
     * @return mixed 返回值
     */
    public function import_params($public){
        return kernel::single('finance_io_bill_rule')->type( $public['importFiletype'] )->getParams($public);
    }

    /**
     * import_title
     * @return mixed 返回值
     */
    public function import_title(){
         return kernel::single('finance_io_bill_rule')->type( $this->get_import_filetype() )->getTitle();
    }

    /**
     * export_template
     * @param mixed $public public
     * @return mixed 返回值
     */
    public function export_template($public){
        return kernel::single('finance_io_bill_rule')->type($public['salesExportType'])->getTitle();
    }

    /**
     * import_line_rule
     * @param mixed $row row
     * @param mixed $line line
     * @return mixed 返回值
     */
    public function import_line_rule($row,$line){
        return kernel::single('finance_io_bill_rule')->type( $this->get_import_filetype() )->isFilterLine($row,$line);
    }

    /**
     * import_title_rule
     * @param mixed $row row
     * @param mixed $line line
     * @return mixed 返回值
     */
    public function import_title_rule($row,$line){
        return kernel::single('finance_io_bill_rule')->type( $this->get_import_filetype() )->isTitle($row,$line);
    }

    /**
     * structure_import_isGetItems
     * @return mixed 返回值
     */
    public function structure_import_isGetItems(){
        return true;
    }

    public function structure_import_data($row,&$format_row=array(),&$result){
        kernel::single('finance_io_bill_process')->type( $this->get_import_filetype() )->structure_import_data($this,$row,$format_row,$result);
    }

    /**
     * 检查ing_import_data
     * @param mixed $row row
     * @param mixed $result result
     * @return mixed 返回验证结果
     */
    public function checking_import_data($row,&$result){
        kernel::single('finance_io_bill_process')->type( $this->get_import_filetype() )->checking_import_data($this,$row,$result);
    }

    public $is_transaction = true; 
    /**
     * finish_import_data
     * @param mixed $row row
     * @param mixed $result result
     * @return mixed 返回值
     */
    public function finish_import_data($row,&$result){
        kernel::single('finance_io_bill_process')->type( $this->get_import_filetype() )->finish_import_data($this,$row,$result);
    }

    private function get_import_filetype(){
        return '';
    }
    
    function modifier_type($row){
        return kernel::single('finance_ar')->get_name_by_type($row);
    }

    //重载记账状态，展示
    function modifier_charge_status($row){
        return kernel::single('finance_ar')->get_name_by_charge_status($row);
    }

    //重载核销状态，展示
    function modifier_status($row){
        return kernel::single('finance_ar')->get_name_by_status($row);
    }

    //重载核销状态，展示
    function modifier_monthly_status($row){
        return kernel::single('finance_ar')->get_name_by_monthly_status($row);
    }

    //重载单据类型
    function modifier_ar_type($row){
        return kernel::single('finance_ar')->get_name_by_ar_type($row);
    }
    
    function modifier_verification_flag($row){
        return $row == '1' ? '是' : '否';
    }

    /*
    **通过实收账单id获取单号相同，或关联单号等于单号，或交易对方（member）相同的应收单据
    **@params $bill_id 实收账单单据ID
    **@params $flag 空 表示三种单据  order_bn表示只有单据号相同 relate_order_bn表示关联单号等于单号 member 表示交易对方相同
    **@return array() 应收单据的相关信息
    */

    public function get_ar_by_bill_id($bill_id,$flag='all'){
        $db = kernel::database();
        $billObj = &app::get('finance')->model('bill');
        $data = $billObj->getList('member,crc32_order_bn,order_bn',array('bill_id'=>$bill_id));
        if(empty($data)) return false;
        $base_filter = " and status != 2";
        $member = $data[0]['member'];
        $crc32_order_bn = $data[0]['crc32_order_bn'];
        $cols = 'ar_id,ar_bn,member,order_bn,trade_time,serial_number,channel_name,type,money,unconfirm_money,confirm_money,charge_status';
        if($flag == 'order_bn'){
            $sql = "select ".$cols." from sdb_finance_ar where crc32_order_bn = '".$crc32_order_bn."'".$base_filter;
            $data = $db->select($sql);
            $res = array();
            foreach($data as $k=>$v){
                if($v['order_bn'] == $data[0]['order_bn']){
                    $res[] = $v;
                }
            }
        }else if($flag == 'relate_order_bn'){
            $sql = "select ".$cols." from sdb_finance_ar where crc32_relate_order_bn = '".$crc32_order_bn."'".$base_filter;
            $data = $db->select($sql);
            $res = array();
            foreach($data as $k=>$v){
                if($v['order_bn'] == $data[0]['order_bn']){
                    $res[] = $v;
                }
            }
        }else if($flag == 'member'){
            $sql = "select ".$cols." from sdb_finance_ar where member = '".$member."'".$base_filter;
            $res = $db->select($sql);
        }else{
            $sql = "select ".$cols." from sdb_finance_ar where (crc32_order_bn = '".$crc32_order_bn."' or crc32_relate_order_bn = '".$crc32_order_bn."' or member = '".$member."')".$base_filter;
            $res = $db->select($sql);
        }
        $result = array();
        foreach($res as $k=>$v){
            $result[$k] = $v;
            $result[$k]['type'] = kernel::single('finance_ar')->get_name_by_type($v['type']);
        }
        return $result;
    }
    /*
    **应收账单核销更新
    **@params $data array('0'=>array('ar_id'=>'','unconfirm_money'=>''))
    **@params $money 核销金额（整笔交易核销总金额）
    **@return array() 应收单据的相关信息
    */

    public function do_verificate($data,$money){
        $tmp = array();
        $db = kernel::database();
        foreach ($data as $key=>$value) {
          $tmp[$value['ar_id']] = abs($value['unconfirm_money']);
          if($value['unconfirm_money'] < 0) $tmp_flag = true;
        }
        asort($tmp);
        $standard_money = abs($money);
        foreach($tmp as $a_id=>$a_money){
            if($a_money >= $standard_money){
                #金额全为正 或者 全为负 操作符判断
                if($tmp_flag == true){
                  $operator_con = "-";
                  $operator_uncon = "+";
                }else{
                  $operator_con = '+';
                  $operator_uncon = '-';
                }
                $update_ar = "update sdb_finance_ar set confirm_money = (confirm_money ".$operator_con."'".$standard_money."'),unconfirm_money = (unconfirm_money ".$operator_uncon." '".$standard_money."'),status = '1' where ar_id = '".$a_id."'";
                if(!$db->exec($update_ar)){
                    $rs_flag = true;
                    break;
                }
                break;
            }else{
                $update_ar = "update sdb_finance_ar set confirm_money = money,unconfirm_money =0 ,status = 2,verification_time=".time()." where ar_id = '".$a_id."'";
                if(!$db->exec($update_ar)){
                    $rs_flag = true;
                    break;
                }
                $standard_money = abs($standard_money-$a_money);
            }
        }
        if($rs_flag == true){
            return false;
        }
        return true;
    }

    /*
    **撤销核销
    **@params $id 主键id
    **@params $money 撤销金额
    **@return ture/false 字符串
    */

    public function do_cancel($id,$money){
        $data = $this->getList('confirm_money,unconfirm_money',array('ar_id'=>$id));
        $update['confirm_money'] = $data[0]['confirm_money'] - $money;
        $update['unconfirm_money'] = $data[0]['unconfirm_money'] + $money;
        $update['verification_time'] = 0;#完全核销时间 置为0
        if($update['confirm_money'] == 0){
            $update['status'] = 0;#未核销
        }else{
            $update['status'] = 1;#部分核销
        }
        $filter= array('ar_id'=>$id);
        if(!$this->update($update,$filter)){
            return 'false';
        }
        return 'true';
    }

    /*
    **通过order_bn获取应收账单数据
    **@params $order_bn 订单号
    **@return array()
    */
    /**
     * 获取_ar_by_order_bn
     * @param mixed $order_bn order_bn
     * @return mixed 返回结果
     */
    public function get_ar_by_order_bn($order_bn){
        if(empty($order_bn)) return false;
        $crc32_order_bn = sprintf('%u',crc32($order_bn));
        $rs = $this->getList('*',array('crc32_order_bn'=>$crc32_order_bn));
        $data = array();
        if($rs){
            foreach($rs as $k=>$v){
                if($v['order_bn'] == $order_bn){
                    $data[] = $v;
                }
            }
            return $data;
        }else{
            return false;
        }
    }

    /**
     * 导入前初始化
     * 
     * @return void
     * @author 
     * */
    public function prepared_import_csv()
    {

    }

    /**
     * 表面数据验证
     * 
     * @param Array $data 一行数据
     * @param Array $title 标题
     * @param mixed $tmpl 未知
     * @param String $mark 类型 such as title or contents
     * @param Bool $newObjFlag 是否数据库层验证
     * @param Array $msg 错误信息
     * @return void
     * @author 
     * */
    public function prepared_import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        static $rowLimit;

        if(empty($row)) return false;
        $rowLimit++;

        $initTime = app::get('finance')->getConf('finance_setting_init_time');
        if ($initTime['flag'] == false) {
            $msg['error'] = '请先设置帐期！';$newObjFlag = true;
            return false;
        }

        $row = array_map('trim', $row);

        /** 二层结构  **/
        # 标题
        $titles = kernel::single('finance_io_bill_rule')->type('ar')->getTitle();

        # 主表标题
        $master = $titles['1'];

        # 从表标题
        $slave = $titles['2'];

        # verify data belong to master or slave
        static $dataIdentity,$pCols;
        if ($row[0][0] == '*' && array_values($master) == array_intersect($row, $master) ) {
            $dataIdentity = 'master';
            $mark = 'title';
            
            $row = array_filter($row);
            asort($row); $row = array_flip($row); asort($master); $master = array_flip($master);
            
            $pCols = array_combine($row, $master);

            return $row;
        } elseif($row[0][0] == '*' && array_values($slave) == array_intersect($row, $slave)){
            $dataIdentity = 'slave';
            $mark = 'title';

            $row = array_filter($row);
            asort($row); $row = array_flip($row); asort($slave); $slave = array_flip($slave); 
            
            $pCols = array_combine($row, $slave);

            return $row;
        }

        if ($dataIdentity != 'master' && $dataIdentity != 'slave') {
            $msg['error'] = '导入模板错误！'; $newObjFlag = true;
            return false;
        }

        # $key:position in file $value: value
        foreach ($row as $key => $value) {
            if (isset($pCols[$key])) {
                $sdf[$pCols[$key]] = $value;
            }
        }

        $mark = 'contents';

        if ($dataIdentity == 'master') {
            /** 验证数据 **/

            # 限制导入条数
            if ($rowLimit > 5000) {
                $msg['error'] = '导入数据不能超过5000条！'; $newObjFlag = true;
                return false;
            }
            
            if (empty($sdf['serial_number'])) {
                $msg['warning'][] = 'LINE '.$rowLimit.'：业务流水号不能为空！';
                return false;
            }

            if (empty($sdf['member'])) {
                $msg['warning'][] = 'LINE '.$rowLimit.'：交易对方不能为空！';
                return false;
            }

            $rs = finance_io_bill_verify::checkOrder($sdf['order_bn']);
            if ($rs['status'] == 'fail') {
                $msg['warning'][] = 'LINE '.$rowLimit.'：订单号不存在！';
                return false;
            } 
            $sdf['channel_id'] = $rs['order']['shop_id'];
            $sdf['channel_name'] = $rs['order']['shop_name'];

            $rs = finance_io_bill_verify::isPrice($sdf['sale_money']);
            if ($rs['status'] == 'fail') {
                $msg['warning'][] = 'LINE '.$rowLimit.'：商品成交金额格式错误！';
                return false;
            }
            $sdf['sale_money'] = $sdf['sale_money'] ? strval(round($sdf['sale_money'],2)) : 0;

            $rs = finance_io_bill_verify::isPrice($sdf['fee_money']);
            if ($rs['status'] == 'fail') {
                $msg['warning'][] = 'LINE '.$rowLimit.'：运费收入格式错误！';
                return false;
            }
            $sdf['fee_money'] = $sdf['fee_money'] ? strval(round($sdf['fee_money'],2)) : 0;

            $rs = finance_io_bill_verify::isPrice($sdf['money']);
            if ($rs['status'] == 'fail') {
                $msg['warning'][] = 'LINE '.$rowLimit.'：应收金额格式错误！';
                return false;
            }
            $sdf['money'] = $sdf['money'] ? strval(round($sdf['money'],2)) : 0;

            $rs = finance_io_bill_verify::isDate($sdf['trade_time']);
            if ($rs['status'] == 'fail') {
                $msg['warning'][] = 'LINE '.$rowLimit.'：账单日期格式错误！';
                return false;
            }

            if ( !in_array( $sdf['type'], array('销售出库','销售退货','销售换货','销售退款') ) ) {
                $msg['warning'][] = 'LINE '.$rowLimit.'：业务类型错误！';
                return false;
            }
            $sdf['type'] = kernel::single('finance_ar')->get_type_by_name($sdf['type']);

            $rs = $this->getList('ar_id',array('serial_number'=>$sdf['serial_number']),0,1);
            if ($rs) {
                $msg['warning'][] = 'LINE '.$rowLimit.'：业务流水号已经存在！';
                return false;
            }
            
            $rs = finance_io_bill_verify::checkInitTime($sdf['trade_time'],$this->import_filter['checkTime']);

            if ($rs['status'] == 'fail') {
                $msg['warning'][] = 'LINE '.$rowLimit.'：'.$rs['msg'];
                
                unset($sdf[$k]); return false;
            }

            if ($sdf['relate_order_bn']) {
                $rs = finance_io_bill_verify::isOrder($sdf['relate_order_bn'],'关联订单号不存在');
                if($rs['status'] == 'fail'){
                    $msg['warning'][] = 'LINE '.$rowLimit.'：关联订单号不存在！';
                    return false;
                }
            }

            $rs = $this->getList('ar_id',array('unique_id'=>$sdf['unique_id']));
            if(!empty($rs)){
                $msg['warning'][] = 'LINE '.$rowLimit.'：该单据已存在！';
                return false;
            }

            $sdf['ar_bn']             = kernel::single('finance_ar')->gen_ar_bn();
            $sdf['trade_time']        = strtotime($sdf['trade_time']);
            $sdf['create_time']       = time();
            $sdf['crc32_order_bn']    = sprintf('%u',crc32($sdf['order_bn']));
            $sdf['relate_order_bn']   = $sdf['relate_order_bn'] ? sprintf('%u',crc32($sdf['relate_order_bn'])) : '';
            $sdf['unconfirm_money']   = $sdf['money'];
            
            $addon                    = array('sale_money'=>$sdf['sale_money'],'fee_money'=>$sdf['fee_money']);
            $sdf['addon']             = serialize($addon);
            $sdf['memo']              = serialize($sdf['memo']);
            $sdf['unique_id']         = finance_func::unique_id(array($sdf['serial_number']));

            $this->import_data[$sdf['serial_number']] = $sdf;

            
        } else if ($dataIdentity == 'slave') {
            /** 验证数据 **/

            static $products;
            if (!isset($products[$sdf['bn']])) {
                $p = app::get('material')->model('basic_material')->getList('material_bn as bn,bm_id as product_id',array('material_bn'=>$sdf['bn']),0,1);
                if (empty($p)) {
                    unset($this->import_data[$sdf['serial_number']]);
                    $msg['warning'][] = 'LINE '.$rowLimit.'：商品货号不存在！';
                    return false;        
                }

                $products[$p[0]['bn']] = $p[0];
            }

            if (is_int($sdf['nums'])) {
                unset($this->import_data[$sdf['serial_number']]);
                $msg['warning'][] = 'LINE '.$rowLimit.'：数量格式错误！';
                return false;
            }

            $rs = finance_io_bill_verify::isPrice($sdf['money']);
            if ($rs['status'] == 'fail') {
                unset($this->import_data[$sdf['serial_number']]);
                $msg['warning'][] = 'LINE '.$rowLimit.'：金额格式错误！';
                return false;
            }

            if (!isset($this->import_data[$sdf['serial_number']])) {
                $msg['warning'][] = 'LINE '.$rowLimit.'：主信息找不到！';
                return false;
            }

            $this->import_data[$sdf['serial_number']]['items'][] = $sdf;
        }   

        
        return true;
    }

    /**
     * 数据库层验证
     * 
     * @return void
     * @author 
     * */
    public function prepared_import_csv_obj($data,$mark,$tmpl,&$msg)
    {
        return NULL;
    }

    /**
     * 导入结束操作
     * 
     * @return void
     * @author 
     * */
    public function finish_import_csv()
    {
        foreach ($this->import_data as $key => $value) {

            $this->db->beginTransaction();
            $result = $this->save($value);
            if ($result) {
                $this->db->commit();
            } else {
                $this->db->rollBack();
            }
        } 
        return;
        $oQueue = app::get('base')->model('queue');

        $queueData = array(
            'queue_title'=>'单据导入',
            'start_time'=>time(),
            'params'=>array(
                'sdfdata'=>$this->import_data,
                'app' => 'finance',
                'mdl' => 'ar',
            ),
            'worker'=>'finance_mdl_ar.import_run',
        );
        $oQueue->save($queueData);

        $oQueue->flush();
    }

    /**
     * 导入数据入库
     * 
     * @return void
     * @author 
     * */
    public function import_run(&$cursor_id,$params)
    {
        # $model = app::get($params['app'])->model($params['mdl']);

        foreach ($params['sdfdata'] as $key => $value) {
            if(!$value['items']) continue;

            $this->db->beginTransaction();

            $result = $this->save($value);
            if ($result) {
                $this->db->commit();
            } else {
                $this->db->rollBack();
            }
        }

        return false;
    }

    private function gen_id() {
      $i = rand(0,99999);
      $receObj = &app::get('finance')->model('ar');
      do{
           if(99999==$i){
                $i=0;
           }
           $i++;
           $ar_bn="AR".date('Ymd').str_pad($i,5,'0',STR_PAD_LEFT);
           $row = $receObj->getlist('ar_id',array('ar_bn'=>$ar_bn));
      }while($row);
      return $ar_bn;
    }

         /**
     * exportName
     * @param mixed $filename filename
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function exportName(&$filename,$filter='')
    {
        if(isset($_POST['ctler']) && isset($_POST['add'])){
            return $filename = "销售应收帐单";
        }else{
            return $filename['name'] = "销售应收帐单";
        }
    } 
    function io_title( $filter=null,$ioType='csv' ){
        #新增导出字段
        if($this->export_flag){
            $new_titles['main'] = array(
                'ar_bn' => '*:单据编号',
                'channel_name' => '*:渠道名称',
                'trade_time'=> '*:账单日期',
                'member'=> '*:客户/会员',
                'type'=> '*:业务类型',
                'order_bn'=> '*:订单号',
                'relate_order_bn'=> '*:关联订单号',
                'sale_money'=> '*:商品成交金额',
                'fee_money'=> '*:运费收入',
                'money'=> '*:应收金额',
                'status'=> '*:核销状态',
                'confirm_money'=> '*:已核销金额',
                'unconfirm_money'=> '*:未核销金额',
                'charge_status'=> '*:记账状态',
                'charge_time'=> '*:记账日期',
                'monthly_status'=> '*:月结状态',
                'serial_number'=> '*:业务流水号'
            );
            $new_titles['items'] = array(
                'serial_number' => '*:业务流水号',
                'bn' => '*:销售物料编码',
                'name' => '*:销售物料名称',
                'nums' => '*:数量',
                'money' => '*:金额'
             );
            return $new_titles;
        }
    }    
    /**
     * fgetlist_csv
     * @param mixed $data 数据
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $export_type export_type
     * @return mixed 返回值
     */
    public function fgetlist_csv(&$data,$filter,$offset,$export_type){
         $this->export_flag = true;
         $titles = $this->io_title();
         $obj_ar = kernel::single('finance_ar');
       
        set_time_limit(0); // 30分钟
        $max_offset = 1000; // 最多一次导出10w条记录
        if ($offset>$max_offset) return false;// 限制导出的最大页码数

        if( !$data['title']['main'] ){
            $title = array();
            foreach(  $titles['main'] as $k => $v ){
                $title[] = $this->charset->utf2local($v);
            }
            $data['title']['main'] = '"'.implode('","',$title).'"';
        }
        if( !$data['title']['items'] ){
            $title = array();
            foreach( $titles['items'] as $k => $v )
                $title[] = $this->charset->utf2local($v);
            $data['title']['items'] = '"'.implode('","',$title).'"';
        }
        $limit = 100;
        if( !$list=$this->getList('ar_id',$filter,$offset*$limit,$limit) )return false;
        $rowDate = array();
        foreach($list as $v){
            $data_ar = $this->dump($v['ar_id']);
            $addon = unserialize($data_ar['addon']);
            $type = $obj_ar->get_name_by_type($data_ar['type']);
            $status = $obj_ar->get_name_by_status($data_ar['status']);
            $monthly_status = $obj_ar->get_name_by_monthly_status($data_ar['monthly_status']);
            $charge_status = $obj_ar->get_name_by_charge_status($data_ar['charge_status']);
            

            
            $rowDate['*:单据编号'] = $data_ar['ar_bn'];
            $rowDate['*:渠道名称'] = $data_ar['channel_name'];
            $rowDate['*:账单日期'] = date('Y-m-d',$data_ar['trade_time']);
            $rowDate["*:客户/会员"] = $data_ar['member'];
            $rowDate['*:业务类型'] = $type;
            $rowDate['*:订单号'] = "\t".$data_ar['order_bn'];
            $rowDate['*:关联订单号'] = "\t".$data_ar['relate_order_bn'];
            $rowDate['*:商品成交金额'] = number_format($addon['sale_money'],2);
            $rowDate['*:运费收入'] = number_format($addon['fee_money'],2);
            $rowDate['*:应收金额'] = $data_ar['money'];
            $rowDate['*:核销状态'] = $status;
            $rowDate['*:已核销金额'] = $data_ar['confirm_money'];
            $rowDate['*:未核销金额'] = $data_ar['unconfirm_money'];
            $rowDate['*:记账状态']=  $charge_status;
            $rowDate['*:记账日期'] = date('Y-m-d',$data_ar['charge_time']);
            $rowDate['*:月结状态'] = $monthly_status;
            $rowDate['*:业务流水号'] = "\t".$data_ar['serial_number'];
            
            $_items = $this->app->model('ar_items')->getList('*',array('ar_id'=>$v['ar_id']));
            foreach($_items as $_k=>$_v){
                $items = array();
                $items['*:业务流水号'] = "\t".$data_ar['serial_number'];
                $items['*:销售物料编码'] = "\t".$_v['bn'];
                $items['*:销售物料名称'] = $_v['name'];
                $items['*:数量'] = $_v['num'];
                $items['*:金额'] = $_v['money'];
                $data['content']['items'][] = $this->charset->utf2local('"'.implode( '","', $items ).'"');
            }
            $data['content']['main'][] = $this->charset->utf2local('"'.implode( '","', $rowDate ).'"');
        }
        return true;
    } 
    function export_csv($data,$exportType = 1 ){
        $output = array();
        foreach( $data['title'] as $k => $val ){
            $output[] = $val."\n".implode("\n",(array)$data['content'][$k]);
        }
        echo implode("\n",$output);
    }    

    /**
     * fgetlist
     * @param mixed $data 数据
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $export_type export_type
     * @return mixed 返回值
     */
    public function fgetlist(&$data,$filter,$offset,$export_type) 
    {
        if ($offset == 0 ) {
            #$title = $this->io_title('main');
            #$data['main'][] = $title;
            $titles = kernel::single('finance_io_bill_rule')->type('ar')->getTitle();
            foreach ((array)$titles as $key => $title) {
                $data[$key][] = array_values($title);
            }

            return true;
        }
        if ($filter['template'] == 1) {
            return false;
        }

        return false;
    }
    /**
     * 获得日志类型(non-PHPdoc)
     * @see dbeav_model::getLogType()
     */
    public function getLogType($logParams) {
        $type = $logParams['type'];
        $logType = 'none';
        if ($type == 'export') {
            $logType = $this->exportLogType($logParams);
        }
        elseif ($type == 'import') {
            $logType = $this->importLogType($logParams);
        }
        return $logType;
    }
    /**
     * 导出日志类型
     * @param Array $logParams 日志参数
     */
    public function exportLogType($logParams) {
        $params = $logParams['params'];
        $type = 'finance';
        if ($logParams['app'] == 'omecsv' && $logParams['ctl'] == 'admin_export') {
            if ($params['add'] == 'finance' && $params['filter']['template'] == 1) {
                $type .= '_saleReceivable_template';
            }
        }
        $type .= '_export';
        return $type;
    }
    /**
     * 导入操作日志类型
     * @param Array $logParams 日志参数
     */
    public function importLogType($logParams) {
        $params = $logParams['params'];
        $type = 'finance';
        if ($params['add'] == 'finance' && $params['filter']['checkTime'] == 'after') {
            //销售应收单
            $type .= '_saleReceivable';
        }
        $type .= '_import';
        return $type;
    }
    
    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        if (isset($filter['monthly_date']) && $filter['monthly_date']) {
            $monthlyIds = [0];
            $miList     = app::get('finance')->model('monthly_report')->getList('monthly_id, monthly_date', ['monthly_date' => $filter['monthly_date']]);
            if ($miList) {
                $monthlyIds = array_column($miList, 'monthly_id');
            }
            $filter['monthly_id'] = $monthlyIds;
            unset($filter['monthly_date']);
        }
    
        return parent::_filter($filter, $tableAlias, $baseWhere);
    }
}