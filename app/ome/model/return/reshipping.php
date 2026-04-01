<?php

class ome_mdl_return_reshipping extends dbeav_model
{

    /**
     * 补寄状态映射
     */
    var $status = array(
        '0' => '补寄待处理',
        '1' => '等待卖家发货',
        '2' => '等待买家收货',
        '3' => '补寄成功',
        '4' => '卖家拒绝补寄',
        '5' => '补寄关闭',
        '6' => '转退款',
    );

    /**
     * 状态字段格式化（Finder自动调用）
     * @param string $col 状态值
     * @return string 状态名称
     */
    public function modifier_status($col)
    {
        return isset($this->status[$col]) ? $this->status[$col] : $col;
    }

    /**
     * 退款阶段字段格式化（Finder自动调用）
     * @param string $col 退款阶段值
     * @return string 退款阶段名称
     */
    public function modifier_refund_phase($col)
    {
        $refund_phase_map = array(
            'onsale' => '售中',
            'aftersale' => '售后',
        );
        return isset($refund_phase_map[$col]) ? $refund_phase_map[$col] : ($col ? $col : '-');
    }
}

