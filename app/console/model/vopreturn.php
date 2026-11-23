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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 
 * @describe: 
 * ============================
 */
class console_mdl_vopreturn extends dbeav_model {
    var $export_name = '唯品会退供单';
    var $has_export_cnf = true;

    function searchOptions(){
        $parentOptions = parent::searchOptions();
        $childOptions = array(
            'transferoutcode'=>app::get('base')->_('出库单号'),
            'refundid'=>app::get('base')->_('退货单号'),
            'saleordid'=>app::get('base')->_('平台原始订单号'),
            'partcode'=>'备件码',
        );
        return array_merge($childOptions,$parentOptions);
    }

    public function _filter($filter,$tableAlias=null,$baseWhere=null){

        if (isset($filter['transferoutcode'])){
            $return_ids = array();
            $return_ids[] = 0;
            $items = $this->db->select("SELECT return_id FROM sdb_console_vopreturn_items  WHERE transferoutcode='".$filter['transferoutcode']."'");

            if($items){
                foreach($items as $v){
                    $return_ids[] = $v['return_id'];
                }
            }
            $where .= '  AND id IN ('.implode(',', $return_ids).')';
            unset($filter['transferoutcode']);

        }

        if (isset($filter['refundid'])){
            $return_ids = array();
            $return_ids[] = 0;
            $items = $this->db->select("SELECT return_id FROM sdb_console_vopreturn_items  WHERE refundid='".$filter['refundid']."'");

            if($items){
                foreach($items as $v){
                    $return_ids[] = $v['return_id'];
                }
            }
            $where .= '  AND id IN ('.implode(',', $return_ids).')';
            unset($filter['refundid']);

        }
        if (isset($filter['saleordid'])){
            $return_ids = array();
            $return_ids[] = 0;
            $items = $this->db->select("SELECT return_id FROM sdb_console_vopreturn_items  WHERE saleordid='".$filter['saleordid']."'");

            if($items){
                foreach($items as $v){
                    $return_ids[] = $v['return_id'];
                }
            }
            $where .= '  AND id IN ('.implode(',', $return_ids).')';
            unset($filter['saleordid']);

        }

        if (isset($filter['partcode'])){
            $return_ids = array();
            $return_ids[] = 0;
            $items = $this->db->select("SELECT return_id FROM sdb_console_vopreturn_items  WHERE partcode='".$filter['partcode']."'");

            if($items){
                foreach($items as $v){
                    $return_ids[] = $v['return_id'];
                }
            }
            $where .= '  AND id IN ('.implode(',', $return_ids).')';
            unset($filter['partcode']);

        }
        return parent::_filter($filter,$tableAlias,$baseWhere).$where;
    }

    public function prepared_import_csv(){
        $this->import_data =[];
        $this->import_data_barcode =[];
        $this->import_msg = [];
        $this->ioObj->cacheTime = time();
    }

   
    
    public function prepared_import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        $postData = $this->import_filter;
        //唯品会退供确认
        if(isset($postData['source_type']) && $postData['source_type'] == 'import_check'){
            $this->import_csv_row_check($row, $title, $tmpl, $mark, $newObjFlag, $msg);
        }else{
            $this->import_csv_row($row, $title, $tmpl, $mark, $newObjFlag, $msg);
        }
        
        return true;
    }
    
    public function import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg){
        $shop_id = $_POST['shop_id'];
        if(empty($shop_id)){
            $msg['error'] = '请选择店铺';
            return false;
        }
        $shop_type = $_POST['shop_type'];
        $export_templateColumn = $this->getexportTemplateColumn($shop_type);
        
        if(empty($row) || empty(array_filter($row))) return false;
        if( $row[0] == '退货单号' || $row[0] == '客退单号'){
            $this->nums = 1;
            $title = array_flip($row);
            
            foreach($export_templateColumn as $k => $val) {
                if(!isset($title[$k])) {
                    $msg['error'] = '请使用正确的模板'.$k;
                    return false;
                }
            }
            return false;
        }
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        if($shop_type=='360buy'){
            $arrRequired = ['return_sn','shop_product_bn','partcode','saleordid'];
        }elseif($shop_type=='vop'){
            $arrRequired = ['return_sn','po_no','barcode','qty'];
        }
        
        
        $arrData = array();
        
        foreach($export_templateColumn as $k => $val) {
            $arrData[$val] = trim($row[$title[$k]]);
            if(in_array($val, $arrRequired) && empty($arrData[$val])) {
                $msg['warning'][] = 'Line '.$this->nums.'：'.$k.'不能为空！';
                return false;
            }
        }
        
        if(isset($this->nums)){
            $this->nums++;
            if($this->nums > 10000){
                $msg['error'] = "导入的数据量过大，请减少到10000条以下！";
                return false;
            }
        }
        $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$shop_id], 'shop_id,shop_type');
        $arrData['shop_id']=$shop['shop_id'];
        $arrData['shop_type']=$shop['shop_type'];
        if(!$this->import_data[$arrData['return_sn']]) {
            
            $sdf = [];
            $sdf['return_sn'] = $arrData['return_sn'];
            if($sdf['return_sn']) {
                if($this->db_dump(['return_sn'=>$sdf['return_sn']], 'id')) {
                    $msg['error'] = '退供单号重复:'.$sdf['return_sn'];
                    return false;
                }
            }
            $shop_bn = $arrData['shop_bn'];
            
            
            if(empty($shop)) {
                $msg['error'] = '店铺不存在';
                return false;
            }
            $sdf['shop_id'] = $shop['shop_id'];
            
            $sdf['shop_type'] = $shop['shop_type'];
            $sdf['logi_no'] = $arrData['logi_no'];
            $this->import_data[$arrData['return_sn']] = $sdf;
        }
        
        
        list($rs,$item) = $this->formatItems($arrData,$shop_type);
        
        if(!$rs){
            $msg['error'] = $item ? $item : '货品不存在';
            return false;
        }
        
        $this->import_data[$arrData['return_sn']]['items'][] = $item['items'];
        $mark = 'contents';
    }
    
    public function import_csv_row_check($row, &$title, &$tmpl, &$mark, &$newObjFlag, &$msg)
    {
        if (empty($row)) {
            return true;
        }
        
        foreach ($row as $k => $v) {
            $encode = mb_detect_encoding($v, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ('UTF-8' != $encode) {
                $v = mb_convert_encoding($v, 'UTF-8', $encode);
            }
            $row[$k] = $v;
        }
        
        if (substr($row[0], 0, 12) == '退供单号') {
            $titleRs = array_flip($row);
            $mark    = 'title';
            return $titleRs;
        } else {
            list($return_sn, $branch_name, $material_bn, $box_no, $qty, $split_num, $num) = $row;
            $sdf = array(
                'return_sn'   => trim($return_sn),
                'branch_name' => trim($branch_name),
                'material_bn' => trim($material_bn),
                'box_no'      => trim($box_no),
                'qty'         => (int)trim($qty),
                'split_num'   => (int)trim($split_num),
                'num'         => (int)trim($num),
            );
            
            //过滤入库为0的明细行
            if (empty($sdf['num'])) {
                return true;
            }
            
            //Post数据
            $postData = $this->import_filter;
            
            //检查数据有效性
            list($res, $err_msg, $item) = $this->_checkParams($sdf, $postData);
            if (!$res) {
                $msg['error'] = $err_msg;
                return false;
            }
            
            if (isset($postData['source_type']) && $postData['source_type'] == 'import_check') {
                $sdf['source_type'] = $postData['source_type'];
            }
            
            $this->import_data[$sdf['return_sn'].'_'.$sdf['branch_bn']]['return_id']               = $item['id'];
            $this->import_data[$sdf['return_sn'].'_'.$sdf['branch_bn']]['branch_id']               = $item['branch_id'];
            $this->import_data[$sdf['return_sn'].'_'.$sdf['branch_bn']]['items'][$item['item_id']] = $sdf['num'];
            $mark                                                            = 'contents';
            
            //销毁
            unset($sdf, $return_sn, $branch_bn, $material_bn, $box_no, $qty, $split_num, $num, $row);
        }
    }
    
    /**
     * 导入确认数据验证
     * @param $sdf
     * @param $post
     * @return array
     * @date 2025-04-22 下午5:16
     */
    public function _checkParams($sdf, $post)
    {
        $return_id   = $post['return_id'];
        $return_sn   = $sdf['return_sn'];
        $material_bn = $sdf['material_bn'];
        $box_no      = $sdf['box_no'];
        $num         = $sdf['num'];
        $branch_name = $sdf['branch_name'];
        
        if (empty($return_sn) || empty($return_id)) {
            $error_msg = '导入文件缺少退供单号！';
            return [false, $error_msg];
        }
        if (empty($material_bn)) {
            $error_msg = sprintf('退供单%s缺少基础物料编码！', $return_sn);
            return [false, $error_msg];
        }
        
        if (empty($box_no)) {
            $error_msg = sprintf('退供单%s缺少退供箱号！', $return_sn);
            return [false, $error_msg];
        }
        
        $returnInfo = app::get('console')->model('vopreturn')->db_dump(['id' => $return_id], 'id,return_sn,status');
        if (empty($returnInfo)) {
            $error_msg = sprintf('未查到%s退供单！', $return_sn);
            return [false, $error_msg];
        }
        
        if (!in_array($returnInfo['status'], ['0', '4'])) {
            $error_msg = sprintf('退供单%s已确认完成！', $return_sn);
            return [false, $error_msg];
        }
        
        if ($return_sn != $returnInfo['return_sn']) {
            $error_msg = sprintf('导入文件不能包含其它退供单号%s', $return_sn);
            return [false, $error_msg];
        }
        
        $items = app::get('console')->model('vopreturn_items')->db_dump(['return_id' => $returnInfo['id'], 'material_bn' => $material_bn, 'box_no' => $box_no], 'id as item_id,material_bn,qty,split_num,num');
        if (!$items) {
            $error_msg = sprintf('未查到%s退供单下基础物料编码%s', $return_sn,$material_bn);
            return [false, $error_msg];
        }
        
        $itemNum = $items['qty'] - $items['split_num'];
        if ($itemNum < 1) {
            $error_msg = sprintf('箱号%s,基础物料编码%s已无可入库数量！', $box_no, $material_bn);
            return [false, $error_msg];
        }
        if ($num > $itemNum) {
            $error_msg = sprintf('箱号%s,基础物料编码%s已超出可入库数量%s', $box_no, $material_bn, $itemNum);
            return [false, $error_msg];
        }
        
        $branch = app::get('ome')->model('branch')->db_dump(['name' => $branch_name], 'branch_id,branch_bn,name');
        if (!$branch) {
            $error_msg = sprintf('仓库【%s】不存在！', $branch_name);
            return [false, $error_msg];
        }
        
        return [true, '检测通过', array_merge($branch, $returnInfo, $items)];
    }

    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){

        return null;
       
    }


    public function finish_import_csv()
    {
        $data = $this->import_data;
        
        //导入确认退供单
        $postData = $this->import_filter;
        if (isset($postData['source_type']) && $postData['source_type'] == 'import_check') {
            $arrMsg = [];
            if (empty($data)) {
                $arrMsg[] = '导入文件未检测到有效数据！';
            }
    
            if (count((array)$data) > 1) {
                $arrMsg[] = '导入确认每次只允许确认一个仓库！';
            }
            
            if (empty($arrMsg)) {
                foreach ($data as $val) {
                    list($rs, $msg) = kernel::single('console_vopreturn')->doCheck($val['return_id'], $val['branch_id'], $val['items']);
                    if (!$rs) {
                        $arrMsg[] = $msg;
                    }
                }
            }
            header("content-type:text/html; charset=utf-8");
            if ($arrMsg) {
                echo "<script>if(parent.$('allocation-error'))parent.$('allocation-error').setHTML(\"" . implode('；',$arrMsg) . "\");if(parent.$('allocation-error'))parent.$('allocation-error').style.display = 'block';</script>";
            }else{
                echo "<script>if(parent.$('import_form').getParent('.dialog'))parent.$('import_form').getParent('.dialog').retrieve('instance').close();if(parent.window.finderGroup&&parent.window.finderGroup['" . $_GET['finder_id'] . "'])parent.window.finderGroup['" . $_GET['finder_id'] . "'].refresh();</script>";
            }
        
            return null;
        }
        $oQueue = app::get('base')->model('queue');
        foreach($data as $v){
            $queueData = array(
                'queue_title'=>'退供单导入',
                'start_time'=>time(),
                'params'=>array(
                    'sdfdata'=>$v,
                    'app' => 'console',
                    'mdl' => 'vopreturn'
                ),
                'worker'=>'console_mdl_vopreturn.import_run',
            );

            $oQueue->save($queueData);
        }
        $oQueue->flush();
      
    }

    function io_title($filter=null, $ioType='csv'){
        switch( $ioType ){
            case 'csv':
            default:
                $this->oSchema['csv']['vopreturn_item'] = array(
                        '*:退供单号' => 'return_sn',
                        '*:商品条形码' => 'barcode',
                        '*:商品名称' => 'product_name',
                        '*:货品等级' => 'grade',
                        '*:采购订单号' => 'po_no',
                        '*:实退数量' => 'qty',
                        '*:入库数量' => 'num',
                        '*:退供箱号' =>'box_no',
                        '*:供应商入库单号' =>'storage_no',
                        '*:供应商入库单箱号' =>'storage_box_no',
                );
            break;
        }
        
        $this->ioTitle[$ioType]['vopreturn'] = array_keys( $this->oSchema[$ioType]['vopreturn'] );
        $this->ioTitle[$ioType]['vopreturn_item'] = array_keys( $this->oSchema[$ioType]['vopreturn_item'] );
        
        return $this->ioTitle[$ioType][$filter];
    }

    public function getexportdetail($fields,$filter,$offset=0,$limit=1,$has_title=false)
    {
                
        $data = [];

        if($has_title){
            foreach ($this->io_title('vopreturn_item') as $key => $value) {
                $title[$key] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }
            
            $data[] = implode(',', $title);
        }

        $items = app::get('console')->model('vopreturn_items')->getList('*',[
            'return_id' => $filter['id'],
        ]);


        $vopreturn = app::get('console')->model('vopreturn')->getList('id,return_sn',[
            'id' => $filter['id'],
        ]);
        $vopreturn = array_column($vopreturn, null, 'id');

        foreach ($items as $value)
        {
            //出库单号
            $value['return_sn']    = $vopreturn[$value['return_id']]['return_sn'];
            

            $row    = array();
            foreach( $this->oSchema['csv']['vopreturn_item'] as $k => $v ){
                $row[$k] = mb_convert_encoding(utils::apath($value, explode('/',$v)), 'GBK', 'UTF-8');
            }
            
            $data[]    = implode(',', $row);
        }
        
        return $data;
    }


    public function getJdTitle()
    {

        $title = array(
            '退货单号'     => 'return_sn',
            '申请时间'     => 'create_time',
            '备件条码'     => 'partcode',
            'UPC码'       => 'barcode',
            '原始订单号'   => 'saleordid',
            '采购渠道'     => 'channel',
            '商品编号'     => 'shop_product_bn',
            '商品名称'     => 'product_name',
            '退货金额'     => 'price',
            '采销员'       => 'supplier_name',
            '出库单号'     => 'transferoutcode',
            '出库类型'     => 'return_type',
            '机构'         => 'org',
            '备件库'       => 'storeid',
            '运单号'       => 'logi_no',
            '确认收货时间'  => 'out_time',
            '确认收货人'    => 'consignee',
            '订单类型'      => 'order_type',
            '订单渠道'      => 'order_channel',

        );

        return $title;
    }

    public function getVopTitle()
    {

        $title = array(
            '客退单号'          => 'return_sn',
            '箱号'             => 'box_no',
            '采购单号（PO）'    => 'po_no',
            '条码'             =>'barcode',
            '货号'            =>'material_bn',
            '数量'            =>'qty',
            '入库日期'        =>'iostock_time',
        );

        return $title;
    }
    
    public function getVopCheckTitle()
    {
        $title = array(
            '退供单号'   => 'return_sn',
            '仓库名称'   => 'branch_name',
            '基础物料编码' => 'material_bn',
            '退供箱号'   => 'box_no',
            '退供数量'   => 'qty',
            '已拆数量'   => 'split_num',
            '实退数量'   => 'num',
        );
        return $title;
    }

    public function getTemplateColumn($shop_type) {

        
        if($shop_type=='360buy'){
            $templateColumn = $this->getJdTitle();
        }else{
            $templateColumn = $this->getVopTitle();
        }
        return array_keys($templateColumn);
    }

    public function getexportTemplateColumn($shop_type){
        if($shop_type=='360buy'){
            $templateColumn = $this->getJdTitle();
        }else{
            $templateColumn = $this->getVopTitle();
        }
        return $templateColumn;
    }

    public function formatItems($row,$shop_type){
        $purchaseSkuPriceMdl = app::get('purchase')->model('order_sku_price');
        $basicObj = kernel::single('material_basic_material');
        if($shop_type=='360buy'){
            $items = array(
                'shop_product_bn'   =>  $row['shop_product_bn'],
                'partcode'          =>  $row['partcode'],
                'product_name'      =>  $row['product_name'],
             
                'qty'               =>  1,
                'price'             =>  $row['price'],
                'saleordid'         =>  $row['saleordid'],
                'transferoutcode'   =>  $row['transferoutcode'],

            );
            $materials = kernel::single('ediws_jdlvmi')->get_sku($row['shop_id'],$row['shop_product_bn']);

            if($materials){
                $items['bm_id'] = $materials['bm_id'];
                $items['material_bn'] = $materials['material_bn'];
            }else{

                return [false,$row['shop_product_bn'].'商品系统不存在'];
            }

            return [true,array('items'=>$items)];
        }elseif($shop_type=='vop'){
            $items = array(
                'box_no'        =>  $row['box_no'],
                'po_no'         =>  $row['po_no'],
                'barcode'       =>  $row['barcode'],
                'qty'           =>  $row['qty'],
                
            );

            
            $bmId = $basicObj->getMaterialBmidByCode($items['barcode']);
          
            if(empty($bmId)) {
               
                return [false,$row['barcode'].' 条码不存在'];
            }
           
            $materials = $this->getBasicMaterialByid($bmId);

            $items['material_bn'] = $materials['material_bn'];
            $items['bm_id'] = $bmId;
            $items['product_name'] = $materials['material_name'];
            $skuPrices        = $purchaseSkuPriceMdl->db_dump( ['po_bn' => $row['po_no'], 'barcode' => $row['barcode']],'id,po_bn,barcode,actual_market_price,price');
            $items['price'] = $skuPrices['price'] ? $skuPrices['price'] : $materials['retail_price'];
            return [true,array('items'=>$items)];

        }

        
    }

    public function addReturn($data){
        $logObj = app::get('ome')->model('operation_log');
        $vopreturns = $this->db_dump(array('return_sn'=>$data['return_sn']),'id');
        if($vopreturns){
            $msg['error'] = $data['return_sn'].'已存在';
            return false;
        }
        kernel::database()->beginTransaction();
        $items = $data['items'];
        unset($data['items']);
        $main = $data;
        $main['create_time']=time();
        app::get('console')->model('vopreturn')->insert($main);
        $mainId = $main['id'];
        if(empty($mainId)) {
            kernel::database()->rollBack();
            $msg['error'] = '主信息写入失败';
            return false;
        }
        
        $insertData = [];
        $total_qtys = 0;
        foreach($items as $v) {
            $v['return_id'] = $mainId;
            $total_qtys += $v['qty'];
            $insertData[$v['bm_id']] = $v;
        }
        $itemObj = app::get('console')->model('vopreturn_items');
        $sql = kernel::single('ome_func')->get_insert_sql($itemObj, $insertData);
        $itemObj->db->exec($sql);
        $upData = [
            'total_skus' => count($insertData),
            'total_qtys' => $total_qtys,
        ];
        $this->update($upData, ['id'=>$mainId]);
        $logObj->write_log('vopreturn@console',$mainId,'导入成功');
       
        kernel::database()->commit();
    }


    public function getBasicMaterialByid($bm_id)
    {
        $materialMdl = app::get('material')->model('basic_material');
        $extMdl = app::get('material')->model('basic_material_ext');
        $basicMateriaItem    = $materialMdl->dump(array('bm_id'=>$bm_id), 'bm_id, material_bn, material_name');
        if(empty($basicMateriaItem))
        {
            return [];
        }

        #扩展信息
        $basicMateriaExt    = $extMdl->dump(array('bm_id'=>$basicMateriaItem['bm_id']), 'cost, retail_price');

        return array_merge($basicMateriaItem, $basicMateriaExt);
    }
}
