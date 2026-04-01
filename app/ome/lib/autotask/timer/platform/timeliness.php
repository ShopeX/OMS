<?PHP
/**
 * 订单发货时效检查定时任务
 *
 * @author AI Assistant
 * @version 1.0
 */

class ome_autotask_timer_platform_timeliness
{
    public function process($params, &$error_msg=''){
        set_time_limit(0);
        ignore_user_abort(1);
        ini_set('memory_limit', '512M');
        try {
            // 1. 获取平台时效数据
            $platformSetModel = app::get('ome')->model('platform_set');
            $timelinessList = $platformSetModel->getList('shop_type, kname, kvalue', array('scene' => 'delivery', 'kname' => 'delivery_hour'));
            
            if (empty($timelinessList)) {
                $error_msg = '未找到平台时效配置数据';
                return false;
            }
            
            // 2. 获取符合条件的订单数据
            $orderModel = app::get('ome')->model('orders');
            $currentTime = time();
            
            // 构建查询条件：status=active, pay_status=1, ship_status=0
            $filter = array(
                'status' => 'active',
                'pay_status' => '1',
                'ship_status' => '0',
                'paytime|than' => strtotime('-7 days'), // 近7天已支付订单
            );
            
            $orders = $orderModel->getList('order_id, order_bn, shop_type, paytime', $filter, 0, 10000);
            
            if (empty($orders)) {
                $error_msg = '未找到符合条件的订单';
                return true;
            }
            
            // 3. 检查每个订单的发货时效
            $timelinessAlerts = array(); // 时效提醒
            
            foreach ($orders as $order) {
                if (empty($order['paytime'])) {
                    continue;
                }
                
                $payTime = $order['paytime'];
                $timeDiff = $currentTime - $payTime;
                $timeDiffHours = $timeDiff / 3600; // 转换为小时
                
                // 获取该平台配置的时效
                $platformHour = 24; // 默认24小时
                foreach ($timelinessList as $timeliness) {
                    if ($timeliness['shop_type'] == $order['shop_type']) {
                        $platformHour = intval($timeliness['kvalue']);
                        break;
                    }
                }
                
                // 计算剩余时间
                $remainingHours = $platformHour - $timeDiffHours;
                
                // 检查是否需要发送提醒（剩余时间小于等于1小时时提醒）
                if ($remainingHours <= 1 && $remainingHours > 0) {
                    $timelinessAlerts[] = array(
                        'order_bn' => $order['order_bn'],
                        'shop_type' => ome_shop_type::shop_name($order['shop_type']),
                        'paytime' => date('Y-m-d H:i:s', $order['paytime']),
                        'remaining_hours' => round($remainingHours, 1)
                    );
                }
            }
            
            // 4. 发送提醒通知
            $alertCount = 0;
            
            // 发送时效提醒
            if (!empty($timelinessAlerts)) {
                foreach ($timelinessAlerts as $alert) {
                    kernel::single('monitor_event_notify')->addNotify('order_delivery_timeliness', $alert);
                    $alertCount++;
                }
            }
            
            $error_msg = "处理完成，共发送 {$alertCount} 条时效提醒";
            return true;
            
        } catch (Exception $e) {
            $error_msg = '处理过程中发生错误：' . $e->getMessage();
            return false;
        }
    }
}
