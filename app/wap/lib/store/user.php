<?php

/**
 * WAP 当前操作员关联门店（多门店权限时取数规则统一）
 */
class wap_store_user
{
    /**
     * 获取当前操作员在 WAP 展示的门店（与工作台一致：create_time 最新的一条）
     *
     * @param array|null $branch_ids
     * @return array|null
     */
    public function getCurrentStore($branch_ids = null)
    {
        if ($branch_ids === null) {
            $branch_ids = kernel::single('o2o_store_branch')->getO2OBranchByUser(true);
        }
        if (empty($branch_ids)) {
            return null;
        }
        if (!is_array($branch_ids)) {
            $branch_ids = array($branch_ids);
        }

        $storeObj = app::get('o2o')->model('store');
        $list     = $storeObj->getList(
            'store_id, store_bn, name, addr, branch_id',
            array('branch_id' => $branch_ids),
            0,
            1,
            'create_time desc'
        );

        return $list ? $list[0] : null;
    }

    /**
     * @param array $userInfo
     * @param array|null $storeInfo
     * @return array
     */
    public function mergeUserStoreInfo($userInfo, $storeInfo)
    {
        if (empty($storeInfo)) {
            return (array) $userInfo;
        }

        return array_merge((array) $userInfo, (array) $storeInfo);
    }
}
