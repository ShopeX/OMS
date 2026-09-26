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
 * 公共类
 */
class invoice_event_trigger_data_common
{
    protected $tax_rate = '';
    protected $__total_se = 0;#合计税额
    protected $__total_je = 0;#合计金额
    
    public function get_invoice_setting_info(){
        $this->__invoice_channel_id;
    }
    
    /**
     * 组织参数
     * 
     * @param array $order
     * @param string $einvoice_type
     * @return array
     */
    public function getEinvoiceRequestParams($orderInfo, $einvoice_type='blue')
    {
        $rsp = array('rsp'=>'fail', 'error_msg'=>'');
        
        $error_msg = '没有组织参数';
        $rsp['error_msg'] = $error_msg;
        
        return $rsp;
    }
    
    public function getDirectSdf($order_params,$einvoice_type='blue')
    {
        //==
    }
    
    public function getEinvoiceInvoiceItems($order_params,$einvoice_type='blue',$source='', &$error_msg=null)
    {
        if(isset($order_params['hsbz'])){
            $hsbz = $order_params['hsbz'];
        }else{
            $hsbz = '1';#含税
        }
        if(!empty($order_params['lslbs'])){
            $lslbs = $order_params['lslbs'];
        }else{
            $lslbs = '';
        }
        
        $tax_rate = $order_params['tax_rate'] / 100;

        if(isset($order_params['yhzcbs'])){
            $yhzcbs = $order_params['yhzcbs'];
        }else{
            $yhzcbs = '0';#未使用优惠
        }
        
        if(isset($order_params['zzstsgl'])){
            $zzstsgl = $order_params['zzstsgl'];
        }else{
            $zzstsgl = '';
        }
        
        if($einvoice_type =='red'){
            $status = '-';#负数发票
        }
        
        $sale_data = kernel::single('invoice_sales_data')->getInvoiceData($order_params);
        
        if(!$sale_data['sales_items']) {
            $msg = "缺少开票明细";
            $opObj = app::get('ome')->model('operation_log');
            $opObj->write_log('invoice_billing@invoice', $order_params['id'], $msg);
        }
        

        $all_product_bns = $all_sales_material_bns = array();
        foreach($sale_data['sales_items'] as $v)
        {
            if($v['item_type'] == 'basic'){
                $all_product_bns[] = $v['bn'];
            }elseif ($v['item_type'] == 'sales') {
                $all_sales_material_bns[] = $v['sales_material_bn'];
            }
        }

        $goods_info = $this->get_goods_info($all_product_bns);
        $salesMaterialInfo = invoice_func::getSalesMaterialInfo($all_sales_material_bns);

        $fpmxxh = 0;
        $isCheckTaxCode = true;
        
        foreach($sale_data['sales_items'] as $k => $v)
        {
            if($v['sales_amount'] <= 0) continue; //金额为0的,过滤掉，不开票
            //不开运费配置开启后过滤运费行明细
            if ('1' != app::get('ome')->getConf('ome.invoice.amount.infreight')) {
                if ($sale_data['cost_freight'] > 0 && $v['item_type'] == 'ship') {
                    continue;
                }
            }
            $product_tax_rate = $tax_rate;
            $spec = $v['specification'];
            $unit = $v['unit'];
            $name = $v['item_name'];
            $tax_code = $v['tax_code'];
            if ($v['tax_rate'] > 0) $product_tax_rate = $v['tax_rate']/100;

            //只要有一个明细没有商品编码，则整单跳过，不开票
            if(!$tax_code && $isCheckTaxCode){
                $msg = "开票失败,发票明细".$v['bn']."无分类编码";
                $opObj = app::get('ome')->model('operation_log');
                $opObj->write_log('invoice_billing@invoice', $order_params['id'], $msg);
                
                // error_msg
                $error_msg = '发票明细货号：'. $v['bn'] .'未设置分类编码';
                
                return false;
            }
            
            $product_jshj = round($v['sales_amount'],2);#使用销售单模式计算出来的金额
            $product_se   = round($product_jshj * $product_tax_rate / (1+$product_tax_rate),2);#商品金额(含)*税率/(1+税率)
            $product_je   = round($product_jshj - $product_se,2);  #商品金额 = 商品金额(含) - 税额

            $this->__total_se += $product_se;
            $this->__total_je += $product_je;

            $data[] = array(
                'fpmxxh'  => ++$fpmxxh,#行号。第一条发票明细行号为1，第二条为2，以此类推
                'fphxz'   => '0',    #票行性质，0：正常行，1：折扣行，2：被折扣行
                'spbm'    => $tax_code,  #商品编码
                'spmc'    => $name?:$v['name'],  #商品名称,取ERP名称，平台名称由于过长，会限制
                'spsm'    => '',# 当前商品对应的税目，可为空
                'zxbm'    => '',
                'ggxh'    => $spec,# 规格型号
                'dw'      => $unit, # 计量单位
                'spsl'    => $status.$v['nums'],  #商品数量
                'spdj'    => $product_je>0?round(($product_je/$v['nums']),6):0,  # 单价,保留6位小数
                'jshj'    => $product_jshj>0?$status.$product_jshj:0,#价税合计
                'sl'      => $product_tax_rate > 0 ? $product_tax_rate : 0,  #税率,开票设置时，一定要客户设置好，设置错了就开错了
                'se'      => $product_se>0?$status.$product_se:0,#税额
                'je'      => $product_je>0?$status.$product_je:0,  #商品金额
                'zhdyhh'  => '',#折行对应行号,如果为正常普通行，忽略该值
                'hsbz'    => '0',  #含税标志,             一定要注意，写1,是不行的,要写0
                'zzstsgl' => $zzstsgl,  #增值税特殊管理
                'yhzcbs'  => $yhzcbs,  # 优惠政策标识
                'lslbs'   => $lslbs,  # 零税率标识
            );
        }

        // 处理销售价不等于0的情况
        if ($einvoice_type == 'blue' && $source != 'upload') {
            foreach($sale_data['sales_items'] as $k => $v)
            {
                if($v['sales_amount'] > 0) continue;
                $spec = $v['specification'];

                $product_tax_rate = $tax_rate;

                $bn = strtoupper($v['bn']);
                $tax_code = $v['tax_code']    ? $v['tax_code']  : '1';
                $unit     = $v['unit']        ? $v['unit']      : '';
                $name     = $v['item_name']    ? $v['item_name']  : $v['name'];
                if ($v['tax_rate'] > 0) $product_tax_rate = $v['tax_rate']/100;
                // 普通商品发票分类编码
                if(!empty($v['product_id'])){
                    $price    = $goods_info[$bn]['price']       ? $goods_info[$bn]['price']     : 0;
                }
                if ($v['item_type'] == 'sales' && $salesMaterialInfo[$v['bn']]['sales_material_type'] == '3' && app::get('ome')->getConf('ome.invoice.gift') == 'on') {
                    $price = $salesMaterialInfo[$v['bn']] ? $salesMaterialInfo[$v['bn']]['material_basic_cost_total'] : 0;
                }

                // 开票分类不存在
                if(!$tax_code || $price<=0) continue;

                $product_jshj = round($price,2);#使用销售单模式计算出来的金额
                $product_se   = round($product_jshj * $product_tax_rate / (1+$product_tax_rate),2);#商品金额(含)*税率/(1+税率)
                $product_je   = round($product_jshj - $product_se,2);  #商品金额 = 商品金额(含) - 税额

                $data[] = array(
                    'fpmxxh'  => ++$fpmxxh,#行号。第一条发票明细行号为1，第二条为2，以此类推
                    'fphxz'   => '2',    #票行性质，0：正常行，1：折扣行，2：被折扣行
                    'spbm'    => $tax_code,  #商品编码
                    'spmc'    => $name?:$v['name'],  #商品名称,取ERP名称，平台名称由于过长，会限制
                    'spsm'    => '',# 当前商品对应的税目，可为空
                    'zxbm'    => '',
                    'ggxh'    => $spec,# 规格型号
                    'dw'      => $unit, # 计量单位
                    'spsl'    => $v['nums'],  #商品数量
                    'spdj'    => $product_je       > 0 ? round(($product_je/$v['nums']),6) : 0,  # 单价,保留6位小数
                    'jshj'    => $product_jshj     > 0 ? $product_jshj : 0,#价税合计
                    'sl'      => $product_tax_rate > 0 ? $product_tax_rate : 0,  #税率,开票设置时，一定要客户设置好，设置错了就开错了
                    'se'      => $product_se       > 0 ? $product_se : 0,#税额
                    'je'      => $product_je       > 0 ? $product_je:0,  #商品金额
                    'zhdyhh'  => '',#折行对应行号,如果为正常普通行，忽略该值
                    'hsbz'    => '0',  #含税标志,             一定要注意，写1,是不行的,要写0
                    'zzstsgl' => $zzstsgl,  #增值税特殊管理
                    'yhzcbs'  => $yhzcbs,  # 优惠政策标识
                    'lslbs'   => $lslbs,  # 零税率标识
                );
                
                $data[] = array(
                    'fpmxxh'  => ++$fpmxxh,#行号。第一条发票明细行号为1，第二条为2，以此类推
                    'fphxz'   => '1',    #票行性质，0：正常行，1：折扣行，2：被折扣行
                    'spbm'    => $tax_code,  #商品编码
                    'spmc'    => $name?:$v['name'],  #商品名称,取ERP名称，平台名称由于过长，会限制
                    'spsm'    => '',# 当前商品对应的税目，可为空
                    'zxbm'    => '',
                    'ggxh'    => $spec,# 规格型号
                    'dw'      => $unit, # 计量单位
                    'spsl'    => -$v['nums'],  #商品数量
                    'spdj'    => $product_je        > 0 ? round(($product_je/$v['nums']),6) : 0,  # 单价,保留6位小数
                    'jshj'    => $product_jshj      > 0 ? -$product_jshj : 0,#价税合计
                    'sl'      => $product_tax_rate  > 0 ? $product_tax_rate : 0,  #税率,开票设置时，一定要客户设置好，设置错了就开错了
                    'se'      => $product_se        > 0 ? -$product_se : 0,#税额
                    'je'      => $product_je        > 0 ? -$product_je:0,  #商品金额
                    'zhdyhh'  => '',#折行对应行号,如果为正常普通行，忽略该值
                    'hsbz'    => '0',  #含税标志,             一定要注意，写1,是不行的,要写0
                    'zzstsgl' => $zzstsgl,  #增值税特殊管理
                    'yhzcbs'  => $yhzcbs,  # 优惠政策标识
                    'lslbs'   => $lslbs,  # 零税率标识
                );
            }
        }

        return $data;
    }

    /**
     * 获取上传数据
     *
     * @return void
     * @author
     */
    public function getUploadParams($invoice, $electronic)
    {
        $sdf = array(
            'electronic' => $electronic,
            'invoice'    => $invoice,
        );

        if ($electronic['billing_type'] == '1') {
            $this->__total_je = $this->__total_se = 0;
            $this->tax_rate   = $invoice['tax_rate']/100;

            $items = self::getEinvoiceInvoiceItems($invoice,'blue','upload'); //发票明细列表

            if(!$items) return false;

            $sdf['items'] = $items;
        } elseif ($electronic['billing_type'] == '2') {
            // 获取蓝票信息
            $invEleItemMdl = app::get('invoice')->model('order_electronic_items');

            $blueEle = $invEleItemMdl->db_dump(array('id'=>$invoice['id'],'billing_type'=>'1'));

            $sdf['electronic']['normal_invoice_code'] = $blueEle['invoice_code'];
            $sdf['electronic']['normal_invoice_no']   = $blueEle['invoice_no'];
        }

        return $sdf;
    }
    
    //废弃
    public function tran_sales_items(&$sales)
    {
        $basicMaterialMdl = app::get('material')->model('basic_material');
        $smMdl = app::get('material')->model('sales_material');
        $_salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
        $basicMextMdl     = app::get('material')->model('basic_material_ext');
        $goodsTypeMdl     = app::get('ome')->model('goods_type');
        
        foreach ($sales['sales_items'] as $key => $val) {
            if ($val['item_type'] == 'sales') {
                $salesInfo = $smMdl->db_dump($val['product_id']);
                if ($salesInfo['sales_material_type'] == '2') {
                    $salesBasicMInfos = $_salesBasicMaterialObj->getList('bm_id,sm_id,number,rate',array('sm_id'=>$val['product_id']), 0, -1);
                    //查询捆绑商品对应的机器码物料的发票分类编码
                    $prodcutIds    = array_column($salesBasicMInfos, 'bm_id');
                    $extCatIdList  = $basicMextMdl->getList('cat_id,bm_id', ['bm_id' => $prodcutIds]);
                    $catIds        = array_column($extCatIdList, 'bm_id', 'cat_id');
                    $goodsTypeInfo = $goodsTypeMdl->db_dump(['type_id' => array_keys($catIds), 'name' => 'M'], 'type_id');
                    if ($goodsTypeInfo) {
                        $basicInfo                              = $basicMaterialMdl->db_dump(['bm_id' => $catIds[$goodsTypeInfo['type_id']]], 'tax_code,tax_rate');
                        $sales['sales_items'][$key]['tax_code'] = $basicInfo['tax_code'];
                        $sales['sales_items'][$key]['tax_rate'] = $basicInfo['tax_rate'];
                    }
                }
            }
        }
    }

    //废弃
    public function get_goods_info($product_bns)
    {
        $sales_list = kernel::single('material_basic_select')->getlist_ext('unit,tax_rate,tax_name,tax_code,material_bn', array('material_bn'=>$product_bns));

        $sales = array();
        foreach($sales_list as $v){
            $bn = strtoupper($v['bn']);
            $sales[$bn] = $v;
        }
        
        return $sales;
    }
    //废弃
    public function getSalesMaterialInfo($sales_bns)
    {
        $smMdl = app::get('material')->model('sales_material');
        $smExtMdl = app::get('material')->model('sales_material_ext');
        $sales_list = $smMdl->getList('*',['sales_material_bn'=>$sales_bns]);
        $data = array();
        if ($sales_list) {
            $data = array_column($sales_list,null,'sales_material_bn');
            $smIds = array_column($sales_list,'sm_id');
            $smExtList = $smExtMdl->getList('sm_id,unit',['sm_id'=>$smIds]);
            $smExtList = array_column($smExtList,null,'sm_id');
            foreach ($data as $key => $val) {
                if (isset($smExtList[$val['sm_id']])) {
                    $data[$key] = array_merge($val,$smExtList[$val['sm_id']]);
                }
            }
        }
        
        return $data;
    }

    /**
     * 检查红冲是否需进行确认, 数电专票业务使用
     * @param $sdf
     * @return void
     */
    public function checkCancelConfirm($sdf)
    {
        // 校验发票类型, 普票不需要确认
        if ($sdf['type_id'] != '1' || $sdf['channel_node_type'] == 'baiwang') {
            return false;
        }

        // 已确认或无需确认, 则放行
        if (in_array($sdf['order_electronic_items']['red_confirm_status'], ['1', '4'])) {
            return false;
        }

        return true;
    }

    /**
     * 获取红字申请创建参数
     * @param $orderInfo
     * @return array|false
     */
    public function getCancelApplyRequestParams($orderInfo)
    {
        return false;
    }
}
