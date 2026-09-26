<?php
/**
*
*/
class inventorydepth_mdl_shop_adjustment extends dbeav_model
{
    public $filter_use_like = false;

    public $defaultOrder = 'shop_product_bn_crc32';
    
    var $has_export_cnf = true;
    var $export_name = '库存管理';

    function __construct($app)
    {
        parent::__construct($app);

        $this->app = $app;
    }

    public function table_name($real=false){
        $table_name = 'shop_skus';
        if($real){
            return kernel::database()->prefix.$this->app->app_id.'_'.$table_name;
        }else{
            return $table_name;
        }
    }
    
    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'shop_iid'=>app::get('base')->_('店铺商品ID'),
            'shop_sku_id'=>app::get('base')->_('平台SKU ID'),
        );
        return array_merge($childOptions,$parentOptions);
    }

    /**
     * 覆盖店铺库存
     *
     * @return void
     * @author
     **/
    public function convert_shop_stock($filter)
    {
        $sql = 'UPDATE '.$this->table_name(true).' SET shop_stock=release_stock WHERE '.$this->_filter($filter);
        $this->db->exec($sql);
    }

    /*
     * “导出”相关开始
     */
    public function disabled_export_cols(&$cols){
        unset($cols['column_operator']);
    }
    
    private function get_export_title_by_fields($fields,&$arr_fields){
        $arr_fields_titles = array(
                "column_request" => "*:回写库存",
                "column_source_shop" => "*:来源店铺",
                "shop_sku_id" => "*:平台SKU ID",
                "shop_iid" => "*:平台商品ID",
                "shop_product_bn" => "*:店铺货号",
                "column_bind" => "*:商品类型",
                "shop_title" => "*:店铺货品名称",
                "column_regulation" => "*:库存更新规则",
                "column_shop_stock" => "*:店铺库存",
                "column_actual_stock" => "*:店铺可售库存",
                "column_release_stock" => "*:发布库存",
                "shop_properties_name" => "*:店铺货品属性",
                "shop_price" => "*:销售价",
                "download_time" => "*:同步时间",
                "mapping" => "*:已对映上本地货品",
                "update_time" => "*:最后更新时间",
                "sales_material_type" => "*:销售物料类型",
                "shop_barcode" => "*:条形码",
                "column_bidding_type" => "*:出价类型",
                "sync_map" => "*:关系映射",
        );
        $title_arr = array();
        // 与数据行共用同一份字段列表，避免空字段导致表头/数据列错位
        $arr_fields = array_values(array_filter(array_map('trim', explode(',', $fields)), 'strlen'));
        $schema = $this->get_schema();
        foreach($arr_fields as $var_af){
            if (isset($arr_fields_titles[$var_af]) && $arr_fields_titles[$var_af] !== '') {
                $title_arr[] = $arr_fields_titles[$var_af];
            } elseif (!empty($schema['columns'][$var_af]['label'])) {
                // 未在硬编码映射中的列表字段，回退到 schema label，避免表头空白
                $title_arr[] = '*:' . $schema['columns'][$var_af]['label'];
            } else {
                $title_arr[] = '*:' . $var_af;
            }
        }
        return mb_convert_encoding(implode(',',$title_arr), 'GBK', 'UTF-8');
    }
    
    private function get_export_row_value_by_field($field,$row_arr,$arr_shop_stock,$arr_release_stock){
        $return_data = $row_arr[$field];
        switch($field){
            case "column_request":
                $return_data = "关闭";
                if($row_arr["request"] == 'true'){
                    $return_data = "开启";
                }
                break;
            case "column_source_shop":
                $return_data = !empty($row_arr['shop_name']) ? $row_arr['shop_name'] : '-';
                break;
            case "column_bind":
                if($row_arr["bind"] == "1"){
                    $return_data = "组合";
                }elseif($row_arr["bind"] == "2"){
                    $return_data = "多选一";
                }else{
                    $return_data = "普通";
                }
                break;
            case "shop_price":
                $return_data = "￥".number_format($return_data,2,".","");
                break;
            case "download_time":
            case "update_time":
                $return_data = date("Y-m-d H:i:s",$return_data);
                break;
            case "mapping":
                if($return_data == "1"){
                    $return_data = "SKU已匹配";
                }else{
                    $return_data = "SKU未匹配";
                }
                break;
            case "column_shop_stock":
                if(!empty($arr_shop_stock)){
                    foreach($arr_shop_stock as $var_ass){
                        if($row_arr["id"] == $var_ass["id"]){
                            $return_data = $var_ass["num"];break;
                        }
                    }
                }
                break;
            case "column_regulation":
                if(!empty($arr_release_stock)){
                    foreach($arr_release_stock as $var_ars){
                        if($row_arr["id"] == $var_ars["id"]){
                            $return_data = $var_ars["rule_heading"];break;
                        }
                    }
                }
                break;
            case "column_actual_stock":
                $return_data = "0";
                if(!empty($arr_release_stock)){
                    foreach($arr_release_stock as $var_ars){
                        if($row_arr["id"] == $var_ars["id"]){
                            $return_data = $var_ars["actual_stock"];break;
                        }
                    }
                }
                break;
            case "column_release_stock":
                $return_data = $row_arr["release_stock"];;
                if(!empty($arr_release_stock)){
                    foreach($arr_release_stock as $var_ars){
                        if($row_arr["id"] == $var_ars["id"]){
                            $return_data = $var_ars["quantity"];break;
                        }
                    }
                }
                break;
        }
        return kernel::single('ome_func')->csv_filter($return_data);
    }
    
    //参考控制器getShopStock方法 店铺库存获取
    private function get_export_shop_stock($iids,$shop_id){
        if(empty($iids) || !$shop_id){
            return array();
        }
        $shop = $this->app->model('shop')->dump(array('shop_id'=>$shop_id));
        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type'],$shop['business_type']);
        if ($shopfactory === false){
            return array();
        }
        $result = $shopfactory->downloadByIIds($iids,$shop_id,$errormsg);
        if (empty($result)) {
            return array();
        }
        $items = array();
        foreach ($result as $r){
            if($r['skus']){
                foreach ($r['skus']['sku'] as $sku){
                    $items[] = array(
                        'iid' => strval($r['iid']),
                        'sku_id' => $sku['sku_id'],
                        'num' => $sku['quantity'],
                        'id' => md5($shop_id.$r['iid'].$sku['sku_id']),
                    );
                }
            }else{
                $items[] = array(
                    'iid' => strval($r['iid']),
                    'num' => $r['num'],
                    'id' => md5($shop_id.$r['iid']),
                );
            }
        }
        return $items;
    }
    
    //参考控制器getReleaseStock方法 库存更新规则、店铺可售库存、发布库存获取
    private function get_export_release_stock($ids,$shop_id){
        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesMLib = kernel::single('material_sales_material');
        if(empty($ids) || !$shop_id) {
            return array();
        }
        $adjustmentModel = $this->app->model('shop_adjustment');
        $skus = $adjustmentModel->getList('shop_product_bn,bind,shop_id,shop_bn,id,mapping',array('id'=>$ids));
        
        $pbns = array_filter(array_column($skus, 'shop_product_bn'));
        if (empty($pbns)) {
            return array();
        }

        // [普通]销售物料
        $products = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn, sales_material_type,shop_id,class_id',array('sales_material_bn'=>$pbns));
        if (empty($products)) {
            return array();
        }

        $products = array_column($products, null, 'sales_material_bn');
        
        kernel::single('inventorydepth_calculation_basicmaterial')->init($products);
        kernel::single('inventorydepth_calculation_salesmaterial')->init($products);

        $data = array();
        foreach ($skus as $sku) {

            $stock = kernel::single('inventorydepth_logic_stock')->getStock($products[$sku['shop_product_bn']],$sku['shop_id'],$sku['shop_bn']);
            $quantity     = $stock['quantity'];
            $actual_stock = $stock['actual_stock'];
            $asRs         = $stock['regulation']['detail']['可售库存']['info'];
            
            if($actual_stock === false) continue;
            
            if ($quantity !== false) {
                $adjustmentModel->update(array('release_stock'=>$quantity),array('id'=>$sku['id']));
            }

            // 详情
            $actual_product_stock = array();
            foreach ($asRs['basic'] as $bn => $bcRs) {
                $actual_product_stock[] = array(
                    'bn'    =>  $bn,
                    'stock' =>  $bcRs['quantity'],
                );
            }

            if ($sku['mapping'] =='1') {
                $rule_heading= $stock['regulation']['规则名称'];
            }else{
                $rule_heading= '-';
            }
            $data[] = array(
                'id' => $sku['id'],
                'quantity' => $quantity,
                'actual_stock' => $sku['mapping']=='1' ? $actual_stock : '-',
                'actual_product_stock'=>($sku['bind']=='1' || $sku['bind']=='2') ? $actual_product_stock :'',
                'rule_heading' => $rule_heading,
            );
        }
        return $data;
    }
    
    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end){
        $title_row = $this->get_export_title_by_fields($fields,$arr_fields);
        //根据选择的字段定义导出的第一行标题
        if($curr_sheet == 1){
            $data['content']['main'][] = $title_row;
        }

        // 过滤掉NULL与空数据
        $filter = array_filter($filter, function($var){
            return !is_null($var) && $var !== '';
        });

        if(!$list = $this->getList('*',$filter,$start,$end)){
            return false;
        }
        //统一获取接口数据
        $ids = array();
        $iids = array();
        foreach($list as $var_list){
            $ids[] = $var_list["id"];
            $iids[] = $var_list["shop_iid"];
        }
        $arr_shop_stock = $this->get_export_shop_stock($iids,$filter["shop_id"]);
        $arr_release_stock = $this->get_export_release_stock($ids,$filter["shop_id"]);
        foreach($list as $line => $row ){
            $row_data_arr = array();
            foreach($arr_fields as $k => $v ){
                $current_value = $this->get_export_row_value_by_field($v,$row,$arr_shop_stock,$arr_release_stock);
                $row_data_arr[$k] = $current_value;
            }
            ksort($row_data_arr);
            $data['content']['main'][] = implode(',',$row_data_arr);
        }
        return $data;
    }
    /*
     * 导出结束
     */
    
    public function exportTemplate($filter){
        foreach ($this->io_title($filter) as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }


    /**
     * 导入前的单纯数据验证
     *
     * @return void
     * @author 
     **/
    public function  prepared_import_csv_row($row,$title,&$goodsTmpl,&$mark,&$newObjFlag,&$msg)
    {
        if(empty($row)) return false;

        $shop_bn = $row[$title['*:店铺编码']];
        $shop_product_bn = $row[$title['*:货品编号']];
        $release_stock = $row[$title['*:发布库存']];
        //$barcode = $row[$title['*:条形码']];

        if( substr($row[0],0,1) == '*' ){
            $titleRs =  array_flip($row);
            $mark = 'title';

            return $titleRs;
        }

        if(isset($this->nums)){
                $this->nums++;
                if($this->nums > 5000){
                    $msg['error'] = "导入的商品数据量过大，请减少到5000单以下！";
                    return false;
                }
        }else{
            $this->nums = 0;
        }

        $mark = 'contents';

        if (empty($shop_bn)) {
            $msg['warning'][] = '存在店铺编码为空的记录!';
            return false;
        }

        if (empty($shop_product_bn)) {
            $msg['warning'][] = '存在货品编码为空的记录!';
            return false;
        }
        
        if ((int)$release_stock<1) {
            $msg['warning'][] = '存在发布库存为零的记录!';
            return false;
        }
        return $row;
    }

    /**
     * 导入前的数据库数据验证
     *
     * @return void
     * @author 
     **/
    public function prepared_import_csv_obj($data,$mark,$goodsTmpl,&$msg = '')
    {
        $title = $data['title'];
        $contents = &$data['contents'];
        
        $shopModel = $this->app->model('shop');
        foreach($contents as $content){
            $shop_bn = $content[$title['*:店铺编码']];
            $shop_product_bn = $content[$title['*:货品编号']];
            $release_stock = $content[$title['*:发布库存']];

            
            $filter['shop_bn'] = $shop_bn;
            $filter['shop_product_bn'] = $shop_product_bn;

            $sku = $this->getList('id,shop_product_bn,shop_id,shop_type',$filter,0,1);

            if (!$sku) {
                $msg['error'] = "店铺【{$shop_bn}】不存在货品【{$shop_product_bn}】!";
                return false;
            }

            $shop = $shopModel->getList('shop_id,shop_bn,name,shop_type',array('shop_bn'=>$shop_bn),0,1);
            if (!$shop) {
                $msg['error'] = "店铺【编号是{$shop_bn}】不存在!";
                return false;
            }
            
            $sdf[] = array('release_stock'=>$release_stock,'id'=>$sku[0]['id'],'shop_id'=>$sku[0]['shop_id'],'shop_product_bn'=>$sku[0]['shop_product_bn'],'shop_type'=>$sku[0]['shop_type']);

            // 记录操作日志
            $optLogModel = app::get('inventorydepth')->model('operation_log');
            $optLogModel->write_log('sku',$sku[0]['id'],'stockup','导入发布库存：'.$release_stock);

        }
        base_kvstore::instance('inventorydepth_shop_goods')->store('shop-goods-'.$this->ioObj->cacheTime,serialize($sdf));

        return null;
    }

    public function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
    }

    public function finish_import_csv(){
        base_kvstore::instance('inventorydepth_shop_goods')->fetch('shop-goods-'.$this->ioObj->cacheTime,$data);
        base_kvstore::instance('inventorydepth_shop_goods')->store('shop-goods-'.$this->ioObj->cacheTime,'');
        
        $data = unserialize($data);

        $title = '导入店铺发布库存';
        kernel::single('inventorydepth_queue')->insert_shop_skus_queue($title,$data);
        return null;
    }

    /**
     * 
     *
     * @return void
     * @author 
     **/
    public function update_shop_stock($ids)
    {
        $sql = 'UPDATE '.$this->table_name(true).' SET shop_stock=release_stock WHERE id in('.implode(',', $ids).')';

        return $this->db->exec($sql);
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $where = array(1);
        if (isset($filter['shop_product_bn'])) {

            if (is_string($filter['shop_product_bn']) && strpos($filter['shop_product_bn'], "\n") !== false) {
                $shop_product_bn = array_unique(array_map('trim', array_filter(explode("\n", $filter['shop_product_bn']))));
                $filter['shop_product_bn|in'] = $shop_product_bn;
                unset($filter['shop_product_bn']);
            }

            if ($filter['shop_product_bn'] == 'repeat') {
                unset($filter['shop_product_bn']);

                $pbn = $this->get_repeat_product_bn($filter);
                if ($pbn) {
                    $filter['shop_product_bn'] = $pbn;
                } else {
                    # 没有重复的，则结果为空
                    $filter['shop_product_bn'][] = 'norepeat';
                }
            }

            if ($filter['shop_product_bn'] == 'exceptrepeat') {
                unset($filter['shop_product_bn']);

                $pbn = $this->get_repeat_product_bn($filter);
                if ($pbn) {
                    $filter['shop_product_bn|notin'] = $pbn;
                }
            }
        }

        if (isset($filter['shop_iid'])) {
            if (is_string($filter['shop_iid']) && strpos($filter['shop_iid'], "\n") !== false) {
                $shop_iid              = array_unique(array_map('trim', array_filter(explode("\n", $filter['shop_iid']))));
                if ($shop_iid) $filter['shop_iid|in'] = $shop_iid;
                unset($filter['shop_iid']);
            }
        }
    
        if (isset($filter['shop_sku_id'])) {
            if (is_string($filter['shop_sku_id']) && strpos($filter['shop_sku_id'], "\n") !== false) {
                $shop_sku_id              = array_unique(array_map('trim', array_filter(explode("\n", $filter['shop_sku_id']))));
                if ($shop_sku_id) $filter['shop_sku_id|in'] = $shop_sku_id;
                unset($filter['shop_sku_id']);
            }
        }

        return parent::_filter($filter,$tableAlias,$baseWhere).' AND '.implode(' AND ', $where);
    }
    
    /**
     * @description 获取重复货号
     */
    public function get_repeat_product_bn($filter) 
    {
        $sql = 'SELECT id,shop_product_bn,shop_id FROM '.$this->table_name(true).' WHERE shop_id="'.$filter['shop_id'].'" AND shop_product_bn!="" AND shop_product_bn is not null GROUP BY shop_product_bn,shop_id  Having count(1)>1 ';
        $list = $this->db->select($sql);
        $pbn = array();
        if ($list) {
            foreach ($list as $key=>$value) {
                $pbn[] = $value['shop_product_bn'];
            }
        }
        return $pbn;
    }

    /**
     * @description
     * @access public
     * @param void
     * @return void
     */
    public function modifier_mapping($row) 
    {
        if ($row == '1') {
            $row = '<div style="color:green;">SKU已匹配</div>';
        } else {
            $row = '<div style="color:red;">SKU未匹配</div>';
        }
        return $row;
    }

    public function getFinderList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null) 
    {

        $this->appendCols = 'shop_iid,shop_sku_id';

        $list = parent::getList($cols, $filter, $offset, $limit, $orderType);

        return $list;
    }

}
