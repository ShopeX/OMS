<?php
/**
 * 平台 token 失效/恢复通知
 *
 * @author wangbiao@shopex.cn
 * @version 2026.09.07
 */
class erpapi_system_response_token extends erpapi_system_response_abstract
{
    /**
     * 接收token消息通知
     * @todo：矩阵method=system.access_token.notify
     *
     * @return void
     * @author 
     */
    public function notify($params)
    {
        $this->__apilog['title'] = 'token消息通知';
        
        // OMS系统节点
        $system_node_id = $params['to_node_id'];
        
        // original_bn
        $this->__apilog['original_bn'] = $system_node_id;
        
        if (!$params['data']) {
            $this->__apilog['result']['msg'] = 'token消息内容为空，请检查!';
            return false;
        }
        
        // content
        $content = is_array($params['data']) ? $params['data'] : json_decode($params['data'], true);
        if (!is_array($content)) {
            $this->__apilog['result']['msg'] = 'token消息内容错误，请检查!';
            return false;
        }
        
        // OMS系统节点
        $content['node_id'] = $system_node_id;
        
        // 店铺节点
        $shop_node_id = ($params['node_id'] ? $params['node_id'] : $params['from_node_id']);
        
        // sdf
        $sdf = array(
            'node_id'    => $shop_node_id,
            'date'       => $params['date'],
            'content'    => $content,
        );
        
        return $sdf;
    }
}
