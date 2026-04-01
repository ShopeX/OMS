<?php
/**
 * 订单备注处理类
 * 
 * @author system
 * @version 1.0
 */
class ome_order_marktext
{
    /**
     * 添加订单备注
     * 
     * @param int $order_id 订单ID
     * @param string $content 备注内容
     * @param string $op_name 操作人名称，默认为系统
     * @return array [true,'成功'] 或 [false,'失败原因']
     */
    public static function add($order_id, $content, $op_name = '系统')
    {
        if (empty($order_id) || empty($content)) {
            return array(false, '订单ID或备注内容不能为空');
        }
        
        $oOrders = app::get('ome')->model('orders');
        
        // 取出原备注信息
        $oldmemo = $oOrders->dump(array('order_id' => $order_id), 'mark_text');
        if (!$oldmemo) {
            return array(false, '订单不存在');
        }
        
        $oldmemo = unserialize($oldmemo['mark_text']);
        
        $memo = array();
        if ($oldmemo) {
            foreach ($oldmemo as $k => $v) {
                $memo[] = $v;
            }
        }
        
        // 添加新备注
        $newmemo = htmlspecialchars($content);
        $newmemo = array(
            'op_name' => $op_name, 
            'op_time' => date('Y-m-d H:i:s', time()), 
            'op_content' => $newmemo
        );
        $memo[] = $newmemo;
        
        // 更新订单备注
        $updateData = array('mark_text' => serialize($memo));
        $result = $oOrders->update($updateData, array('order_id' => $order_id));
        
        if ($result) {
            // 写操作日志
            $oOperation_log = app::get('ome')->model('operation_log');
            $oOperation_log->write_log('order_modify@ome', $order_id, '订单备注修改');
            
            // 订单留言 API
            foreach (kernel::servicelist('service.order') as $object => $instance) {
                if (method_exists($instance, 'update_memo')) {
                    $instance->update_memo($order_id, $newmemo);
                }
            }
            
            return array(true, '订单备注添加成功');
        }
        
        return array(false, '订单备注更新失败');
    }
}
?> 