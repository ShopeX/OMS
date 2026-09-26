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

class console_event_trigger_reship{

    
    /**
     * 
     * 退货单通知创建发起方法
     * @param string $wms_id 仓库类型ID
     * @param array $data 商品同步数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function create($wms_id, &$data, $sync = false){
        
        // $result = kernel::single('middleware_wms_request', $wms_id)->reship_create($data, $sync);
        $result = kernel::single('erpapi_router_request')->set('wms',$wms_id)->reship_create($data);

        if ($result['rsp'] == 'success' && $result['data']['wms_order_code']) {
            $this->update_out_bn($data['reship_bn'],$result['data']['wms_order_code']);
            
        }
        if ($result['rsp'] == 'fail') {
            $this->_sendReshipCreateFailNotify($wms_id, $data, $result);
        }
    }

    /**
     * 推送 WMS 退货单创建失败预警（事件码 reship_create_fail_notify）
     *
     * @param string|int $wms_id WMS channel
     * @param array      $data   reship_create 入参（含 reship_bn、order_bn、branch_id、items 等）
     * @param array      $result erpapi 返回，含 rsp、msg / err_msg
     */
    protected function _sendReshipCreateFailNotify($wms_id, $data, $result)
    {
        try {
            $fail_msg = '';
            if (!empty($result['msg'])) {
                $fail_msg = (string) $result['msg'];
            } elseif (!empty($result['err_msg'])) {
                $fail_msg = (string) $result['err_msg'];
            }
            $fail_rsp = isset($result['rsp']) ? (string) $result['rsp'] : '';

            $reship_bn = isset($data['reship_bn']) ? (string) $data['reship_bn'] : '';
            $order_bn = isset($data['order_bn']) ? (string) $data['order_bn'] : '';
            $branch_bn = isset($data['branch_bn']) ? (string) $data['branch_bn'] : '';
            $branch_id = isset($data['branch_id']) ? (int) $data['branch_id'] : 0;
            if (!$branch_id && !empty($data['items'][0]['branch_id'])) {
                $branch_id = (int) $data['items'][0]['branch_id'];
            }

            $shop_type_label = '';
            if (!empty($data['shop_type']) && class_exists('ome_shop_type')) {
                $shop_type_label = ome_shop_type::shop_name($data['shop_type']);
            } elseif (!empty($data['shop_type'])) {
                $shop_type_label = (string) $data['shop_type'];
            }
            $shop_name = isset($data['shop_code']) ? (string) $data['shop_code'] : (isset($data['shop_bn']) ? (string) $data['shop_bn'] : '');

            $detail_list = '';
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $index => $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $seq = $index + 1;
                    $bn = isset($item['bn']) ? (string) $item['bn'] : '';
                    $pname = isset($item['product_name']) ? (string) $item['product_name'] : (isset($item['name']) ? (string) $item['name'] : '');
                    $num = isset($item['num']) ? (string) $item['num'] : '';
                    $detail_list .= "\r\n<br/>序号：<font color=\"warning\">{$seq}</font>，物料号：<font color=\"warning\">{$bn}</font>，名称：<font color=\"warning\">{$pname}</font>，数量：<font color=\"warning\">{$num}</font>";
                }
            }
            if ($detail_list === '') {
                $detail_list = "\r\n<br/>（无明细）";
            }

            $branch_name = $branch_bn;
            $receiver = array();
            if ($branch_id) {
                $branchRow = app::get('ome')->model('branch')->db_dump(['branch_id' => $branch_id], 'name,email');
                if (!empty($branchRow['name'])) {
                    $branch_name = (string) $branchRow['name'];
                }
                if (!empty($branchRow['email'])) {
                    $receiver = explode(',', $branchRow['email']);
                }
            }

            $wms_name = '';
            if ($wms_id) {
                $wmsRow = app::get('wmsmgr')->model('wms')->db_dump(['channel_id' => $wms_id], 'channel_name');
                if (!empty($wmsRow['channel_name'])) {
                    $wms_name = (string) $wmsRow['channel_name'];
                }
            }

            $notifyParams = array(
                'reship_bn' => $reship_bn,
                'order_bn' => $order_bn,
                'branch_bn' => $branch_bn,
                'branch_name' => $branch_name,
                'branch_id' => (string) $branch_id,
                'wms_id' => (string) $wms_id,
                'wms_name' => $wms_name,
                'shop_type' => $shop_type_label,
                'shop_name' => $shop_name,
                'fail_rsp' => $fail_rsp,
                'fail_msg' => $fail_msg,
                'detail_list' => $detail_list,
            );
            if (!empty($receiver)) {
                $notifyParams['receiver'] = $receiver;
            }

            kernel::single('monitor_event_notify')->addNotify('reship_create_fail_notify', $notifyParams, true);
        } catch (Exception $e) {
            // 不影响主流程
        }
    }

    /**
     * 
     * 采购通知创建发起的响应接收方法
     * @param array $data
     */
    public function create_callback($res){

    }

    /**
     * 
     * 退货单通知更新发起方法
     * @param string $wms_id 仓库类型ID
     * @param array $data 商品同步数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function cancel($wms_id, &$data, $sync = false){
        
        // $result = kernel::single('middleware_wms_request', $wms_id)->reship_cancel($data, $sync);
        $store_id = kernel::single('ome_branch')->isStoreBranch($data['branch_id']);
     
        if($store_id){
            $channel_type = 'store';
            $channel_id = $store_id;
            $result = kernel::single('erpapi_router_request')->set($channel_type,$channel_id)->reship_cancel($data);
        }else{
            $result = kernel::single('erpapi_router_request')->set('wms',$wms_id)->reship_cancel($data);
            if($result['rsp']!='succ'){
                $reship_id = $data['reship_id'];
                $db = kernel::database();
                $db->exec("UPDATE sdb_ome_reship  set sync_status='4' where reship_id=".$reship_id."");
            }
        }
        
        return $result;
    }

    /**
     * 
     * 采购通知更新发起的响应接收方法
     * @param array $data
     */
    public function cancel_callback($res){

    }

    /**
     * 退货单查询
     * @param 
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function search($wms_id,&$data, $sync = false)
    {

        //$result =  kernel::single('middleware_wms_request', $wms_id)->reship_search($data, $sync);
        $result = kernel::single('erpapi_router_request')->set('wms',$wms_id)->reship_search($data);
    }

     
    /**
     * 更新退货单外部编号
     * @param
     * @return  
     * @access  protected
     * @author sunjing@shopex.cn
     */
    protected function update_out_bn($reship_bn,$out_iso_bn)
    {
        $oReship = app::get('ome')->model('reship');
        $data = array(
            'out_iso_bn'=>$out_iso_bn,    
        );
        $oReship->update($data,array('reship_bn'=>$reship_bn));
    }
}