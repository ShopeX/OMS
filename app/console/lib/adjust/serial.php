<?php

/**
 * 库存调整单唯一码列表 Service 桥接
 */
class console_adjust_serial
{
    private static $_service = null;
    private static $_resolved = false;

    public static function service()
    {
        if (!self::$_resolved) {
            self::$_resolved = true;
            foreach (kernel::servicelist('console.service.adjust.serial') as $instance) {
                if (method_exists($instance, 'isEnabled') && $instance->isEnabled()) {
                    self::$_service = $instance;
                    break;
                }
            }
        }

        return self::$_service;
    }

    /**
     * 解析 adjust_items.sn：逗号分隔字符串，兼容历史 json_encode 数组
     *
     * @param mixed $snValue
     * @return string[]
     */
    public static function parseSnList($snValue)
    {
        if ($snValue === null || $snValue === '') {
            return array();
        }

        $raw = trim((string) $snValue);
        if ($raw === '') {
            return array();
        }

        $sns = array();
        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $part) {
                    if (!is_scalar($part)) {
                        continue;
                    }
                    $part = trim((string) $part);
                    if ($part === '') {
                        continue;
                    }
                    foreach (self::splitSnString($part) as $sn) {
                        $sns[] = $sn;
                    }
                }

                return array_values(array_unique($sns));
            }
        }

        return array_values(array_unique(self::splitSnString($raw)));
    }

    /**
     * @param string $snString
     * @return string[]
     */
    protected static function splitSnString($snString)
    {
        $parts = preg_split('/[,，]/', (string) $snString);
        $sns = array();
        foreach ((array) $parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $sns[] = $part;
            }
        }

        return $sns;
    }

    /**
     * 将 adjust_items 按唯一码平铺
     *
     * @param array $adjustItems
     * @return array
     */
    public static function flattenAdjustItems(array $adjustItems)
    {
        return self::flattenSnItems($adjustItems, 'bm_bn', 'bm_name', array('sn'));
    }

    /**
     * 将 iso_items 按唯一码平铺（优先 sn_list，其次 sn）
     *
     * @param array $isoItems
     * @return array
     */
    public static function flattenIsoItems(array $isoItems)
    {
        return self::flattenSnItems($isoItems, 'bn', 'product_name', array('sn_list', 'sn'));
    }

    /**
     * @param array  $items
     * @param string $materialBnKey
     * @param string $nameKey
     * @param array  $snKeys
     * @return array
     */
    public static function flattenSnItems(array $items, $materialBnKey = 'bm_bn', $nameKey = 'bm_name', array $snKeys = array('sn'))
    {
        $rows = array();
        foreach ($items as $item) {
            $snRaw = '';
            foreach ($snKeys as $key) {
                if (!empty($item[$key])) {
                    $snRaw = $item[$key];
                    break;
                }
            }
            $sns = self::parseSnList($snRaw);
            if (empty($sns)) {
                continue;
            }
            foreach ($sns as $sn) {
                $rows[] = array(
                    'material_bn' => trim((string) ($item[$materialBnKey] ?? '')),
                    'bm_name'     => trim((string) ($item[$nameKey] ?? '')),
                    'sn'          => $sn,
                );
            }
        }

        return $rows;
    }

    /**
     * 唯一码列表 Tab 渲染数据
     *
     * @param array $rows
     * @return array
     */
    public static function prepareSerialListViewData(array $rows)
    {
        $columnDefs = self::getColumnDefinitions();
        $columns = array();
        $columnKeys = array();
        foreach ($columnDefs as $key => $def) {
            $columns[] = array(
                'key' => $key,
                'label' => $def['label'],
                'width' => isset($def['width']) ? $def['width'] : 100,
            );
            $columnKeys[] = $key;
        }

        return array(
            'columns' => $columns,
            'column_keys' => $columnKeys,
            'column_count' => max(count($columnKeys), 1),
            'rows' => $rows,
        );
    }

    /**
     * 分页构建调整单唯一码列表（避免一次加载全部 SN + 主档导致内存溢出）
     *
     * @param int $adjustId
     * @param int $page
     * @param int $pageSize
     * @return array view data + pager
     */
    public static function preparePagedAdjustSerialListViewData($adjustId, $page = 1, $pageSize = 50)
    {
        $page = max(1, (int) $page);
        $pageSize = max(1, min(200, (int) $pageSize));
        $offset = ($page - 1) * $pageSize;

        $items = app::get('console')->model('adjust_items')->getList(
            'bm_bn,bm_name,sn',
            array('adjust_id' => $adjustId)
        );

        $total = 0;
        $pageRows = array();
        foreach ((array) $items as $item) {
            $sns = self::parseSnList($item['sn'] ?? '');
            if (empty($sns)) {
                continue;
            }
            foreach ($sns as $sn) {
                if ($total >= $offset && count($pageRows) < $pageSize) {
                    $pageRows[] = array(
                        'material_bn' => trim((string) ($item['bm_bn'] ?? '')),
                        'bm_name'     => trim((string) ($item['bm_name'] ?? '')),
                        'sn'          => $sn,
                    );
                }
                $total++;
            }
            // 已凑满当前页后仍继续计数以得到 total
        }
        unset($items);

        $pageRows = self::enrichFlatSerialRows($pageRows);
        $viewData = self::prepareSerialListViewData($pageRows);
        $viewData['pager'] = self::buildSerialPager($total, $page, $pageSize, 'detail_serial');

        return $viewData;
    }

    /**
     * 分页构建出入库单唯一码列表
     *
     * @param int $isoId
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public static function preparePagedIsoSerialListViewData($isoId, $page = 1, $pageSize = 50)
    {
        $page = max(1, (int) $page);
        $pageSize = max(1, min(200, (int) $pageSize));
        $offset = ($page - 1) * $pageSize;

        $items = app::get('taoguaniostockorder')->model('iso_items')->getList(
            'bn,product_name,sn_list,sn',
            array('iso_id' => $isoId)
        );

        $total = 0;
        $pageRows = array();
        foreach ((array) $items as $item) {
            $snRaw = '';
            if (!empty($item['sn_list'])) {
                $snRaw = $item['sn_list'];
            } elseif (!empty($item['sn'])) {
                $snRaw = $item['sn'];
            }
            $sns = self::parseSnList($snRaw);
            if (empty($sns)) {
                continue;
            }
            foreach ($sns as $sn) {
                if ($total >= $offset && count($pageRows) < $pageSize) {
                    $pageRows[] = array(
                        'material_bn' => trim((string) ($item['bn'] ?? '')),
                        'bm_name'     => trim((string) ($item['product_name'] ?? '')),
                        'sn'          => $sn,
                    );
                }
                $total++;
            }
        }
        unset($items);

        $pageRows = self::enrichFlatSerialRows($pageRows);
        $viewData = self::prepareSerialListViewData($pageRows);
        $viewData['pager'] = self::buildSerialPager($total, $page, $pageSize, 'detail_serial');

        return $viewData;
    }

    /**
     * 仅对当前页平铺行补充扩展字段（goldentra 等）
     *
     * @param array $flatRows
     * @return array
     */
    public static function enrichFlatSerialRows(array $flatRows)
    {
        if (empty($flatRows)) {
            return array();
        }
        $service = self::service();
        if ($service && method_exists($service, 'buildRowsFromFlatRows')) {
            return $service->buildRowsFromFlatRows($flatRows);
        }

        return $flatRows;
    }

    /**
     * @param int    $total
     * @param int    $page
     * @param int    $pageSize
     * @param string $finderview
     * @return array
     */
    public static function buildSerialPager($total, $page, $pageSize, $finderview = 'detail_serial')
    {
        $total = (int) $total;
        $pageSize = max(1, (int) $pageSize);
        $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1;
        $page = min(max(1, (int) $page), $totalPages);

        $query = $_GET;
        if (!is_array($query)) {
            $query = array();
        }
        $query['action'] = 'detail';
        $query['finderview'] = $finderview;
        if (empty($query['id']) && !empty($_GET['id'])) {
            $query['id'] = $_GET['id'];
        }

        $makeUrl = function ($p) use ($query) {
            $query['serial_page'] = $p;
            return 'index.php?' . http_build_query($query);
        };

        $finderId = '';
        if (!empty($_GET['_finder']['finder_id'])) {
            $finderId = $_GET['_finder']['finder_id'];
        } elseif (!empty($_GET['finder_id'])) {
            $finderId = $_GET['finder_id'];
        }

        return array(
            'total'         => $total,
            'page'          => $page,
            'page_size'     => $pageSize,
            'total_pages'   => $totalPages,
            'has_prev'      => $page > 1,
            'has_next'      => $page < $totalPages,
            'prev_url'      => $page > 1 ? $makeUrl($page - 1) : '',
            'next_url'      => $page < $totalPages ? $makeUrl($page + 1) : '',
            'update_target' => $finderId ? ('finder-detail-' . $finderId) : '',
        );
    }

    public static function getColumnDefinitions()
    {
        $service = self::service();
        if ($service && method_exists($service, 'getColumnDefinitions')) {
            return $service->getColumnDefinitions();
        }

        return array(
            'material_bn' => array('label' => '货号', 'width' => 160),
            'sn'          => array('label' => '唯一码', 'width' => 120),
        );
    }

    public static function buildRowsFromAdjustItems(array $adjustItems)
    {
        $service = self::service();
        if ($service && method_exists($service, 'buildRowsFromAdjustItems')) {
            return $service->buildRowsFromAdjustItems($adjustItems);
        }

        return self::flattenAdjustItems($adjustItems);
    }

    public static function buildRowsFromIsoItems(array $isoItems)
    {
        $service = self::service();
        if ($service && method_exists($service, 'buildRowsFromFlatRows')) {
            return $service->buildRowsFromFlatRows(self::flattenIsoItems($isoItems));
        }

        return self::flattenIsoItems($isoItems);
    }

    /**
     * 导出 snlists Sheet 行数据（GBK CSV）
     */
    public static function buildExportSnListCsvData($firstColLabel, array $bills, $billPk, $billBnField, array $itemsByBill, $buildRowsCallback, $first_sheet)
    {
        $columnDefs = self::getColumnDefinitions();
        $columnKeys = array_keys($columnDefs);

        $title = array($firstColLabel);
        foreach ($columnDefs as $def) {
            $title[] = $def['label'];
        }

        $data = array();
        foreach ($bills as $bill) {
            $billId = $bill[$billPk];
            $items = !empty($itemsByBill[$billId]) ? $itemsByBill[$billId] : array();
            if (empty($items)) {
                continue;
            }
            $rows = call_user_func($buildRowsCallback, $items);
            foreach ($rows as $row) {
                $data[] = self::buildExportSnListCsvLine($bill[$billBnField], $row, $columnKeys);
            }
        }

        if ($first_sheet) {
            array_unshift($data, self::buildExportCsvTitleLine($title));
        }

        return $data;
    }

    public static function buildExportCsvTitleLine(array $title)
    {
        $encoded = array();
        foreach ($title as $label) {
            $encoded[] = mb_convert_encoding((string) $label, 'GBK', 'UTF-8');
        }

        return implode(',', $encoded);
    }

    public static function buildExportSnListCsvLine($billBn, array $row, array $columnKeys)
    {
        $fields = array(self::encodeExportCsvField($billBn));
        foreach ($columnKeys as $key) {
            $fields[] = self::encodeExportCsvField(isset($row[$key]) ? $row[$key] : '');
        }

        return self::joinExportCsvFields($fields);
    }

    public static function encodeExportCsvField($value)
    {
        return mb_convert_encoding((string) $value, 'GBK', 'UTF-8');
    }

    public static function joinExportCsvFields(array $fields)
    {
        $out = array();
        foreach ($fields as $field) {
            $str = (string) $field;
            if (strpos($str, ',') !== false || strpos($str, '"') !== false || strpos($str, "\n") !== false) {
                $str = '"' . str_replace('"', '""', $str) . '"';
            }
            $out[] = $str;
        }

        return implode(',', $out);
    }
}
