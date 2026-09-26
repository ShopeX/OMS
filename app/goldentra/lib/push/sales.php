<?php

/**
 * SD003 销售/退货上传数据组装
 */
class goldentra_push_sales
{
    /**
     * 发货完成后组装销售上传（ZSALESTYPE=01）
     *
     * @param int $delivery_id
     * @return int|false push_id
     */
    public function createFromDelivery($delivery_id)
    {
        $delivery_id = intval($delivery_id);
        if ($delivery_id <= 0) {
            return false;
        }

        $delivery = app::get('ome')->model('delivery')->db_dump(
            array('delivery_id' => $delivery_id, 'parent_id' => 0, 'status' => 'succ'),
            'delivery_id,delivery_bn,shop_id,branch_id,delivery_time,ship_time,logi_id'
        );
        if (!$delivery) {
            return false;
        }

        $orderRel = kernel::database()->selectrow(
            'SELECT o.order_id,o.order_bn,o.createtime,o.shipping FROM sdb_ome_delivery_order do '
            . 'INNER JOIN sdb_ome_orders o ON o.order_id=do.order_id '
            . 'WHERE do.delivery_id=' . $delivery_id
        );
        if (!$orderRel) {
            return false;
        }

        $sales = app::get('ome')->model('sales')->db_dump(
            array('delivery_id' => $delivery_id),
            'sale_id,sale_bn,order_id,order_bn,shop_id,branch_id,sale_amount,actually_amount,total_amount,ship_time,sale_time'
        );
        if (!$sales) {
            $sales = app::get('ome')->model('sales')->db_dump(
                array('order_id' => $orderRel['order_id']),
                'sale_id,sale_bn,order_id,order_bn,shop_id,branch_id,sale_amount,actually_amount,total_amount,ship_time,sale_time'
            );
        }
        if (!$sales) {
            return false;
        }

        $shop_id = !empty($sales['shop_id']) ? $sales['shop_id'] : $delivery['shop_id'];
        $branch_id = !empty($sales['branch_id']) ? $sales['branch_id'] : $delivery['branch_id'];
        $eventTime = !empty($delivery['delivery_time'])
            ? intval($delivery['delivery_time'])
            : (!empty($sales['ship_time']) ? intval($sales['ship_time']) : time());

        $summe = isset($sales['actually_amount']) && $sales['actually_amount'] !== ''
            ? $sales['actually_amount']
            : (isset($sales['sale_amount']) ? $sales['sale_amount'] : 0);

        $storeBn = $this->resolveStoreBn($shop_id, $branch_id);
        $loc = goldentra_metadata::lookupStorePlantLocation($storeBn);

        $header = array(
            'zptdhyz' => $this->cut($delivery['delivery_bn'], 24),
            'bonnummer' => $this->cut($orderRel['order_bn'], 24),
            'vorgdatum' => date('Ymd', $eventTime),
            'vorgzeit' => date('His', $eventTime),
            'zsalestype' => '01',
            'kunnr' => $loc['kunnr'],
            'werks' => $loc['werks'],
            'lgort' => $loc['lgort'],
            'zgldjlx' => 'A',
            'zahlart' => kernel::single('goldentra_zahlart')->getByShopId($shop_id),
            'summe' => round(floatval($summe), 2),
            'belegwaerks' => 'CNY',
            'zmdbm' => $this->cut($storeBn, 10),
            'zremarks1' => $this->cut($orderRel['order_bn'], 50),
            'zremarks2' => '',
            'sale_id' => $this->idValue($sales['sale_id']),
            'sale_bn' => isset($sales['sale_bn']) ? $sales['sale_bn'] : '',
            'aftersale_id' => 0,
            'aftersale_bn' => '',
            'delivery_id' => $this->idValue($delivery_id),
            'delivery_bn' => $delivery['delivery_bn'],
            'order_id' => $this->idValue($orderRel['order_id']),
            'order_bn' => $orderRel['order_bn'],
            'reship_id' => 0,
            'reship_bn' => '',
            'shop_id' => $shop_id,
            'sync_status2' => 'wait',
        );

        $items = $this->buildItemsFromSales($sales['sale_id'], $delivery_id, '01');
        if (!$items) {
            return false;
        }

        return kernel::single('goldentra_push')->saveSales($header, $items);
    }

    /**
     * 售后单生成后组装退货上传（ZSALESTYPE=02），仅退货/退款
     *
     * @param int $aftersale_id
     * @return int|false
     */
    public function createFromAftersale($aftersale_id)
    {
        $aftersale_id = intval($aftersale_id);
        if ($aftersale_id <= 0) {
            return false;
        }

        $aftersale = app::get('sales')->model('aftersale')->db_dump(
            array('aftersale_id' => $aftersale_id),
            'aftersale_id,aftersale_bn,shop_id,shop_bn,order_id,order_bn,reship_id,reship_bn,delivery_id,delivery_bn,return_type,return_bn,aftersale_time,acttime,refundmoney'
        );
        if (!$aftersale) {
            return false;
        }
        if (!in_array($aftersale['return_type'], array('return', 'refund'), true)) {
            return false;
        }

        $bizBn = !empty($aftersale['reship_bn'])
            ? $aftersale['reship_bn']
            : (!empty($aftersale['aftersale_bn']) ? $aftersale['aftersale_bn'] : $aftersale['return_bn']);
        if ($bizBn === '' || $bizBn === null) {
            return false;
        }

        $eventTime = !empty($aftersale['aftersale_time'])
            ? intval($aftersale['aftersale_time'])
            : (!empty($aftersale['acttime']) ? intval($aftersale['acttime']) : time());

        $branch_id = 0;
        $reshipTotal = null;
        if (!empty($aftersale['reship_id'])) {
            $reship = app::get('ome')->model('reship')->db_dump(
                array('reship_id' => $aftersale['reship_id']),
                'branch_id,totalmoney'
            );
            if ($reship) {
                $branch_id = intval($reship['branch_id']);
                if (isset($reship['totalmoney']) && $reship['totalmoney'] !== '') {
                    $reshipTotal = $reship['totalmoney'];
                }
            }
        }

        // aftersale 表无 totalmoney，金额优先 refundmoney，其次退货单 totalmoney
        if (isset($aftersale['refundmoney']) && $aftersale['refundmoney'] !== '') {
            $summe = abs(floatval($aftersale['refundmoney']));
        } elseif ($reshipTotal !== null) {
            $summe = abs(floatval($reshipTotal));
        } else {
            $summe = 0;
        }

        $storeBn = $this->resolveStoreBn($aftersale['shop_id'], $branch_id);
        $loc = goldentra_metadata::lookupStorePlantLocation($storeBn);

        $header = array(
            'zptdhyz' => $this->cut($bizBn, 24),
            'bonnummer' => $this->cut($aftersale['order_bn'], 24),
            'vorgdatum' => date('Ymd', $eventTime),
            'vorgzeit' => date('His', $eventTime),
            'zsalestype' => '02',
            'kunnr' => $loc['kunnr'],
            'werks' => $loc['werks'],
            'lgort' => $loc['lgort'],
            'zgldjlx' => 'A',
            'zahlart' => kernel::single('goldentra_zahlart')->getByShopId($aftersale['shop_id']),
            'summe' => round($summe, 2),
            'belegwaerks' => 'CNY',
            'zmdbm' => $this->cut($storeBn, 10),
            'zremarks1' => $this->cut($aftersale['order_bn'], 50),
            'sale_id' => 0,
            'sale_bn' => '',
            'aftersale_id' => $aftersale_id,
            'aftersale_bn' => isset($aftersale['aftersale_bn']) ? $aftersale['aftersale_bn'] : '',
            'delivery_id' => $this->idValue(isset($aftersale['delivery_id']) ? $aftersale['delivery_id'] : 0),
            'delivery_bn' => isset($aftersale['delivery_bn']) ? $aftersale['delivery_bn'] : '',
            'order_id' => $this->idValue(isset($aftersale['order_id']) ? $aftersale['order_id'] : 0),
            'order_bn' => isset($aftersale['order_bn']) ? $aftersale['order_bn'] : '',
            'reship_id' => $this->idValue(isset($aftersale['reship_id']) ? $aftersale['reship_id'] : 0),
            'reship_bn' => isset($aftersale['reship_bn']) ? $aftersale['reship_bn'] : '',
            'shop_id' => $aftersale['shop_id'],
            // 退货仅发货上传，不做定时确认收货+凭证
            'sync_status2' => 'wait',
            'sync_msg2' => '退货无确认收货推送',
        );

        $items = $this->buildItemsFromAftersale($aftersale_id, $aftersale['reship_id'], '02');
        if (!$items) {
            return false;
        }

        return kernel::single('goldentra_push')->saveSales($header, $items);
    }

    /**
     * @param int $sale_id
     * @param int $delivery_id
     * @param string $zsalestype
     * @return array
     */
    protected function buildItemsFromSales($sale_id, $delivery_id, $zsalestype)
    {
        $salesItems = app::get('ome')->model('sales_items')->getList(
            'item_id,product_id,bn,nums,price,pmt_price,sale_price,sales_amount,actually_amount',
            array('sale_id' => $sale_id),
            0,
            -1
        );
        if (!$salesItems) {
            return array();
        }

        $serialMap = $this->loadDeliverySerials($delivery_id);
        $vorzeichen = $zsalestype === '02' ? '+' : '-';
        $items = array();
        $line = 10;
        foreach ($salesItems as $si) {
            $bn = trim($si['bn']);
            $qty = floatval($si['nums']);
            if ($qty <= 0) {
                continue;
            }
            $serials = isset($serialMap[$bn]) ? $serialMap[$bn] : array();
            if ($serials) {
                $perAmt = round(floatval($si['actually_amount'] !== '' && $si['actually_amount'] !== null
                    ? $si['actually_amount']
                    : $si['sales_amount']) / max(count($serials), 1), 2);
                $perPrice = round(floatval($si['price']) , 2);
                foreach ($serials as $sn) {
                    $mat = $this->loadMaterialByUniqueOrBn($sn, $bn);
                    $items[] = $this->makeItemRow($line, $mat, $sn, $bn, 1, $vorzeichen, $perAmt, $perPrice, $si);
                    $line += 10;
                }
            } else {
                $mat = $this->loadMaterialByUniqueOrBn('', $bn);
                $amt = floatval($si['actually_amount'] !== '' && $si['actually_amount'] !== null
                    ? $si['actually_amount']
                    : $si['sales_amount']);
                $items[] = $this->makeItemRow(
                    $line,
                    $mat,
                    !empty($mat['unique_code']) ? $mat['unique_code'] : '',
                    $bn,
                    $qty,
                    $vorzeichen,
                    $amt,
                    floatval($si['price']),
                    $si
                );
                $line += 10;
            }
        }
        return $items;
    }

    /**
     * @param int $aftersale_id
     * @param int $reship_id
     * @param string $zsalestype
     * @return array
     */
    protected function buildItemsFromAftersale($aftersale_id, $reship_id, $zsalestype)
    {
        $asItems = app::get('sales')->model('aftersale_items')->getList(
            'item_id,product_id,bn,num,price,amount,saleamount',
            array('aftersale_id' => $aftersale_id),
            0,
            -1
        );
        if (!$asItems && $reship_id) {
            $asItems = app::get('ome')->model('reship_items')->getList(
                'item_id,product_id,bn,num,price',
                array('reship_id' => $reship_id),
                0,
                -1
            );
        }
        if (!$asItems) {
            return array();
        }

        $serialMap = $this->loadReshipSerials(intval($reship_id));
        $vorzeichen = '+';
        $items = array();
        $line = 10;
        foreach ($asItems as $si) {
            $bn = trim($si['bn']);
            $qty = floatval(isset($si['num']) ? $si['num'] : (isset($si['nums']) ? $si['nums'] : 0));
            if ($qty <= 0) {
                continue;
            }
            $amt = isset($si['amount']) ? floatval($si['amount']) : (floatval($si['price']) * $qty);
            $serials = isset($serialMap[$bn]) ? $serialMap[$bn] : array();
            if ($serials) {
                $perAmt = round($amt / max(count($serials), 1), 2);
                foreach ($serials as $sn) {
                    $mat = $this->loadMaterialByUniqueOrBn($sn, $bn);
                    $items[] = $this->makeItemRow($line, $mat, $sn, $bn, 1, $vorzeichen, $perAmt, floatval($si['price']), $si);
                    $line += 10;
                }
            } else {
                $mat = $this->loadMaterialByUniqueOrBn('', $bn);
                $items[] = $this->makeItemRow(
                    $line,
                    $mat,
                    !empty($mat['unique_code']) ? $mat['unique_code'] : '',
                    $bn,
                    $qty,
                    $vorzeichen,
                    $amt,
                    floatval($si['price']),
                    $si
                );
                $line += 10;
            }
        }
        return $items;
    }

    protected function makeItemRow($line, $mat, $charg, $bn, $qty, $vorzeichen, $amt, $price, $src)
    {
        $artnr = '';
        $refbonnr = '';
        $meins = 'PC';
        $processCost = 0;
        if ($mat) {
            $artnr = !empty($mat['material_no']) ? $mat['material_no'] : '';
            $refbonnr = !empty($mat['mold_code']) ? $mat['mold_code'] : '';
            if (!empty($mat['unit'])) {
                $meins = $this->cut($mat['unit'], 3);
            }
            $processCost = floatval(isset($mat['process_cost']) ? $mat['process_cost'] : 0);
        }
        if ($artnr === '') {
            $artnr = $this->cut($bn, 18);
        }
        $zyj = round(floatval($price), 2);
        $kond = round(floatval($amt), 2);
        $zyhj = $kond;

        return array(
            'itemid' => str_pad((string) $line, 6, '0', STR_PAD_LEFT),
            'artnr' => $this->cut($artnr, 18),
            'charg' => $this->cut($charg, 10),
            'refbonnr' => $this->cut($refbonnr, 18),
            'vorzeichen' => $vorzeichen,
            'menge' => (string) $qty,
            'meins' => $meins,
            'kondvalue' => $kond,
            'belegwaerks' => 'CNY',
            'zyj' => $zyj,
            'zyhj' => $zyhj,
            'zlsjj' => 0,
            'zjj' => 0,
            'zkgf' => 0,
            'zjgf' => round($processCost, 2),
        );
    }

    protected function loadDeliverySerials($delivery_id)
    {
        $map = array();
        $rows = app::get('ome')->model('delivery_items_serial')->getList(
            'bn,serial_number',
            array('delivery_id' => intval($delivery_id)),
            0,
            -1
        );
        foreach ((array) $rows as $r) {
            $bn = trim($r['bn']);
            if ($bn === '' || empty($r['serial_number'])) {
                continue;
            }
            $map[$bn][] = $r['serial_number'];
        }
        return $map;
    }

    protected function loadReshipSerials($reship_id)
    {
        $map = array();
        if ($reship_id <= 0) {
            return $map;
        }
        $rows = app::get('ome')->model('reship_items_serial')->getList(
            'bn,serial_number',
            array('reship_id' => $reship_id),
            0,
            -1
        );
        foreach ((array) $rows as $r) {
            $bn = trim(isset($r['bn']) ? $r['bn'] : '');
            if ($bn === '' || empty($r['serial_number'])) {
                continue;
            }
            $map[$bn][] = $r['serial_number'];
        }
        return $map;
    }

    protected function loadMaterialByUniqueOrBn($unique_code, $bn)
    {
        $mdl = app::get('goldentra')->model('material');
        if ($unique_code !== '') {
            $row = $mdl->db_dump(array('unique_code' => $unique_code), 'material_no,mold_code,unique_code,unit,process_cost');
            if ($row) {
                return $row;
            }
        }
        if ($bn !== '') {
            $row = $mdl->db_dump(array('unique_code' => $bn), 'material_no,mold_code,unique_code,unit,process_cost');
            if ($row) {
                return $row;
            }
            $row = $mdl->db_dump(array('material_no' => $bn), 'material_no,mold_code,unique_code,unit,process_cost');
            if ($row) {
                return $row;
            }
        }
        return array();
    }

    /**
     * 解析 OMS 门店编码：仓关联门店 > 仓编码 > 店铺编码（完整，供 Z42 匹配）
     *
     * @param string $shop_id
     * @param int $branch_id
     * @return string
     */
    protected function resolveStoreBn($shop_id, $branch_id)
    {
        $branch_id = intval($branch_id);
        if ($branch_id > 0) {
            $branch = app::get('ome')->model('branch')->db_dump(
                array('branch_id' => $branch_id),
                'branch_bn,store_id'
            );
            if ($branch) {
                if (!empty($branch['store_id'])) {
                    $store = app::get('o2o')->model('store')->db_dump(
                        array('store_id' => $branch['store_id']),
                        'store_bn'
                    );
                    if (!empty($store['store_bn'])) {
                        return trim((string) $store['store_bn']);
                    }
                }
                if (!empty($branch['branch_bn'])) {
                    return trim((string) $branch['branch_bn']);
                }
            }
        }
        if ($shop_id) {
            $shop = app::get('ome')->model('shop')->db_dump(array('shop_id' => $shop_id), 'shop_bn');
            if (!empty($shop['shop_bn'])) {
                return trim((string) $shop['shop_bn']);
            }
        }
        return '';
    }

    /**
     * 保留 bigint ID，避免 intval 溢出 / 写入 int 列超范围
     *
     * @param mixed $id
     * @return string 数字字符串，非法则为 '0'
     */
    protected function idValue($id)
    {
        if ($id === null || $id === '') {
            return '0';
        }
        if (is_int($id)) {
            return $id < 0 ? '0' : (string) $id;
        }
        $id = trim((string) $id);
        if ($id === '' || !ctype_digit($id)) {
            return '0';
        }
        return $id;
    }

    protected function cut($str, $len)
    {
        $str = (string) $str;
        if (strlen($str) <= $len) {
            return $str;
        }
        return substr($str, 0, $len);
    }
}
