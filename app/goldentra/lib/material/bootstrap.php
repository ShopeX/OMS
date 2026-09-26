<?php

/**
 * 创建基础物料（规格首次出现时）
 * 主表/属性/销售物料同一事务；失败回滚，避免半截空壳料号
 */
class goldentra_material_bootstrap
{
    public function genMaterialBn($price_method, $mold_code)
    {
        if ($price_method == 1) {
            $prefix = 'J' . $mold_code;
        } else {
            $prefix = 'D' . $mold_code;
        }

        return $this->_nextBn($prefix);
    }

    public function genDiamondMaterialBn($mold_code)
    {
        return $this->_nextBn('A' . $mold_code);
    }

    private function _nextBn($prefix)
    {
        return kernel::single('goldentra_db')->withLock('bn_prefix_' . $prefix, 10, function () use ($prefix) {
            $db = kernel::database();
            $prefix_esc = kernel::single('goldentra_db')->escape($prefix, $db);
            $row = $db->selectrow(
                "SELECT MAX(material_bn) AS maxno FROM sdb_material_basic_material WHERE material_bn LIKE '{$prefix_esc}%'"
            );
            $maxno = empty($row['maxno']) ? 0 : (int) substr($row['maxno'], -6);
            $conObj = app::get('ome')->model('concurrent');

            for ($attempt = 0; $attempt < 1000; $attempt++) {
                $maxno += 1;
                if ($maxno >= 1000000) {
                    break;
                }
                $material_bn = $prefix . str_pad($maxno, 6, '0', STR_PAD_LEFT);
                if ($conObj->is_pass($material_bn, 'material_bn')) {
                    return $material_bn;
                }
            }

            throw new Exception('生成物料编码失败: 无法获取可用流水号 prefix=' . $prefix);
        });
    }

    public function createFromMaterialRow($row, $norm, $mapping_item_id = 0, $preset_material_bn = '')
    {
        $is_diamond = goldentra_material_spec::isDiamond($row);
        $price_method = $is_diamond ? 2 : (int) $row['price_method'];

        $weight = isset($norm['weight']) ? $norm['weight'] : $row['weight'];
        $main_weight = isset($norm['main_weight']) ? $norm['main_weight'] : $row['main_weight'];
        $secondary_weight = isset($norm['secondary_weight']) ? $norm['secondary_weight'] : $row['secondary_weight'];
        $retail_price = isset($norm['tag_price']) ? $norm['tag_price'] : $row['price'];
        $process_cost = $row['process_cost'];

        $allocated_bn = '';
        if ($is_diamond) {
            $index = goldentra_material_spec::diamondSearchIndex(
                $row['mold_code'],
                $row['fcolor'],
                $row['fpurity'],
                $row['fcut'],
                $main_weight
            );
            if ($preset_material_bn) {
                $material_bn = $preset_material_bn;
            } else {
                $material_bn = kernel::single('goldentra_material_bootstrap')->genDiamondMaterialBn($row['mold_code']);
                $allocated_bn = $material_bn;
            }
        } else {
            $index = goldentra_material_spec::moldSearchIndex(
                $price_method,
                $row['mold_code'],
                $weight,
                $main_weight,
                $secondary_weight,
                $process_cost
            );
            if ($preset_material_bn) {
                $material_bn = $preset_material_bn;
            } else {
                $material_bn = kernel::single('goldentra_material_bootstrap')->genMaterialBn($price_method, $row['mold_code']);
                $allocated_bn = $material_bn;
            }
        }

        $cat_id = $this->_catIdByCode($row['cate_code']);
        $brand_id = goldentra_pricedimension::resolveBrandId($row['pricedimension_code']);

        $mainData = array(
            'material_name' => $row['goods_name'],
            'material_bn' => $material_bn,
            'material_bn_crc32' => sprintf('%u', crc32($material_bn)),
            'type' => 1,
            'visibled' => 1,
            'omnichannel' => 1,
            'serial_number' => 'true',
            'cat_id' => $cat_id,
            'tax_rate' => 0,
            'create_time' => time(),
            'source' => 'api',
        );

        $db = kernel::database();
        $ownTx = false;
        if (method_exists($db, 'isInTransaction') && !$db->isInTransaction()) {
            $db->beginTransaction();
            $ownTx = true;
        }

        try {
            $bmMdl = app::get('material')->model('basic_material');
            $bm_id = false;
            $insertErr = '';
            try {
                $bm_id = $bmMdl->insert($mainData);
            } catch (Throwable $e) {
                $insertErr = $e->getMessage();
                if (stripos($insertErr, 'Duplicate') === false) {
                    throw $e;
                }
            }
            if (!$bm_id) {
                // 并发下同号已由他人创建：幂等复用，不当失败
                $existBm = $bmMdl->dump(array('material_bn' => $material_bn), 'bm_id,material_bn');
                if (!empty($existBm['bm_id'])) {
                    if ($ownTx) {
                        $db->rollBack();
                        $ownTx = false;
                    }
                    if ($mapping_item_id) {
                        kernel::single('goldentra_mapping')->updateMappingItemBn(
                            $mapping_item_id,
                            $existBm['material_bn'],
                            $existBm['bm_id']
                        );
                    }
                    if (!empty($row['pricedimension_code'])) {
                        goldentra_pricedimension::syncBrandToMaterial(
                            $existBm['bm_id'],
                            $row['pricedimension_code'],
                            true
                        );
                    }
                    return array('bm_id' => $existBm['bm_id'], 'material_bn' => $existBm['material_bn']);
                }
                throw new Exception(
                    '创建基础物料主表失败'
                    . ($insertErr !== '' ? '：' . $insertErr : '')
                    . ' bn=' . $material_bn
                );
            }

            $extData = array(
                'bm_id' => $bm_id,
                'cost' => 0,
                'retail_price' => floatval($retail_price),
                'weight' => floatval($weight),
                'unit' => $row['unit'] ? $row['unit'] : '',
                'cat_id' => $cat_id,
                'brand_id' => $brand_id,
                'specifications' => isset($row['spec']) ? trim((string) $row['spec']) : '',
            );
            app::get('material')->model('basic_material_ext')->save($extData);

            $propsData = array(
                'mold_search_crc32' => sprintf('%u', $index[0]),
                'mold_search_hash' => $index[1],
                'style_name' => $row['style_name'],
                'style_code' => $row['style_code'],
                'mold_name' => $row['mold_name'],
                'mold_code' => $row['mold_code'],
                'texture_name' => $row['texture_name'],
                'texture_code' => $row['texture_code'],
                'class_name' => $row['class_name'],
                'class_code' => $row['class_code'],
                'small_series_name' => $row['small_series_name'],
                'pricedimension_code' => $row['pricedimension_code'],
                'sales_mode' => $row['sales_mode'] !== '' && $row['sales_mode'] !== null
                    ? $row['sales_mode']
                    : goldentra_material::$sales_mode_default,
                'price_method' => $price_method,
                'process_cost' => $process_cost,
                'weight' => $weight,
                'main_weight' => $main_weight,
                'secondary_weight' => $secondary_weight,
                'color' => $row['fcolor'],
                'clarity' => $row['fpurity'],
                'diamond_cut' => $row['fcut'],
                'certificate' => $row['fcertificate'],
            );
            goldentra_material_jewelry_props::saveProps($bm_id, $propsData);
            $this->_assertJewelryPropsSaved($bm_id, $row['mold_code']);

            $stockData = array(
                'bm_id' => $bm_id,
                'store' => 0,
                'store_freeze' => 0,
            );
            app::get('material')->model('basic_material_stock')->save($stockData);

            $codType = kernel::single('material_codebase')->getBarcodeType();
            $codebaseData = array(
                'bm_id' => $bm_id,
                'type' => $codType,
                'code' => $material_bn,
            );
            app::get('material')->model('codebase')->insert($codebaseData);

            $confData = array(
                'bm_id' => $bm_id,
                'use_expire' => 0,
                'warn_day' => 0,
                'quit_day' => 0,
                'create_time' => time(),
            );
            app::get('material')->model('basic_material_conf')->save($confData);

            $confSpecialData = array(
                'bm_id' => $bm_id,
                'openscan' => '4',
                'fromposition' => '0',
                'toposition' => '0',
            );
            app::get('material')->model('basic_material_conf_special')->insert($confSpecialData);

            if ($mapping_item_id) {
                kernel::single('goldentra_mapping')->updateMappingItemBn($mapping_item_id, $material_bn, $bm_id);
            }

            if (class_exists('material_basic_exchange')) {
                kernel::single('material_basic_exchange')->process($bm_id);
            }
            $this->_assertSalesMaterialCreated($material_bn);

            if ($ownTx) {
                $db->commit();
            }
        } catch (Exception $e) {
            if ($ownTx) {
                $db->rollBack();
            }
            if ($allocated_bn !== '') {
                $this->_releaseMaterialBn($allocated_bn);
            }
            throw $e;
        }

        // 远程图片不进事务，失败不影响物料完整性
        if (!empty($row['pic_url'])) {
            goldentra_material_image::attachRemoteUrl($bm_id, $row['pic_url']);
        }

        return array('bm_id' => $bm_id, 'material_bn' => $material_bn);
    }

    protected function _assertJewelryPropsSaved($bm_id, $mold_code)
    {
        $mold_code = trim((string) $mold_code);
        if ($mold_code === '') {
            throw new Exception('创建基础物料缺少模号编码');
        }
        $saved = goldentra_material_jewelry_props::getProps($bm_id, array('mold_code', 'mold_search_hash'));
        if (empty($saved['mold_code']) || (string) $saved['mold_code'] !== $mold_code) {
            throw new Exception('基础物料模号属性写入失败');
        }
        if (empty($saved['mold_search_hash'])) {
            throw new Exception('基础物料规格索引写入失败');
        }
    }

    protected function _assertSalesMaterialCreated($material_bn)
    {
        $material_bn = trim((string) $material_bn);
        if ($material_bn === '' || !class_exists('material_basic_exchange')) {
            return;
        }
        $sales = app::get('material')->model('sales_material')->dump(
            array('sales_material_bn' => $material_bn),
            'sm_id'
        );
        if (empty($sales['sm_id'])) {
            throw new Exception('生成销售物料失败: ' . $material_bn);
        }
    }

    private function _releaseMaterialBn($material_bn)
    {
        $material_bn = trim((string) $material_bn);
        if ($material_bn === '') {
            return;
        }
        $conObj = app::get('ome')->model('concurrent');
        if (method_exists($conObj, 'release')) {
            $conObj->release($material_bn, 'material_bn');
        }
    }

    private function _catIdByCode($cate_code)
    {
        static $cache = array();
        if (isset($cache[$cate_code])) {
            return $cache[$cate_code];
        }
        $cat = app::get('material')->model('basic_material_cat')->dump(array('cat_code' => $cate_code), 'cat_id');
        $cache[$cate_code] = $cat ? $cat['cat_id'] : 0;
        return $cache[$cate_code];
    }
}
