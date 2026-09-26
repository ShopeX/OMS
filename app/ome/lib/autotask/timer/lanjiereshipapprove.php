<?php
/**
 * 拦截入库退货单：已录入物流号未审核 → 报警 + 自动审核
 *
 * 对齐列表页签「已录入物流号未审核」+ 标识「拦截入库」
 */
class ome_autotask_timer_lanjiereshipapprove
{
    /** 单次处理上限，避免一次扫太多拖垮审核 */
    const BATCH_LIMIT = 100;

    /**
     * @param array  $params
     * @param string $error_msg
     * @return bool
     */
    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);

        $list = $this->_getPendingList();
        if (empty($list)) {
            return true;
        }

        $this->_notifyAlarm($list);

        $result = $this->_autoApprove($list);
        if ($result['fail'] > 0) {
            $error_msg = sprintf(
                '拦截入库自动审核：总数%d，成功%d，失败%d。%s',
                $result['total'],
                $result['succ'],
                $result['fail'],
                $result['fail_msg']
            );
        }

        return true;
    }

    /**
     * 查询：未审核 + 已录入退回物流号 + 拦截入库标识 + 无「实付不等」 + 有售后单
     *
     * @return array
     */
    protected function _getPendingList()
    {
        $lanjieBit = (int) ome_reship_const::__LANJIE_RUKU;
        $paidUnequalBit = (int) ome_reship_const::__PAID_EQUAL_AUTO_BLOCK;
        // 创建时间早于当前2分钟，避免刚建单/人工正在审时并发撞车
        // 有「实付不等」需人工审，不进自动审核；须关联售后单 return_id>0
        $filter = array(
            'is_check'       => '0',
            'return_type'    => 'return',
            'return_id|than' => 0,
            't_begin|sthan'  => time() - 120,
            'filter_sql'     => '(return_logi_no!="" AND return_logi_no IS NOT NULL)'
                . ' AND (flag_type & ' . $lanjieBit . ')'
                . ' AND (flag_type & ' . $paidUnequalBit . ')=0',
        );

        return app::get('ome')->model('reship')->getList(
            'reship_id,reship_bn,order_id,return_id,return_logi_no,return_logi_name,shop_id,flag_type,is_check',
            $filter,
            0,
            self::BATCH_LIMIT,
            'reship_id asc'
        );
    }

    /**
     * 监控报警
     *
     * @param array $list
     */
    protected function _notifyAlarm($list)
    {
        $orderIds = array_unique(array_filter(array_column($list, 'order_id')));
        $orderBnMap = array();
        if ($orderIds) {
            $orders = app::get('ome')->model('orders')->getList('order_id,order_bn', array('order_id' => $orderIds));
            $orderBnMap = array_column($orders, 'order_bn', 'order_id');
        }

        $reshipBns = array();
        $orderBns = array();
        $detailLines = array();
        foreach ($list as $row) {
            $reshipBns[] = $row['reship_bn'];
            $orderBn = isset($orderBnMap[$row['order_id']]) ? $orderBnMap[$row['order_id']] : '';
            if ($orderBn !== '') {
                $orderBns[] = $orderBn;
            }
            $detailLines[] = sprintf(
                '退货单：%s，订单：%s，物流号：%s',
                $row['reship_bn'],
                $orderBn !== '' ? $orderBn : '-',
                $row['return_logi_no']
            );
        }

        $reshipBns = array_values(array_unique($reshipBns));
        $orderBns = array_values(array_unique($orderBns));

        kernel::single('monitor_event_notify')->addNotify('lanjie_reship_logi_unaudited', array(
            'total'       => count($list),
            'reship_bns'  => implode(',', array_slice($reshipBns, 0, 30)),
            'order_bns'   => implode(',', array_slice($orderBns, 0, 30)),
            'detail_list' => implode("\n", array_slice($detailLines, 0, 30)),
            'errmsg'      => sprintf('共%d单拦截入库退货单已录入物流号未审核，系统将自动审核', count($list)),
        ), true);
    }

    /**
     * 调用自动审核（confirm_reship，exec_type=1）
     *
     * @param array $list
     * @return array
     */
    protected function _autoApprove($list)
    {
        $reshipLib = kernel::single('ome_reship');
        $result = array('total' => 0, 'succ' => 0, 'fail' => 0, 'fail_msg' => '');

        foreach ($list as $row) {
            $result['total']++;
            $reshipId = (int) $row['reship_id'];
            if ($reshipId <= 0) {
                $result['fail']++;
                continue;
            }

            // 再查一次，避免并发已审
            $fresh = app::get('ome')->model('reship')->db_dump(
                array('reship_id' => $reshipId),
                'reship_id,reship_bn,is_check,return_id,return_logi_no,flag_type'
            );
            if (empty($fresh) || (string) $fresh['is_check'] !== '0') {
                continue;
            }
            if ((int) $fresh['return_id'] <= 0) {
                continue;
            }
            if (empty($fresh['return_logi_no'])) {
                continue;
            }
            if (!((int) $fresh['flag_type'] & ome_reship_const::__LANJIE_RUKU)) {
                continue;
            }
            // 实付不等需人工审，跳过
            if ((int) $fresh['flag_type'] & ome_reship_const::__PAID_EQUAL_AUTO_BLOCK) {
                continue;
            }

            $params = array(
                'reship_id' => $reshipId,
                'status'    => '1',
                'is_anti'   => false,
                'exec_type' => 1,
            );
            $confirmErrorMsg = '';
            $isRollback = true;
            $ok = $reshipLib->confirm_reship($params, $confirmErrorMsg, $isRollback);
            if (!$ok) {
                $result['fail']++;
                $result['fail_msg'] .= $fresh['reship_bn'] . ':' . $confirmErrorMsg . '; ';
            } else {
                $result['succ']++;
            }
        }

        return $result;
    }
}
