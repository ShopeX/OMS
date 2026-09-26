<?php
/**
 * 定价/计价转换：给基础物料、SAP主档、SN 打标记，并记录变更时间/变更前料号
 */
class goldentra_material_pricetype_label
{
    const LABEL_OLD = 'SOMS_PRICE_CONVERT_OLD';
    const LABEL_NEW = 'SOMS_PRICE_CONVERT_NEW';
    const LABEL_MASTER = 'SOMS_PRICE_CONVERT';

    const BILL_BASIC = 'basic_material';
    const BILL_MASTER = 'goldentra_material';
    const BILL_SN = 'material_sn';

    /** SN props 键：计价方式变更时间 */
    const PROP_CHANGED_AT = 'price_method_changed_at';
    /** SN props 键：变更前物料编码 */
    const PROP_OLD_BN = 'price_method_old_bn';

    /**
     * 计价方式一旦变更：主档打标 + 记变更时间/变更前料号，并同步 SN 标记/props
     * 无 Mapping、待匹配等场景也必须调用（业务靠标记/时间识别）
     * SN 列表通过 material_sn_props 扩展表展示（finder 虚拟列）
     *
     * @param int $material_id
     * @param string $old_bn 变更前物料编码（最新一次变动前）
     * @param string $unique_code 唯一码；空则从主档取
     * @return bool
     */
    public function recordPriceMethodChange($material_id, $old_bn = '', $unique_code = '')
    {
        $material_id = intval($material_id);
        $old_bn = trim((string) $old_bn);
        $unique_code = trim((string) $unique_code);
        $now = time();

        if ($material_id <= 0) {
            return false;
        }

        $upd = array(
            'price_method_changed_at' => $now,
            'up_time' => $now,
        );
        if ($old_bn !== '') {
            $upd['price_method_old_bn'] = substr($old_bn, 0, 30);
        }
        app::get('goldentra')->model('material')->update(
            $upd,
            array('material_id' => $material_id)
        );
        $ok = $this->markMaster($material_id);
        if ($unique_code === '') {
            $mat = app::get('goldentra')->model('material')->dump(
                array('material_id' => $material_id),
                'unique_code'
            );
            if (!empty($mat['unique_code'])) {
                $unique_code = trim((string) $mat['unique_code']);
            }
        }
        if ($unique_code !== '') {
            $this->markSnByUniqueCode($unique_code);
            $this->saveSnPriceMethodProps($unique_code, $now, $old_bn);
        }
        return $ok;
    }

    /**
     * 写入 SN props：计价方式变更时间、变更前物料编码
     *
     * @param string $unique_code
     * @param int $changed_at
     * @param string $old_bn
     * @return bool
     */
    public function saveSnPriceMethodProps($unique_code, $changed_at, $old_bn = '')
    {
        $unique_code = trim((string) $unique_code);
        if ($unique_code === '' || !app::get('machinecenter')->is_installed()) {
            return false;
        }

        $sns = app::get('machinecenter')->model('material_sn')->getList(
            'id',
            array('sn' => $unique_code),
            0,
            -1
        );
        if (empty($sns)) {
            return false;
        }

        $props = array(
            self::PROP_CHANGED_AT => (string) (intval($changed_at) > 0 ? intval($changed_at) : time()),
        );
        $old_bn = trim((string) $old_bn);
        if ($old_bn !== '') {
            $props[self::PROP_OLD_BN] = substr($old_bn, 0, 30);
        }

        $ok = false;
        foreach ($sns as $sn) {
            if ($this->upsertSnProps(intval($sn['id']), $props)) {
                $ok = true;
            }
        }
        return $ok;
    }

    /**
     * @param int $sn_id
     * @param array $props props_col => props_value
     * @return bool
     */
    protected function upsertSnProps($sn_id, array $props)
    {
        $sn_id = intval($sn_id);
        if ($sn_id <= 0 || !$props) {
            return false;
        }
        $mdl = app::get('machinecenter')->model('material_sn_props');
        $now = time();
        $ok = false;
        foreach ($props as $col => $val) {
            $col = trim((string) $col);
            if ($col === '') {
                continue;
            }
            $val = (string) $val;
            $exist = $mdl->dump(
                array('sn_id' => $sn_id, 'props_col' => $col),
                'id'
            );
            if ($exist) {
                $ret = $mdl->update(
                    array(
                        'props_value' => substr($val, 0, 200),
                        'up_time' => $now,
                    ),
                    array('id' => intval($exist['id']))
                );
            } else {
                $row = array(
                    'sn_id' => $sn_id,
                    'props_col' => substr($col, 0, 50),
                    'props_value' => substr($val, 0, 200),
                    'at_time' => $now,
                    'up_time' => $now,
                );
                // PHP 8+: insert 需要可引用变量
                $ret = $mdl->insert($row);
            }
            if ($ret) {
                $ok = true;
            }
        }
        return $ok;
    }

    /**
     * SAP主档打「定价/计价转换」，并同步到对应唯一码 SN
     *
     * @param int $material_id
     * @return bool
     */
    public function markMaster($material_id)
    {
        $material_id = intval($material_id);
        if ($material_id <= 0) {
            return false;
        }
        $err = '';
        $ok = (bool) kernel::single('ome_bill_label')->markBillLabel(
            $material_id,
            '',
            self::LABEL_MASTER,
            self::BILL_MASTER,
            $err
        );
        $this->markSnByMaterialId($material_id);
        return $ok;
    }

    /**
     * 按主档 ID 给对应唯一码 SN 打与主档相同的转换标记
     *
     * @param int $material_id
     * @return bool
     */
    public function markSnByMaterialId($material_id)
    {
        $material_id = intval($material_id);
        if ($material_id <= 0) {
            return false;
        }
        $mat = app::get('goldentra')->model('material')->dump(
            array('material_id' => $material_id),
            'unique_code'
        );
        if (empty($mat['unique_code'])) {
            return false;
        }
        return $this->markSnByUniqueCode($mat['unique_code']);
    }

    /**
     * 按唯一码给 SN 打「定价/计价转换」
     *
     * @param string $unique_code
     * @return bool
     */
    public function markSnByUniqueCode($unique_code)
    {
        $unique_code = trim((string) $unique_code);
        if ($unique_code === '') {
            return false;
        }
        if (!app::get('machinecenter')->is_installed()) {
            return false;
        }

        $sns = app::get('machinecenter')->model('material_sn')->getList(
            'id',
            array('sn' => $unique_code)
        );
        if (empty($sns)) {
            return false;
        }

        $billLabelLib = kernel::single('ome_bill_label');
        $ok = false;
        foreach ($sns as $sn) {
            $err = '';
            if ($billLabelLib->markBillLabel(
                intval($sn['id']),
                '',
                self::LABEL_MASTER,
                self::BILL_SN,
                $err
            )) {
                $ok = true;
            }
        }
        return $ok;
    }

    /**
     * 老基础物料打「定价/计价转换（老）」
     *
     * @param string $material_bn
     * @return bool
     */
    public function markOldByBn($material_bn)
    {
        return $this->markBasicByBn($material_bn, self::LABEL_OLD);
    }

    /**
     * 新基础物料打「定价/计价转换（新）」
     *
     * @param string $material_bn
     * @return bool
     */
    public function markNewByBn($material_bn)
    {
        return $this->markBasicByBn($material_bn, self::LABEL_NEW);
    }

    /**
     * @param string $material_bn
     * @param string $label_code
     * @return bool
     */
    protected function markBasicByBn($material_bn, $label_code)
    {
        $material_bn = trim((string) $material_bn);
        if ($material_bn === '') {
            return false;
        }
        $bm = app::get('material')->model('basic_material')->dump(
            array('material_bn' => $material_bn),
            'bm_id'
        );
        if (empty($bm['bm_id'])) {
            return false;
        }
        $err = '';
        return (bool) kernel::single('ome_bill_label')->markBillLabel(
            intval($bm['bm_id']),
            '',
            $label_code,
            self::BILL_BASIC,
            $err
        );
    }
}
