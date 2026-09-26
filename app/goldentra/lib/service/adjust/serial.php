<?php

/**
 * 库存调整单明细唯一码平铺列表（字段定义与数据加载）
 */
class goldentra_service_adjust_serial
{
    const PRICE_METHOD_LABELS = array(
        '1' => '计价（按克）',
        '2' => '定价（按件）',
    );

    public function isEnabled()
    {
        return app::get('goldentra')->is_installed();
    }

    /**
     * 列表列定义（唯一配置源）
     *
     * @return array[] key => ['label' => string, 'width' => int]
     */
    public function getColumnDefinitions()
    {
        return array(
            'material_bn' => array('label' => '货号', 'width' => 160),
            'sn'          => array('label' => '唯一码', 'width' => 120),
            'process_cost' => array('label' => '工费', 'width' => 90),
            'mold_code'   => array('label' => '模号编码', 'width' => 110),
            'weight'      => array('label' => '克重', 'width' => 90),
            'main_weight' => array('label' => '主石重', 'width' => 90),
            'secondary_weight' => array('label' => '辅石重', 'width' => 90),
            'price_method' => array('label' => '计价方式', 'width' => 90),
        );
    }

    /**
     * 将调整单明细按唯一码平铺，并补充 goldentra 主档字段
     *
     * @param array $adjustItems console adjust_items 行
     * @return array
     */
    public function buildRowsFromAdjustItems(array $adjustItems)
    {
        return $this->buildRowsFromFlatRows(console_adjust_serial::flattenAdjustItems($adjustItems));
    }

    /**
     * 已平铺的唯一码行补充 goldentra 主档字段
     *
     * @param array $flatRows
     * @return array
     */
    public function buildRowsFromFlatRows(array $flatRows)
    {
        if (empty($flatRows)) {
            return array();
        }

        $masterMaps = $this->loadMasterMaps($flatRows);
        foreach ($flatRows as $key => $row) {
            $master = $this->resolveMasterRow($row, $masterMaps);
            $flatRows[$key] = $this->mergeMasterRow($row, $master);
        }

        return $flatRows;
    }

    /**
     * 批量加载主档：优先 unique_code，补充 bn 兜底（同货号多唯一码时取最新一条）
     *
     * @param array $flatRows
     * @return array ['by_sn' => [], 'by_bn' => []]
     */
    protected function loadMasterMaps(array $flatRows)
    {
        $maps = array(
            'by_sn' => array(),
            'by_bn' => array(),
        );
        if (!$this->isEnabled()) {
            return $maps;
        }

        $uniqueCodes = array();
        $bnList = array();
        foreach ($flatRows as $row) {
            $sn = trim((string) ($row['sn'] ?? ''));
            if ($sn !== '') {
                $uniqueCodes[] = $sn;
            }
            $bn = trim((string) ($row['material_bn'] ?? ''));
            if ($bn !== '') {
                $bnList[] = $bn;
            }
        }

        $uniqueCodes = array_values(array_unique($uniqueCodes));
        $bnList = array_values(array_unique($bnList));
        if (empty($uniqueCodes) && empty($bnList)) {
            return $maps;
        }

        $materialMdl = app::get('goldentra')->model('material');
        $selectCols = 'unique_code,bn,mold_code,process_cost,weight,main_weight,secondary_weight,price_method,material_id';

        if (!empty($uniqueCodes)) {
            $list = $materialMdl->getList(
                $selectCols,
                array('unique_code|in' => $uniqueCodes),
                0,
                -1
            );
            foreach ((array) $list as $row) {
                $code = trim((string) ($row['unique_code'] ?? ''));
                if ($code !== '') {
                    $maps['by_sn'][$code] = $row;
                }
            }
        }

        if (!empty($bnList)) {
            $list = $materialMdl->getList(
                $selectCols,
                array('bn|in' => $bnList),
                0,
                -1,
                'material_id DESC'
            );
            foreach ((array) $list as $row) {
                $bn = trim((string) ($row['bn'] ?? ''));
                if ($bn !== '' && !isset($maps['by_bn'][$bn])) {
                    $maps['by_bn'][$bn] = $row;
                }
            }
        }

        return $maps;
    }

    /**
     * @param array $base
     * @param array $masterMaps
     * @return array
     */
    protected function resolveMasterRow(array $base, array $masterMaps)
    {
        $sn = trim((string) ($base['sn'] ?? ''));
        if ($sn !== '' && !empty($masterMaps['by_sn'][$sn])) {
            return $masterMaps['by_sn'][$sn];
        }

        $bn = trim((string) ($base['material_bn'] ?? ''));
        if ($bn !== '' && !empty($masterMaps['by_bn'][$bn])) {
            return $masterMaps['by_bn'][$bn];
        }

        return array();
    }

    /**
     * @param array $base
     * @param array $master
     * @return array
     */
    protected function mergeMasterRow(array $base, array $master)
    {
        return array(
            'material_bn'      => trim((string) ($base['material_bn'] ?? '')),
            'sn'               => $base['sn'],
            'process_cost'     => $this->formatDecimal($master['process_cost'] ?? '', 2),
            'mold_code'        => trim((string) ($master['mold_code'] ?? '')),
            'weight'           => $this->formatDecimal($master['weight'] ?? '', 3),
            'main_weight'      => $this->formatDecimal($master['main_weight'] ?? '', 3),
            'secondary_weight' => $this->formatDecimal($master['secondary_weight'] ?? '', 3),
            'price_method'     => $this->formatPriceMethod($master['price_method'] ?? ''),
        );
    }

    protected function formatDecimal($value, $scale = 3)
    {
        if ($value === '' || $value === null) {
            return '';
        }
        if (!is_numeric($value)) {
            return (string) $value;
        }
        if ((float) $value == 0) {
            return number_format(0, (int) $scale, '.', '');
        }

        return number_format((float) $value, (int) $scale, '.', '');
    }

    protected function formatPriceMethod($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return isset(self::PRICE_METHOD_LABELS[$value])
            ? self::PRICE_METHOD_LABELS[$value]
            : $value;
    }
}
