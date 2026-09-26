<?php
class sales_mdl_delivery_order_item extends dbeav_model
{

    //是否有导出配置
    public $has_export_cnf = true;

    public $export_name = '发货销售明细单';

    public $filter_use_like = true;

    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        // 多订单号查询
        if ($filter['order_bn'] && is_string($filter['order_bn']) && strpos($filter['order_bn'], "\n") !== false) {
            $filter['order_bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['order_bn']))));
        }

        // 多基础物料编码查询
        if ($filter['bn'] && is_string($filter['bn']) && strpos($filter['bn'], "\n") !== false) {
            $filter['bn'] = array_unique(array_map('trim', array_filter(explode("\n", $filter['bn']))));
        }

        return parent::_filter($filter, $tableAlias, $baseWhere);
    }

    public function addReturnNum($reshipItems) {
        $filter = [
            'order_item_id' => array_column($reshipItems, 'order_item_id'),
            'filter_sql' => 'nums <> return_num'
        ];
        $items = $this->getList('id,order_item_id,nums,return_num,return_amount', $filter);
        $orderItemIdItems = [];
        foreach($items as $v) {
            $orderItemIdItems[$v['order_item_id']][] = $v;
        }
        foreach($reshipItems as $v) {
            $num = intval($v['normal_num']+$v['defective_num']);
            if ($num <= 0) {
                continue;
            }
            $amount = $v['amount'] ? : $num * $v['price'];
            $price = $amount/$num;
            if($orderItemIdItems[$v['order_item_id']]) {
                foreach($orderItemIdItems[$v['order_item_id']] as $ik => $iv) {
                    if($num < 1) {
                        break;
                    }
                    $valid = $iv['nums']-$iv['return_num'];
                    if($valid < 1) {
                        break;
                    }
                    if($valid < $num) {
                        $returnNum = $valid;
                    } else {
                        $returnNum = $num;
                    }
                    $num -= $returnNum;
                    $orderItemIdItems[$v['order_item_id']]['return_num'] += $returnNum;
                    $sql = 'update sdb_sales_delivery_order_item set
                            return_num=return_num+'.$returnNum.',
                            return_amount=return_amount+'.sprintf('%.2f', $price*$returnNum).'
                            where id='.$iv['id'];
                    $this->db->exec($sql);
                }
            }
        }
    }
}
