<?php

/**
 * 每日金价 priceAdd
 */
class goldentra_daily_goldprice
{
    public static $shop_mapping = array(
        '9898' => '9801200',
    );

    public function mapShopBn($shop_bn)
    {
        return isset(self::$shop_mapping[$shop_bn]) ? self::$shop_mapping[$shop_bn] : $shop_bn;
    }

    public function parsePriceDate($date)
    {
        return kernel::single('ome_func')->date2time($date);
    }

    /**
     * 唯一键：shop_bn + pricedimension_code + sales_area + pricedimension_name（不含金价日期）
     */
    public function findByUniqueKey($shop_bn, $pricedimension_code, $sales_area = '', $pricedimension_name = '')
    {
        $db = kernel::database();
        $shop_bn = kernel::single('goldentra_db')->escape((string) $shop_bn, $db);
        $pricedimension_code = kernel::single('goldentra_db')->escape($pricedimension_code, $db);
        $sales_area = kernel::single('goldentra_db')->escape((string) $sales_area, $db);
        $pricedimension_name = kernel::single('goldentra_db')->escape($pricedimension_name, $db);

        $sql = "SELECT goldprice_id, gold_price, fcode, pricedimension_name, sales_area, price_date, shop_bn
            FROM sdb_goldentra_daily_goldprice
            WHERE IFNULL(shop_bn, '') = '{$shop_bn}'
            AND pricedimension_code = '{$pricedimension_code}'
            AND IFNULL(sales_area, '') = '{$sales_area}'
            AND pricedimension_name = '{$pricedimension_name}'
            ORDER BY goldprice_id ASC";

        return $db->selectrow($sql);
    }

    /**
     * @return array 缺失字段名
     */
    public function validatePriceRow($price, $is_insert)
    {
        $price = goldentra_daily_goldprice_fields::normalizeRow($price);
        $missing = array();
        foreach (goldentra_daily_goldprice_fields::$pos_required as $field) {
            if (!isset($price[$field]) || $price[$field] === '' || $price[$field] === null) {
                $missing[] = $field;
            }
        }
        if ($is_insert) {
            foreach (goldentra_daily_goldprice_fields::$oms_insert_required as $field) {
                if (!isset($price[$field]) || $price[$field] === '' || $price[$field] === null) {
                    $missing[] = $field;
                }
            }
        }
        return $missing;
    }

    /**
     * SAP/POS 批量推送金价
     * 缺必填 → 整批失败；同价跳过；变价更新 gold_price、price_date、up_time 并写操作日志
     *
     * @return array inserted, updated, skipped
     */
    public function upsertBatchFromApi($prices, $data_source = 'sap', $sync_batch_no = '')
    {
        $mdl = app::get('goldentra')->model('daily_goldprice');
        $now = time();
        $stats = array('inserted' => 0, 'updated' => 0, 'skipped' => 0);
        $seen = array();

        foreach ($prices as $priceRaw) {
            $price = goldentra_daily_goldprice_fields::normalizeRow($priceRaw);
            $shop_bn_raw = trim($price['shop_bn']);
            $price_date_raw = $price['date'];
            $pricedimension_code = trim($price['pricedimension_code']);
            $pricedimension_name = trim($price['pricedimension_name']);
            $sales_area = trim($price['sales_area']);

            $shop_bn = (string) $this->mapShopBn($shop_bn_raw);
            $price_date = $price_date_raw !== '' ? $this->parsePriceDate($price_date_raw) : 0;

            $exist = null;
            if ($pricedimension_code !== '') {
                $exist = $this->findByUniqueKey(
                    $shop_bn,
                    $pricedimension_code,
                    $sales_area,
                    $pricedimension_name
                );
            }

            $missing = $this->validatePriceRow($price, empty($exist));
            if ($missing) {
                throw new Exception(goldentra_api_normalize::formatRowError(
                    goldentra_api_normalize::formatMissingFieldsMessage(
                        $missing,
                        goldentra_daily_goldprice_fields::$descriptions
                    ),
                    array(
                        'shop_bn' => $shop_bn_raw,
                        'date' => $price_date_raw,
                        'pricedimension_code' => $pricedimension_code,
                        'sales_area' => $sales_area,
                        'pricedimension_name' => $pricedimension_name,
                    )
                ));
            }

            $gold_price = $price['gold_price'];

            $dedupe_key = $shop_bn . '|' . $pricedimension_code
                . '|' . $sales_area . '|' . $pricedimension_name;
            if (isset($seen[$dedupe_key])) {
                $stats['skipped']++;
                continue;
            }
            $seen[$dedupe_key] = true;

            if ($exist) {
                if (bccomp((string) $exist['gold_price'], (string) $gold_price, 3) === 0
                    && intval($exist['price_date']) === intval($price_date)
                ) {
                    $stats['skipped']++;
                    continue;
                }
                $mdl->update(
                    array(
                        'gold_price' => $gold_price,
                        'price_date' => $price_date,
                        'up_time' => $now,
                        'sync_batch_no' => $sync_batch_no,
                    ),
                    array('goldprice_id' => $exist['goldprice_id'])
                );
                $stats['updated']++;
                app::get('ome')->model('operation_log')->write_log(
                    'daily_goldprice_edit@goldentra',
                    $exist['goldprice_id'],
                    sprintf(
                        '金价同步[更新] %s->%s，日期 %s',
                        $exist['gold_price'],
                        $gold_price,
                        $price_date ? date('Y-m-d H:i:s', $price_date) : '-'
                    )
                );
                continue;
            }

            $row = array(
                'data_source' => $data_source,
                'sync_batch_no' => $sync_batch_no,
                'fcode' => trim($price['fcode']),
                'shop_bn' => $shop_bn,
                'price_date' => $price_date,
                'pricedimension_code' => $pricedimension_code,
                'pricedimension_name' => $pricedimension_name,
                'gold_price' => $gold_price,
                'level_flag' => trim($price['level_flag']),
                'sales_area' => $sales_area,
                'ext_field' => goldentra_daily_goldprice_fields::buildExtField($priceRaw),
                'at_time' => $now,
                'up_time' => $now,
            );
            $id = $mdl->insert($row);
            if ($id === false) {
                throw new Exception(goldentra_api_normalize::formatRowError(
                    '每日金价插入失败',
                    array(
                        'shop_bn' => $shop_bn_raw,
                        'date' => $price_date_raw,
                        'pricedimension_code' => $pricedimension_code,
                        'sales_area' => $sales_area,
                        'pricedimension_name' => $pricedimension_name,
                    )
                ));
            }
            $stats['inserted']++;
            app::get('ome')->model('operation_log')->write_log(
                'daily_goldprice_edit@goldentra',
                $id,
                sprintf(
                    '金价同步[新增] %s，日期 %s',
                    $gold_price,
                    $price_date ? date('Y-m-d H:i:s', $price_date) : '-'
                )
            );
        }

        return $stats;
    }

    public function parsePricesPayload($params)
    {
        if (!empty($params['goldprices'])) {
            return goldentra_api_normalize::decodeJsonBatch($params['goldprices'], 'pricedimension_code');
        }
        if (isset($params['PRICES']['PRICE'])) {
            $prices = $params['PRICES']['PRICE'];
            if (isset($prices['shop_bn']) || isset($prices['SHOP_BN'])) {
                return array($prices);
            }
            return is_array($prices) ? $prices : false;
        }
        if (goldentra_api_normalize::pick($params, 'pricedimension_code') !== null
            || goldentra_api_normalize::pick($params, 'date') !== null) {
            return array($params);
        }
        return false;
    }

    /**
     * 取本店各 pricedimension_code 最新一条金价（用于刷价任务）
     */
    public function getDailyGoldPrice($shop_bn)
    {
        $shop_bn = kernel::single('goldentra_db')->escape($shop_bn);
        $sql = "SELECT price_date, gold_price, pricedimension_code
            FROM (
                SELECT * FROM sdb_goldentra_daily_goldprice
                WHERE shop_bn = '{$shop_bn}' ORDER BY price_date DESC
            ) temp
            GROUP BY pricedimension_code";

        $rows = kernel::database()->select($sql);
        return $rows ? $rows : array();
    }

    public function getChgProducts($shop_bn)
    {
        return $this->getDailyGoldPrice($shop_bn);
    }

    public function getMaterialListOfUpdate($shop_bn)
    {
        $filter = array(
            'shop_type' => 'taobao',
            'shop_bn' => $shop_bn,
            'filter_sql' => '{table}node_id is not null and {table}node_id !=""',
        );
        $shop = app::get('ome')->model('shop')->dump($filter, 'shop_id,shop_bn,node_type');
        if (empty($shop)) {
            return array();
        }

        $products = $this->getDailyGoldPrice($shop['shop_bn']);
        if (empty($products)) {
            return array();
        }

        $pricedimension_code = $products[0]['pricedimension_code'];
        $price = $products[0]['gold_price'];
        foreach ($products as $p) {
            if ($p['price_date'] >= $products[0]['price_date']) {
                $pricedimension_code = $p['pricedimension_code'];
                $price = $p['gold_price'];
            }
        }

        $material_detail = app::get('material')->model('basic_material')->getList(
            'bm_id,process_cost,weight,main_weight',
            array('pricedimension_code' => $pricedimension_code, 'price_method' => '1')
        );

        return array($shop['shop_id'], $price, $material_detail);
    }

    public function updatePricetag($shop_id, $price, $material_detail)
    {
        $logObj = app::get('ome')->model('operation_log');
        $materialObj = app::get('material')->model('basic_material');
        $goodsObj = app::get('material')->model('shop_goods');

        foreach ($material_detail as $material) {
            $bm_id = $material['bm_id'];
            $today_price = $material['weight'] * $price;
            $price_tag = kernel::single('eccommon_math')->number_plus(array($today_price, $material['process_cost']));

            $sales_material = $materialObj->db->select(
                "SELECT s.sales_material_bn FROM sdb_material_sales_basic_material AS bs
                LEFT JOIN sdb_material_sales_material AS s ON bs.sm_id=s.sm_id WHERE bs.bm_id=" . intval($bm_id)
            );
            $sales_material_bns = array_map('current', $sales_material);
            if (empty($sales_material_bns)) {
                continue;
            }

            $goods_sku_list = $materialObj->db->select(
                "SELECT id,price,shop_goods_id,sku_bn FROM sdb_material_shop_goods_sku
                WHERE shop_id='" . intval($shop_id) . "' AND sku_bn IN ('" . implode("','", $sales_material_bns) . "')"
            );

            foreach ($goods_sku_list as $v) {
                $materialObj->db->exec(
                    "UPDATE sdb_material_shop_goods_sku SET price=" . floatval($price_tag) . ",price_state='2' WHERE id=" . intval($v['id'])
                );
                $materialObj->db->exec(
                    "UPDATE sdb_material_shop_goods SET upload_state='2',price_state='2' WHERE id=" . intval($v['shop_goods_id'])
                );
                $shop_goods = $goodsObj->dump(array('id' => $v['shop_goods_id']), 'goods_id');
                $materialObj->db->exec(
                    "UPDATE sdb_material_products SET price=" . floatval($price_tag)
                    . " WHERE sku_bn='" . $v['sku_bn'] . "' AND goods_id=" . intval($shop_goods['goods_id'])
                );
                $logObj->write_log(
                    'goods_upload_price@ome',
                    $v['shop_goods_id'],
                    $v['sku_bn'] . '商品价格' . $v['price'] . '更新为:' . $price_tag
                );
            }
        }
    }
}
