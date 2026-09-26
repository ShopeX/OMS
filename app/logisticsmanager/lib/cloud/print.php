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

class logisticsmanager_cloud_print extends logisticsmanager_cloud_abstract
{
    /**
     * 打印电子面单
     * @param $params
     * @return array
     */
    public function doPrint($params)
    {
       
        $orderMdl = app::get('ome')->model('orders');
        $shopMdl = app::get('ome')->model('shop');
        $basicMdl = app::get('material')->model('basic_material');
        $mdl_operation_log = app::get('ome')->model('operation_log');

        
        # 发货人信息
        $sendInfo = $this->_format_sendinfo($waybillInfo, $params['branch_id']);
        if (!empty($sendInfo)) {
            $printInfo['senderName'] = $sendInfo['senderName'];
            # 发货人手机号加密
            $printInfo['senderPhone'] = $this->entry_mobile($sendInfo['senderPhone']);
            $printInfo['senderAddress'] = $sendInfo['senderAddress'];
            $printInfo['storeName'] = $sendInfo['storeName'] ?? '';
        }
        # 收件人信息
        $delivery = $params['delivery'];
        if ($delivery['consignee']['name'] && $encrytPos = strpos($delivery['consignee']['name'], '>>')) {
            $printInfo['receiverName'] = substr($delivery['consignee']['name'], 0, $encrytPos);
        } else {
            $printInfo['receiverName'] = $delivery['consignee']['name'];
        }
        if ($delivery['consignee']['mobile'] && $encrytPos = strpos($delivery['consignee']['mobile'], '>>')) {
            $printInfo['receiverPhone'] = substr($delivery['consignee']['mobile'], 0, $encrytPos);
        } else {
            $printInfo['receiverPhone'] = $delivery['consignee']['mobile'];
        }
        $consignee = [];
        $consignee[] = $delivery['consignee']['province'];
        $consignee[] = $delivery['consignee']['city'];
        $consignee[] = $delivery['consignee']['district'];
        if (!empty($delivery['consignee']['district'])) {
            $consignee[] = $delivery['consignee']['town'];
        }
        if ($delivery['consignee']['addr'] && $encrytPos = strpos($delivery['consignee']['addr'], '>>')) {
            $consignee[] = substr($delivery['consignee']['addr'], 0, $encrytPos);
        } else {
            $consignee[] = $delivery['consignee']['addr'];
        }
        $printInfo['receiverAddress'] = implode('', $consignee);

        # 订单信息
        $orderIds = array_column($params['delivery']['delivery_order'], 'order_id');
        $orderList = $orderMdl->getList('order_id,order_bn,shop_id,mark_text,custom_mark', array('order_id' => $orderIds));
        # 买家备注、卖家留言
        $mark_text = $custom_mark = [];
        foreach ($orderList as $order) {
            if ($order['mark_text']) {
                $mark = unserialize($order['mark_text']);
                if (is_array($mark) || !empty($mark)) {
                    $mark = array_pop($mark);
                    $mark_text[] = $mark['op_content'];
                }
            }

            if ($order['custom_mark']) {
                $custommark = unserialize($order['custom_mark']);
                if (is_array($custommark) || !empty($custommark)) {
                    $mark = array_pop($custommark);
                    $custom_mark[] = $mark['op_content'];
                }
            }
        }

        # 店铺信息
        $shopInfo = $shopMdl->dump(array('shop_id' => $orderList[0]['shop_id']), 'shop_id,name');

        $sdf = [
            'logi_no' => $waybillInfo['waybill_number'],
            'order_bn' => implode(',', array_column($orderList, 'order_bn')),
            'storeName' => empty($printInfo['storeName']) ? $shopInfo['name'] : $printInfo['storeName'],
            'buyer_memo' => empty($custom_mark) ? '' : implode(';', $custom_mark),
            'seller_memo' => empty($mark_text) ? '' : implode(';', $mark_text),
            'waybill' => $printInfo,
            'order_items' => [],
        ];
        foreach ($delivery['delivery_items'] as $item) {
            $orderItem = [
                'name' => $item['product_name'],
                'nums' => $item['number']
            ];
            # 获取基础物料货号
            $basicInfo = $basicMdl->dump(array('bm_id' => $item['product_id']), 'bm_id,material_bn');
            if (!empty($basicInfo['material_bn'])) {
                $orderItem['bn'] = $basicInfo['material_bn'];
            }
            $sdf['order_items'][] = $orderItem;
        }


        # 打印电子面单
      
        $printResult = $this->formatcontent($sdf);
        if ($printResult['rsp'] == 'fail') {
            return $this->error($printResult['msg']);
        }

        # 打印电子面单
        $printParams = [
            'order_bn' => $sdf['order_bn'],
            'outer_delivery_bn' => $params['wap_delivery_bn'],
            'outer_delivery_id' => $params['wap_delivery_id'],
            'branch_id' => $params['delivery']['branch_id'],
            'content' => $printResult['data']['img_url'],
        ];
        //$result = kernel::single('erpapi_router_request')->set('yilianyun', true)->logistics_printImage($printParams);
        if ($result['rsp'] == 'fail') {
            # 记录日志
            $mdl_operation_log->write_log('delivery_expre@ome', $params['delivery']['delivery_id'], $result['msg']);
            return $this->error($result['msg']);
        }

        # 记录返回数据
        $data = [
            'order_id' => $orderIds,
            'waybill_number' => $sdf['logi_no']
        ];
        return $this->success('打印提交成功', $data);
    }

    /**
     * 获取发货人信息
     * @param $waybill
     * @param $branch_id
     * @return array
     */
    private function _format_sendinfo($waybill, $branch_id)
    {
     
        $result = [];
        # 获取门店发件人信息
        $sendInfo = $this->getStoreSenderInfoByBranchId($branch_id);
        if (!empty($sendInfo['consignee'])) {
            $result['senderName'] = $sendInfo['consignee']['name'];
            $result['senderPhone'] = $sendInfo['consignee']['mobile'];
            # 详细地址
            $consignee = [];
            $consignee[] = $sendInfo['consignee']['province'];
            $consignee[] = $sendInfo['consignee']['city'];
            $consignee[] = $sendInfo['consignee']['district'];
            if (!empty($sendInfo['consignee']['town'])) {
                $consignee[] = $sendInfo['consignee']['town'];
            }
            $consignee[] = $sendInfo['consignee']['addr'];
            $result['senderAddress'] = implode('', $consignee);
        }

        return $result;
    }

    /**
     * formatcontent
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function formatcontent($sdf)
    {
        
       

        
    }

    /**
     * 根据仓库ID获取门店的发件人信息
     * @param $branch_id
     * @return void
     */
    public function getStoreSenderInfoByBranchId($branch_id)
    {
        if (empty($branch_id)) {
            return false;
        }

        $fields = 'a.branch_id,a.branch_bn,a.store_id,b.store_bn,b.name,b.org_id,b.area,b.addr,b.contacter,b.mobile,b.tel,b.zip,b.custom_login_mobile,b.custom_login_tel';
        # 查询sql
        $sql = "SELECT {$fields} FROM `sdb_ome_branch` AS a LEFT JOIN sdb_o2o_store AS b ON a.store_id = b.store_id"
            . " WHERE a.branch_id = {$branch_id}";
        $storeList = kernel::database()->select($sql);
        if (empty($storeList)) {
            return false;
        }

        $result = [
            'branch_id' => $storeList[0]['branch_id'],
            'branch_bn' => $storeList[0]['branch_bn'],
            'store_id' => $storeList[0]['store_id'],
            'store_bn' => $storeList[0]['store_bn'],
            'store_name' => $storeList[0]['name'],
            'org_id' => $storeList[0]['org_id'],
            'consignee' => [
                'name' => $storeList[0]['contacter'],
                'area' => $storeList[0]['area'],
                'addr' => $storeList[0]['addr'],
                'telephone' => $storeList[0]['tel'],
                'mobile' => $storeList[0]['mobile'],
                'zip' => $storeList[0]['zip'],
                'custom_login_mobile' => $storeList[0]['custom_login_mobile'],
                'custom_login_tel' => $storeList[0]['custom_login_tel'],
            ]
        ];

        list(, $to_area) = explode(':', $storeList[0]['area']);
        $areaData = explode('/', $to_area);

        # 省市区
        $result['consignee']['province'] = $areaData[0];
        $result['consignee']['city'] = $areaData[1];
        $result['consignee']['district'] = $areaData[2];
        # 4级地址
        if (!empty($areaData[3])) {
            $result['consignee']['town'] = $areaData[3];
        }
        return $result;
    }


    /**
     * entry_mobile
     * @param mixed $mobile mobile
     * @return mixed 返回值
     */
    public function entry_mobile($mobile)
    {
        if ($this->contains($mobile, '*')) {
            return $mobile;
        }

        $start = substr($mobile, 0, 3);
        $end = substr($mobile, -4);
        return $start . '****' . $end;
    }

    /**
     * contains
     * @param mixed $haystack haystack
     * @param mixed $needles needles
     * @return mixed 返回值
     */
    public function contains($haystack, $needles)
    {
        foreach ((array)$needles as $needle) {
            if ($needle != '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}