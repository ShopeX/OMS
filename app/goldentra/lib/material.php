<?php

/**
 * 唯一码主档：SAP materialAdd 落库
 */
class goldentra_material
{
    /** SAP/POS 计价方式 → OMS enum(1=计价按克,2=定价按件) */
    public static $price_method_maps = array(
        'jj' => '1',
        'dj' => '2',
        '1' => '1',
        '2' => '2',
        '计价' => '1',
        '定价' => '2',
        '计价（按克）' => '1',
        '定价（按件）' => '2',
        '按克' => '1',
        '按件' => '2',
    );

    /** SAP/POS 商品类型 → OMS enum(0=无,1=经销,2=代销) */
    public static $sales_mode_maps = array(
        '0' => '0',
        '1' => '1',
        '2' => '2',
        '无' => '0',
        '经销' => '1',
        '代销' => '2',
    );

    public static $sales_mode_default = '0';

    public static function fieldMap()
    {
        return goldentra_material_fields::$field_map;
    }

    public function parseRow($raw, $data_source = 'sap', $sync_batch_no = '')
    {
        $raw = goldentra_material_fields::normalizeRow($raw);
        $row = array(
            'data_source' => $data_source,
            'sync_batch_no' => $sync_batch_no,
            'bn' => '',
            'match_status' => '0',
            'process_status' => 'pending',
            'process_msg' => '',
            'at_time' => time(),
            'up_time' => time(),
        );

        foreach (goldentra_material_fields::$field_map as $api => $col) {
            $val = isset($raw[$col]) ? $raw[$col] : (isset($raw[$api]) ? $raw[$api] : '');
            $row[$col] = $val === null ? '' : $val;
        }
        $row['ext_field'] = goldentra_material_fields::buildExtField($raw);
        if ($row['goods_name'] === '' && !empty($row['style_name'])) {
            $row['goods_name'] = $row['style_name'];
        }

        $row['sales_mode'] = self::normalizeSalesMode($row['sales_mode']);
        $row['price_method'] = self::normalizePriceMethod($row['price_method']);
        $row = self::fillDictNames($row);
        if ($row['pic_url'] !== '' && $row['pic_url'] !== null) {
            $row['pic_url'] = goldentra_material_image::resolveProductUrl($row['pic_url']);
        }
        if ($row['weight'] === '' || $row['weight'] === null) {
            $row['weight'] = 0;
        }
        if ($row['main_weight'] === '' || $row['main_weight'] === null) {
            $row['main_weight'] = 0;
        }
        if ($row['secondary_weight'] === '' || $row['secondary_weight'] === null) {
            $row['secondary_weight'] = 0;
        }
        if ($row['price'] === '' || $row['price'] === null) {
            $row['price'] = 0;
        }
        if ($row['process_cost'] === '' || $row['process_cost'] === null) {
            $row['process_cost'] = 0;
        }

        return $row;
    }

    public static function normalizeSalesMode($val)
    {
        $val = trim((string) $val);
        if ($val === '') {
            return '';
        }
        if (isset(self::$sales_mode_maps[$val])) {
            return self::$sales_mode_maps[$val];
        }
        return $val;
    }

    public static function normalizePriceMethod($val)
    {
        $val = trim((string) $val);
        if ($val === '') {
            return '';
        }
        if (isset(self::$price_method_maps[$val])) {
            return self::$price_method_maps[$val];
        }
        return $val;
    }

    /** V1.5 编码→名称字典映射 */
    public static $dict_name_pairs = array(
        array('cate_code', 'cate_name'),
        array('class_code', 'class_name'),
        array('texture_code', 'texture_name'),
        array('small_series_code', 'small_series_name'),
    );

    /**
     * V1.5：SAP 不再传名称字段，按编码查数据字典回填名称
     */
    public static function fillDictNames(array $row)
    {
        foreach (self::$dict_name_pairs as $pair) {
            list($codeKey, $nameKey) = $pair;
            if (empty($row[$codeKey])) {
                $row[$nameKey] = '';
                continue;
            }
            $name = goldentra_metadata::lookupMaterialDictLabel($codeKey, $row[$codeKey]);
            $row[$nameKey] = $name !== '' ? $name : $row[$codeKey];
        }
        return $row;
    }

    /**
     * 按 OMS 必填规则校验
     *
     * @return array 缺失的 API 字段名
     */
    public function validateRequired($row)
    {
        $missing = array();

        foreach (goldentra_material_fields::$oms_required as $api) {
            $col = goldentra_material_fields::apiToColumn($api);
            if ($col === '') {
                continue;
            }
            if ($this->isEmpty($row[$col])) {
                $missing[] = $api;
            }
        }

        if (goldentra_material_spec::isDiamond($row)) {
            foreach (goldentra_material_fields::$diamond_required as $api) {
                $col = goldentra_material_fields::apiToColumn($api);
                if ($this->isEmpty($row[$col])) {
                    $missing[] = $api;
                }
            }
        }

        if (!empty($row['price_method']) && !in_array((string) $row['price_method'], array('1', '2'), true)) {
            $missing[] = 'price_method';
        }
        if (!empty($row['sales_mode']) && !in_array((string) $row['sales_mode'], array('0', '1', '2'), true)) {
            $missing[] = 'sales_mode';
        }

        return array_values(array_unique($missing));
    }

    private function isEmpty($val)
    {
        return $val === '' || $val === null;
    }

    /**
     * 同唯一码多门店推送：备注1(ext_1)=X 才允许覆盖门店编码、金价定价维度
     *
     * @param array $raw 原始接口行
     * @param array $row 解析后行（含 ext_field）
     * @return bool
     */
    public function shouldUpdateStoreDimensionFields(array $raw, array $row = array())
    {
        $remark1 = goldentra_api_normalize::pickString($raw, 'ext_1');
        if ($remark1 === '' && !empty($row['ext_field'])) {
            $ext = json_decode($row['ext_field'], true);
            if (is_array($ext) && isset($ext['ext_1'])) {
                $remark1 = trim((string) $ext['ext_1']);
            }
        }

        return strtoupper($remark1) === 'X';
    }

    public function upsertFromApi($raw, $data_source = 'sap', $sync_batch_no = '')
    {
        $rowKey = array(
            'unique_code' => goldentra_api_normalize::pickString($raw, 'unique_code'),
        );
        $row = $this->parseRow($raw, $data_source, $sync_batch_no);
        if ($rowKey['unique_code'] === '' && $row['unique_code'] !== '') {
            $rowKey['unique_code'] = $row['unique_code'];
        }

        $missing = $this->validateRequired($row);
        $row = $this->applyRowDefaultsForSave($row);

        // 无唯一码：生成占位码落库并标记异常（表 unique 约束要求非空且不冲突）
        if ($this->isEmpty($row['unique_code'])) {
            $row['unique_code'] = $this->genPlaceholderUniqueCode($sync_batch_no);
            if (!in_array('unique_code', $missing, true)) {
                array_unshift($missing, 'unique_code');
            }
        }
        $rowKey['unique_code'] = $rowKey['unique_code'] !== '' ? $rowKey['unique_code'] : $row['unique_code'];

        if ($missing) {
            // 缺必填：正常接收落库，标记异常，不进入转换队列
            $row['process_status'] = 'none';
            $row['process_msg'] = goldentra_api_normalize::formatMissingFieldsMessage(
                $missing,
                goldentra_material_fields::$descriptions
            );
            $row['process_time'] = time();
            $row['match_status'] = '0';
            $row['bn'] = '';
        } else {
            $row['process_status'] = 'pending';
            $row['process_msg'] = '';
            $row['process_time'] = 0;
        }

        $mdl = app::get('goldentra')->model('material');
        // 占位唯一码每次新建，真实唯一码才做 upsert
        $exist = null;
        if (!$this->isPlaceholderUniqueCode($row['unique_code'])) {
            $exist = $mdl->dump(array('unique_code' => $row['unique_code']), 'material_id,process_status,bn');
        }

        // SAP 主档下发时自动生成模号（sdb_goldentra_mold）
        if (!$this->isEmpty($row['mold_code'])) {
            app::get('goldentra')->model('mold')->ensureFromCode($row['mold_code']);
        }

        if ($exist) {
            unset($row['at_time']);
            // 已转成功的记录若再次推送完整数据，保留 bn，交由 pending 重映射
            if ($row['process_status'] === 'pending' && !empty($exist['bn'])) {
                $row['bn'] = $exist['bn'];
            }
            $oldRow = $mdl->dump($exist['material_id'], '*');
            // 同唯一码多门店同步：仅当备注1(ext_1)=X 时更新门店编码、金价定价维度
            if (!$this->shouldUpdateStoreDimensionFields($raw, $row)) {
                $row['store_bn'] = isset($oldRow['store_bn']) ? $oldRow['store_bn'] : '';
                $row['pricedimension_code'] = isset($oldRow['pricedimension_code'])
                    ? $oldRow['pricedimension_code']
                    : '';
            }
            $mdl->update($row, array('material_id' => $exist['material_id']));
            goldentra_material_changelog::logUpdate(
                $exist['material_id'],
                $row['unique_code'],
                $oldRow,
                $row,
                $sync_batch_no
            );

            // 计价方式变更 / 料号前缀与计价方式不一致 → 必须入队迁移事件（不能只打标）
            // 注意：打标由 enqueueFromUpsert / enqueueIfBnPrefixMismatch 内部完成；
            // 原先仅在 pending 时入队，会导致 process_status=none/succ 时「有转换标、无事件、bn 未重匹配」。
            $oldRow['material_id'] = $exist['material_id'];
            $pricetype = kernel::single('goldentra_material_pricetype');
            $check = $row;
            $check['material_id'] = $exist['material_id'];
            if (empty($check['bn']) && !empty($oldRow['bn'])) {
                $check['bn'] = $oldRow['bn'];
            } elseif (empty($check['bn']) && !empty($exist['bn'])) {
                $check['bn'] = $exist['bn'];
            }

            $oldPm = isset($oldRow['price_method']) ? (string) $oldRow['price_method'] : '';
            $newPm = isset($row['price_method']) ? (string) $row['price_method'] : '';
            if ($oldPm !== '' && $newPm !== '' && $oldPm !== $newPm
                && in_array($oldPm, array('1', '2'), true) && in_array($newPm, array('1', '2'), true)
            ) {
                // 入队时 oldRow.bn 必须是变更前料号；newRow 可暂挂旧 bn（重匹配在事件处理里做）
                $enqueueOld = $oldRow;
                if (empty($enqueueOld['bn']) && !empty($exist['bn'])) {
                    $enqueueOld['bn'] = $exist['bn'];
                }
                $enqueueNew = $row;
                $enqueueNew['material_id'] = $exist['material_id'];
                if (empty($enqueueNew['bn']) && !empty($enqueueOld['bn'])) {
                    $enqueueNew['bn'] = $enqueueOld['bn'];
                }
                $pricetype->enqueueFromUpsert($enqueueOld, $enqueueNew);
            }

            // 字段未变但 J/D 前缀与 price_method 已不一致（历史脏数据）→ 补入队
            $pricetype->enqueueIfBnPrefixMismatch($check);

            return array(true, $row['unique_code'], $row['process_status'], $row['process_msg']);
        }

        $material_id = $mdl->insert($row);
        if ($material_id) {
            goldentra_material_changelog::logAdd($material_id, $row['unique_code'], $sync_batch_no);
        }
        return array(true, $row['unique_code'], $row['process_status'], $row['process_msg']);
    }

    /**
     * 无唯一码落库用的占位码（varchar(30)，前缀 NOUC_）
     */
    public function genPlaceholderUniqueCode($sync_batch_no = '')
    {
        $seed = $sync_batch_no . '|' . microtime(true) . '|' . mt_rand();
        return 'NOUC_' . substr(md5($seed), 0, 25);
    }

    public function isPlaceholderUniqueCode($unique_code)
    {
        return strpos((string) $unique_code, 'NOUC_') === 0;
    }

    /**
     * 可选字段落库默认值
     */
    private function applyRowDefaultsForSave(array $row)
    {
        if ($row['sales_mode'] === '' || $row['sales_mode'] === null) {
            $row['sales_mode'] = self::$sales_mode_default;
        }
        return $row;
    }

    public function markAbnormal($material_id, $msg)
    {
        return app::get('goldentra')->model('material')->update(
            array(
                'process_status' => 'none',
                'process_msg' => $msg,
                'process_time' => time(),
                'up_time' => time(),
            ),
            array('material_id' => $material_id)
        );
    }

    public function markFail($material_id, $msg)
    {
        return app::get('goldentra')->model('material')->update(
            array(
                'process_status' => 'fail',
                'process_msg' => $msg,
                'process_time' => time(),
                'up_time' => time(),
            ),
            array('material_id' => $material_id)
        );
    }

    public function markSuccess($material_id, $bn)
    {
        return app::get('goldentra')->model('material')->update(
            array(
                'bn' => $bn,
                'match_status' => '1',
                'process_status' => 'succ',
                'process_msg' => '',
                'process_time' => time(),
                'up_time' => time(),
            ),
            array('material_id' => $material_id)
        );
    }

    public function updatePriceByBn($bn)
    {
        if (empty($bn)) {
            return false;
        }

        $db = kernel::database();
        $bn_esc = kernel::single('goldentra_db')->escape($bn, $db);
        $sql = "SELECT MIN(m.price) AS min_price FROM sdb_goldentra_material AS m
            LEFT JOIN sdb_wms_product_serial AS wps ON wps.serial_number = m.unique_code
            WHERE m.bn = '{$bn_esc}' AND m.price_method = '2'
            AND wps.status IN ('0', '4', '5')";
        $data = $db->selectrow($sql);
        if (empty($data['min_price'])) {
            return false;
        }

        try {
            kernel::single('goldentra_material_price')->updateTagPrice($bn, $data['min_price']);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
