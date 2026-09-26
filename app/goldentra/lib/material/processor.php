<?php

class goldentra_material_processor
{
    const BATCH_SIZE = 50;

    public function processPending($limit = self::BATCH_SIZE)
    {
        $mdl = app::get('goldentra')->model('material');
        $list = $mdl->getList('*', array('process_status' => 'pending'), 0, $limit, 'material_id ASC');
        $processed = 0;

        foreach ($list as $row) {
            if ($this->processRow($row)) {
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * 接口同步：按 unique_code 转基础物料
     */
    public function processByUniqueCodes(array $unique_codes)
    {
        $unique_codes = array_values(array_filter(array_unique(array_map('trim', $unique_codes))));
        if (!$unique_codes) {
            return array('ok' => 0, 'fail' => 0, 'fail_msgs' => array('unique_codes 为空'));
        }

        $mdl = app::get('goldentra')->model('material');
        $list = $mdl->getList('*', array('unique_code|in' => $unique_codes), 0, -1);
        $byCode = array();
        foreach ((array) $list as $row) {
            $byCode[$row['unique_code']] = $row;
        }

        $ok = 0;
        $fail = 0;
        $fail_msgs = array();

        foreach ($unique_codes as $unique_code) {
            if (empty($byCode[$unique_code])) {
                $fail++;
                $fail_msgs[] = $unique_code . '：记录不存在';
                continue;
            }
            $row = $byCode[$unique_code];
            if ($row['process_status'] === 'none') {
                $fail++;
                $fail_msgs[] = $unique_code . '：数据异常不可转换'
                    . (!empty($row['process_msg']) ? '（' . $row['process_msg'] . '）' : '');
                continue;
            }
            if ($row['process_status'] === 'succ' && !empty($row['bn'])) {
                $ok++;
                continue;
            }
            if ($this->processRow($row)) {
                $ok++;
            } else {
                $fail++;
                $fresh = $mdl->dump($row['material_id'], 'unique_code,process_msg');
                $fail_msgs[] = $fresh['unique_code'] . '：' . ($fresh['process_msg'] ? $fresh['process_msg'] : '转换失败');
            }
        }

        return array(
            'ok' => $ok,
            'fail' => $fail,
            'fail_msgs' => $fail_msgs,
        );
    }

    /**
     * 人工转换：按主档 ID 批量转基础物料
     */
    public function processByIds(array $material_ids)
    {
        $mdl = app::get('goldentra')->model('material');
        $ok = 0;
        $fail = 0;
        $fail_msgs = array();

        foreach ($material_ids as $material_id) {
            $material_id = intval($material_id);
            if ($material_id <= 0) {
                continue;
            }
            $row = $mdl->dump($material_id, '*');
            if (!$row) {
                $fail++;
                $fail_msgs[] = 'ID ' . $material_id . '：记录不存在';
                continue;
            }
            if ($row['process_status'] === 'none') {
                $fail++;
                $fail_msgs[] = $row['unique_code'] . '：数据异常不可转换'
                    . (!empty($row['process_msg']) ? '（' . $row['process_msg'] . '）' : '');
                continue;
            }
            if ($row['process_status'] === 'succ' && !empty($row['bn'])) {
                $fail++;
                $fail_msgs[] = $row['unique_code'] . '：已转换（' . $row['bn'] . '）';
                continue;
            }
            if ($this->processRow($row)) {
                $ok++;
            } else {
                $fail++;
                $fresh = $mdl->dump($material_id, 'unique_code,process_msg');
                $fail_msgs[] = $fresh['unique_code'] . '：' . ($fresh['process_msg'] ? $fresh['process_msg'] : '转换失败');
            }
        }

        return array(
            'ok' => $ok,
            'fail' => $fail,
            'fail_msgs' => $fail_msgs,
        );
    }

    public function processRow($row)
    {
        $mdl = app::get('goldentra')->model('material');
        $mdl->update(
            array('process_status' => 'running', 'up_time' => time()),
            array('material_id' => $row['material_id'])
        );

        try {
            $pricetype = kernel::single('goldentra_material_pricetype');
            // 价格类型变更：冻结 → 调整单出库清空 → 重匹配 → 调整单入库
            $priceTypeRet = $pricetype->processIfNeeded($row);
            if ($priceTypeRet !== null) {
                return $priceTypeRet;
            }

            $old_bn = !empty($row['bn']) ? $row['bn'] : '';
            $bn = $this->processOne($row);
            if ($bn) {
                // 禁止静默跨 D/J 重映射：补迁移事件后改走价格类型流程
                if ($old_bn !== '' && $old_bn !== $bn && $pricetype->isCrossPriceTypeBn($old_bn, $bn)) {
                    $mdl->update(
                        array(
                            'bn' => $old_bn,
                            'process_status' => 'pending',
                            'up_time' => time(),
                        ),
                        array('material_id' => $row['material_id'])
                    );
                    $impliedOld = $pricetype->priceMethodFromBn($old_bn);
                    $impliedNew = $pricetype->priceMethodFromBn($bn);
                    if ($impliedOld === '') {
                        $impliedOld = ($impliedNew === '1') ? '2' : '1';
                    }
                    if ($impliedNew === '') {
                        $impliedNew = (string) $row['price_method'];
                    }
                    $pricetype->enqueueFromUpsert(
                        array(
                            'material_id' => $row['material_id'],
                            'unique_code' => $row['unique_code'],
                            'bn' => $old_bn,
                            'price_method' => $impliedOld,
                        ),
                        array(
                            'material_id' => $row['material_id'],
                            'unique_code' => $row['unique_code'],
                            'bn' => $old_bn,
                            'price_method' => $impliedNew,
                            'sync_batch_no' => isset($row['sync_batch_no']) ? $row['sync_batch_no'] : '',
                        )
                    );
                    $fresh = $mdl->dump($row['material_id'], '*');
                    $priceTypeRet = $pricetype->processIfNeeded($fresh);
                    if ($priceTypeRet !== null) {
                        return $priceTypeRet;
                    }
                }
                if ($old_bn !== '' && $old_bn !== $bn) {
                    goldentra_material_changelog::logRemap($row['material_id'], $old_bn, $bn);
                }
                kernel::single('goldentra_material')->markSuccess($row['material_id'], $bn);
                if ($row['price_method'] == '2') {
                    kernel::single('goldentra_material')->updatePriceByBn($bn);
                }
                $bm = app::get('material')->model('basic_material')->dump(array('material_bn' => $bn), 'bm_id');
                if (!empty($row['sales_mode']) && $bm) {
                    goldentra_material_jewelry_props::saveProps($bm['bm_id'], array('sales_mode' => $row['sales_mode']));
                }
                if ($bm && !empty($row['pic_url'])) {
                    goldentra_material_image::attachRemoteUrl($bm['bm_id'], $row['pic_url']);
                }
                return true;
            }
            kernel::single('goldentra_material')->markFail($row['material_id'], '转基础物料失败');
        } catch (Exception $e) {
            $status = app::get('goldentra')->model('material')->dump($row['material_id'], 'process_status');
            // 必填缺失已标记异常(none)，勿覆盖成 fail
            if (empty($status['process_status']) || $status['process_status'] !== 'none') {
                kernel::single('goldentra_material')->markFail($row['material_id'], $e->getMessage());
            }
        }

        return false;
    }

    public function processOne($row)
    {
        $missing = kernel::single('goldentra_material')->validateRequired($row);
        if ($missing) {
            $msg = goldentra_api_normalize::formatMissingFieldsMessage(
                $missing,
                goldentra_material_fields::$descriptions
            );
            kernel::single('goldentra_material')->markAbnormal($row['material_id'], $msg);
            throw new Exception($msg);
        }

        $mappingLib = kernel::single('goldentra_mapping');
        $norm = array();
        $mapping_item = null;
        $is_diamond = goldentra_material_spec::isDiamond($row);

        if ($is_diamond) {
            $item = $mappingLib->getDiamondMappingItem(
                $row['mold_code'],
                $row['fcolor'],
                $row['fpurity'],
                $row['fcut'],
                $row['main_weight']
            );
            if (empty($item)) {
                throw new Exception(sprintf(
                    'Mapping未匹配[%s,color:%s,purity:%s,cut:%s,main:%s]',
                    $row['mold_code'],
                    $row['fcolor'],
                    $row['fpurity'],
                    $row['fcut'],
                    $row['main_weight']
                ));
            }
            $norm = array(
                'main_weight' => $item['main_weight'],
                'tag_price' => $item['tag_price'],
            );
            $mapping_item = $item;
        } elseif ($row['price_method'] == '2') {
            $item = $mappingLib->getGoldMappingItem(
                $row['mold_code'],
                $row['weight'],
                $row['main_weight'],
                $row['secondary_weight']
            );
            if (empty($item)) {
                throw new Exception(sprintf(
                    'Mapping未匹配[%s,weight:%s,main:%s,extra:%s]',
                    $row['mold_code'],
                    $row['weight'],
                    $row['main_weight'],
                    $row['secondary_weight']
                ));
            }
            $norm = array(
                'weight' => $item['weight'],
                'main_weight' => $item['main_weight'],
                'secondary_weight' => $item['extra_weight'],
                'tag_price' => $item['tag_price'],
            );
            $mapping_item = $item;
        } else {
            $norm = array(
                'weight' => $row['weight'],
            );
        }

        $bn = $this->_resolveBn($row, $norm, $is_diamond, $mapping_item);
        if (empty($bn)) {
            throw new Exception('转基础物料失败');
        }

        return $bn;
    }

    private function _resolveBn($row, $norm, $is_diamond, $mapping_item = null)
    {
        if (!$is_diamond && $row['price_method'] == '2' && !empty($mapping_item)) {
            return kernel::single('goldentra_mapping')->resolveBasicMaterialByMappingItem($row, $norm, $mapping_item);
        }

        $weight = isset($norm['weight']) ? $norm['weight'] : $row['weight'];
        $main_weight = isset($norm['main_weight']) ? $norm['main_weight'] : $row['main_weight'];
        $secondary_weight = isset($norm['secondary_weight']) ? $norm['secondary_weight'] : $row['secondary_weight'];
        $process_cost = $row['process_cost'];

        if ($is_diamond) {
            $index = goldentra_material_spec::diamondSearchIndex(
                $row['mold_code'],
                $row['fcolor'],
                $row['fpurity'],
                $row['fcut'],
                $main_weight
            );
        } else {
            $index = goldentra_material_spec::moldSearchIndex(
                (int) $row['price_method'],
                $row['mold_code'],
                $weight,
                $main_weight,
                $secondary_weight,
                $process_cost
            );
        }

        $self = $this;
        return kernel::single('goldentra_db')->withLock('bm_spec_' . $index[1], 15, function () use (
            $self,
            $row,
            $norm,
            $mapping_item,
            $index
        ) {
            $exist = goldentra_material_spec::findBasicMaterialByIndex($index[0], $index[1]);
            if ($exist) {
                return $self->_reuseExistingBn($row, $exist, $mapping_item);
            }

            $mapping_item_id = !empty($mapping_item['item_id']) ? $mapping_item['item_id'] : 0;
            $created = kernel::single('goldentra_material_bootstrap')->createFromMaterialRow(
                $row,
                $norm,
                $mapping_item_id
            );
            return $created ? $created['material_bn'] : '';
        });
    }

    /**
     * 复用已有基础物料：回写 mapping / 品牌 / 规格
     *
     * @param array $row
     * @param array $exist
     * @param array|null $mapping_item
     * @return string
     */
    private function _reuseExistingBn($row, $exist, $mapping_item = null)
    {
        if (!empty($mapping_item['item_id'])) {
            kernel::single('goldentra_mapping')->updateMappingItemBn(
                $mapping_item['item_id'],
                $exist['material_bn'],
                $exist['bm_id']
            );
        }
        if (!empty($row['pricedimension_code'])) {
            goldentra_pricedimension::syncBrandToMaterial($exist['bm_id'], $row['pricedimension_code'], true);
        }
        if (isset($row['spec']) && trim((string) $row['spec']) !== '') {
            app::get('material')->model('basic_material_ext')->update(
                array('specifications' => trim((string) $row['spec'])),
                array('bm_id' => $exist['bm_id'])
            );
        }
        return $exist['material_bn'];
    }
}
