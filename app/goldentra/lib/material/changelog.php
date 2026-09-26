<?php

/**
 * SAP 主档变更日志（operation_log）
 *
 * 注意：ome write_log 依赖 operation_log 服务注册；OpenAPI 场景下若注册缺失会写失败。
 * 读取时必须按写入的 obj_type=material@goldentra 查询，不能用 operation 标识当 obj_type。
 */
class goldentra_material_changelog
{
    const OBJ_TYPE = 'material@goldentra';

    public static function logAdd($material_id, $unique_code, $sync_batch_no = '')
    {
        $msg = 'SAP主档新增';
        if ($unique_code !== '') {
            $msg .= '，唯一码:' . $unique_code;
        }
        if ($sync_batch_no !== '') {
            $msg .= '，批次:' . $sync_batch_no;
        }
        self::write('sap_material_add@goldentra', $material_id, $msg, $unique_code);
    }

    public static function logUpdate($material_id, $unique_code, array $oldRow, array $newRow, $sync_batch_no = '')
    {
        $diff = self::diffFields($oldRow, $newRow);
        if (empty($diff)) {
            $msg = 'SAP主档更新（无业务字段变化）';
        } else {
            $parts = array();
            foreach ($diff as $field => $change) {
                $parts[] = sprintf('%s:%s→%s', $field, self::shortVal($change['old']), self::shortVal($change['new']));
            }
            $msg = 'SAP主档更新 ' . implode('；', $parts);
        }
        if ($sync_batch_no !== '') {
            $msg .= '，批次:' . $sync_batch_no;
        }
        self::write('sap_material_update@goldentra', $material_id, $msg, $unique_code);
    }

    public static function logRemap($material_id, $old_bn, $new_bn)
    {
        $msg = sprintf('基础物料映射变更 %s→%s', $old_bn, $new_bn);
        self::write('sap_material_remap@goldentra', $material_id, $msg);
    }

    public static function diffFields(array $oldRow, array $newRow)
    {
        $diff = array();
        $cols = array_values(goldentra_material_fields::$field_map);
        $cols[] = 'ext_field';
        $cols = array_unique($cols);
        // 处理态字段不算 SAP 业务变更
        $skip = array('process_status', 'process_msg', 'process_time', 'match_status', 'bn', 'at_time', 'up_time');
        foreach ($cols as $col) {
            if (in_array($col, $skip, true)) {
                continue;
            }
            $oldVal = isset($oldRow[$col]) ? (string) $oldRow[$col] : '';
            $newVal = isset($newRow[$col]) ? (string) $newRow[$col] : '';
            if ($oldVal !== $newVal) {
                $diff[$col] = array('old' => $oldVal, 'new' => $newVal);
            }
        }
        return $diff;
    }

    private static function shortVal($val)
    {
        $val = (string) $val;
        if (strlen($val) > 80) {
            return substr($val, 0, 77) . '...';
        }
        return $val === '' ? '(空)' : $val;
    }

    /**
     * @return int|false log_id
     */
    private static function write($operation, $material_id, $msg, $unique_code = '')
    {
        $material_id = (int) $material_id;
        if ($material_id <= 0) {
            return false;
        }

        $opinfo = kernel::single('ome_func')->getDesktopUser();
        if (empty($opinfo['op_id'])) {
            $opinfo = kernel::single('ome_func')->get_system();
        }

        $mdl = app::get('ome')->model('operation_log');

        // 优先走官方 write_log（需 operation_log 服务已注册 goldentra）
        try {
            $log_id = $mdl->write_log($operation, $material_id, $msg, null, $opinfo);
            if ($log_id) {
                return $log_id;
            }
        } catch (Exception $e) {
            // fall through
        } catch (Throwable $e) {
            // PHP7+：服务未注册时 _get_operations 可能抛 Error
        }

        // 兜底直写，避免 OpenAPI 更新成功但变更日志丢失
        if ($unique_code === '') {
            $mat = app::get('goldentra')->model('material')->dump($material_id, 'unique_code');
            $unique_code = !empty($mat['unique_code']) ? $mat['unique_code'] : '';
        }

        $ip = '';
        try {
            $ip = kernel::single('base_request')->get_remote_addr();
        } catch (Exception $e) {
            $ip = '';
        }

        $row = array(
            'obj_id' => (string) $material_id,
            'obj_name' => $unique_code,
            'obj_type' => self::OBJ_TYPE,
            'operation' => $operation,
            'op_id' => $opinfo['op_id'],
            'op_name' => $opinfo['op_name'] ? $opinfo['op_name'] : 'system',
            'operate_time' => time(),
            'memo' => $msg,
            'ip' => $ip,
        );

        return $mdl->insert($row);
    }

    public static function readLogs($material_id, $limit = 50)
    {
        $material_id = (int) $material_id;
        if ($material_id <= 0) {
            return array();
        }

        // 不走 ome read_log：其内部用 $this->operations[...] 映射操作名，
        // 若 operation_log 服务缓存未加载 goldentra，访问 null 会直接报错导致详情空白。
        $mdl = app::get('ome')->model('operation_log');
        $rows = $mdl->getList(
            '*',
            array(
                'obj_id' => (string) $material_id,
                'obj_type' => self::OBJ_TYPE,
            ),
            0,
            $limit > 0 ? $limit : -1,
            'operate_time desc'
        );
        if (!is_array($rows) || !$rows) {
            return array();
        }

        $nameMap = self::operationNameMap();
        foreach ($rows as $k => $row) {
            $op = isset($row['operation']) ? $row['operation'] : '';
            if (isset($nameMap[$op])) {
                $rows[$k]['operation'] = $nameMap[$op];
            }
            if (isset($row['memo'])) {
                $rows[$k]['memo'] = strip_tags($row['memo']);
            }
        }

        return $rows;
    }

    /**
     * operation 标识 => 展示名
     */
    private static function operationNameMap()
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = array();
        $ops = kernel::single('goldentra_operation_log')->get_operations();
        if (!empty($ops['goldentra']) && is_array($ops['goldentra'])) {
            foreach ($ops['goldentra'] as $key => $cfg) {
                if (!empty($cfg['name'])) {
                    $map[$key . '@goldentra'] = $cfg['name'];
                }
            }
        }
        return $map;
    }
}
