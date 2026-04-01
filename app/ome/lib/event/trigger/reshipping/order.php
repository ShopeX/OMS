<?php
/**
 * 补寄申请单Event Trigger
 * 处理补寄申请单的单拉业务逻辑
 */
class ome_event_trigger_reshipping_order
{
    /**
     * 单拉补寄申请单
     * 从平台拉取补寄详情并生成补寄申请单
     * 
     * @param int $shop_id 店铺ID
     * @param string $dispute_id 补寄单号
     * @return array
     */
    public function pullReshipping($shop_id, $dispute_id)
    {
        // 参数验证
        if (empty($shop_id)) {
            return array('rsp' => 'fail', 'msg' => '店铺ID不能为空');
        }
        
        if (empty($dispute_id)) {
            return array('rsp' => 'fail', 'msg' => '补寄单号不能为空');
        }
        
        // 检查补寄申请单是否已存在
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $existingReshipping = $reshippingModel->dump(array(
            'reshipping_bn' => $dispute_id,
            'shop_id' => $shop_id,
        ));
        
        if ($existingReshipping) {
            return array('rsp' => 'fail', 'msg' => '补寄申请单已存在，补寄单号：' . $dispute_id);
        }
        
        // 调用接口获取补寄详情
        $apiResult = kernel::single('erpapi_router_request')->set('shop', $shop_id)->reshipping_get(array(
            'dispute_id' => $dispute_id,
        ));
        
        if ($apiResult['rsp'] != 'succ') {
            $errorMsg = isset($apiResult['msg']) ? $apiResult['msg'] : '获取补寄详情失败';
            return array('rsp' => 'fail', 'msg' => $errorMsg);
        }
        
        if (empty($apiResult['data'])) {
            return array('rsp' => 'fail', 'msg' => '未获取到补寄详情数据');
        }
        
        // 直接使用接口返回的数据，添加必要的字段
        $params = $apiResult['data'];
        $params['dispute_id'] = $dispute_id;
        
        // 通过 router_response 调用 erpapi_shop_response_reshipping->add
        // 它会自动调用 response->add 进行数据转换，然后调用 process->add 进行业务处理
        $routerResponse = kernel::single('erpapi_router_response');
        $routerResponse->set_channel_id($shop_id);
        $routerResponse->set_api_name('ome.reshipping.add');
        
        $result = $routerResponse->dispatch($params);
        
        return $result;
    }
}

