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
 * @DateTime: 2021/1/26 16:11:52
 * @describe: 有效期
 * ============================
 */
class console_useful_life {

    public function inOutUsefulLife($data, $opType = '+') {
        if(empty($data) || !in_array($opType, array('-','+'))) {
            return array(true, '无需操作');
        }
        $usefulModel = app::get('console')->model('useful_life');
        $usefulLogModel = app::get('console')->model('useful_life_log');
        $usefulLog = array();
        foreach ($data as $val) {
            $filter = array(
                'branch_id' => $val['branch_id'],
                'product_id' => $val['product_id'],
                'purchase_code' => $val['purchase_code'],
            );
            if($useful = $usefulModel->db_dump($filter, 'life_id,product_time,expire_time,num')) {
                $rs = $usefulModel->updateNum($useful['life_id'], $val['num'], $opType);
                if(!$rs) {
                    return array(false, '有效期入库数据更新失败');
                }
                if(($useful['product_time'] != $val['product_time']
                    || $useful['expire_time'] != $val['expire_time']) 
                    && $useful['product_time'] && $useful['expire_time']) {
                    $upData = array(
                        'product_time' => $val['product_time'],
                        'expire_time' => $val['expire_time'],
                    );
                    $usefulModel->update($upData, array('life_id'=>$useful['life_id']));
                }
            } else {
                $useful = array(
                    'purchase_code' => $val['purchase_code'],
                    'produce_code' => $val['produce_code'],
                    'product_id' => $val['product_id'],
                    'bn' => $val['bn'],
                    'branch_id' => $val['branch_id'],
                    'create_time' => $val['create_time'],
                    'num' => $opType == '+' ? $val['num'] : -$val['num'],
                    'product_time' => $val['product_time'],
                    'expire_time' => $val['expire_time'],
                );
                if(!$usefulModel->insert($useful)) {
                    return array(false, '有效期入库数据写入失败');
                }
            }
            $usefulLogModel->update(['life_id' => $useful['life_id']], ['life_log_id'=>$val['life_log_id']]);
        }
        return array(true, '处理完成');
    }

    /**
     *  $return_storagelife = array('branch_id' => $sdf['branch_id'], 'bn' => $sdf['material_bn'], 'product_name' => $sdf['product_name'], 'reship_bn' => $sdf['reship_bn'], 'expire_bn' => $sdf['expire_bn'], 'nums' => $sdf['nums']);
     * @param  
     * @return 
     */
    public function returnProduct($history_storagelife){
        $iostockLib = kernel::single('wms_event_trigger_otherinstorage');
        $useLogModel = app::get('console')->model('useful_life_log');

        $useful = [];
        foreach($history_storagelife as $item){
            $expire_bn = $item['expire_bn'];
            $branch_id = $item['branch_id'];
            $storagelife = $iostockLib->getlifedetail($branch_id,$item['product_id'],$expire_bn);
            $tmpUseful = [];
            $tmpUseful['product_id'] = $item['product_id'];
            $tmpUseful['bn'] = $item['bn'];
            $tmpUseful['original_bn'] = $item['reship_bn'];
            $tmpUseful['original_id'] = $item['reship_id'];
            $tmpUseful['bill_type'] = '退货入库';
            $tmpUseful['business_bn'] = $item['reship_bn'];
            $tmpUseful['sourcetb'] = 'reship';
            $tmpUseful['create_time'] = time();
            $tmpUseful['stock_status'] = '0';
            $tmpUseful['num'] = $item['nums'];
            $tmpUseful['normal_defective'] = $item['nums'];
            $tmpUseful['product_time'] = $storagelife['production_date'];
            $tmpUseful['expire_time'] = $storagelife['expiring_date'];
            $tmpUseful['purchase_code'] = $expire_bn;
         
            $useful[] = $tmpUseful;
           
        }
        if($useful){

            $useLogModel->db->exec(ome_func::get_insert_sql($useLogModel, $useful));
        }
    }
}