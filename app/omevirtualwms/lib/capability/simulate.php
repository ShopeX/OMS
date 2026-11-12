<?php
/**
 * 「开始模拟」表单读写能力（SCRUM-7864 批2）
 *
 * 与 legacy 控制器 `omevirtualwms_ctl_admin_wms` 的**读表单**（getinfo → _getinfo）与
 * **写提交**（doSubmit → _return_params / _return_method / _assemble / _gen_sign）一一对等，
 * 以 JSON 结构供 `omevirtualwms_ctl_api_wms::info()` / `::submit()` 使用。
 * legacy 控制器保持不动（跨项目回收覆盖风险），仅逐字复刻其线上契约。
 *
 * 对等关系（行号 = app/omevirtualwms/controller/admin/wms.php）：
 *   读：getinfo:316-392（$type 由单号首字符路由 326-378）→ _getinfo:394-612（各分支源单 / 明细 / 默认值）
 *   写：doSubmit:234-313（merge 固定字段 254-261、token 263-279、post 290、dataStatus 295-310）
 *       → _return_params:632-915（各 flag 载荷）→ _return_method:947-982（flag → 接口方法名）
 *       → _assemble:984-1004 / _gen_sign:1005-1008
 *   模板：app/omevirtualwms/view/<flag>.html（字段名、默认值、3000 行守卫）
 *
 * 批次范围：stockin / stockout / stockdump / delivery / reship / vopstockout / storeprocess。
 * iostock / inventory / account 属未迁移菜单（计划表 #39/#46/#47/#65），不在本批：本类不提供其读表单，
 * 写提交一律拒绝。
 *
 * 有意偏离 legacy（唯一一处，已记录）：
 *   legacy 明细为空时 `err()` 输出 HTML 片段并 exit（wms.php:917-928），会破坏 JSON 协议；
 *   本类改为返回 ['ok'=>false,'msg'=>'请添加商品！']，前端提示语义一致。
 */
class omevirtualwms_capability_simulate
{
    /** 本批支持的 7 类回传单 */
    public static $flags = array(
        'stockin',
        'stockout',
        'stockdump',
        'delivery',
        'reship',
        'vopstockout',
        'storeprocess',
    );

    /** 明细行数上限（对等 view/*.html 的 `items_count >= 3000` 守卫：超出则不展示且不可提交） */
    const ITEMS_MAX = 3000;

    /** flag → 接口方法名（对等 _return_method:947-982） */
    private static $methods = array(
        'stockin'      => 'wms.stockin.status_update',
        'stockout'     => 'wms.stockout.status_update',
        'stockdump'    => 'wms.stockdump.status_update',
        'delivery'     => 'wms.delivery.status_update',
        'reship'       => 'wms.reship.status_update',
        'vopstockout'  => 'wms.stockout.status_update',
        'storeprocess' => 'wms.storeprocess.status_update',
    );

    /** 提交载荷里的单号键名（对等 _return_params 与 view/*.html） */
    private static $bn_fields = array(
        'stockin'      => 'stockin_bn',
        'stockout'     => 'stockout_bn',
        'stockdump'    => 'stockdump_bn',
        'delivery'     => 'delivery_bn',
        'reship'       => 'reship_bn',
        'vopstockout'  => 'stockout_bn',
        'storeprocess' => 'storeprocess_bn',
    );

    /** 状态可选项（逐字对等 view/<flag>.html 的 <select name="status">，按模板顺序） */
    private static $status_options = array(
        'stockin' => array(
            'FAILED' => '接单失败',
            'ACCEPT' => '已接单',
            'PARTIN' => '部分入库',
            'FINISH' => '全部入库',
            'CANCEL' => '取消',
            'CLOSE'  => '关闭',
        ),
        'stockout' => array(
            'FAILED' => '接单失败',
            'ACCEPT' => '已接单',
            'PARTIN' => '部分出库',
            'FINISH' => '全部出库',
            'CANCEL' => '取消',
            'CLOSE'  => '关闭',
        ),
        'stockdump' => array(
            'FAILED' => '接单失败',
            'FINISH' => '全部入库',
            'CANCEL' => '取消',
            'CLOSE'  => '关闭',
        ),
        'delivery' => array(
            'FAILED'   => '接单失败',
            'ACCEPT'   => '已接单',
            'PRINT'    => '已打印',
            'PICK'     => '已拣配',
            'CHECK'    => '已校验',
            'PACKAGE'  => '已打包',
            'DELIVERY' => '已发货',
        ),
        'reship' => array(
            'FAILED' => '接单失败',
            'ACCEPT' => '已接单',
            'FINISH' => '收货完成',
            'DENY'   => '拒绝',
        ),
        'vopstockout' => array(
            ''       => '=请选择=',
            'CANCEL' => '强制取消',
        ),
        'storeprocess' => array(),
    );

    /** 状态默认值（对等模板 selected 项；vopstockout 默认空、storeprocess 无该字段） */
    private static $status_default = array(
        'stockin'      => 'FINISH',
        'stockout'     => 'FINISH',
        'stockdump'    => 'FINISH',
        'delivery'     => 'DELIVERY',
        'reship'       => 'FINISH',
        'vopstockout'  => '',
        'storeprocess' => '',
    );

    /**
     * 读「开始模拟」表单（对等 legacy getinfo + _getinfo）
     *
     * @param string $flag   7 类回传单之一
     * @param array  $params bn=单号（必填）；i_type=purchase_return（仅 stockout，走采购退货路由）；
     *                       type_id='1'（对等 getinfo 的 type_id 入参）
     * @return array ['ok'=>bool,'data'=>array] 或 ['ok'=>false,'msg'=>string]
     */
    public function info($flag, $params = array())
    {
        if (!in_array($flag, self::$flags, true)) {
            return array('ok' => false, 'msg' => '不支持的回传类型：' . $flag);
        }
        $bn = trim((string)$this->val($params, 'bn'));
        if ($bn === '') {
            return array('ok' => false, 'msg' => '单号不能为空');
        }

        $type = $this->resolveType($flag, $bn, $params);

        $form = array(
            'flag'           => $flag,
            'type'           => $type,
            'bn_field'       => self::$bn_fields[$flag],
            'bn'             => $bn,
            'bn_editable'    => ($flag === 'reship'), // 仅 reship 的单号是可编辑输入框（reship.html:13）
            'status_field'   => ($flag === 'storeprocess' ? '' : 'status'),
            'status'         => self::$status_default[$flag],
            'status_options' => $this->optionPairs($flag),
            'remark'         => '',
            'operate_time'   => '',
            'task'           => '',
            'warehouse'      => '',
            'warehouse_name' => '',
            'node_id'        => '',
            'node_name'      => '',
            'items_max'      => self::ITEMS_MAX,
            'items_count'    => 0,
            'over_limit'     => false,
            'rows'           => array(),
            'extras'         => array(),
            'logi_options'   => array(),
        );

        $branch_id = $this->loadDoc($flag, $type, $bn, $form);
        if ($branch_id === false) {
            return array('ok' => false, 'msg' => '未找到单号「' . $bn . '」对应的数据');
        }

        // 第三方仓储节点信息（对等 getWmsInfo:930-960）
        if ($branch_id) {
            $wms = $this->getWmsInfo($branch_id);
            $form['node_id']   = isset($wms['node_id']) ? $wms['node_id'] : '';
            $form['node_name'] = isset($wms['wms_name']) ? $wms['wms_name'] : '';
            if ($flag === 'delivery' && !empty($wms['wms_name'])) {
                // delivery.html:31 展示 wms_name（该页仓库为固定 001）
                $form['warehouse_name'] = $wms['wms_name'];
            }
        }

        $form['items_count'] = count($form['rows']);
        $form['over_limit']  = ($form['items_count'] >= self::ITEMS_MAX);

        return array('ok' => true, 'data' => $form);
    }

    /**
     * $type 路由（逐字对等 getinfo:326-378）
     */
    private function resolveType($flag, $bn, $params)
    {
        if ($flag === 'vopstockout') {
            $iostocktype = 'VOP';
        } else {
            $iostocktype = substr($bn, 0, 1);
        }
        if ($flag === 'stockin' && $iostocktype != 'I') {
            $iostocktype = 'E';
        }
        if ($flag === 'stockout' && $iostocktype != 'H') {
            $iostocktype = 'A';
        }
        if ((string)$this->val($params, 'type_id') === '1') {
            $iostocktype = 'I';
        }
        if ((string)$this->val($params, 'i_type') === 'purchase_return') {
            $iostocktype = 'H';
        }
        if ($iostocktype == 'H' && $flag === 'reship') {
            $iostocktype = 'HUAN';
        }

        switch ($iostocktype) {
            case 'VOP':
                return 'vopstockout';
            case 'I':
                return 'purchase';
            case 'H':
                return 'purchase_return';
            case 'T':
            case 'R':
            case 'A':
            case 'E':
            case 'F':
            case 'G':
            case 'J':
            case 'K':
            case 'D':
            case 'B':
            case 'X':
                return 'allocate';
            default:
                return $flag;
        }
    }

    /**
     * 载入源单与明细，填充 $form['rows'] / extras / warehouse / remark / operate_time / task
     *
     * @return int|false 成功返回 branch_id（无则为 0），未找到单号返回 false
     */
    private function loadDoc($flag, $type, $bn, &$form)
    {
        $branch_id = 0;

        switch ($flag) {
            case 'stockin':
            case 'stockout':
                if ($type === 'purchase') { // 采购入库：purchase_po + po_items（_getinfo:435-460）
                    $po = app::get('purchase')->model('po')->db_dump(array('po_bn' => $bn), '*');
                    if (empty($po)) {
                        return false;
                    }
                    $branch_id = $po['branch_id'];
                    $form['warehouse']      = isset($po['branch_bn']) ? $po['branch_bn'] : '';
                    $form['warehouse_name'] = $this->branchName($branch_id);
                    $form['remark']         = isset($po['memo']) ? $po['memo'] : '';
                    // legacy _getinfo:448 逐字：name AS product_name / in_num AS normal_num
                    $rows = app::get('purchase')->model('po_items')->getList(
                        'bn,name AS product_name,num,in_num AS normal_num,defective_num',
                        array('po_id' => $po['po_id'])
                    );
                    foreach ($rows as $v) {
                        $normal = isset($v['normal_num']) ? (int)$v['normal_num'] : 0;
                        $bad    = isset($v['defective_num']) ? (int)$v['defective_num'] : 0;
                        $form['rows'][] = array(
                            'product_bn'   => $v['bn'],
                            'product_name' => $this->pick($v, array('product_name', 'name')),
                            'num'          => max(0, (int)$v['num'] - $normal - $bad),
                            'defective_num'=> 0,
                            'sn'           => '',
                        );
                    }
                } elseif ($type === 'purchase_return') { // 采购退货出库：returned_purchase + returned_purchase_items（_getinfo:462-486）
                    $rp = app::get('purchase')->model('returned_purchase')->db_dump(array('rp_bn' => $bn), '*');
                    if (empty($rp)) {
                        return false;
                    }
                    $branch_id = $rp['branch_id'];
                    $form['warehouse']      = isset($rp['branch_bn']) ? $rp['branch_bn'] : '';
                    $form['warehouse_name'] = $this->branchName($branch_id);
                    $form['remark']         = isset($rp['memo']) ? $rp['memo'] : '';
                    // legacy _getinfo:474 逐字：name AS product_name
                    $rows = app::get('purchase')->model('returned_purchase_items')->getList(
                        'bn,name AS product_name,num,out_num',
                        array('rp_id' => $rp['rp_id'])
                    );
                    foreach ($rows as $v) {
                        $form['rows'][] = array(
                            'product_bn'   => $v['bn'],
                            'product_name' => $this->pick($v, array('product_name', 'name')),
                            'num'          => max(0, (int)$v['num'] - (int)$v['out_num']),
                            'sn'           => '',
                        );
                    }
                } else { // allocate：固单 iso + iso_items（_getinfo:488-512）
                    $iso = app::get('taoguaniostockorder')->model('iso')->db_dump(array('iso_bn' => $bn), '*');
                    if (empty($iso)) {
                        return false;
                    }
                    $branch_id = isset($iso['branch_id']) ? $iso['branch_id'] : 0;
                    $form['warehouse']      = isset($iso['branch_bn']) ? $iso['branch_bn'] : '';
                    $form['warehouse_name'] = $this->branchName($branch_id);
                    $form['remark']         = isset($iso['iso_memo']) ? $iso['iso_memo'] : '';
                    // legacy _getinfo:500 逐字：nums as num
                    $rows = app::get('taoguaniostockorder')->model('iso_items')->getList(
                        'bn,product_name,nums as num,normal_num,defective_num,normal_num AS out_num',
                        array('iso_id' => $iso['iso_id'])
                    );
                    foreach ($rows as $v) {
                        $normal = isset($v['normal_num']) ? (int)$v['normal_num'] : 0;
                        $bad    = isset($v['defective_num']) ? (int)$v['defective_num'] : 0;
                        $form['rows'][] = array(
                            'product_bn'   => $v['bn'],
                            'product_name' => $v['product_name'],
                            'num'          => max(0, (int)$v['num'] - $normal - $bad),
                            'defective_num'=> 0,
                            'sn'           => '',
                        );
                    }
                }
                break;

            case 'vopstockout': // 拣货出库单：pick_stockout_bills + pick_stockout_bill_items（_getinfo:401-432）
                $bills = app::get('purchase')->model('pick_stockout_bills')->getList(
                    '*',
                    array('stockout_no' => $bn, 'status' => 1, 'confirm_status' => array(1, 2), 'o_status' => 1)
                );
                if (empty($bills)) {
                    return false;
                }
                $bill      = $bills[0];
                $branch_id = isset($bill['branch_id']) ? $bill['branch_id'] : 0;
                $form['warehouse']      = isset($bill['branch_bn']) ? $bill['branch_bn'] : '';
                $form['warehouse_name'] = $this->branchName($branch_id);
                $form['remark']         = isset($bill['memo']) ? $bill['memo'] : '';
                $rows = app::get('purchase')->model('pick_stockout_bill_items')->getList(
                    'stockout_item_id,bn,product_name,barcode,num',
                    array('stockout_id' => $bill['stockout_id'], 'is_del' => 'false')
                );
                foreach ($rows as $v) {
                    $form['rows'][] = array(
                        'product_bn'   => $v['bn'],
                        'product_name' => $v['product_name'],
                        'num'          => (int)$v['num'],
                        'sn'           => '',
                    );
                }
                break;

            case 'stockdump': // 库存变更：console_stockdump + stockdump_items（_getinfo:513-540）
                $sd = app::get('console')->model('stockdump')->db_dump(array('stockdump_bn' => $bn), '*');
                if (empty($sd)) {
                    return false;
                }
                // 逐字对等 _getinfo:513-540：仓库取 from_branch_id（stockdump 表无 branch_id 列）
                $branch_id = isset($sd['from_branch_id']) ? $sd['from_branch_id'] : 0;
                $branch    = app::get('ome')->model('branch')->db_dump(array('branch_id' => $branch_id), 'branch_bn,name');
                $form['warehouse']      = isset($branch['branch_bn']) ? $branch['branch_bn'] : '';
                $form['warehouse_name'] = isset($branch['name']) ? $branch['name'] : '';
                $form['remark']         = isset($sd['memo']) ? $sd['memo'] : '';
                $rows = app::get('console')->model('stockdump_items')->getList('*', array('stockdump_id' => $sd['stockdump_id']));
                foreach ($rows as $v) {
                    $in = isset($v['in_nums']) ? (int)$v['in_nums'] : 0;
                    $form['rows'][] = array(
                        'product_bn'   => $v['bn'],
                        'product_name' => $this->pick($v, array('product_name', 'name')),
                        'num'          => max(0, (int)$v['num'] - $in),
                        'sn'           => '',
                    );
                }
                break;

            case 'delivery': // 发货：ome_delivery + delivery_items（_getinfo:542-564 → _getinfo_delivery:616-635）
                $dly = app::get('ome')->model('delivery')->db_dump(array('delivery_bn' => $bn), '*');
                if (empty($dly)) {
                    return false;
                }
                $branch_id = isset($dly['branch_id']) ? $dly['branch_id'] : 0;
                $form['warehouse']      = '001'; // delivery.html / _return_params 里固定 001
                $form['warehouse_name'] = $this->branchName($branch_id);
                $form['remark']         = isset($dly['memo']) ? $dly['memo'] : '';
                $rows = app::get('ome')->model('delivery_items')->getList('*', array('delivery_id' => $dly['delivery_id']));
                foreach ($rows as $v) {
                    $form['rows'][] = array(
                        'product_bn'   => $v['bn'],
                        'product_name' => $this->pick($v, array('product_name', 'name')),
                        'num'          => (int)$v['number'],
                        'num_editable' => false, // delivery.html:102 数量只读
                        'sn'           => '',
                    );
                }
                break;

            case 'reship': // 退货：ome_reship + reship_items（_getinfo:566-596）
                $rs = app::get('ome')->model('reship')->db_dump(array('reship_bn' => $bn), '*');
                if (empty($rs)) {
                    return false;
                }
                $branch_id = isset($rs['branch_id']) ? $rs['branch_id'] : 0;
                $form['warehouse']      = isset($rs['branch_bn']) ? $rs['branch_bn'] : '';
                $form['warehouse_name'] = $this->branchName($branch_id);
                $form['remark']         = isset($rs['memo']) ? $rs['memo'] : '';
                $rows = app::get('ome')->model('reship_items')->getList(
                    '*',
                    array('reship_id' => $rs['reship_id'], 'return_type' => array('return', 'refuse'))
                );
                foreach ($rows as $v) {
                    $normal = isset($v['num_normal']) ? (int)$v['num_normal'] : 0;
                    $bad    = isset($v['num_defective']) ? (int)$v['num_defective'] : 0;
                    $form['rows'][] = array(
                        'product_bn'   => $v['bn'],
                        'product_name' => $this->pick($v, array('product_name', 'name')),
                        'num'          => max(0, (int)$v['num'] - $bad - $normal),
                        'defective_num'=> 0,
                        'sn'           => '',
                    );
                }
                break;

            case 'storeprocess': // 仓内加工：console_material_package + items/detail（_getinfo:598-612）
                $mp = app::get('console')->model('material_package')->db_dump(array('mp_bn' => $bn), '*');
                if (empty($mp)) {
                    return false;
                }
                $branch_id = isset($mp['branch_id']) ? $mp['branch_id'] : 0;
                $form['warehouse']      = isset($mp['branch_bn']) ? $mp['branch_bn'] : '';
                $form['warehouse_name'] = $this->branchName($branch_id);
                // 逐字对等 _getinfo:598-612：仅 material_package_items（不并入 detail 表）
                foreach (app::get('console')->model('material_package_items')->getList('*', array('mp_id' => $mp['id'])) as $v) {
                    $form['rows'][] = array('product_bn' => $v['bm_bn'], 'product_name' => '', 'num' => (int)$v['number'], 'sn' => '');
                }
                break;
        }

        // 物流公司下拉（delivery/reship：view 内为 $data.wuliu → 承运商表 sdb_ome_dly_corp）
        // legacy _getinfo:553-556 / 564-565 逐字：getlist('*') 后由 wmsmgr_func 计算 wms_code，取不到则回落 type
        if ($flag === 'delivery' || $flag === 'reship') {
            $wms_info = $this->getWmsInfo($branch_id);
            $corps    = app::get('ome')->model('dly_corp')->getList('*', array());
            foreach ($corps as $c) {
                $code = '';
                if (!empty($wms_info['wms_id']) && class_exists('wmsmgr_func')) {
                    $code = (string) kernel::single('wmsmgr_func')->getWmslogiCode($wms_info['wms_id'], $c['type']);
                }
                $form['logi_options'][] = array(
                    'value' => $code !== '' ? $code : $c['type'],
                    'label' => $c['name'],
                    'type'  => $c['type'],
                );
            }
            $form['extras']['wuliu'] = $form['logi_options'];
            $form['extras']['logistics'] = '';
            $form['extras']['logi_no']   = '';
        }
        if ($flag === 'delivery') {
            $form['extras']['volume'] = '';
            $form['extras']['weight'] = '0'; // delivery.html:68 默认 0
        }

        return $branch_id;
    }

    /**
     * 第三方仓储节点信息（逐字对等 legacy getWmsInfo:930-944）
     * 返回 ['wms_id'=>..,'node_id'=>..,'wms_name'=>..,'wms_bn'=>..]
     */
    public function getWmsInfo($branch_id)
    {
        $wms_id   = kernel::single('ome_branch')->getWmsIdById($branch_id);
        $node_id  = kernel::single('channel_func')->getNodeIdByChannelId($wms_id);
        $wms_name = kernel::single('channel_func')->getChannelNameById($wms_id);
        $wms_bn   = kernel::single('channel_func')->getWmsBnByWmsId($wms_id);
        if (!$node_id) {
            $channel_adapter = app::get('channel')->model('channel');
            $detail = $channel_adapter->db->selectrow("SELECT node_id,channel_id FROM sdb_channel_channel where channel_type='wms' and node_id!='' and node_id!='selfwms'");
            $node_id = $detail['node_id'];
            $wms_id  = $detail['channel_id'];
        }
        return array('wms_id' => $wms_id, 'node_id' => $node_id, 'wms_name' => $wms_name, 'wms_bn' => $wms_bn);
    }

    /** 仓库名称（拿不到就返回空串，不阻断读表单） */
    private function branchName($branch_id)
    {
        if (empty($branch_id)) {
            return '';
        }
        $branch = app::get('ome')->model('branch')->db_dump(array('branch_id' => $branch_id), 'name');
        return empty($branch['name']) ? '' : $branch['name'];
    }

    /** 状态选项转 [['value'=>..,'label'=>..]] 供前端直接渲染 */
    private function optionPairs($flag)
    {
        $out = array();
        foreach (self::$status_options[$flag] as $value => $label) {
            $out[] = array('value' => (string)$value, 'label' => $label);
        }
        return $out;
    }

    /** 取数组键，缺省空串 */
    private function val($arr, $key, $default = '')
    {
        return (is_array($arr) && isset($arr[$key]) && $arr[$key] !== '') ? $arr[$key] : $default;
    }

    /** 按优先级取第一个非空字段（兼容不同 app 的列名差异） */
    private function pick($row, $keys)
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '') {
                return $row[$k];
            }
        }
        return '';
    }

    /**
     * 提交「开始模拟」表单
     * （对等 legacy doSubmit:234-313 + _return_params:632-915 + _return_method:947-982 + _gen_sign:1005-1008）
     *
     * @param string $flag   7 类回传单之一
     * @param array  $params 表单字段（键名与 view/<flag>.html 一致）；明细可用
     *                       `rows`（[['product_bn'=>,'num'=>,'defective_num'=>,'sn'=>], ...]）
     *                       或 legacy 形态 `product`（每行按模板顺序的定序数组）
     * @return array ['ok'=>bool,'msg'=>string,'data'=>array]
     */
    public function submit($flag, $params = array())
    {
        if (!in_array($flag, self::$flags, true)) {
            return array('ok' => false, 'msg' => '不支持的回传类型：' . $flag);
        }

        $built = $this->buildPayload($flag, $params);
        if (isset($built['error'])) {
            return array('ok' => false, 'msg' => $built['error']);
        }
        $payload = $built['params'];

        $bn_key = self::$bn_fields[$flag];
        $bn     = isset($params[$bn_key]) ? (string)$params[$bn_key] : '';
        if ($bn === '' && $flag !== 'storeprocess') {
            return array('ok' => false, 'msg' => '单号不能为空');
        }
        $node_id = trim((string)$this->val($params, 'node_id'));
        if ($node_id === '') {
            return array('ok' => false, 'msg' => '未指定仓储节点（node_id），请先选择仓库');
        }

        $query_params = array(
            'method'  => self::$methods[$flag],
            'date'    => '',
            'format'  => 'json',
            'node_id' => $node_id,
            'app_id'  => 'ecos.ome',
            'task'    => time() . $bn,
        );

        // token 判定（逐字对等 doSubmit:263-279）
        $adapter_type = kernel::single('channel_func')->getAdapterByNodeId($node_id);
        $token        = '';
        $config       = array();
        $node_type    = '';
        if (in_array($adapter_type, array('matrixwms'))) {
            $token = base_certificate::token('ome');
        }
        if (in_array($adapter_type, array('openapiwms'))) {
            $channel_id = kernel::single('channel_func')->getWmsIdByNodeId($node_id);
            $detail     = app::get('channel')->model('adapter')->getList('config', array('channel_id' => $channel_id), 0, 1);
            $config     = unserialize($detail[0]['config']);
            $node_type  = $config['node_type'];
            if ($config['node_type'] != 'sku360') {
                $token = $config['private_key'];
            }
        }

        $query_params = array_merge($payload, $query_params);
        if ($node_type == 'sku360') { // 逐字对等 doSubmit:282-286
            $timestamp                 = date('Y-m-d H:i:s');
            $query_params['timestamp'] = strtotime($timestamp);
            $query_params['sign']      = strtoupper(md5($config['owner'] . $config['appkey'] . $timestamp));
        } else {
            $query_params['sign'] = $this->genSign($query_params, $token);
        }

        // 请求第三方仓储（对等 doSubmit:290）
        $api_url = kernel::base_url(1) . kernel::url_prefix() . '/api';
        $rs      = kernel::single('base_httpclient')->post($api_url, $query_params);
        $info    = json_decode($rs, 1);
        if (!is_array($info)) {
            return array('ok' => false, 'msg' => '仓储接口返回异常：' . (string)$rs);
        }

        // 成功才写数据状态表（逐字对等 doSubmit:294-310；须先赋值再传引用，PHP8 传字面量致命）
        if (isset($info['rsp']) && $info['rsp'] == 'succ') {
            $status_data = array(
                'bn'          => $bn,
                'type'        => $flag,
                'status'      => !empty($query_params['status']) ? $query_params['status'] : 'success',
                'op_id'       => kernel::single('desktop_user')->get_id(),
                'op_name'     => kernel::single('desktop_user')->get_name(),
                'create_time' => time(),
            );
            app::get('omevirtualwms')->model('dataStatus')->save($status_data);
        }

        return array(
            'ok'   => (isset($info['rsp']) && $info['rsp'] == 'succ'),
            'msg'  => !empty($info['msg']) ? $info['msg'] : '回传成功',
            'data' => $info,
        );
    }

    /**
     * 按 flag 组装接口载荷（逐字对等 _return_params:632-915）
     * 明细 `item` / `items` 与 legacy 一致：json_encode 后的字符串作为表单字段提交。
     *
     * @return array ['params'=>array] 或 ['error'=>string]
     */
    private function buildPayload($flag, $params)
    {
        $rows = $this->normalizeRows($params);
        $json = function ($arr) {
            return json_encode($arr);
        };

        switch ($flag) {
            case 'stockin': // _return_params:662-698
                if (!$rows) {
                    return array('error' => '请添加商品！');
                }
                $payload = array(
                    'stockin_bn'   => isset($params['stockin_bn']) ? $params['stockin_bn'] : '',
                    'warehouse'    => $this->val($params, 'warehouse'),
                    'status'       => $this->val($params, 'status'),
                    'remark'       => $this->val($params, 'remark'),
                    'operate_time' => !empty($params['operate_time']) ? $params['operate_time'] : time(),
                    'type'         => 'QTRK', // 其他入库
                );
                $bn = (string)$payload['stockin_bn'];
                if ('I' == substr($bn, 0, 1) || (string)$this->val($params, 'type_id') === '1') {
                    $payload['type'] = 'CGRK';
                }
                $item = array();
                foreach ($rows as $v) {
                    $item[] = array(
                        'product_bn'    => $v['product_bn'],
                        'normal_num'    => $v['num'],
                        'defective_num' => $v['defective_num'],
                        'sn_list'       => !empty($v['sn']) ? array('sn' => explode(',', $v['sn'])) : array(),
                    );
                }
                $payload['item'] = $json($item);
                return array('params' => $payload);

            case 'stockout': // _return_params:699-729
                if (!$rows) {
                    return array('error' => '请添加商品！');
                }
                $payload = array(
                    'stockout_bn'  => isset($params['stockout_bn']) ? $params['stockout_bn'] : '',
                    'warehouse'    => $this->val($params, 'warehouse'),
                    'status'       => $this->val($params, 'status'),
                    'remark'       => $this->val($params, 'remark'),
                    'operate_time' => !empty($params['operate_time']) ? $params['operate_time'] : time(),
                    'type'         => 'QTCK',
                );
                $bn = (string)$payload['stockout_bn'];
                if ('H' == substr($bn, 0, 1) || (string)$this->val($params, 'i_type') === 'purchase_return') {
                    $payload['type'] = 'CGTH';
                }
                $item = array();
                foreach ($rows as $v) {
                    $item[] = array(
                        'product_bn' => $v['product_bn'],
                        'num'        => $v['num'],
                        'sn_list'    => !empty($v['sn']) ? array('sn' => explode(',', $v['sn'])) : array(),
                    );
                }
                $payload['item'] = $json($item);
                return array('params' => $payload);

            case 'stockdump': // _return_params:730-755（键名是 items，复数）
                if (!$rows) {
                    return array('error' => '请添加商品！');
                }
                $payload = array(
                    'stockdump_bn' => isset($params['stockdump_bn']) ? $params['stockdump_bn'] : '',
                    'warehouse'    => $this->val($params, 'warehouse'),
                    'status'       => $this->val($params, 'status'),
                    'remark'       => $this->val($params, 'remark'),
                    'operate_time' => !empty($params['operate_time']) ? $params['operate_time'] : time(),
                );
                $item = array();
                foreach ($rows as $v) {
                    $item[] = array(
                        'product_bn' => $v['product_bn'],
                        'num'        => $v['num'],
                    );
                }
                $payload['items'] = $json($item);
                return array('params' => $payload);

            case 'delivery': // _return_params:756-786（warehouse 固定 001；明细为空不报错）
                $payload = array(
                    'delivery_bn'  => isset($params['delivery_bn']) ? $params['delivery_bn'] : '',
                    'logistics'    => $this->val($params, 'logistics'),
                    'logi_no'      => $this->val($params, 'logi_no'),
                    'warehouse'    => '001',
                    'status'       => $this->val($params, 'status'),
                    'volume'       => $this->val($params, 'volume'),
                    'weight'       => $this->val($params, 'weight'),
                    'remark'       => $this->val($params, 'remark'),
                    'operate_time' => !empty($params['operate_time']) ? $params['operate_time'] : time(),
                );
                $item = array();
                foreach ($rows as $v) {
                    $item[] = array(
                        'product_bn' => $v['product_bn'],
                        'num'        => $v['num'],
                        'sn_list'    => !empty($v['sn']) ? array('sn' => explode(',', $v['sn'])) : array(),
                    );
                }
                $payload['item'] = $json($item);
                return array('params' => $payload);

            case 'reship': // _return_params:787-816
                if (!$rows) {
                    return array('error' => '请添加商品！');
                }
                $payload = array(
                    'reship_bn'    => isset($params['reship_bn']) ? $params['reship_bn'] : '',
                    'logistics'    => $this->val($params, 'logistics'),
                    'logi_no'      => $this->val($params, 'logi_no'),
                    'warehouse'    => $this->val($params, 'warehouse'),
                    'status'       => $this->val($params, 'status'),
                    'remark'       => $this->val($params, 'remark'),
                    'operate_time' => !empty($params['operate_time']) ? $params['operate_time'] : time(),
                );
                $item = array();
                foreach ($rows as $v) {
                    $item[] = array(
                        'product_bn'    => $v['product_bn'],
                        'normal_num'    => $v['num'],
                        'defective_num' => $v['defective_num'],
                        'sn_list'       => !empty($v['sn']) ? array('sn' => explode(',', $v['sn'])) : array(),
                    );
                }
                $payload['item'] = $json($item);
                return array('params' => $payload);

            case 'vopstockout': // _return_params:871-882（item 固定空串）
                $payload = array(
                    'stockout_bn'  => isset($params['stockout_bn']) ? $params['stockout_bn'] : '',
                    'warehouse'    => $this->val($params, 'warehouse'),
                    'status'       => $this->val($params, 'status'),
                    'remark'       => $this->val($params, 'remark'),
                    'operate_time' => !empty($params['operate_time']) ? $params['operate_time'] : time(),
                );
                $payload['item'] = '';
                return array('params' => $payload);

            case 'storeprocess': // _return_params:883-912（硬编码 processOrderCode，服务类型 2 互换）
                $mp   = app::get('console')->model('material_package')->db_dump(
                    array('mp_bn' => $this->val($params, 'storeprocess_bn')),
                    'id,service_type'
                );
                $mpid = empty($mp['id']) ? 0 : $mp['id'];
                $materialitems = array();
                foreach (app::get('console')->model('material_package_items')->getList('*', array('mp_id' => $mpid)) as $v) {
                    $materialitems[] = array('itemCode' => $v['bm_bn'], 'quantity' => $v['number']);
                }
                $productitems = array();
                foreach (app::get('console')->model('material_package_items_detail')->getList('*', array('mp_id' => $mpid)) as $v) {
                    $productitems[] = array('itemCode' => $v['bm_bn'], 'quantity' => $v['number']);
                }
                $payload = array(
                    'processOrderCode' => $this->val($params, 'storeprocess_bn'),
                    'materialitems'    => $json(array('item' => $materialitems)),
                    'productitems'     => $json(array('item' => $productitems)),
                );
                if (!empty($mp['service_type']) && $mp['service_type'] == '2') { // 仓内组合拆分：物料与成品互换
                    $payload['materialitems'] = $json(array('item' => $productitems));
                    $payload['productitems']  = $json(array('item' => $materialitems));
                }
                return array('params' => $payload);
        }

        return array('error' => '不支持的回传类型：' . $flag);
    }

    /**
     * 统一明细入参：前端 `rows`（关联数组）或 legacy 形态 `product`（定序数组）
     * 定序数组按模板列顺序 → [product_bn, num, defective_num, sn]
     */
    private function normalizeRows($params)
    {
        $out = array();
        if (!empty($params['rows']) && is_array($params['rows'])) {
            foreach ($params['rows'] as $r) {
                if (empty($r['product_bn'])) {
                    continue;
                }
                $out[] = array(
                    'product_bn'    => $r['product_bn'],
                    'num'           => isset($r['num']) ? $r['num'] : 0,
                    'defective_num' => isset($r['defective_num']) ? $r['defective_num'] : 0,
                    'sn'            => isset($r['sn']) ? $r['sn'] : '',
                );
            }
            return $out;
        }
        if (!empty($params['product']) && is_array($params['product'])) {
            foreach ($params['product'] as $v) {
                if (empty($v[0])) {
                    continue;
                }
                $out[] = array(
                    'product_bn'    => $v[0],
                    'num'           => isset($v[1]) ? $v[1] : 0,
                    'defective_num' => isset($v[2]) ? $v[2] : 0,
                    'sn'            => isset($v[3]) ? $v[3] : '',
                );
            }
        }
        return $out;
    }

    /** 签名（逐字对等 _gen_sign:1005-1008 + _assemble:984-1004） */
    private function genSign($params, $token)
    {
        return strtoupper(md5(strtoupper(md5($this->assemble($params))) . $token));
    }

    /** 签名字符串拼装（ksort + 递归 + 跳过 null + bool 转 01） */
    private function assemble($params)
    {
        if (!is_array($params)) {
            return null;
        }
        ksort($params, SORT_STRING);
        $sign = '';
        foreach ($params as $key => $val) {
            if (is_null($val)) {
                continue;
            }
            if (is_bool($val)) {
                $val = ($val) ? 1 : 0;
            }
            $sign .= $key . (is_array($val) ? $this->assemble($val) : $val);
        }
        return $sign;
    }
}
