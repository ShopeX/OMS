<?php
/**
 * Mapping 禁用后同步基础物料 / SN 标记
 */
class goldentra_mapping_label
{
    const LABEL_CODE = 'SOMS_MAPPING_DISABLED';

    /**
     * 模号 Mapping 禁用后，为关联基础物料与 SN 打上 MAPPING禁用 标记
     *
     * @param int    $mapping_id
     * @param string $error_msg
     * @return bool
     */
    public function syncDisabledByMappingId($mapping_id, &$error_msg = '')
    {
        $mapping_id = intval($mapping_id);
        if ($mapping_id <= 0) {
            $error_msg = '无效的 Mapping ID';
            return false;
        }

        $items = app::get('goldentra')->model('mapping_item')->getList(
            'item_id,bn,bm_id,mapping_id,mold_code,min_weight,max_weight,weight,min_main,max_main,min_extra,max_extra,main_weight,extra_weight',
            array('mapping_id' => $mapping_id)
        );
        if (empty($items)) {
            $error_msg = '无 Mapping 明细';
            return false;
        }

        list($bmIds, $materialBns) = $this->collectMaterialTargets($items);
        if (empty($bmIds) && empty($materialBns)) {
            $error_msg = '未找到关联基础物料编码';
            return false;
        }

        $billLabelLib = kernel::single('ome_bill_label');
        $markedBm = 0;
        $markedSn = 0;

        foreach ($bmIds as $bm_id) {
            if ($billLabelLib->markBillLabel($bm_id, '', self::LABEL_CODE, 'basic_material', $err)) {
                $markedBm++;
            }
        }

        $snIds = $this->collectSnIds($bmIds, $materialBns);
        foreach ($snIds as $sn_id) {
            if ($billLabelLib->markBillLabel($sn_id, '', self::LABEL_CODE, 'material_sn', $err)) {
                $markedSn++;
            }
        }

        if ($markedBm === 0 && $markedSn === 0) {
            $error_msg = '未找到可打标的基础物料或 SN';
            return false;
        }

        return true;
    }

    /**
     * @param array $items mapping_item 行
     * @return array [bmIds, materialBns]
     */
    protected function collectMaterialTargets(array $items)
    {
        $bmIds = array();
        $materialBns = array();
        $bmMdl = app::get('material')->model('basic_material');

        foreach ($items as $item) {
            $bn = trim((string) ($item['bn'] ?? ''));
            if ($bn === '' && !empty($item['item_id'])) {
                $bn = trim((string) goldentra_mapping_code::ensureItemBn($item));
            }
            if ($bn !== '') {
                $materialBns[] = $bn;
            }

            $bm_id = intval($item['bm_id'] ?? 0);
            if ($bm_id > 0) {
                $bmIds[] = $bm_id;
                continue;
            }

            if ($bn === '') {
                continue;
            }

            $bm = $bmMdl->dump(array('material_bn' => $bn), 'bm_id');
            if (!empty($bm['bm_id'])) {
                $bmIds[] = intval($bm['bm_id']);
            }
        }

        return array(
            array_values(array_unique(array_filter($bmIds))),
            array_values(array_unique(array_filter($materialBns))),
        );
    }

    /**
     * @param array $bmIds
     * @param array $materialBns
     * @return array
     */
    protected function collectSnIds(array $bmIds, array $materialBns)
    {
        $snIds = array();
        $snMdl = app::get('machinecenter')->model('material_sn');

        if (!empty($materialBns)) {
            $sns = $snMdl->getList('id', array('material_bn' => $materialBns));
            $snIds = array_merge($snIds, array_column($sns, 'id'));
        }
        if (!empty($bmIds)) {
            $sns = $snMdl->getList('id', array('bm_id' => $bmIds));
            $snIds = array_merge($snIds, array_column($sns, 'id'));
        }

        return array_values(array_unique(array_filter($snIds)));
    }
}
