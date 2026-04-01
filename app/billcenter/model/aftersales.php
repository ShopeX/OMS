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

class billcenter_mdl_aftersales extends dbeav_model
{
    /**
     * 生成VOP售后单单号
     *
     * @return string
     **/
    private function gen_aftersale_bn()
    {
        $prefix = 'GXASO'.date("Ymd");
        
        $sign   = kernel::single('eccommon_guid')->incId('GXASO', $prefix, 6);
        
        return $sign;
    }
    
    /**
     * 生成VOP售后单
     *
     *
     * @param array $data Description
     * @return array
     **/
    public function create_aftersales($data)
    {
        // 数据验证
        if (!$data['bill_bn']) {
            return [false, '业务单号不能为空'];
        }
        if (!$data['bill_type']) {
            return [false, '业务类型不能为空'];
        }
        
        foreach ($data['items'] as $key => $item) {
            if (!$item['material_bn']){
                return [false, '基础物料编码为空'];
            }
            
            if (!$item['bm_id']) {
                return [false, '基础物料ID为空'];
            }
        }
        
        $aftersale = [
            'aftersale_bn' => $this->gen_aftersale_bn(),
            'order_bn' => $data['order_bn'],
            'bill_bn' => $data['bill_bn'],
            'bill_type' => $data['bill_type'],
            'bill_id' => $data['bill_id'],
            'shop_id' => $data['shop_id'],
            'shop_bn' => $data['shop_bn'],
            'shop_name' => $data['shop_name'],
            'aftersale_time' => $data['aftersale_time'],
            'original_bn' => $data['original_bn'],
            'original_id' => $data['original_id'],
            'branch_id' => $data['branch_id'],
            'branch_bn' => $data['branch_bn'],
            'branch_name' => $data['branch_name'],
            'logi_code' => $data['logi_code'],
            'logi_no' => $data['logi_no'],
            'po_bn' => $data['po_bn'] ?? '',
            'total_amount' => $data['total_amount'],
            'settlement_amount' => $data['settlement_amount'],
            'total_sale_price' => $data['total_sale_price'] ?? 0,
            'sku_qty'          => 0,//总数量
            'item_lines'       => 0,//总行数
        ];
        
        
        if (!$this->insert($aftersale)) {
            return [false, $this->db->errorinfo()];
        }
        
        $items = [];
        $sku_qty = 0;
        $item_lines = 0;
        foreach ($data['items'] as $item) {
            
            // 批量插入如果数据有NULL会导致一行缺失，故使用??
            $items[] = [
                'aftersale_id' => $aftersale['id'],
                'material_bn' => $item['material_bn'] ?? '',
                'barcode' => $item['barcode'] ?? '',
                'material_name' => $item['material_name'] ?? '',
                'bm_id' => $item['bm_id'] ?? 0,
                'nums' => $item['nums'] ?? 0,
                'price' => $item['price'] ?? 0,
                'amount' => $item['amount'] ?? 0,
                'settlement_amount' => $item['settlement_amount'] ?? 0,
                'sale_price' => $item['sale_price'] ?? 0,
                'box_no' => $item['box_no'] ?? '',
                'original_item_id' => $item['original_item_id'] ?? 0,
            ];
            $sku_qty += (int)$item['nums'];
            $item_lines ++;
        }
        
        $itemMdl = app::get('billcenter')->model('aftersales_items');
        $sql = ome_func::get_insert_sql($itemMdl, $items);
        
        if (!$this->db->exec($sql)) {
            return [false, $this->db->errorinfo()];
        }
        app::get('billcenter')->model('aftersales')->update(['sku_qty'=>$sku_qty,'item_lines'=>$item_lines],['id'=>$aftersale['id']]);
        return [true];
    }
}
