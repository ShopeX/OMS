<?php
/**
 * 补寄申请请求相关接口
 */
class erpapi_shop_request_reshipping extends erpapi_shop_request_abstract
{
    /**
     * 查询补寄详情
     * @param array $params 参数（dispute_id）
     * @return array
     */
    public function get($params)
    {
        $title = '店铺('.$this->__channelObj->channel['name'].')查询补寄详情,(补寄单号:'.$params['dispute_id'].')';
        
        $apiParams = array(
            'dispute_id' => $params['dispute_id'],
            //'fields' => 'dispute_id,status,modified,created,biz_order_id,time_out,attributes',
        );
        
        $result = $this->__caller->call(SHOP_RESHIPPING_GET, $apiParams, array(), $title, 10, $params['dispute_id']);
        
        $rs = array();
        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }
        $rs['rsp'] = $result['rsp'];
        $rs['data'] = $result['data'] ? (is_string($result['data']) ? json_decode($result['data'], true) : $result['data']) : array();
        
        return $rs;
    }
    
    /**
     * 查询补寄留言列表
     * @param array $params 参数（dispute_id, operator_roles, page_size, page_no）
     * @return array
     */
    public function messages_get($params)
    {
        $title = '店铺('.$this->__channelObj->channel['name'].')查询补寄留言列表,(补寄单号:'.$params['dispute_id'].')';
        
        $apiParams = array(
            'dispute_id' => $params['dispute_id'],
            'page_size' => isset($params['page_size']) ? $params['page_size'] : 20,
            'page_no' => isset($params['page_no']) ? $params['page_no'] : 1,
            //'fields' => 'message_id,content,operator_roles,created,message_pics',
        );
        $apiParams['operator_roles'] = '1,2,3,4,5,6';
        
        $result = $this->__caller->call(SHOP_RESHIPPING_MESSAGES_GET, $apiParams, array(), $title, 10, $params['dispute_id']);
        
        $rs = array();
        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }
        $rs['rsp'] = $result['rsp'];
        
        // 格式化返回数据
        $data = $result['data'] ? (is_string($result['data']) ? json_decode($result['data'], true) : $result['data']) : array();
        $rs['data'] = $this->_formatMessagesData($data);
        
        return $rs;
    }
    
    /**
     * 格式化留言列表数据
     * @param mixed $data 原始数据
     * @return array 格式化后的消息列表
     */
    private function _formatMessagesData($data)
    {
        if (!is_array($data)) {
            return array();
        }
        
        // 检查新格式：$data['result']['results']['refund_message']
        if (isset($data['result']['results']['refund_message']) && is_array($data['result']['results']['refund_message'])) {
            $messages = $data['result']['results']['refund_message'];
            $formattedMessages = array();
            
            foreach ($messages as $msg) {
                if (!is_array($msg)) {
                    continue;
                }
                
                $formattedMsg = $this->_formatSingleMessage($msg);
                if ($formattedMsg) {
                    $formattedMessages[] = $formattedMsg;
                }
            }
            
            return $formattedMessages;
        }
        
        // 如果数据格式不正确，返回空数组
        return array();
    }
    
    /**
     * 格式化单条留言数据
     * @param array $msg 原始消息数据
     * @return array|null 格式化后的消息数据
     */
    private function _formatSingleMessage($msg)
    {
        if (!is_array($msg)) {
            return null;
        }
        
        $formattedMsg = array(
            'message_id' => isset($msg['message_id']) ? $msg['message_id'] : (isset($msg['id']) ? $msg['id'] : ''),
            'content' => isset($msg['content']) ? $msg['content'] : (isset($msg['message']) ? $msg['message'] : ''),
            'operator_roles' => isset($msg['operator_roles']) ? $msg['operator_roles'] : (isset($msg['owner_role']) ? $msg['owner_role'] : (isset($msg['role']) ? $msg['role'] : '')),
            'created' => isset($msg['created']) ? $msg['created'] : (isset($msg['create_time']) ? $msg['create_time'] : (isset($msg['created_time']) ? $msg['created_time'] : '')),
            'message_pics' => $this->_formatMessagePics($msg),
        );
        
        // 保留其他可能有用的字段
        if (isset($msg['refund_id'])) {
            $formattedMsg['refund_id'] = $msg['refund_id'];
        }
        if (isset($msg['owner_id'])) {
            $formattedMsg['owner_id'] = $msg['owner_id'];
        }
        if (isset($msg['owner_nick'])) {
            $formattedMsg['owner_nick'] = $msg['owner_nick'];
        }
        if (isset($msg['message_type'])) {
            $formattedMsg['message_type'] = $msg['message_type'];
        }
        if (isset($msg['open_uid'])) {
            $formattedMsg['open_uid'] = $msg['open_uid'];
        }
        
        return $formattedMsg;
    }
    
    /**
     * 格式化留言图片数组
     * @param array $msg 消息数据
     * @return array 图片数组
     */
    private function _formatMessagePics($msg)
    {
        // 处理图片数组 - 支持多种字段名称和格式
        if (isset($msg['message_pics']) && is_array($msg['message_pics'])) {
            return $msg['message_pics'];
        } elseif (isset($msg['pic_urls']) && !empty($msg['pic_urls'])) {
            // pic_urls 可能是字符串（逗号分隔）或数组
            if (is_array($msg['pic_urls'])) {
                return $msg['pic_urls'];
            } elseif (is_string($msg['pic_urls'])) {
                // 尝试按逗号分割
                $pics = explode(',', $msg['pic_urls']);
                return array_filter(array_map('trim', $pics));
            }
        } elseif (isset($msg['pics']) && is_array($msg['pics'])) {
            return $msg['pics'];
        } elseif (isset($msg['pictures']) && is_array($msg['pictures'])) {
            return $msg['pictures'];
        } elseif (isset($msg['pic']) && !empty($msg['pic'])) {
            return is_array($msg['pic']) ? $msg['pic'] : array($msg['pic']);
        }
        
        return array();
    }
    
    /**
     * 创建补寄留言
     * @param array $params 参数（dispute_id, content, message_pics）
     * @return array
     */
    public function message_add($params)
    {
        $title = '店铺('.$this->__channelObj->channel['name'].')创建补寄留言,(补寄单号:'.$params['dispute_id'].')';
        
        $apiParams = array(
            'dispute_id' => $params['dispute_id'],
            'content' => $params['content'],
            //'fields' => 'message_id,content,created',
        );
        if (isset($params['message_pics']) && !empty($params['message_pics'])) {
            $apiParams['message_pics'] = $params['message_pics']; // 单个 base64 字符串，不需要 json_encode
        }
        
        $result = $this->__caller->call(SHOP_RESHIPPING_MESSAGE_ADD, $apiParams, array(), $title, 10, $params['dispute_id']);
        
        $rs = array();
        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }
        $rs['rsp'] = $result['rsp'];
        $rs['data'] = $result['data'] ? (is_string($result['data']) ? json_decode($result['data'], true) : $result['data']) : array();
        
        return $rs;
    }
    
    /**
     * 同意补寄申请
     * @param array $params 参数（dispute_id）
     * @return array
     */
    public function agree($params)
    {
        $title = '店铺('.$this->__channelObj->channel['name'].')同意补寄申请,(补寄单号:'.$params['dispute_id'].')';
        
        $apiParams = array(
            'dispute_id' => $params['dispute_id'],
            //'fields' => 'dispute_id,status,modified,created,biz_order_id,time_out,attributes',
        );
        
        $result = $this->__caller->call(SHOP_RESHIPPING_AGREE, $apiParams, array(), $title, 10, $params['dispute_id']);
        
        $rs = array();
        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }
        $rs['rsp'] = $result['rsp'];
        $rs['data'] = $result['data'] ? (is_string($result['data']) ? json_decode($result['data'], true) : $result['data']) : array();
        
        return $rs;
    }
    
    /**
     * 拒绝补寄申请
     * @param array $params 参数（dispute_id, seller_refuse_reason_id, leave_message_pics, leave_message）
     * @return array
     */
    public function refuse($params)
    {
        $title = '店铺('.$this->__channelObj->channel['name'].')拒绝补寄申请,(补寄单号:'.$params['dispute_id'].')';
        
        $apiParams = array(
            'dispute_id' => $params['dispute_id'],
            'seller_refuse_reason_id' => $params['seller_refuse_reason_id'],
            'leave_message' => $params['leave_message'],
            //'fields' => 'dispute_id,status,modified,biz_order_id',
        );
        if (isset($params['leave_message_pics']) && !empty($params['leave_message_pics'])) {
            $apiParams['leave_message_pics'] = $params['leave_message_pics']; // 单个 base64 字符串，不需要 json_encode
        }
        
        $result = $this->__caller->call(SHOP_RESHIPPING_REFUSE, $apiParams, array(), $title, 10, $params['dispute_id']);
        
        $rs = array();
        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }
        $rs['rsp'] = $result['rsp'];
        $rs['data'] = $result['data'] ? (is_string($result['data']) ? json_decode($result['data'], true) : $result['data']) : array();
        
        return $rs;
    }
    
    /**
     * 补寄发货
     * @param array $params 参数（dispute_id, logistics_no, logistics_type, company_name, company_code）
     * @return array
     */
    public function consigngoods($params)
    {
        $title = '店铺('.$this->__channelObj->channel['name'].')补寄发货,(补寄单号:'.$params['dispute_id'].')';
        
        $apiParams = array(
            'dispute_id' => $params['dispute_id'],
            'logistics_no' => $params['logistics_no'],
            'logistics_type' => isset($params['logistics_type']) ? $params['logistics_type'] : '200', // 200表示快递
            //'fields' => 'dispute_id,status,modified',
        );
        if (isset($params['company_name'])) {
            $apiParams['company_name'] = $params['company_name'];
        }
        if (isset($params['company_code'])) {
            $apiParams['company_code'] = $params['company_code'];
        }
        
        $result = $this->__caller->call(SHOP_RESHIPPING_CONSIGNGOODS, $apiParams, array(), $title, 10, $params['dispute_id']);
        
        $rs = array();
        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }
        $rs['rsp'] = $result['rsp'];
        $rs['data'] = $result['data'] ? (is_string($result['data']) ? json_decode($result['data'], true) : $result['data']) : array();
        
        // 记录发货日志
        $log_id = uniqid($_SERVER['HOSTNAME']);
        $status = ($rs['rsp']=='succ') ? 'succ' : 'fail';
        $log = array(
            'shopId'           => $this->__channelObj->channel['shop_id'],
            'ownerId'          => '16777215',
            'orderBn'          => isset($params['order_bn']) ? $params['order_bn'] : '',
            'deliveryCode'     => $params['logistics_no'],
            'deliveryCropCode' => isset($params['company_code']) ? $params['company_code'] : '',
            'deliveryCropName' => isset($params['company_name']) ? $params['company_name'] : '',
            'receiveTime'      => time(),
            'status'           => $status,
            'updateTime'       => time(),
            'message'          => $rs['msg'] ? $rs['msg'] : '成功',
            'log_id'           => $log_id,
        );

        $shipmentLogModel = app::get('ome')->model('shipment_log');
        $shipmentLogModel->insert($log);
        
        // 更新订单同步状态
        if (isset($params['order_id']) && $params['order_id']) {
            $orderModel = app::get('ome')->model('orders');
            $updateOrderData = array(
                'sync'    => $status,
                'up_time' => time(),
            );
            $orderModel->update($updateOrderData, array('order_id' => $params['order_id'], 'sync|noequal' => 'succ'));
        }
        return $rs;
    }
    
    /**
     * 查询拒绝原因列表
     * @param array $params 参数（dispute_id, dispute_type）
     * @return array
     */
    public function refusereason_get($params)
    {
        $title = '店铺('.$this->__channelObj->channel['name'].')查询拒绝原因列表,(补寄单号:'.$params['dispute_id'].')';
        
        $apiParams = array(
            'dispute_id' => $params['dispute_id'],
            //'fields' => 'reason_id,reason_text',
        );
        if (isset($params['dispute_type'])) {
            $apiParams['dispute_type'] = $params['dispute_type'];
        }
        
        $result = $this->__caller->call(SHOP_RESHIPPING_REFUSEREASON_GET, $apiParams, array(), $title, 10, $params['dispute_id']);
        
        $rs = array();
        if(isset($result['msg']) && $result['msg']){
            $rs['msg'] = $result['msg'];
        }elseif(isset($result['err_msg']) && $result['err_msg']){
            $rs['msg'] = $result['err_msg'];
        }
        $rs['rsp'] = $result['rsp'];
        $data = $result['data'] ? (is_string($result['data']) ? json_decode($result['data'], true) : $result['data']) : array();
        
        // 检查新格式：$data['result']['results']['reason']
        if (is_array($data) && isset($data['result']['results']['reason']) && is_array($data['result']['results']['reason'])) {
            $rs['data'] = $data['result']['results']['reason'];
        } else {
            // 如果数据格式不正确，返回空数组
            $rs['data'] = array();
        }
        
        return $rs;
    }
}

