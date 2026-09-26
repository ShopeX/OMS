<?php

/**
 * 销售单详情「唯一码明细」
 *
 * 数据源优先级：
 * 1) 机器中心 material_sn_flow（发货出库流水）+ material_sn.sn
 * 2) 兜底 ome delivery_items_serial
 * 属性/标签价来自 goldentra_material；销售价按销售明细货号分摊。
 */
class goldentra_service_sales_serial
{
    /**
     * @param array $ctx order_bn, delivery_bn?, delivery_id?, sale_items?
     * @return array [sum_price, sum_sale_price, items]
     */
    public function getSerialNumberItemsForSale(array $ctx)
    {
        $order_bn = isset($ctx['order_bn']) ? trim((string) $ctx['order_bn']) : '';
        $delivery_bn = isset($ctx['delivery_bn']) ? trim((string) $ctx['delivery_bn']) : '';
        $delivery_id = isset($ctx['delivery_id']) ? intval($ctx['delivery_id']) : 0;
        $sale_items = !empty($ctx['sale_items']) && is_array($ctx['sale_items']) ? $ctx['sale_items'] : array();

        $rows = $this->loadFromMachineCenter($order_bn, $delivery_bn, $delivery_id);
        if (empty($rows)) {
            $rows = $this->loadFromDeliverySerial($delivery_id, $order_bn);
        }

        if (empty($rows)) {
            return array(0, 0, array());
        }

        $salePriceQueues = $this->buildSalePriceQueues($sale_items);
        $masters = $this->loadMastersByUniqueCodes(array_column($rows, 'serial_number'));

        $items = array();
        $sum_price = 0;
        $sum_sale_price = 0;
        foreach ($rows as $row) {
            $sn = trim((string) $row['serial_number']);
            $bn = trim((string) $row['bn']);
            $master = isset($masters[$sn]) ? $masters[$sn] : array();

            if ($bn === '' && !empty($master['bn'])) {
                $bn = trim((string) $master['bn']);
            }

            $price = $this->resolveTagPrice($master, $sale_items, $bn);
            $sale_price = $this->shiftSalePrice($salePriceQueues, $bn);
            $weight = isset($master['weight']) ? (float) $master['weight'] : 0;
            $process_cost = isset($master['process_cost']) ? (float) $master['process_cost'] : 0;
            $price_method = isset($master['price_method']) ? (string) $master['price_method'] : '';
            $gold_value = 0;
            if ($price_method === '1' && $weight > 0) {
                $gold_value = (float) bcdiv((string) ($price - $process_cost), (string) $weight, 2);
            }

            $product_name = '';
            if (!empty($master['goods_name'])) {
                $product_name = $master['goods_name'];
            } elseif (!empty($row['product_name'])) {
                $product_name = $row['product_name'];
            }

            $item = array(
                'bn' => $bn,
                'product_name' => $product_name,
                'serial_number' => $sn,
                'price' => $price,
                'sale_price' => $sale_price,
                'gold_value' => $gold_value,
                'color' => isset($master['fcolor']) ? $master['fcolor'] : '',
                'clarity' => isset($master['fpurity']) ? $master['fpurity'] : '',
                'diamond_cut' => isset($master['fcut']) ? $master['fcut'] : '',
                'certificate' => isset($master['fcertificate']) ? $master['fcertificate'] : '',
                'price_method' => $price_method,
                'weight' => $weight,
                'process_cost' => $process_cost,
            );
            $items[] = $item;
            $sum_price = (float) bcadd((string) $sum_price, (string) $price, 2);
            $sum_sale_price = (float) bcadd((string) $sum_sale_price, (string) $sale_price, 2);
        }

        return array($sum_price, $sum_sale_price, $items);
    }

    /**
     * 机器中心发货出库流水
     *
     * @return array[] bn, serial_number, product_name
     */
    protected function loadFromMachineCenter($order_bn, $delivery_bn, $delivery_id)
    {
        if ($order_bn === '' || !app::get('machinecenter')->is_installed()) {
            return array();
        }

        $db = kernel::database();
        $order_bn_sql = addslashes($order_bn);
        $where = "f.order_bn='{$order_bn_sql}' AND f.original_type='delivery' AND f.io_status='0'";
        if ($delivery_bn !== '' && strpos($delivery_bn, '|') === false) {
            $where .= " AND f.bill_bn='" . addslashes($delivery_bn) . "'";
        } elseif ($delivery_id > 0) {
            $where .= ' AND f.original_id=' . intval($delivery_id);
        }

        $sql = "SELECT f.material_bn AS bn, sn.sn AS serial_number, '' AS product_name
                FROM sdb_machinecenter_material_sn_flow AS f
                INNER JOIN sdb_machinecenter_material_sn AS sn ON sn.id=f.sn_id
                WHERE {$where}
                ORDER BY f.id ASC";
        $list = $db->select($sql);
        if (!is_array($list) || !$list) {
            // 拆单多发货单号时放宽到仅订单号
            if ($delivery_bn !== '' || $delivery_id > 0) {
                $sql = "SELECT f.material_bn AS bn, sn.sn AS serial_number, '' AS product_name
                        FROM sdb_machinecenter_material_sn_flow AS f
                        INNER JOIN sdb_machinecenter_material_sn AS sn ON sn.id=f.sn_id
                        WHERE f.order_bn='{$order_bn_sql}' AND f.original_type='delivery' AND f.io_status='0'
                        ORDER BY f.id ASC";
                $list = $db->select($sql);
            }
        }

        return $this->uniqueBySerial(is_array($list) ? $list : array());
    }

    /**
     * ome 发货唯一码兜底
     */
    protected function loadFromDeliverySerial($delivery_id, $order_bn)
    {
        $db = kernel::database();
        $list = array();

        if ($delivery_id > 0) {
            $sql = 'SELECT bn, product_name, serial_number
                    FROM sdb_ome_delivery_items_serial
                    WHERE delivery_id=' . intval($delivery_id) . '
                    ORDER BY item_serial_id ASC';
            $list = $db->select($sql);
        }

        if ((!is_array($list) || !$list) && $order_bn !== '') {
            $order_bn_sql = addslashes($order_bn);
            $sql = "SELECT dis.bn, dis.product_name, dis.serial_number
                    FROM sdb_ome_delivery_items_serial AS dis
                    INNER JOIN sdb_ome_delivery_order AS dord ON dord.delivery_id=dis.delivery_id
                    INNER JOIN sdb_ome_orders AS o ON o.order_id=dord.order_id
                    WHERE o.order_bn='{$order_bn_sql}'
                    ORDER BY dis.item_serial_id ASC";
            $list = $db->select($sql);
        }

        return $this->uniqueBySerial(is_array($list) ? $list : array());
    }

    protected function uniqueBySerial(array $list)
    {
        $out = array();
        foreach ($list as $row) {
            $sn = isset($row['serial_number']) ? trim((string) $row['serial_number']) : '';
            if ($sn === '' || isset($out[$sn])) {
                continue;
            }
            $out[$sn] = array(
                'bn' => isset($row['bn']) ? trim((string) $row['bn']) : '',
                'serial_number' => $sn,
                'product_name' => isset($row['product_name']) ? $row['product_name'] : '',
            );
        }
        return array_values($out);
    }

    /**
     * @param string[] $uniqueCodes
     * @return array unique_code => material row
     */
    protected function loadMastersByUniqueCodes(array $uniqueCodes)
    {
        $uniqueCodes = array_values(array_unique(array_filter(array_map('trim', $uniqueCodes))));
        if (!$uniqueCodes || !app::get('goldentra')->is_installed()) {
            return array();
        }

        $list = app::get('goldentra')->model('material')->getList(
            'unique_code,bn,goods_name,price,weight,process_cost,price_method,fcolor,fpurity,fcut,fcertificate',
            array('unique_code|in' => $uniqueCodes),
            0,
            -1
        );
        $map = array();
        foreach ((array) $list as $row) {
            $code = trim((string) $row['unique_code']);
            if ($code !== '') {
                $map[$code] = $row;
            }
        }
        return $map;
    }

    /**
     * 按销售明细货号拆成单价队列（销售金额/数量，尾差落最后一件）
     *
     * @return array bn => float[]
     */
    protected function buildSalePriceQueues(array $sale_items)
    {
        $queues = array();
        foreach ($sale_items as $item) {
            $bn = isset($item['bn']) ? trim((string) $item['bn']) : '';
            if ($bn === '') {
                continue;
            }
            $nums = isset($item['nums']) ? intval($item['nums']) : 0;
            if ($nums <= 0) {
                continue;
            }
            $total = 0;
            if (isset($item['sales_amount']) && $item['sales_amount'] !== '' && $item['sales_amount'] !== null) {
                $total = (float) $item['sales_amount'];
            } elseif (isset($item['sale_price'])) {
                $total = (float) $item['sale_price'];
            }
            $unit = (float) bcdiv((string) $total, (string) $nums, 2);
            if (!isset($queues[$bn])) {
                $queues[$bn] = array();
            }
            $acc = 0;
            for ($i = 0; $i < $nums; $i++) {
                if ($i === $nums - 1) {
                    $queues[$bn][] = (float) bcsub((string) $total, (string) $acc, 2);
                } else {
                    $queues[$bn][] = $unit;
                    $acc = (float) bcadd((string) $acc, (string) $unit, 2);
                }
            }
        }
        return $queues;
    }

    protected function shiftSalePrice(array &$queues, $bn)
    {
        $bn = trim((string) $bn);
        if ($bn !== '' && !empty($queues[$bn])) {
            return (float) array_shift($queues[$bn]);
        }
        return 0;
    }

    /**
     * 吊牌价：主档标签价优先；否则取销售明细行单价
     */
    protected function resolveTagPrice(array $master, array $sale_items, $bn)
    {
        if (isset($master['price']) && (float) $master['price'] > 0) {
            return (float) $master['price'];
        }
        $bn = trim((string) $bn);
        foreach ($sale_items as $item) {
            if (trim((string) $item['bn']) !== $bn) {
                continue;
            }
            if (isset($item['price']) && $item['price'] !== '' && $item['price'] !== null) {
                return (float) $item['price'];
            }
        }
        return 0;
    }
}
