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

class taoguaniostockorder_mdl_iso extends dbeav_model{
    //是否有导出配置
    var $has_export_cnf = true;
    var $export_name = '出入库单';
    var $has_many = array(
        'iso_items' => 'iso_items');
    var $key = 0;
    var $import_nums = 0;
    var $import_flag = true;

    //var $mark = array();
    var $defaultOrder = array('create_time DESC ,iso_id DESC');
    #导入或导出商品标题格式

     static $bill_type = array(
        'b2b' => 'B2B出入库单',
        'branchinventory' => '大仓盘点调整单',
        'storeinventory' => '门店盘点调整单',
        'o2otransfer' => '门店调拨单',
        'transfer' => '大仓调拨单',
        'replenishment' => '门店订货单',
        'returnnormal' => '门店退仓单',
        'try_drink' => '门店试饮入库单',
        'demo' => '门店陈列入库单',
        'workorder' => '加工单',
        'branchadjust' => '大仓库存调整',
        'storeadjust' => '门店库存调整',
        'branchadjust_init' => '大仓库存初始化',
        'storeadjust_init' => '门店库存初始化',
        'headless' => '无头件入库',
        'prtostore' => 'PR仓配门店',
        'prtoperson' => 'PR仓配个人',
        'asn' => 'ASN出入库单',
        'oms_reship_diff' => '差异退货入库',
        'oms_reshipdiffout' => '差异退货出库',
        'vopjitrk' => '唯品会JIT入库单',
        'o2oprepayed'=> '门店预订单',
        'jdlreturn' => '京东自营',
    );
    #出库模板
    private $temple_out = array(
        '*:单据号' => 'iso_no',//编号关联商品,支持一次导入多张出库单
        '*:出库单名称' => 'name',//出入库单名称
        '*:是否紧急出库' => 'emergency',
        '*:供应商' => 'supplier_name',
        '*:出货仓库' => 'branch_id',
        '*:出库类型' => 'type_id',
        '*:出库费用'=>'iso_price',
        '*:经办人' => 'oper',
        '*:备注'=>'memo',
        '*:外部仓库'=>'extrabranch',
        '*:业务类型'=>'bill_type',
        '*:业务单号'=>'business_bn',
        '*:货号' => 'bn',
        '*:货品名称'=>'product_name',
        '*:货品条形码'=>'barcode',
        '*:数量'=>'nums',
        '*:价格'=>'price',
        '*:收货地址省份'=>'area_state',
        '*:收货地址城市'=>'area_city',
        '*:收货地址区/县'=>'area_district',
        '*:收货人详细地址'=>'extra_ship_addr',
        '*:收货人姓名'=>'extra_ship_name',
        '*:收货人手机'=>'extra_ship_mobile',
    );

    #入库模板
    private $temple_in = array(
        '*:单据号' => 'iso_no',//编号关联商品,支持一次导入多张入库单
        '*:入库单名称' => 'name',//出入库单名称
        '*:是否紧急入库' => 'emergency',
        '*:供应商' => 'supplier_name',
        '*:入库仓库' => 'branch_id',
        '*:入库类型' => 'type_id',
        '*:入库费用'=>'iso_price',
        '*:经办人' => 'oper',
        '*:备注'=>'memo',
        '*:外部仓库'=>'extrabranch',
        '*:业务类型'=>'bill_type',
        '*:业务单号'=>'business_bn',
        '*:货号' => 'bn',
        '*:货品名称'=>'product_name',
        '*:货品条形码'=>'barcode',
        '*:数量'=>'nums',
        '*:价格'=>'price',
        '*:发货地址省份'=>'area_state',
        '*:发货地址城市'=>'area_city',
        '*:发货地址区/县'=>'area_district',
        '*:发货人详细地址'=>'extra_ship_addr',
        '*:发货人姓名'=>'extra_ship_name',
        '*:发货人手机'=>'extra_ship_mobile',
    );

    private $item = array(
        '*:单据号' => 'iso_no',//编号关联商品,支持一次导入多张单据
        '*:货号' => 'bn',
        '*:货品名称'=>'product_name',
        '*:货品条形码'=>'barcode',
        '*:数量'=>'nums',
        '*:价格'=>'price'
    );

    #这是用来转换数据的属性
    private $relation_iso = array(
        0=>'name',
        1=>'emergency',
        2=>'supplier_name',
        3=>'branch_id',
        4=>'type_id',
        5=>'iso_price',
        6=>'oper',
        7=>'memo',
        8=>'extrabranch',
    );
    #这是用来转换数据的属性
    private $relation_item = array(
        0=>'bn',
        1=>'product_name',

        2=>'barcode',
        3=>'nums',
        4=>'price'
    );


    function modifier_bill_type($col, $list, $row){
        $itype = app::get('ome')->model('iso_type')->db_dump(['type_id'=>$row['type_id'], 'bill_type'=>$col], 'bill_type_name');
        if($itype['bill_type_name']) {
            return $itype['bill_type_name'];
        }
        return self::$bill_type[$col] ? : $col;
    }

    /**
     * 
     */
    function iso_items($iso_id) {
        $eoObj = $this->app->model("iso_items");
        $rows['items'] = $eoObj->getList('product_name as name,nums as num,bn,price',array('iso_id'=>$iso_id));
        $total_num = 0;
        $total_price = 0;

        foreach($rows['items'] as $v){
            $total_num += intval($v['num']);
            $total_price += intval($v['num'])*floatval($v['price']);
        }
        $rows['total_num'] = $total_num;
        $rows['total_price'] = $total_price;
        return $rows;
    }
    #出入库模板
    function exportTemplate($filter=null,$iso_type=null){
        foreach ($this->io_title($filter) as $v){
            $title[] = $v;
        }
        return $title;
    }
    function io_title( $filter=null,$ioType ='csv'){
        if($filter == '1'||$filter == 'temple_in'){
            #导出入库模板
            $this->oSchema['csv']['iso'] = $this->temple_in;
            $this->ioTitle[$ioType] = array_keys($this->oSchema['csv']['iso']);
        }elseif($filter == '0'||$filter == 'temple_out'){
            #导出出库模板
            $this->oSchema['csv']['iso'] = $this->temple_out;
            $this->ioTitle[$ioType] = array_keys($this->oSchema['csv']['iso']);
        }elseif($filter == 'item' ){
            #导出出库模板
            $this->oSchema['csv']['item'] = $this->item ;
            $this->ioTitle[$ioType] = array_keys($this->oSchema['csv']['item']);
        }
        return $this->ioTitle[$ioType];
    }
    function prepared_import_csv_row($row,$title,&$Tmpl,&$mark,&$newObjFlag,&$msg){
        $fileData = $this->import_data;
        if( !$fileData ){
            $fileData = array();
        }
        if(!empty($row)){

            //标题栏
            $row[0] = trim($row[0]);
            if(substr($row[0], 0, 2) == '*:'){
                if($row[1] == '*:货号'){
                    $this->import_flag = false; //明细不用判断记录数
                }
            }else{
                //一次性最多允许导入100条记录
                if($this->import_flag && isset($this->import_nums)){
                    $this->import_nums++;
                    if($this->import_nums > 100){
                        unset($this->import_data, $fileData);

                        $error_msg = "导入的数据量过大，请减少到100单或以下！";
                        echo "<script>alert('导入失败: ". $error_msg ."')</script>";exit;
                        return false;
                    }
                }elseif($this->import_flag){
                    $this->import_nums = 0;
                }
            }

            $fileData[ $this->key++] = $row;
            #获取所有csv导入数据数组
            $this->import_data = $fileData;
        }
        return null;
    }
    function prepared_import_csv_obj($data,&$mark,$Tmpl,&$msg = ''){
        return null;
    }
    #读取csv数据完成以后处理，处理相关业务, 注意：一次只能一笔出入库
    function finish_import_csv()
    {
        header("Content-type: text/html; charset=utf-8");

        $oBranchProduct = app::get('ome')->model('branch_product');
        $ioOrderLib = kernel::single('taoguaniostockorder_iostockorder');
        $iso_obj = app::get('taoguaniostockorder')->model('iso');
        $item_obj = app::get('taoguaniostockorder')->model('iso_items');

        #获取已经定义好的入库数据的标题
        if($_GET['io'] == '1'){
            $iso_title = $this->temple_in;
        }
        #获取已经定义好的出库的标题
        if($_GET['io'] == '0'){
            $iso_title = $this->temple_out;
        }
        #获取所有已读取的csv导入数据
        $fileData = $this->import_data;


        //格式化导入的数据
        $dataList = $this->formatImportData($_POST['io'], $fileData, $error_msg);
        if(!$dataList){
            echo "<script>alert('导入失败: ". $error_msg ."')</script>";exit;
        }


        //开启事务
        kernel::database()->beginTransaction();

        foreach ($dataList as $key => $iso_data){
            $iso_no = $iso_data['iso_no'];
            $iso_items = $iso_data['items'];
            unset($iso_data['iso_no'], $iso_data['items']);

            //生成出入库单号
//            $iostockorder_bn = $ioOrderLib->get_iostockorder_bn($iso_data['type_id']);
            $iso_data['iso_bn'] = $iso_no;
//            if(!$iostockorder_bn){
//                echo "<script>alert('导入失败: 生成出入库单号失败,iso_no: ". $iso_no ."')</script>";exit;
//            }

            //insert
            $this->saveIsoDate($iso_obj, $iso_data);
            if($iso_data['iso_id'])
            {
                //insert items
                foreach($iso_items as $itemVal){
                    $itemVal['iso_id'] = $iso_data['iso_id'];
                    $itemVal['iso_bn'] = $iso_data['iso_bn'];
                    unset($itemVal['iso_no']);

                    $result = $item_obj->save($itemVal);
                    if(!$result){
                        kernel::database()->rollBack(); //回滚
                        echo "<script>alert('导入失败: 保存入库单明细失败,iso_no: ". $iso_no ."')</script>";exit;
                    }
                }
            }
            else
            {
                kernel::database()->rollBack(); //回滚
                echo "<script>alert('导入失败: 保存入库单主数据失败,iso_no: ". $iso_no ."')</script>";exit;
            }
        }

        //事务提交
        kernel::database()->commit();

        unset($fileData, $dataList, $iso_data, $iso_items);

        return true;
    }

    #组织iso数据，并保存数据
    function saveIsoDate($iso_obj,&$iso_data){
      $iso_data['name'] = $iso_data['name'];#入库单名称
      $iso_data['iso_bn'] =$iso_data['iso_bn'];
      $iso_data['type_id'] = $iso_data['type_id'];#出入库类型
      $iso_data['branch_id'] = $iso_data['branch_id'];#出入库仓库
      $iso_data['original_bn'] = '';
      $iso_data['original_id'] = 0;
      $iso_data['supplier_id'] = $iso_data['supplier_id'];
      $iso_data['supplier_name'] = $iso_data['supplier_name'];#供应商
      $iso_data['product_cost'] = $iso_data['product_cost'];#商品总额
      $iso_data['iso_price'] = $iso_data['iso_price'];#出入库费用
      $iso_data['oper'] = $iso_data['oper'];#经办人
      $iso_data['create_time'] = time();
      $iso_data['operator'] = $iso_data['operator'];#网站操作人员
      $iso_data['memo'] = $iso_data['memo'];#备注
      $iso_data['emergency'] = $iso_data['emergency'];#是否紧急
      $iso_data['bill_type'] = $iso_data['bill_type'];#单据业务类型
      $iso_data['business_bn'] = $iso_data['iso_bn'];//导入业务单号使用入库单号

      $iso_obj->save($iso_data);

      return ;
    }
    #增加调拨单号的搜索
    function searchOptions(){
        if($_GET['act'] == 'search_iostockorder' && $_GET['io'] == '1'){
             $parentOptions = parent::searchOptions();
             $childOptions = array(
                     'purchase_name'=>app::get('base')->_('采购单名称'),
             );
             return $Options = array_merge($parentOptions,$childOptions);
         }
         if($_GET['act'] == 'search_iostockorder' && $_GET['io'] == '0'){
             $parentOptions = parent::searchOptions();
             $childOptions = array(
                     'return_name'=>app::get('base')->_('采购退货单名称'),
             );
             return $Options = array_merge($parentOptions,$childOptions);
         }

        return parent::searchOptions();
    }
    function _filter($filter,$tableAlias=null,$baseWhere=null){

        #采购单名称模糊查询
        if(!empty($filter['purchase_name'])){
            $purchase_name = trim($filter['purchase_name']);
            $sql  = 'select
                        original_id
                    from sdb_purchase_po  po
                    left join sdb_taoguaniostockorder_iso iso on iso.original_id=po.po_id
                    where  iso.type_id =\'1\' and po.name like \''.$purchase_name.'%\'';
            $original_id =   $this->db->selectRow($sql);
            unset($filter['purchase_name']);
            $where = ' AND type_id=\'1\' and original_id='.$original_id['original_id'];
        }
        #采购退货单名称模糊查询
        if(!empty($filter['return_name'])){
            $name = $filter['return_name'];
            $sql  = 'select
                        original_id
                    from sdb_purchase_returned_purchase  returned
                    left join sdb_taoguaniostockorder_iso iso on iso.original_id=returned.rp_id
                    where  iso.type_id =\'10\' and returned.name like \''.$name.'%\'';
            $original_id =   $this->db->selectRow($sql);
            unset($filter['return_name']);
            $where = ' AND type_id=\'10\' and original_id='.$original_id['original_id'];
        }

        if(isset($filter['bn'])){


            $itemsObj = app::get('taoguaniostockorder')->model('iso_items');
            $items = $itemsObj->getlist('iso_id',array('bn'=>$filter['bn']));
            if ($items) $isoId= array_map('current', $items);
            $isoId[] = 0;
            $where .= ' AND iso_id IN ('.implode(',', $isoId).')';

            unset($filter['bn']);
        }
    
        if($filter['iso_bn'] && is_string($filter['iso_bn']) && strpos($filter['iso_bn'], "\n") !== false){
            $filter['iso_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['iso_bn']))));
        }
        
        if($filter['original_bn'] && is_string($filter['original_bn']) && strpos($filter['original_bn'], "\n") !== false){
            $filter['original_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['original_bn']))));
        }
        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }

    function pre_recycle($data=null) {
        if (is_array($_POST['iso_id'])) {
            foreach ($_POST['iso_id'] as $key => $val) {
                $iso = $this->dump($val, 'check_status');
                if ($iso['check_status'] == '2') {
                    $this->recycle_msg = '已审核单据不可以删除';
                    return false;
                }
            }
            return true;
        }
    }

    public function get_Schema()
    {

        if( $_GET['ctl']=='admin_iostockorder' && ($_GET['act']=='allocate_iostock' || $_GET['act']=='other_iostock')){
            $data = parent::get_Schema();
            $data['columns']['original_bn']['filtertype'] = '';
            $data['columns']['original_bn']['filterdefault'] = false;

            foreach($data['in_list'] as $k=>$v){
                if(in_array($v,array('original_bn'))){
                    //unset($data['in_list'][$k]);
                }
            }
            if(isset($data['deafult_in_list']) && is_array($data['deafult_in_list'])){
                foreach($data['deafult_in_list'] as $k1=>$v1){
                    if(in_array($v1,array('original_bn'))){
                        //unset($data['deafult_in_list'][$k1]);
                    }
                }
            }
            if ($_GET['act']=='other_iostock' && $_GET['app']=='console') {
                unset($data['columns']['type_id']['type']);
                if ($_GET['io'] == '1') {
                    $data['columns']['type_id']['type'] = array(
                        '70'=>'直接入库',
                        '50'=>'残损入库',
                        '200'=>'赠品入库',
                        '400'=>'样品入库',
                        '800'=>'分销入库',
                        '11'=>'调拨入库取消',
                    );
                }else{
                   $data['columns']['type_id']['type'] = array(
                        '7'=>'直接出库',
                        '5'=>'残损出库',
                        '100'=>'赠品出库',
                        '300'=>'样品出库',
                        '700'=>'分销出库',
                    );
                }

            }
            return $data;
        }else{
            return parent::get_Schema();
        }
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
        $type = 'warehouse';
        if ($logParams['app'] == 'taoguaniostockorder' && $logParams['ctl'] == 'admin_iostockorder') {
            if ($logParams['act'] == 'search_iostockorder') {
                if ($params['type_id'][0] == 1) {
                    $type .= '_enterManager_enterFind';
                }
                else {
                    $type .= '_outManager_outFind';
                }
            }
            else {
                $type .= '_enterManager_other';
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
        $type = 'warehouse';
        if ($logParams['app'] == 'taoguaniostockorder' && $logParams['ctl'] == 'admin_iostockorder') {
            $type .= '_other';
        }
        $type .= '_import';
        return $type;
    }

    /**
     * 导出明细
     * 
     * @param array $list
     * @param array $colArray
     * @return array
     * */
    public function getExportDetailV2($list, $colArray)
    {
        $iso_id = array_unique(array_column($list, 'iso_id'));
        if (!$iso_id) {
            return [$list, $colArray];
        }

        $colArray['e_item_product_name']    = ['label' => '基础物料名称'];
        $colArray['e_item_bn']              = ['label' => '基础物料编码'];
        $colArray['e_item_partcode']        = ['label' => '备件条码'];
        $colArray['e_item_unit']            = ['label' => '单位'];
        $colArray['e_item_nums']            = ['label' => '申请数量'];
        $colArray['e_item_normal_num']      = ['label' => '良品数量'];
        $colArray['e_item_defective_num']   = ['label' => '不良品数量'];


        $list = array_column($list, null, 'iso_id');

        $mdl = app::get('taoguaniostockorder')->model('iso_items');
        $baseMaterialMdl = app::get('material')->model('basic_material');
    
        $isoItemList      = $mdl->getList('*', array('iso_id|in' => $iso_id));
        $productIds       = array_unique(array_column($isoItemList, 'product_id'));
        $baseMaterialList = $baseMaterialMdl->getList('bm_id,material_bn,material_name', ['bm_id' => $productIds]);
        $baseMaterialList = array_column($baseMaterialList, null, 'bm_id');
        
        $listV2 = [];
        foreach ($isoItemList as $item) {
            $l = array_merge((array)$list[$item['iso_id']], [
                'e_item_product_name'    => isset($baseMaterialList[$item['product_id']]) ? $baseMaterialList[$item['product_id']]['material_name'] : '',
                'e_item_bn'              => $item['bn'],
                'e_item_partcode'        => $item['partcode'],
                'e_item_unit'            => $item['unit'],
                'e_item_nums'            => $item['nums'],
                'e_item_normal_num'      => $item['normal_num'],
                'e_item_defective_num'      => $item['defective_num'],
            ]);

            // 兼容导出数据，过滤掉特殊符号
            // $l = array_map(function($v) {
            //     $v = str_replace([',',"\r\n", "\r", "\n"],['，',' ',' ',' '],$v );
            // }, $l);

            $listV2[] = $l;
        }

        return [$listV2, $colArray];
    }

    /**
     * 导出唯一码平铺明细（snlists Sheet）
     */
    public function getexportsnlists($field, $filter, $offset = 0, $limit = -1, $first_sheet = false)
    {
        $isoIds = array();
        if (!empty($filter['iso_id'])) {
            $isoIds = is_array($filter['iso_id']) ? $filter['iso_id'] : array($filter['iso_id']);
        } elseif (!empty($filter['iso_id|in'])) {
            $isoIds = $filter['iso_id|in'];
        }
        if (empty($isoIds)) {
            return array();
        }

        $isoList = $this->getList('iso_id,iso_bn', array('iso_id' => $isoIds), $offset, $limit);
        if (empty($isoList)) {
            if ($first_sheet) {
                $columnDefs = console_adjust_serial::getColumnDefinitions();
                $title = array('*:出入库单号');
                foreach ($columnDefs as $def) {
                    $title[] = $def['label'];
                }
                return array(console_adjust_serial::buildExportCsvTitleLine($title));
            }
            return array();
        }

        $objItems = $this->app->model('iso_items');
        $items = $objItems->getList('*', array('iso_id' => $isoIds), 0, -1);
        $itemsByIso = array();
        foreach ((array) $items as $item) {
            $itemsByIso[$item['iso_id']][] = $item;
        }

        return console_adjust_serial::buildExportSnListCsvData(
            '*:出入库单号',
            $isoList,
            'iso_id',
            'iso_bn',
            $itemsByIso,
            array('console_adjust_serial', 'buildRowsFromIsoItems'),
            $first_sheet
        );
    }

    // public function getexportdetail($fields,$filter,$offset=0,$limit=1,$has_title=false){
    //     $iso = $this->getList('iso_id,iso_bn', array('iso_id|in'=>$filter['iso_id']));
    //     $aIso = array();
    //     foreach($iso as $val){
    //         $aIso[$val['iso_id']] = $val['iso_bn'];
    //     }
    //     $objItems = $this->app->model('iso_items');
    //     $items = $objItems->getList('*', array('iso_id|in'=>$filter['iso_id']));
    //     $inList = $objItems->schema['in_list'];
    //     $data = array();
    //     foreach($items as $k => $val) {
    //         $data[$k+1] = '';
    //         foreach($inList as $value){
    //             if(strpos($val[$value], ',') !== false){
    //                 $val[$value] = str_replace(',', '-', $val[$value]);
    //             }
    //             $data[$k+1] .= mb_convert_encoding($val[$value], 'GBK', 'UTF-8') . ',';
    //         }
    //         trim($data[$k+1], ',');
    //     }
    //     if($data && $has_title){
    //         $data[0] = '';
    //         foreach($inList as $value){
    //             $data[0] .= mb_convert_encoding($objItems->schema['columns'][$value]['label'], 'GBK', 'UTF-8') . ',';
    //         }
    //         trim($data[0], ',');
    //     }
    //     ksort($data);
    //     return $data;
    // }

    /**
     * 格式化导入的数据
     */
    public function formatImportData($io_type, $fileData, &$error_msg){
        $branch_obj = app::get('ome')->model('branch');
        $iostock_type_obj = app::get('ome')->model('iostock_type');
        $extrabranchObj = app::get('ome')->model('extrabranch');
        $supplier_obj = app::get('purchase')->model('supplier');

        $oBranchProduct = app::get('ome')->model('branch_product');

        $ioOrderLib = kernel::single('taoguaniostockorder_iostockorder');
        $productLib = kernel::single('ome_goods_product');

        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $basicMaterialBarcode = kernel::single('material_basic_material_barcode');

        //操作人员
        $operator = kernel::single('desktop_user')->get_name();

        //入库类型
        $arr_iso_type = $ioOrderLib->get_create_iso_type($io_type, true);

        //标题
        if($io_type == '1'){
            $iso_title = $this->temple_in;
        }else{
            $iso_title = $this->temple_out;
        }

        //检查第一行标题
        if(substr($fileData[0][0], 0, 2) == '*:' ){
            if(count($fileData[0]) != count($iso_title)){
                $error_msg = '第一行标题列数不正确!';
                return false;
            }

            //检查csv导入的标题是否存在于已定义的iso标题中
            foreach($fileData[0] as $title){
                if(!array_key_exists($title, $iso_title)){
                    $error_msg = '标题错误: ' .$title. '!';
                    return false;
                }
            }
        }else{
            $error_msg = '第一行不是标题!';
            return false;
        }

        $titleList = $fileData[0];
        unset($fileData[0]);

        //字段名
        $relation_iso = array_values($iso_title);

        //主数据
        $iso_data = array();
        foreach($fileData as $key => $dataVal){

            //遇到货品标题则跳出
            if(substr($dataVal[0], 0, 2) == '*:'){
                break;
            }

            //整理数据
            $item = array();
            foreach ($dataVal as $key_i => $val)
            {
                //格式化字段名对应值
                $iso_key = $relation_iso[$key_i];
                
                //过滤空格和全角空格
                $val = str_replace(array("\r\n", "\r", "\n", ' ', '　', "\t"), '', $val);
                
                $item[$iso_key] = $val;
            }
            unset($val);

            //单据号
            $iso_no = trim($item['iso_no']);

            //判断单据号
            if($iso_data[$iso_no]){
                $error_msg = '单据号：'. $iso_no .' 已经存在,请不要重复使用!';
                return false;
            }

            $iso_data[$iso_no] = $item;


            //入库类型
            $type_id = $iostock_type_obj->getList('type_id', array('type_name'=>$item['type_id']));
            if(empty($type_id[0]['type_id'])){
                $error_msg = '出入库类型不能为空,单据号：'. $iso_no;
                return false;
            }
            if(false === array_search($type_id[0]['type_id'], $arr_iso_type)){
                $error_msg = '出入库类型没有找到,单据号：'. $iso_no;
                return false;
            }
            $iso_data[$iso_no]['type_id'] = $type_id[0]['type_id'];


            //外部仓库
            if(empty($item['extrabranch'])){
                $error_msg = '外部仓库不能为空,单据号：'. $iso_no;
                return false;
            }
            $extrabranch = $extrabranchObj->dump(array('name'=>$item['extrabranch']), 'branch_id');
            if(!empty($extrabranch)){
                $iso_data[$iso_no]['extrabranch_id'] = $extrabranch['branch_id'];
            }
            $iso_data[$iso_no]['extrabranch_bn'] = $item['extrabranch'];

            //供应商(todo：供应商非必填)
            if(!empty($item['supplier_name'])){
                $supplier_id = $supplier_obj->getList('supplier_id', array('name'=>$item['supplier_name']));
                if(empty($supplier_id[0]['supplier_id'])){
                    $error_msg = '供应商没有找到,单据号：'. $iso_no;
                    return false;
                }

                $iso_data[$iso_no]['supplier_id'] = $supplier_id[0]['supplier_id'];
            }


            //操作人员
            $operator = $operator ? $operator : 'system';
            $iso_data[$iso_no]['operator'] = $operator;


            //出入库费用
            if(empty($item['iso_price'])){
                $iso_data[$iso_no]['iso_price'] = 0;
            }else{
                $_iso_price = $productLib->valiPositive($item['iso_price']);
                if(!$_iso_price){
                    $error_msg = '出入库费用必须大于等于0,单据号：'. $iso_no;
                    return false;
                }
            }


            //检测是否紧急数据
            if($item['emergency'] == '是'){
                $iso_data[$iso_no]['emergency'] = 'true';
            }elseif($item['emergency'] == '否'){
                $iso_data[$iso_no]['emergency'] = 'false';
            }else{
                $error_msg = '请填写是否紧急：是/否,单据号：'. $iso_no;
                return false;
            }


            //出入库仓库
            $branch_id = $branch_obj->getList('branch_id,type', array('name'=>$item['branch_id']));
            if(empty($branch_id[0]['branch_id'])){
                $error_msg = '请填写正确的仓库名称,单据号：'. $iso_no;
                return false;
            }

            //判断是否残损
            if (in_array($branch_id[0]['type'], array('damaged')) || in_array($type_id[0]['type_id'], array('5', '50'))) {
                if ($branch_id[0]['type'] == 'damaged' && !in_array($type_id[0]['type_id'], array('5','50'))){
                    $error_msg = '残损出入库和仓库类型必须一致,单据号：'. $iso_no;
                    return false;
                }

                if($branch_id[0]['type'] != 'damaged' && in_array($type_id[0]['type_id'], array('5','50'))) {
                    $error_msg = '残损出入库和仓库类型必须一致,单据号：'. $iso_no;
                    return false;
                }
            }
            $iso_data[$iso_no]['branch_id'] = $branch_id[0]['branch_id'];
            //单据业务类型
            $iso_data[$iso_no]['bill_type'] = $item['bill_type'];

            unset($fileData[$key]);
        }


        //item标题
        $item_title = $this->item;

        $itemTitles = array_shift($fileData);
        $itemTitles = array_filter($itemTitles);
        if(substr($itemTitles[0], 0, 2) == '*:' ){
            if(count($itemTitles) != count($item_title)){
                $error_msg = '货品标题列数不正确!';
                return false;
            }

            //检查货品标题字段
            foreach($itemTitles as $title){
                if(!array_key_exists($title, $item_title)){
                    $error_msg = '货品标题错误: ' .$title. '!';
                    return false;
                }
            }
        }else{
            $error_msg = '货品标题不存在!';
            return false;
        }

        //item字段名
        $relation_item = array_values($item_title);

        //items明细
        foreach($fileData as $key => $dataVal){

            //整理数据
            $item = array();
            $dataVal = array_filter($dataVal);
            foreach ($dataVal as $key_i => $val){
                //格式化字段名对应值
                $item_key = $relation_item[$key_i];
                $item[$item_key] = $val;
            }
            unset($val);

            $iso_no = trim($item['iso_no']);//单据号
            $bn = trim($item['bn']);//货号

            //check
            if(empty($bn)){
                $error_msg = '单据号：'. $iso_no .' 货号不能为空!';
                return false;
            }

            if(empty($iso_data[$iso_no])){
                $error_msg = '单据号：'. $iso_no .' 主数据不存在!';
                return false;
            }

            if ($iso_data[$iso_no]['items'][$bn]) {
                $error_msg = '单据号：'. $iso_no .',货号: '. $bn .' 已经存在!';
                return false;
            }
            
            //基础物料主信息
            $productInfo = $basicMaterialObj->dump(array('material_bn'=>$bn), '*');
            if(empty($productInfo)){
                $error_msg = '单据号：'. $iso_no .',货号: '. $bn .' 不存在!';
                return false;
            }
            
            //基础物料扩展信息
            $bMaterialRow = $basicMaterialExtObj->dump(array('bm_id'=>$productInfo['bm_id']), 'retail_price');

            //基础物料条形码
            $productInfo['barcode'] = $basicMaterialBarcode->getBarcodeById($productInfo['bm_id']);

            $productInfo['price'] = $bMaterialRow['retail_price'];
            
            $item['product_id'] = $productInfo['bm_id'];

            //条形码
            if(!empty($item['barcode'])){
                if($item['barcode'] != $productInfo['barcode']){
                    $error_msg = '单据号：'. $iso_no .',货号: '. $bn .' 条形码不存在!';
                    return false;
                }
            }else{
                //直接赋值条形码
                $item['barcode'] = $productInfo['barcode'];
            }

            //数量
            $_nums = $productLib->valiPositive($item['nums']);
            if(!$_nums){
                $error_msg = '单据号：'. $iso_no .',货号: '. $bn .' 数量必须大于0!';
                return false;
            }
            $item['nums'] = intval($item['nums']);

            //价格
            if($item['price']){
                $_price = $productLib->valiPositive($item['price']);
                if(!$_price){
                    $error_msg = '单据号：'. $iso_no .',货号: '. $bn .' 价格必须大于等于0!';
                    return false;
                }
            }else{
                $_price = (float)$productInfo['price'];
            }
            $item['price'] = (float)$item['price'];

            //出库时,检查库存
            if($io_type == '0'){
                $aRow = $oBranchProduct->dump(array('product_id'=>$productInfo['bm_id'], 'branch_id'=>$iso_data[$iso_no]['branch_id']), 'store');
                $store = $aRow['store'];
                if(empty($store)){
                    $error_msg = '单据号：'. $iso_no .',货号: '. $bn .' 出库仓库没有该货号库存!';
                    return false;
                }

                if($item['nums'] > $aRow['store']){
                    $error_msg = '单据号：'. $iso_no .',货号: '. $bn .' 出库数量不能大于库存数: '. $store .'!';
                    return false;
                }
            }


            //产品价格与数量乘积
            if($iso_data[$iso_no]['total_product_cost']){
                $iso_data[$iso_no]['total_product_cost'] += ($item['price'] * $item['nums']);
            }else{
                $iso_data[$iso_no]['total_product_cost'] = ($item['price'] * $item['nums']);
            }

            //拼接数据
            $item['unit'] = '';
            $iso_data[$iso_no]['items'][$bn] = $item;
            unset($fileData[$key]);
        }

        return $iso_data;
    }
}
