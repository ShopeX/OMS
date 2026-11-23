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
 * @DateTime: 2021/9/22 11:27:32
 * @describe: 开普勒一件代发业务发货单处理
 * ============================
 */
class console_delivery_yjdf {

    #获取渠道ID
    public function getWMSChannelId($branchId, $items) {
        $wms_type = kernel::single('ome_branch')->getNodetypBybranchId($branchId);
        if($wms_type != 'yjdf') {
            return '';
        }
        foreach ($items as $key => $value) {
            $product_bn = $value['bn'];
            break;
        }
        if(empty($product_bn)) {
            return '';
        }
        //获取上架状态：只拿最新2条数据
        $sql = "SELECT * FROM sdb_material_basic_material_channel WHERE material_bn='". $product_bn ."' ORDER BY approve_status,id DESC LIMIT 0,2";
        $channelList = kernel::database()->select($sql);
        if(empty($channelList)){
            return '';
        }
        
        foreach ($channelList as $key => $val)
        {
            $channel_id = $val['channel_id'];
            
            if($channel_id && $val['approve_status']=='1'){
                return $channel_id;
            }
        }
        return '';
    }
}