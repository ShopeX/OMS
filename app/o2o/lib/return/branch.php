<?php
/**
 * O2O 退入仓策略与解析
 */
class o2o_return_branch
{
    const CONF_KEY = 'o2o.return.branch.strategy';

    const STRATEGY_ORIGIN = 'origin';

    const STRATEGY_SPLIT_FULFILLMENT = 'split_fulfillment';

    const STRATEGY_DEFAULT_WAREHOUSE = 'default_warehouse';

    /**
     * 策略选项（下拉）
     *
     * @return array
     */
    public static function getStrategyOptions()
    {
        return array(
            self::STRATEGY_ORIGIN             => '原发原退',
            self::STRATEGY_SPLIT_FULFILLMENT  => '分履约退仓（门店快递常规退大仓）',
            self::STRATEGY_DEFAULT_WAREHOUSE  => '统一退默认售后仓',
        );
    }

    /**
     * 策略说明
     *
     * @return array
     */
    public static function getStrategyDescriptions()
    {
        return array(
            self::STRATEGY_ORIGIN => '退入仓默认为原单发货仓：门店发货退门店，大仓发货退大仓。适用于通用 O2O 场景。',
            self::STRATEGY_SPLIT_FULFILLMENT => '按发货方式与售后类型分场景退仓：门店仓发货且物流非自提（含门店快递o2o_ship及顺丰等常规物流）视为门店快递；门店快递常规客退退默认大仓，门店快递且售后单带「拦截入库」标识退原发货门店；仅o2o_pickup归门店自提退门店；大仓发货退原大仓。适用于老庙等分履约业务。',
            self::STRATEGY_DEFAULT_WAREHOUSE => '退入仓统一使用下方配置的默认售后仓库（全局或店铺默认），不区分原发货门店/大仓。',
        );
    }

    /**
     * 解析退入仓
     *
     * @param array $ctx
     * @return array
     */
    public function resolve($ctx)
    {
        $ctx = $this->normalizeContext($ctx);

        if (!app::get('o2o')->is_installed()) {
            return $this->resolveOrigin($ctx);
        }

        switch ($this->getStrategy()) {
            case self::STRATEGY_SPLIT_FULFILLMENT:
                return $this->resolveSplitFulfillment($ctx);
            case self::STRATEGY_DEFAULT_WAREHOUSE:
                return $this->resolveDefaultWarehouse($ctx);
            case self::STRATEGY_ORIGIN:
            default:
                return $this->resolveOrigin($ctx);
        }
    }

    /**
     * 根据发货单等信息组装上下文
     *
     * @param array $delivery
     * @param array $extra
     * @return array
     */
    public function buildContext(array $delivery, array $extra = array())
    {
        $ctx = array(
            'delivery_branch_id' => isset($delivery['branch_id']) ? (int) $delivery['branch_id'] : 0,
            'shop_id'            => isset($delivery['shop_id']) ? $delivery['shop_id'] : 0,
            'logi_id'            => isset($delivery['logi_id']) ? (int) $delivery['logi_id'] : 0,
            'order_id'           => 0,
            'return_type'        => 'return',
            'flag_type'          => 0,
            'source'             => '',
        );

        return array_merge($ctx, $extra);
    }

    /**
     * 获取当前全局策略
     *
     * @return string
     */
    public function getStrategy()
    {
        if (!app::get('o2o')->is_installed()) {
            return self::STRATEGY_ORIGIN;
        }

        $strategy = app::get('o2o')->getConf(self::CONF_KEY);
        if (!$this->isValidStrategy($strategy)) {
            return self::STRATEGY_ORIGIN;
        }

        return $strategy;
    }

    /**
     * 保存全局策略
     *
     * @param string $strategy
     * @return bool
     */
    public function setStrategy($strategy)
    {
        if (!$this->isValidStrategy($strategy)) {
            return false;
        }

        app::get('o2o')->setConf(self::CONF_KEY, $strategy);
        return true;
    }

    /**
     * @param string $strategy
     * @return bool
     */
    public function isValidStrategy($strategy)
    {
        return array_key_exists($strategy, self::getStrategyOptions());
    }

    /**
     * @param array $ctx
     * @return array
     */
    protected function resolveOrigin($ctx)
    {
        $targetId = (int) $ctx['delivery_branch_id'];
        $ruleCode = 'origin';

        if (!$this->isValidReturnBranch($targetId)) {
            $targetId = $this->getDefaultReturnBranchId($ctx['shop_id']);
            $ruleCode = 'P5';
        }

        if (!$this->isValidReturnBranch($targetId)) {
            return $this->buildInvalidResult($ctx, $ruleCode, self::STRATEGY_ORIGIN, '未配置默认售后仓库');
        }

        return $this->buildResult($targetId, $ctx, $ruleCode, self::STRATEGY_ORIGIN);
    }

    /**
     * @param array $ctx
     * @return array
     */
    protected function resolveSplitFulfillment($ctx)
    {
        $fulfillment        = $this->getFulfillmentType($ctx);
        $isLanjieRuku       = $this->isLanjieRukuReturn($ctx);
        $deliveryBranchId   = (int) $ctx['delivery_branch_id'];
        $defaultBranchId    = $this->getDefaultReturnBranchId($ctx['shop_id']);
        $targetId           = 0;
        $ruleCode           = 'P5';

        switch ($fulfillment) {
            case 'store_ship':
                // 门店快递：常规客退退默认大仓；仅「拦截入库」标识退原发货门店
                if ($isLanjieRuku) {
                    $targetId = $deliveryBranchId;
                    $ruleCode = 'P1';
                } else {
                    $targetId = $defaultBranchId;
                    $ruleCode = 'P2';
                }
                break;
            case 'store_pickup':
                $targetId = $deliveryBranchId;
                $ruleCode = 'P3';
                break;
            case 'online':
                $targetId = $deliveryBranchId;
                $ruleCode = 'P4';
                break;
            default:
                $targetId = $defaultBranchId;
                $ruleCode = 'P5';
                break;
        }

        if (!$this->isValidReturnBranch($targetId)) {
            $message = ($ruleCode === 'P2' || $ruleCode === 'P5') ? '未配置默认售后仓库' : '发货仓不可用';
            return $this->buildInvalidResult($ctx, $ruleCode, self::STRATEGY_SPLIT_FULFILLMENT, $message);
        }

        return $this->buildResult($targetId, $ctx, $ruleCode, self::STRATEGY_SPLIT_FULFILLMENT);
    }

    /**
     * @param array $ctx
     * @return array
     */
    protected function resolveDefaultWarehouse($ctx)
    {
        $targetId = $this->getDefaultReturnBranchId($ctx['shop_id']);

        if (!$this->isValidReturnBranch($targetId)) {
            return $this->buildInvalidResult($ctx, 'default_warehouse', self::STRATEGY_DEFAULT_WAREHOUSE, '未配置默认售后仓库');
        }

        return $this->buildResult($targetId, $ctx, 'default_warehouse', self::STRATEGY_DEFAULT_WAREHOUSE);
    }

    /**
     * @param array $ctx
     * @return array
     */
    protected function normalizeContext($ctx)
    {
        return array(
            'delivery_branch_id' => isset($ctx['delivery_branch_id']) ? (int) $ctx['delivery_branch_id'] : 0,
            'shop_id'            => isset($ctx['shop_id']) ? $ctx['shop_id'] : 0,
            'logi_id'            => isset($ctx['logi_id']) ? (int) $ctx['logi_id'] : 0,
            'order_id'           => isset($ctx['order_id']) ? (int) $ctx['order_id'] : 0,
            'return_type'        => isset($ctx['return_type']) ? $ctx['return_type'] : 'return',
            'flag_type'          => isset($ctx['flag_type']) ? (int) $ctx['flag_type'] : 0,
            'source'             => isset($ctx['source']) ? $ctx['source'] : '',
        );
    }

    /**
     * 履约类型：大仓 online；门店仓仅 o2o_pickup 为自提，其余均为门店快递
     *
     * @param array $ctx
     * @return string online|store_pickup|store_ship
     */
    protected function getFulfillmentType($ctx)
    {
        $branchLib = kernel::single('ome_branch');
        $branchId  = (int) $ctx['delivery_branch_id'];

        if ($branchId <= 0 || !$branchLib->isStoreBranch($branchId)) {
            return 'online';
        }

        // 门店仓：仅 o2o_pickup 归门店自提，其余物流（o2o_ship、顺丰 SF 等）均归门店快递
        if ($ctx['logi_id'] > 0 && app::get('o2o')->is_installed()) {
            $corpType = kernel::single('o2o_corp_type')->get_corp_type($ctx['logi_id']);
            if (!empty($corpType['o2o_pickup'])) {
                return 'store_pickup';
            }

            return 'store_ship';
        }

        if ($ctx['order_id'] > 0) {
            $orderExtend = app::get('ome')->model('order_extend')->dump(array('order_id' => $ctx['order_id']), 'store_dly_type');
            if (!empty($orderExtend['store_dly_type'])) {
                return ((int) $orderExtend['store_dly_type'] === 1) ? 'store_pickup' : 'store_ship';
            }
        }

        return 'store_ship';
    }

    /**
     * 售后单是否带「拦截入库」标识（分履约下门店快递退原门店的唯一条件）
     *
     * @param array $ctx
     * @return bool
     */
    protected function isLanjieRukuReturn($ctx)
    {
        return ((int) $ctx['flag_type'] & ome_reship_const::__LANJIE_RUKU) > 0;
    }

    /**
     * 默认售后退入仓：优先店铺配置，其次全局默认
     *
     * @param mixed $shopId
     * @return int
     */
    public function getDefaultReturnBranchId($shopId)
    {
        $branchId = 0;

        if ($shopId) {
            $shopBranchMap = app::get('ome')->getConf('return.auto_shop_branch');
            if (is_array($shopBranchMap) && !empty($shopBranchMap[$shopId])) {
                $branchId = (int) $shopBranchMap[$shopId];
            }
        }

        if ($branchId <= 0) {
            $branchId = (int) app::get('ome')->getConf('return.auto_branch');
        }

        return $branchId;
    }

    /**
     * @param int $branchId
     * @return bool
     */
    protected function isValidReturnBranch($branchId)
    {
        $branchId = (int) $branchId;
        if ($branchId <= 0) {
            return false;
        }

        $branch = app::get('ome')->model('branch')->dump(
            array('branch_id' => $branchId, 'disabled' => 'false', 'check_permission' => 'false'),
            'branch_id'
        );

        return !empty($branch['branch_id']);
    }

    /**
     * @param int $branchId
     * @return array
     */
    protected function getBranchRow($branchId)
    {
        $branch = app::get('ome')->model('branch')->dump(
            array('branch_id' => (int) $branchId, 'check_permission' => 'false'),
            'branch_id,name'
        );

        return $branch ? $branch : array('branch_id' => 0, 'name' => '');
    }

    /**
     * @return array
     */
    protected function getOnlineBranchList()
    {
        return (array) app::get('ome')->model('branch')->getlist(
            'branch_id,name',
            array('disabled' => 'false', 'b_type' => 1, 'check_permission' => 'false')
        );
    }

    /**
     * @param int   $targetBranchId
     * @param array $ctx
     * @return array
     */
    protected function buildBranchList($targetBranchId, $ctx)
    {
        $branchList = array();
        $exists     = array();

        if ($this->isValidReturnBranch($targetBranchId)) {
            $targetBranch = $this->getBranchRow($targetBranchId);
            $branchList[] = array(
                'branch_id' => (int) $targetBranch['branch_id'],
                'name'      => $targetBranch['name'],
            );
            $exists[$targetBranch['branch_id']] = true;
        }

        $deliveryBranchId = (int) $ctx['delivery_branch_id'];
        if ($deliveryBranchId > 0
            && empty($exists[$deliveryBranchId])
            && $this->isValidReturnBranch($deliveryBranchId)) {
            $deliveryBranch = $this->getBranchRow($deliveryBranchId);
            $branchList[] = array(
                'branch_id' => (int) $deliveryBranch['branch_id'],
                'name'      => $deliveryBranch['name'],
            );
            $exists[$deliveryBranch['branch_id']] = true;
        }

        foreach ($this->getOnlineBranchList() as $onlineBranch) {
            if (!empty($exists[$onlineBranch['branch_id']])) {
                continue;
            }
            $branchList[] = array(
                'branch_id' => (int) $onlineBranch['branch_id'],
                'name'      => $onlineBranch['name'],
            );
            $exists[$onlineBranch['branch_id']] = true;
        }

        return $branchList;
    }

    /**
     * @param int    $targetBranchId
     * @param array  $ctx
     * @param string $ruleCode
     * @param string $strategy
     * @return array
     */
    protected function buildResult($targetBranchId, $ctx, $ruleCode, $strategy)
    {
        $branch = $this->getBranchRow($targetBranchId);

        return array(
            'branch_id'   => (int) $targetBranchId,
            'branch_name' => $branch['name'],
            'branch_list' => $this->buildBranchList($targetBranchId, $ctx),
            'rule_code'   => $ruleCode,
            'strategy'    => $strategy,
        );
    }

    /**
     * @param array  $ctx
     * @param string $ruleCode
     * @param string $strategy
     * @param string $message
     * @return array
     */
    protected function buildInvalidResult($ctx, $ruleCode, $strategy, $message)
    {
        return array(
            'branch_id'   => -1,
            'branch_name' => $message,
            'branch_list' => $this->buildBranchList(0, $ctx),
            'rule_code'   => $ruleCode,
            'strategy'    => $strategy,
        );
    }
}
