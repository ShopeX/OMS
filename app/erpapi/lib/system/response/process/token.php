<?php
/**
 * 平台 token 失效/恢复通知
 *
 * @author wangbiao@shopex.cn
 * @version 2026.09.07
 */
class erpapi_system_response_process_token
{
    /**
     * token消息通知处理结果
     * @todo：矩阵method=system.access_token.notify
     *
     * @return void
     * @author
     */
    public function notify($sdf)
    {
        $error_msg = '';
        
        // check node_id
        if (!$sdf['node_id']) {
            return array('rsp' => 'fail', 'msg' => '店铺节点不能为空，请检查!');
        }
        
        $shopMdl = app::get('ome')->model('shop');
        $notifyLib = kernel::single('ome_system_rpcnotify');
        
        // 店铺信息
        $shopInfo = $shopMdl->dump(array('node_id'=>$sdf['node_id']), '*');
        if (!$shopInfo) {
            return array('rsp' => 'fail', 'msg' => '店铺未绑定，请检查!');
        }
        
        // 模板类型
        if($sdf['content']['access_token_available'] == '1'){
            // token恢复通知
            $event_type = 'system_shop_token_recover';
            $info = '【'. $shopInfo['name'] .'】token恢复通知（过期时间：'. $sdf['content']['access_token_expire_in'] .'）';
        }else{
            // token失效通知
            $event_type = 'system_shop_token_invalid';
            $info = '【'. $shopInfo['name'] .'】token失效通知!';
        }
        
        // info
        $sdf['content']['info'] = $info;
        
        // dead_line
        if(!isset($sdf['content']['dead_line'])){
            $sdf['content']['dead_line'] = 0;
        }
        
        // insert / update
        $saveSdf = $sdf;
        $saveSdf['event_type'] = $event_type;
        $saveSdf['shop_type'] = ($shopInfo['node_type'] ? $shopInfo['node_type'] : $shopInfo['shop_type']);
        $saveSdf['shop_bn'] = $shopInfo['shop_bn'];
        $saveSdf['org_id'] = intval($shopInfo['org_id']);
        
        // 默认为插入
        $saveSdf['save_action'] = 'insert';
        
        // 店铺已过期的 token 失效通知：已有同店铺记录则更新，避免重复插入
        if ($event_type === 'system_shop_token_invalid' && (string) $shopInfo['is_expired'] === 'true') {
            $exist = app::get('base')->model('rpcnotify')->getList(
                'id',
                array('shop_bn' => $shopInfo['shop_bn'], 'event_type' => 'system_shop_token_invalid'),
                0, 1, 'id DESC'
            );
            
            // check exist
            if ($exist) {
                // 只做更新
                $saveSdf['save_action'] = 'update';
                
                // rpcnotify_id
                $saveSdf['rpcnotify_id'] = intval($exist[0]['id']);
            }
        }
        
        // insert
        $insert_id = $notifyLib->saveRpcNotify($saveSdf);
        
        // 消息通知提醒（仅新建时推送，更新已有记录不再二次推送）
        if ($insert_id && $saveSdf['save_action'] === 'insert') {
            $saveSdf['rpcnotify_id'] = $insert_id;
            $notifyLib->addShopEventNotify($saveSdf, $error_msg);
        }
        
        // msg
        if($insert_id){
            $result_msg = $info .'，接收成功!';
        }else{
            $result_msg = $info .'，保存token信息失败，请检查!';
        }
        
        return array('rsp'=>'succ', 'msg'=>$result_msg);
    }
}
