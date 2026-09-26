<?php

class goldentra_mdl_mold extends dbeav_model
{
    public $export_name = '模号';

    public function io_title($filter = null, $ioType = 'csv')
    {
        return array('*:模号');
    }

    /**
     * SAP 主档下发：按模号编码自动写入模号表（已存在则跳过）
     *
     * @param string $mold_code
     * @return bool
     */
    public function ensureFromCode($mold_code)
    {
        $mold_code = trim((string) $mold_code);
        if ($mold_code === '') {
            return false;
        }

        $exists = $this->dump(array('mold_code' => $mold_code), 'm_id');
        if ($exists) {
            return true;
        }

        try {
            $mData = array(
                'mold_code' => $mold_code,
                'create_time' => time(),
            );
            return (bool) $this->insert($mData);
        } catch (Exception $e) {
            // 并发下可能已被其他请求写入
            $exists = $this->dump(array('mold_code' => $mold_code), 'm_id');
            return (bool) $exists;
        }
    }
}
