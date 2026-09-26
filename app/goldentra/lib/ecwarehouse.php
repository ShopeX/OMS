<?php

/**
 * 门店/仓库是否电商仓 配置读取
 */
class goldentra_ecwarehouse
{
    /**
     * 门店是否电商仓（无配置或禁用视为否）
     *
     * @param int $store_id
     * @return bool
     */
    public function isEcByStoreId($store_id)
    {
        $store_id = intval($store_id);
        if ($store_id <= 0) {
            return false;
        }
        $row = app::get('goldentra')->model('ecwarehouse')->db_dump(
            array(
                'owner_type' => 'store',
                'store_id' => $store_id,
                'disabled' => 'false',
            ),
            'is_ec'
        );
        return !empty($row) && $row['is_ec'] === 'true';
    }

    /**
     * 仓库是否电商仓（无配置或禁用视为否）
     *
     * @param int $branch_id
     * @return bool
     */
    public function isEcByBranchId($branch_id)
    {
        $branch_id = intval($branch_id);
        if ($branch_id <= 0) {
            return false;
        }
        $row = app::get('goldentra')->model('ecwarehouse')->db_dump(
            array(
                'owner_type' => 'branch',
                'branch_id' => $branch_id,
                'disabled' => 'false',
            ),
            'is_ec'
        );
        return !empty($row) && $row['is_ec'] === 'true';
    }

    /**
     * 按编码判断（门店编码或仓库编号）
     *
     * @param string $owner_type store|branch
     * @param string $owner_bn
     * @return bool
     */
    public function isEcByBn($owner_type, $owner_bn)
    {
        $owner_type = $owner_type === 'store' ? 'store' : 'branch';
        $owner_bn = trim((string) $owner_bn);
        if ($owner_bn === '') {
            return false;
        }
        $row = app::get('goldentra')->model('ecwarehouse')->db_dump(
            array(
                'owner_type' => $owner_type,
                'owner_bn' => $owner_bn,
                'disabled' => 'false',
            ),
            'is_ec'
        );
        return !empty($row) && $row['is_ec'] === 'true';
    }

    /**
     * 已启用且标记为电商仓的仓库 ID 列表
     *
     * @return int[]
     */
    public function listEcBranchIds()
    {
        static $ids = null;
        if ($ids !== null) {
            return $ids;
        }
        $rows = app::get('goldentra')->model('ecwarehouse')->getList(
            'branch_id',
            array(
                'owner_type' => 'branch',
                'is_ec' => 'true',
                'disabled' => 'false',
            ),
            0,
            -1
        );
        $ids = array();
        foreach ((array) $rows as $row) {
            $bid = intval($row['branch_id']);
            if ($bid > 0) {
                $ids[$bid] = $bid;
            }
        }
        $ids = array_values($ids);
        return $ids;
    }

    /**
     * SQL IN 片段（无电商仓时返回空串，调用方需自行跳过）
     *
     * @return string 如 "1,2,3"
     */
    public function ecBranchIdsSqlIn()
    {
        $ids = $this->listEcBranchIds();
        if (!$ids) {
            return '';
        }
        return implode(',', array_map('intval', $ids));
    }
}
