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

    /**
     * 关联单号搜索：兼容 order_bn / platform_order_bn / reshipping_bn / logi_no
     * （平台补寄单 historical 数据 original_bn 可能为空）
     */
    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = '';
        if (!empty($filter['original_bn'])) {
            $bns = array();
            if (is_string($filter['original_bn']) && strpos($filter['original_bn'], "\n") !== false) {
                $bns = array_unique(array_map('trim', array_filter(explode("\n", $filter['original_bn']))));
            } else {
                $bns = array(trim((string) $filter['original_bn']));
            }
            unset($filter['original_bn']);

            if ($bns) {
                $ta = $tableAlias ? $tableAlias . '.' : '';
                $db = kernel::database();
                $orParts = array();
                foreach ($bns as $bn) {
                    if ($bn === '') {
                        continue;
                    }
                    $q = $db->quote($bn);
                    $orParts[] = "({$ta}original_bn={$q} OR {$ta}order_bn={$q} OR {$ta}platform_order_bn={$q} OR {$ta}reshipping_bn={$q} OR {$ta}logi_no={$q})";
                }
                if ($orParts) {
                    $where .= ' AND (' . implode(' OR ', $orParts) . ')';
                }
            }
        }

        return parent::_filter($filter, $tableAlias, $baseWhere) . $where;
    }
}

