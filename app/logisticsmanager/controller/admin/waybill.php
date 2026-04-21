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

class logisticsmanager_ctl_admin_waybill extends desktop_controller{
    /**
     * index
     * @return mixed 返回值
     */
    public function index() {
        $params = array(
            'title'=>'快递面单管理',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
        );
        $this->finder('logisticsmanager_mdl_waybill', $params);


    }



    function findwaybill(){
        $billtype = $_GET['billtype'];
        $pre_title = '可用';
        $status = '0';
        switch ($billtype) {
            case 'active':
                $pre_title = '可用';
                $status = '0';
                break;
             case 'recycle':
                 $pre_title = '作废';
                 $status = '2';
                break;
             case 'used':
                 $pre_title = '已用';
                 $status = '1';
                break;
        }
        $params = array(
                        'title'=>$pre_title.'物流单号列表',
                        'use_buildin_new_dialog' => false,
                        'use_buildin_set_tag'=>false,
                        'use_buildin_recycle'=>false,
                        'use_buildin_export'=>false,
                        'use_buildin_filter'=>true,
                    );

        $params['base_filter']['status'] = $status;            
     	$params['base_filter']['channel_id'] = $_GET['channel_id'];
        
        $this->finder('logisticsmanager_mdl_waybill', $params);
    }

    /**
     * 获取EncryptPrintData
     * @return mixed 返回结果
     */
    public function getEncryptPrintData() {
        $logiNo        = trim($_POST['logi_no']);
        $batchLogiNo   = trim($_POST['batch_logi_no']);
        $deliveryId    = intval($_POST['delivery_id']);
        $channelId     = trim($_POST['channel_id']);
        $requestData   = [
            'logi_no'       => $logiNo,
            'batch_logi_no' => $batchLogiNo,
            'delivery_id'   => $deliveryId,
        ];

        $channelType = '';
        if ($channelId) {
            $channel = app::get('logisticsmanager')->model('channel')->dump(['channel_id' => $channelId], 'channel_type');
            $channelType = isset($channel['channel_type']) ? $channel['channel_type'] : '';
        }
        if ($channelType === 'aikucun') {
            $requestData['print_name']   = isset($_POST['print_name']) ? trim($_POST['print_name']) : '';
            $requestData['mode']         = isset($_POST['mode']) ? trim($_POST['mode']) : '';
            $requestData['prepare_data'] = isset($_POST['prepare_data']) ? $_POST['prepare_data'] : '';
        } elseif (isset($_POST['custom_data'])) {
            $requestData['custom_data'] = $_POST['custom_data'];
        }

        $rs = kernel::single('erpapi_router_request')
            ->set('logistics', $channelId)
            ->electron_getEncryptPrintData($requestData);
        echo json_encode($rs);
    }
}