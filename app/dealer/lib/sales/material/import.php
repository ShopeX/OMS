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

class dealer_sales_material_import
{
    const IMPORT_TITLE = [
        '*:销售物料编码',
        '*:销售物料名称',
        '*:物料类型',
        '*:所属店铺',
        '*:序号',
        '*:基础物料编码',
        '*:基础物料数量',
    ];

    public function getExcelTitle()
    {
        return ['销售物料导入模板.xlsx',[
            self::IMPORT_TITLE,
            ['material_001','样例1：普通销售物料','普通','店铺1','1','product_001','1'],
            ['material_002','样例2：组合销售物料','组合','店铺1','1','product_001','1'],
            ['','','','','2','product_002','5'],
            ['','','','','3','product_003','5'],
            ['material_003','样例3：赠品销售物料','赠品','店铺3','1','product_002','1']
        ]];
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function processExcelRow($import_file, $post)
    {
        $format = [];
        
        // 读取文件
        return kernel::single('omecsv_phpoffice')->import($import_file, function ($line, $buffer, $post, $highestRow) {
            static $title, $salesMaterial;

            if ($line == 1) {
                $title = $buffer;
                // 验证模板是否正确
                if (array_filter($title) != self::IMPORT_TITLE) {
                    return [false, '导入模板不正确'];
                }
                return [true];
            }
            if(count($buffer) < count(self::IMPORT_TITLE)) {
                return [true];
            }
            $buffer = array_combine(self::IMPORT_TITLE, array_slice($buffer, 0, count(self::IMPORT_TITLE)));
            $msg = '';
            if($buffer['*:销售物料编码']) {
                if($salesMaterial['*:销售物料编码'] == $buffer['*:销售物料编码']) {
                    $salesMaterial['items'][] = $buffer;
                } else {
                    if($salesMaterial) {
                        list($rs, $rsData) = $this->_dealSalesMaterial($salesMaterial);
                        if(!$rs) {
                            $msg .= $rsData['msg'];
                        }
                    }
                    $salesMaterial = $buffer;
                    $salesMaterial['items'][] = $buffer;
                }
            } else {
                if($salesMaterial) {
                    $salesMaterial['items'][] = $buffer;
                }
            }
            if($line == $highestRow && $salesMaterial) {
                list($rs, $rsData) = $this->_dealSalesMaterial($salesMaterial);
                if(!$rs) {
                    $msg .= $rsData['msg'];
                }
                $salesMaterial = [];
            }
            return [($msg ? false : true), $msg];
        }, [], $format);
    }

    public function _dealSalesMaterial($salesMaterial) {
        $required = [
            '*:销售物料编码',
            '*:销售物料名称',
            '*:物料类型',
            '*:所属店铺',
            '*:基础物料编码',
            '*:基础物料数量',
        ];
        foreach ($required as $v) {
            if(empty($salesMaterial[$v])) {
                return [false, ['msg'=>$v.' 不能为空']];
            }
        }
        if(!in_array($salesMaterial['*:物料类型'], ['普通','组合','赠品'])) {
            return [false, ['msg'=> '物料类型仅限：普通、组合、赠品']];
        }
        $salesMaterial['sales_material_type'] = $salesMaterial['*:物料类型'] == '普通' ? '1' : 
            ($salesMaterial['*:物料类型'] == '组合' ? '2' : '3');

        if($salesMaterial['*:物料类型'] != '组合' && $salesMaterial['*:物料类型'] != '赠品' ) {
            if(count($salesMaterial['items']) > 1 || array_sum(array_column($salesMaterial['items'], '*:基础物料数量')) > 1) {
                return [false, ['msg'=>'除组合物料和赠品物料外，只能有一条一个基础物料']];
            }
        }

        $cosList = kernel::single('organization_cos')->getCosList();
        if (!$cosList[0]) {
            return [false, ['msg'=>'账号没有权限']];
        }

        $shop = app::get('ome')->model('shop')->db_dump(['name'=>$salesMaterial['*:所属店铺']], 'shop_id,org_id,cos_id,delivery_mode');
        if(empty($shop)) {
            return [false, ['msg'=>'店铺不存在']];
        } elseif ($shop['delivery_mode'] != 'shopyjdf') {
            return [false, ['msg'=>'店铺不是一件代发模式']];
        } elseif (!$shop['cos_id']) {
            return [false, ['msg'=>'店铺企业组织架构ID无效']];
        } elseif (!in_array($shop['cos_id'], array_column($cosList[1], 'cos_id'))) {
            return [false, ['msg'=>'没有店铺权限']];
        }

        $salesMaterial['shop'] = $shop;
        $salesMaterialObj  = app::get('dealer')->model('sales_material');
        $salesMaterialInfo = $salesMaterialObj->db_dump(['sales_material_bn'=>$salesMaterial['*:销售物料编码'], 'shop_id'=>$shop['shop_id']]);
        if($salesMaterialInfo) {
            return $this->_update($salesMaterialInfo, $salesMaterial, $cosList);
        }
        return $this->_insert($salesMaterial, $cosList);
    }

    private function _update($salesMaterialInfo, $salesMaterial, $cosList = []) {
        $salesMaterialObj       = app::get('dealer')->model('sales_material');
        $salesBasicMaterialObj  = app::get('dealer')->model('sales_basic_material');
        $basicMaterialObj       = app::get('material')->model('basic_material');
        $basicMaterialExtObj    = app::get('material')->model('basic_material_ext');

        if ($salesMaterialInfo['sales_material_type'] != $salesMaterial['sales_material_type']) {
            return [false, ['msg'=>$v['*:销售物料名称'].'物料类型不允许更新']];
        }

        $cosIds  = [];
        $bbuCosList = kernel::single('organization_cos')->getBbuFromCosId('', $cosList);
        if ($bbuCosList && $bbuCosList[1] != '_ALL_') {
            $cosIds = array_column($bbuCosList[1], 'cos_id');
        }

        $items = [];
        foreach ($salesMaterial['items'] as $v) {
            if($v['*:基础物料数量'] < 1) {
                return [false, ['msg'=>'基础物料数量不能小于1']];
            }
            $bm = $basicMaterialObj->db_dump(['material_bn'=>$v['*:基础物料编码']], 'bm_id,material_name');
            if(empty($bm)) {
                return [false, ['msg'=>$v['*:基础物料编码'].'不存在']];
            }
            if ($cosIds && !in_array($bm['cos_id'], $cosIds)) {
                return [false, ['msg'=>'基础商品['.$bm['material_bn'].']更新无权限']];
            }
            $bmExt = $basicMaterialExtObj->db_dump(['bm_id'=>$bm['bm_id']], 'cost');
            $items[] = [
                'bm_id'     => $bm['bm_id'],
                'quantity'  => $v['*:基础物料数量'],
                'cost'      => $bmExt['cost'],
                'amount'    => $v['*:基础物料数量'] * $bmExt['cost'],
                // 'material_name' => $bm['material_name'],
            ];
        }
        if(empty($items)) {
            return [false, ['msg'=>'缺少基础物料']];
        }

        $is_update = true;
        $filter = array('sm_id' => $salesMaterialInfo['sm_id']);
        if ($salesMaterial['*:销售物料名称']) {
            //更新销售物料基本信息
            $updateData = array(
                "sales_material_name" => $salesMaterial['*:销售物料名称'],
            );
            $is_update = $salesMaterialObj->update($updateData, $filter);
        }

        if ($is_update) {

            // 商品数量太多，快照不存储
            $snapshoot = [
                // 'sdb_dealer_sales_material'       => $salesMaterialInfo,
                // 'sdb_dealer_sales_basic_material' => $salesBasicMaterialObj->getList('*', $filter),
            ];

            //删除原有关联基础物料信息  后续会新增的（重做关系）
            $salesBasicMaterialObj->delete($filter); //普通、赠品、促销

            $itemSum = array_sum(array_column($items, 'amount'));
            $options = array (
                'part_total'  => 100,
                'part_field'  => 'rate',
                'porth_field' => $itemSum > 0 ? 'amount' : 'quantity',
            );
            $items = kernel::single('ome_order')->calculate_part_porth($items, $options);
            foreach ($items as $k => $v) {
                $addBindData = array(
                    'sm_id'     => $filter['sm_id'],
                    'bm_id'     => $v['bm_id'],
                    'number'    => $v['quantity'],
                    'rate'      => $v['rate']
                );
                
                $salesBasicMaterialObj->insert($addBindData);
            }

            //保存日志
            $omeLogMdl = app::get('ome')->model('operation_log');
            $log_id    = $omeLogMdl->write_log('dealer_sm_edit@dealer', $filter['sm_id'], '导入更新销售商品');
            if ($log_id && $snapshoot) {
                $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
                $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
                $tmp       = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
                $shootMdl->insert($tmp);
            }
        }
        return [true, ['msg'=>'更新完成']];
    }

    private function _insert($salesMaterial, $cosList) {
        $salesMaterialObj       = app::get('dealer')->model('sales_material');
        $salesBasicMaterialObj  = app::get('dealer')->model('sales_basic_material');
        $basicMaterialObj       = app::get('material')->model('basic_material');
        $basicMaterialExtObj    = app::get('material')->model('basic_material_ext');

        $cosIds  = [];
        $bbuCosList = kernel::single('organization_cos')->getBbuFromCosId('', $cosList);
        if ($bbuCosList && $bbuCosList[1] != '_ALL_') {
            $cosIds = array_column($bbuCosList[1], 'cos_id');
        }

        $items = [];
        foreach ($salesMaterial['items'] as $v) {
            if($v['*:基础物料数量'] < 1) {
                return [false, ['msg'=>'基础物料数量不能小于1']];
            }
            $bm = $basicMaterialObj->db_dump(['material_bn'=>$v['*:基础物料编码']], 'bm_id,material_name');
            if(empty($bm)) {
                return [false, ['msg'=>$v['*:基础物料编码'].'不存在']];
            }
            if ($cosIds && !in_array($bm['cos_id'], $cosIds)) {
                return [false, ['msg'=>'基础商品['.$bm['material_bn'].']新建无权限']];
            }
            $bmExt = $basicMaterialExtObj->db_dump(['bm_id'=>$bm['bm_id']], 'cost');
            $items[] = [
                'bm_id'         => $bm['bm_id'],
                'quantity'      => $v['*:基础物料数量'],
                'cost'          => $bmExt['cost'],
                'amount'        => $v['*:基础物料数量'] * $bmExt['cost'],
                'material_name' => $bm['material_name'],
            ];
        }
        if(empty($items)) {
            return [false, ['msg'=>'缺少基础物料']];
        }

        $opinfo = kernel::single('ome_func')->getDesktopUser();
        //保存物料主表信息
        $addData = array(
            'sales_material_bn'       => $salesMaterial['*:销售物料编码'],
            'sales_material_name'     => $salesMaterial['*:销售物料名称']?:$items[0]['material_name'],
            'sales_material_type'     => $salesMaterial['sales_material_type'],
            'shop_id'                 => $salesMaterial['shop']['shop_id'],
            'cos_id'                  => $salesMaterial['shop']['cos_id'],
            'op_name'                 => $opinfo['op_name'],
            'is_bind'                 => 1,
        );
        $is_save = $salesMaterialObj->db_save($addData);
        if ($is_save) {
            $itemSum = array_sum(array_column($items, 'amount'));
            $options = array (
                'part_total'  => 100,
                'part_field'  => 'rate',
                'porth_field' => $itemSum > 0 ? 'amount' : 'quantity',
            );
            $items = kernel::single('ome_order')->calculate_part_porth($items, $options);
            foreach ($items as $k => $v) {
                $addBindData = array(
                    'sm_id'     => $addData['sm_id'],
                    'bm_id'     => $v['bm_id'],
                    'number'    => $v['quantity'],
                    'rate'      => $v['rate']
                );
                
                $salesBasicMaterialObj->insert($addBindData);
            }

            //logs
            $operationLogObj = app::get('ome')->model('operation_log');
            $operationLogObj->write_log('dealer_sm_add@dealer', $addData['sm_id'], '导入添加销售商品');
        }
        return [true, ['msg'=>'新增成功']];
    }
}
