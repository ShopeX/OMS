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


class invoice_mdl_order extends dbeav_model
{

    //发票类型
    const MODE = [
        0 => '纸质发票',
        1 => '电子发票',
    ];

    //是否有导出配置
    var $has_export_cnf = true;
    public $defaultOrder = array('id', 'DESC');
    
    /**
     * 须加密字段
     *
     * @var string
     **/
    private $__encrypt_cols = array(
        'ship_tax'     => 'simple',
        'ship_bank'    => 'simple',
        'ship_bank_no' => 'simple',
        'ship_tel'     => 'phone',
        'ship_addr'    => 'simple',
    );

    private $invoiceTemplateColumn = array(
        '订单号'   => 'order_bn',
        '客户名称' => 'tax_company',
        '客户电话' => 'ship_tel',
        '客户税号' => 'ship_tax',
        '收票地区' => 'ship_area',
        '收票地址' => 'ship_addr',
        '注册电话' => 'ship_company_tel',
        '注册地址' => 'ship_company_addr',
        '开户银行' => 'ship_bank',
        '银行账号' => 'ship_bank_no',
    );

    private $waybillTemplateColumn = array(
        '发票代码'     => 'invoice_code',
        '发票号码'       => 'invoice_no',
        '物流公司'  => 'logi_name',
        '运单号'       => 'logi_no',
    );
    
    /**
     * 是否需要真实 count。
     * 高级筛选/快捷搜索会把条件写入 finder.filter.value，并在 refresh 时拼进 POST；
     * 普通 Tab 浏览、翻页通常只有 _finder、items 等系统字段。
     *
     * @param mixed $filter
     * @return bool
     */
    private function _needRealFinderCount($filter)
    {
        if (is_array($filter) && !empty($filter['advance'])) {
            return true;
        }
        if (isset($_REQUEST['filter']) && is_array($_REQUEST['filter']) && !empty($_REQUEST['filter']['advance'])) {
            return true;
        }

        $skip = array(
            '_finder' => 1,
            'items' => 1,
            'page' => 1,
            'plimit' => 1,
            'finder_plimit' => 1,
            'action' => 1,
            '_token' => 1,
        );

        foreach ((array) $_POST as $key => $value) {
            if (isset($skip[$key]) || strpos($key, '_finder') === 0) {
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            if (is_array($value)) {
                $hasValue = false;
                foreach ($value as $v) {
                    if ($v !== '' && $v !== null) {
                        $hasValue = true;
                        break;
                    }
                }
                if (!$hasValue) {
                    continue;
                }
            }
            return true;
        }

        return false;
    }

    /*------------------------------------------------------ */
    //-- 用户信息
    /*------------------------------------------------------ */
    function getUserName($uid)
    {
    	$uid           = intval($uid);
    	$userData      = array();
    	$filter        = array('user_id'=>$uid);
    	$rows          = app::get('desktop')->model('users')->getList('user_id, name', $filter);
    	
    	return $rows[0];
    }
    
    /**
     * 导出数据格式
     * @param unknown_type $filter
     * @param unknown_type $ioType
     */
    function io_title($filter = null, $ioType = 'csv' )
    {
        switch( $ioType ) {
            case 'csv':
            default:
                $this->oSchema['csv'][$filter] = array(
                    '*:订单号' => 'order_bn',
                    '*:订单创建时间' => 'order_create_time',
                    '*:订单确认状态' => 'process_status',
                    '*:来源店铺' => 'shop_name',
                    '*:发票类型' => 'type_id',
                    '*:开票金额' => 'amount',
                    '*:税金' => 'cost_tax',
                    '*:税率' => 'tax_rate',
                    '*:发票抬头' => 'title',
                    '*:开票状态' => 'is_status',
                    '*:发票号' => 'invoice_no',
                    '*:发票内容' => 'content',
                    '*:批次号' => 'batch_number',
                    '*:发票备注' => 'remarks',
                    '*:打印次数' => 'print_num',
                    '*:创建日期' => 'create_time',
                    '*:开票时间' => 'dateline',
                    '*:客户名称' => 'tax_company',
                    '*:收货地区' => 'ship_area',
                    '*:客户地址' => 'ship_addr',
                    '*:客户电话' => 'ship_tel',
                    '*:客户税号' => 'ship_tax',
                    '*:客户开户银行' => 'ship_bank',
                    '*:客户银行账号' => 'ship_bank_no',
                );
        }
        $this->ioTitle[$ioType][$filter]        = array_keys( $this->oSchema[$ioType][$filter] );
        return $this->ioTitle[$ioType][$filter];
    }
    /**
     * 导出数据(方法名固定，由系统调用)
     * @param Array $data 导出的数据
     * @param Array $filter 过滤器
     * @param Int $offset 当前记录位置
     * @param Int $exportType 导出类型
     */
    function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 )
    {
        set_time_limit(0);
        @ini_set('memory_limit','128M');
        if ($offset > $max_offset) {
            return false;
        }
        
        if( !$data['title'] ){
            $title = array();
            foreach( $this->io_title('order') as $k => $v ){
                $title[]    = $v;
            }
            $data['title']  = '"' . implode('","', $title) . '"';
        }

        $limit     = 100;
        if( !$list = $this->getlist('*', $filter, $offset * $limit, $limit) )return false;
        
        //字段属性
        $order_Obj      = app::get('invoice')->model('order');
        $columns        = $order_Obj->schema;
        $type_id        = $columns['columns']['type_id']['type'];
        $is_status      = $columns['columns']['is_status']['type'];
        
        $Oorders    = app::get('ome')->model('orders');
        $col_list   = $Oorders->schema;
        $process_status = $col_list['columns']['process_status']['type'];
        
        //批次号(第四段)
        $oDelivery      = app::get('ome')->model('print_queue_items');
        
        //
        $db        = kernel::database();
        foreach($list as $key => $row)
        {
            #来源店铺
            $sql_shop  = "SELECT a.createtime, b.name FROM ".DB_PREFIX."ome_orders as a
                   LEFT JOIN ".DB_PREFIX."ome_shop as b ON a.shop_id=b.shop_id
                   WHERE a.order_id='".$row['order_id']."'";
            $order_info = $db->selectrow($sql_shop);
            if (!$order_info) {
                $sql_shop = "SELECT a.createtime, b.name FROM ".DB_PREFIX."archive_orders as a
                   LEFT JOIN ".DB_PREFIX."ome_shop as b ON a.shop_id=b.shop_id
                   WHERE a.order_id='".$row['order_id']."'";
                $order_info = $db->selectrow($sql_shop);
            }
            $row['shop_name'] = $order_info['name'];
            $row['order_create_time'] = $order_info['createtime'];
            
            #批次号(第四段)
            $delivery_arr   = array();
            if(!empty($row['batch_number']) && !empty($row['delivery_id']))
            {
                $delivery_arr   = $oDelivery->getList('ident_dly', array('delivery_id' => $row['delivery_id'],
                                    'ident' => $row['batch_number']), 0, 1);
                $row['batch_number']    .= '_'.$delivery_arr[0]['ident_dly'];
            }
            
            unset($row['id'], $row['order_id'], $row['is_print'], $row['delivery_id'], $row['operator'], $row['print_time']);
            
            $rowVal = array();
            $rowVal['*:订单号']        = "\t".$row['order_bn'];
            $rowVal['*:订单创建时间']   = ($row['order_create_time'] ? date('Y-m-d H:i', $row['order_create_time']) : '');
            $rowVal['*:订单确认状态']   = $process_status[$row['process_status']];
            $rowVal['*:来源店铺']       = $row['shop_name'];
            $rowVal['*:发票类型']       = $type_id[$row['type_id']];
            $rowVal['*:开票金额']       = $row['amount'];
            
            $rowVal['*:税金']     = $row['cost_tax'];
            $rowVal['*:税率']     = $row['tax_rate'];
            $rowVal['*:发票抬头'] = $row['title'];
            $rowVal['*:开票状态'] = $is_status[$row['is_status']];
            $rowVal['*:发票号']   = $row['invoice_no'];
            
            $rowVal['*:发票内容']   = $row['content'];
            $rowVal['*:批次号']     = $row['batch_number'];
            $rowVal['*:发票备注']   = $row['remarks'];
            $rowVal['*:打印次数']   = $row['print_num'];
            $rowVal['*:创建日期']   = ($row['create_time'] ? date('Y-m-d H:i', $row['create_time']) : '');
            
            $rowVal['*:开票时间']   = ($row['dateline'] ? date('Y-m-d H:i', $row['dateline']) : '');
            $rowVal['*:客户名称']   = $row['tax_company'];
            $rowVal['*:收货地区']   = $row['ship_area'];
            $rowVal['*:客户地址']   = $row['ship_addr'];
            $rowVal['*:客户电话']   = $row['ship_tel'];
            
            $rowVal['*:客户税号']       = $row['ship_tax'];
            $rowVal['*:客户开户银行']   = $row['ship_bank'];
            $rowVal['*:客户银行账号']   = $row['ship_bank_no'];
            
            $data['content'][] = '"' . implode( '","', $rowVal ) . '"';
        }
        
        return true;
    }
    /*------------------------------------------------------ */
    //-- 更新批次号
    /*------------------------------------------------------ */
    function update_batch_number($allItems, $idents){
       if(empty($allItems) || empty($idents['idents'])){
          return false;
       }
       
       $inOrder       = app::get('invoice')->model('order');
       $batch_number  = join(',', $idents['idents']);//批次号前三段,例：1-40409-0176
      
       $new_data      = array();
       foreach ($allItems as $key => $volist){
         foreach ($volist['delivery_order'] as $key_j => $val){
            //更新批次号+发货单号
            $new_data   = array('batch_number' => $batch_number, 'delivery_id'=>$val['delivery_id']);
            $inOrder->update($new_data, array('order_id'=>$val['order_id']));
         }
      }
      return true;
    }
    /**
     * 输出导出数据
     * @param Array $data 数据
     * @param Int $exportType 输出类型
     */
    function export_csv($data, $exportType = 1 )
    {
        if(!$this->is_queue_export)
        {
            foreach ($data['content'] as $key => $value)
            {
                $data['content'][$key] = $value;
            }
        }

        $output     = array();
        $output[]   = $this->charset->utf2local($data['title']."\n".implode("\n",(array)$data['content']));

        if ($this->is_queue_export == true)
        {
            return implode("\n",$output);
        } else {
            echo implode("\n",$output);
        }
    }
    //获取操作人名
    function modifier_operator($row){
       $mdlDesktopUsers = app::get('desktop')->model('users');
       $operator_name = "system";
       if( intval($row)>0 ){
        $rs_user = $mdlDesktopUsers->dump(array("user_id"=>$row));
        $operator_name = $rs_user["name"];
       }
       return $operator_name;
    }

    public function disabled_export_cols(&$cols){
        unset($cols['column_edit']);
    }

    public function modifier_ship_tel($ship_tel,$list,$row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_tel);
        if (!$is_encrypt) return $ship_tel;
        
        $base_url = kernel::base_url(1);$id = $row['id'];
        $encrypt = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_tel,'invoice','ship_tel');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=invoice&ctl=admin_order&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_tel">{$encrypt}</span></span>
HTML;
        return $ship_tel?$return:$ship_tel;
    }

    public function modifier_ship_addr($ship_addr,$list,$row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_addr);
        if (!$is_encrypt) return $ship_addr;
        
        $id = $row['id'];
        $encryptAddr = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_addr,'invoice','ship_addr');

$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=invoice&ctl=admin_order&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_addr">{$encryptAddr}</span></span>
HTML;
        return $ship_addr?$return:$ship_addr;
    }

    public function modifier_ship_bank_no($ship_bank_no,$list,$row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_bank_no);
        if (!$is_encrypt) return $ship_bank_no;
        
        $base_url = kernel::base_url(1);$id = $row['id'];
        $encryptValue = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_bank_no,'invoice','ship_bank_no');
$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=invoice&ctl=admin_order&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_bank_no">{$encryptValue}</span></span>
HTML;
        return $ship_bank_no?$return:$ship_bank_no;
    }
    
    public function modifier_ship_tax($ship_tax,$list,$row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_tax);
        if (!$is_encrypt) return $ship_tax;
        
        $base_url = kernel::base_url(1);$id = $row['id'];
        $encryptValue = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_tax,'invoice','ship_tax');

$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=invoice&ctl=admin_order&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_tax">{$encryptValue}</span></span>
HTML;
        return $ship_tax?$return:$ship_tax;
    }
    
    public function modifier_ship_bank($ship_bank,$list,$row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($ship_bank);
        if (!$is_encrypt) return $ship_bank;
        
        $base_url = kernel::base_url(1);$id = $row['id'];
        $encryptValue = kernel::single('ome_view_helper2')->modifier_ciphertext($ship_bank,'invoice','ship_bank');

$return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=invoice&ctl=admin_order&act=showSensitiveData&p[0]={$id}&p[1]=invoice',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="ship_bank">{$encryptValue}</span></span>
HTML;
        return $ship_bank?$return:$ship_bank;
    }

    public function insert(&$data)
    {
        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                $data[$field] = (string) kernel::single('ome_security_factory')->encryptPublic($data[$field],$type);
            }
        }
        return parent::insert($data);
    }
    
    public function update($data,$filter=array(),$mustUpdate = null)
    {
        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                $data[$field] = (string) kernel::single('ome_security_factory')->encryptPublic($data[$field],$type);
            }
        }
        
        return parent::update($data,$filter,$mustUpdate);
    }
    
    function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $where = " 1 ";
        if (isset($filter['order_bn'])) {
            if($filter['order_bn'] && is_string($filter['order_bn']) && strpos($filter['order_bn'], "\n") !== false){
                $filter['order_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['order_bn']))));
            }
            $oItemList = $this->app->model('order_items')->getList('id',['source_bn'=>$filter['order_bn']]);
            if(!empty($oItemList)){
                $where .= ' AND id IN ('.implode(',', array_column($oItemList,'id')).')';
            }else{
                $where .= ' AND order_bn = "order_bn"';
            }
            unset($filter['order_bn']);
        }

        return $where ." AND ".parent::_filter($filter,$tableAlias,$baseWhere);
    }

    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        
        $data = parent::getList($cols,$filter,$offset,$limit,$orderType);
        
        foreach ((array) $data as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field],$type);
                }
            }
        }
        
        return $data;
    }

    public function getInvoiceTemplateColumn()
    {
        return $this->invoiceTemplateColumn;
    }

    public function getWaybillTemplateColumn()
    {
        return $this->waybillTemplateColumn;
    }

    // public function get_Schema()
    // {

    //     $shopName = array_column(app::get('ome')->model('shop')->getList('name,shop_id'),'name','shop_id');

    //     $data = parent::get_Schema();
    //     $data['columns']['shop_id']['type'] = $shopName;
    //     $data['columns']['shop_id']['filtertype'] = 'fuzzy_search_multiple';
    //     $data['columns']['shop_id']['filterdefault'] = true;
    //     return $data;
        
    // }
    
    public function modifier_payee_name($col,$list,$row){
        $mode      = intval($row[$this->col_prefix.'mode']);
        $shop_id   = $row[$this->col_prefix.'shop_id'];
        $rs = $this->__getOrderSetting($shop_id,$mode);
        return $col ?: $rs['payee_name'];
    }
    
    public function modifier_tax_no($col, $list, $row)
    {
        $mode    = intval($row[$this->col_prefix . 'mode']);
        $shop_id = $row[$this->col_prefix . 'shop_id'];
        $rs      = $this->__getOrderSetting($shop_id, $mode);
        return $col ?: $rs['tax_no'];
    }
    
    public function modifier_address($col, $list, $row)
    {
        $mode    = intval($row[$this->col_prefix . 'mode']);
        $shop_id = $row[$this->col_prefix . 'shop_id'];
        $rs      = $this->__getOrderSetting($shop_id, $mode);
        return $col ?: $rs['address'];
    }
    
    public function modifier_telephone($col, $list, $row)
    {
        $mode    = intval($row[$this->col_prefix . 'mode']);
        $shop_id = $row[$this->col_prefix . 'shop_id'];
        $rs      = $this->__getOrderSetting($shop_id, $mode);
        return $col ?: $rs['telephone'];
    }
    
    public function modifier_payee_operator($col, $list, $row)
    {
        $mode    = intval($row[$this->col_prefix . 'mode']);
        $shop_id = $row[$this->col_prefix . 'shop_id'];
        $rs      = $this->__getOrderSetting($shop_id, $mode);
        return $col ?: $rs['payee_operator'];
    }
    
    public function modifier_bank($col, $list, $row)
    {
        $mode    = intval($row[$this->col_prefix . 'mode']);
        $shop_id = $row[$this->col_prefix . 'shop_id'];
        $rs      = $this->__getOrderSetting($shop_id, $mode);
        return $col ?: $rs['bank'];
    }
    
    public function modifier_bank_no($col, $list, $row)
    {
        $mode    = intval($row[$this->col_prefix . 'mode']);
        $shop_id = $row[$this->col_prefix . 'shop_id'];
        $rs      = $this->__getOrderSetting($shop_id, $mode);
        return $col ?: $rs['bank_no'];
    }
    
    public function modifier_payee_checker($col, $list, $row)
    {
        $mode    = intval($row[$this->col_prefix . 'mode']);
        $shop_id = $row[$this->col_prefix . 'shop_id'];
        $rs      = $this->__getOrderSetting($shop_id, $mode);
        return $col ?: $rs['payee_checker'];
    }
    
    public function modifier_payee_receiver($col, $list, $row)
    {
        $mode    = intval($row[$this->col_prefix . 'mode']);
        $shop_id = $row[$this->col_prefix . 'shop_id'];
        $rs      = $this->__getOrderSetting($shop_id, $mode);
        return $col ?: $rs['payee_receiver'];
    }
    
    /**
     * 查询发票配置信息
     * @Author: xueding
     * @Vsersion: 2023/6/15 下午4:09
     * @param $shop_id
     * @param $mode
     * @return mixed
     */
    public function __getOrderSetting($shop_id, $mode)
    {
        static $res;
        
        if ($res) {
            return $res;
        }
        
        $rs = kernel::single('invoice_func')->get_order_setting($shop_id, $mode);
        if ($rs) {
            $res = $rs[0];
        }
        return $rs[0];
    }
    
    /**
     * 开票方式
     *
     * @param $mode
     * @param $list
     * @param $row
     * @return void
     */
    public function modifier_mode($mode, $list, $row)
    {
        $mode_name = '普通发票';
        if($row['mode'] == '1'){
            $mode_name = '数电发票';
        }
        
        return $mode_name;
    }
    
    /**
     * 发票类型
     *
     * @param $mode
     * @param $list
     * @param $row
     * @return void
     */
    public function modifier_type_id($type_id, $list, $row)
    {
        // mode
        if($row['mode'] == '1'){
            $type_name = '数电增值税电子普通发票';
            if($type_id == '1'){
                $type_name = '数电增值税电子专用发票';
            }
        }else{
            $type_name = '普通发票';
            if($type_id == '1'){
                $type_name = '专用发票';
            }
        }
        
        return $type_name;
    }
}
