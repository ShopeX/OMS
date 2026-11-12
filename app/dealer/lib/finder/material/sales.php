<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class dealer_finder_material_sales
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    // sm_id 供 vue 模式下 column_edit 读取行操作所需主键（框架按 addon_cols 下发 _0_sm_id 原始值别名）
    public $addon_cols = "sm_id,cos_id,shop_id,sales_material_bn,sales_material_name,sales_material_type";

    public $column_edit       = '操作';
    public $column_edit_width = 45;
    public $column_edit_order = 1;
    /**
     * column_edit
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_edit($row)
    {
        // vue 模式下 $_GET['ctl'] 为 api_material_sales ⇒ legacy 的
        // `$_GET['ctl'] == 'admin_material_sales'` 判断恒假，必须走 vue_mode 分支。
        if ($this->vue_mode) {
            // Vue Finder 契约：结构化行操作（legacy HTML 片段不下发，SPA 不解析 legacy 链接）
            // 与 legacy 语义逐字对齐：仅 sales_material_edit 权限为真时可编辑；
            // 编辑入口仍走 legacy iframe（写链路本轮不迁，act=edit 弹窗打开）。
            $sm_id = $row['_0_sm_id'] ?? $row[$this->col_prefix . 'sm_id'] ?? $row['sm_id'] ?? 0;
            if (kernel::single('desktop_user')->has_permission('sales_material_edit')) {
                return array(
                    array(
                        'key'    => 'edit',
                        'label'  => '编辑',
                        'action' => 'index.php?app=dealer&ctl=admin_material_sales&act=edit&p[0]=' . $sm_id,
                    ),
                );
            }
            return array();
        }

        if ($_GET['ctl'] == 'admin_material_sales' && $_GET['act'] == 'index') {
            $use_buildin_edit = kernel::single('desktop_user')->has_permission('sales_material_edit');
            $btn              = '-';
            if ($use_buildin_edit) {
                $btn = '<a href="index.php?app=dealer&ctl=admin_material_sales&act=edit&p[0]=' . $row['sm_id'] . '&finder_id=' . $_GET['_finder']['finder_id'] . '" target="_blank">编辑</a>&nbsp;&nbsp;';
            }
            // $use_buildin_look = kernel::single('desktop_user')->has_permission('sales_detail');
            // if ($use_buildin_look) {
            //     if ($btn == '-') {
            //         $btn = '';
            //     }
            //     $btn .= '<a href="index.php?app=dealer&ctl=admin_material_sales&act=detail&p[0]=' . $row['sm_id'] . '&finder_id=' . $_GET['_finder']['finder_id'] . '" target="_blank">查看</a>';
            // }
            return $btn;
        } else {
            return '-';
        }
    }

    public $column_betc_name       = "所属贸易公司";
    public $column_betc_name_width = 150;
    public $column_betc_name_order = 30;
    /**
     * column_betc_name
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_betc_name($row, $list)
    {
        // vue 模式下 col_prefix 未设置（仅 legacy builder 注入），优先读 _0_cos_id 原始值别名
        if ($this->vue_mode) {
            $cosId    = $row['_0_cos_id'] ?? $row['cos_id'] ?? 0;
            $cosIdArr = array_column($list, 'cos_id');
        } else {
            $cosId    = $row[$this->col_prefix . 'cos_id'];
            $cosIdArr = array_column($list, $this->col_prefix . 'cos_id');
        }

        static $cosVsParent;
        static $betcList;
        if (!isset($cosVsParent[$cosId])) {

            $cosMdl      = app::get('organization')->model('cos');
            $shopCosList = $cosMdl->getList('*', ['cos_id|in' => $cosIdArr]);
            $cosVsParent = array_column($shopCosList, 'parent_id', 'cos_id');
            $betcList    = [];
            foreach ($shopCosList as $k => $v) {
                if (!isset($betcList[$v['parent_id']])) {
                    $outBindList = kernel::single('organization_cos')->getCosFindIdSET($v['parent_id'], 'out_bind_id');

                    $betcList[$v['parent_id']] = $outBindList;
                }
            }
        }

        $result = '';
        if (isset($cosVsParent[$cosId])) {
            $parentId = $cosVsParent[$cosId];
            $result   = $betcList[$parentId];
            $result   = implode('<br>', array_column($result, 'cos_name'));
        }

        // 前后端分离：返回纯文本（去掉 legacy span 包裹；多个贸易公司名 <br> → \n）
        if ($this->vue_mode) {
            return str_replace('<br>', "\n", $result);
        }

        return '<span style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;display: flex;">'.$result.'</span>';
    }

    public $column_associated_material       = '关联基础商品信息';
    public $column_associated_material_width = 260;
    public $column_associated_material_order = 50;
    /**
     * column_associated_material
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_associated_material($row, $list)
    {
        static $smWithBmList;

        if (!isset($smWithBmList[$row['sm_id']])) {
            $smBmMdl = app::get('dealer')->model('sales_basic_material');
            $bmMdl   = app::get('material')->model('basic_material');

            $smIdArr  = array_column($list, 'sm_id');
            $smBmList = $smBmMdl->getList('*', ['sm_id|in' => $smIdArr]);

            $bmIdArr = array_unique(array_column($smBmList, 'bm_id'));
            $bmList  = $bmMdl->getList('bm_id, material_bn, material_name', ['bm_id|in' => $bmIdArr]);
            $bmList  = array_column($bmList, null, 'bm_id');

            foreach ($smBmList as $sk => $sv) {
                $sv['bm_info'] = $bmList[$sv['bm_id']];
                if (!isset($smWithBmList[$sv['sm_id']])) {
                    $smWithBmList[$sv['sm_id']] = [];
                }
                $smWithBmList[$sv['sm_id']][] = $sv;
            }
        }

        $associated = [];
        foreach ($smWithBmList[$row['sm_id']] as $k => $v) {
            $associated[] = $v['bm_info']['material_name'] . '(' . $v['bm_info']['material_bn'] . ') x' . $v['number'];
        }

        $result = implode("<br>", $associated);

        // 前后端分离：返回纯文本（去掉 legacy span 包裹；多行 <br> → \n）
        if ($this->vue_mode) {
            return str_replace('<br>', "\n", $result);
        }

        return '<span style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;display: flex;">'.$result.'</span>';
    }
    
    public $column_actual_stock = '可售库存数';
    public $column_actual_stock_order = 89;
    private $initMaterialSale = false;
    /**
     * column_actual_stock
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_actual_stock($row, $list)
    {
        if(!$this->initMaterialSale) {
            $this->initMaterialSale = true;
            $products = app::get('dealer')->model('sales_material')->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type,shop_id',array('sm_id'=>array_column($list, 'sm_id')));
            kernel::single('inventorydepth_calculation_basicmaterial')->init($products);
            kernel::single('inventorydepth_calculation_salesmaterial')->init($products);
            $shopRows = app::get('ome')->model('shop')->getList('shop_id,shop_bn', ['shop_id'=>array_unique(array_column($list, $this->col_prefix.'shop_id'))]);
            $this->shopRows = array_column($shopRows, null, 'shop_id');
        }
        $sku = [
            'sales_material_bn' => $row[$this->col_prefix.'sales_material_bn'],
            'shop_id'         => $row[$this->col_prefix.'shop_id'],
            'shop_bn'               => $this->shopRows[$row[$this->col_prefix.'shop_id']]['shop_bn'],
            'sales_material_name' => $row[$this->col_prefix.'sales_material_name'],
            'sales_material_type' => $row[$this->col_prefix.'sales_material_type'],
        ];
        $stock = kernel::single('inventorydepth_logic_stock')->getStock($sku, $sku['shop_id'], $sku['shop_bn']);
        
        return $stock && is_array($stock)  ? $stock['quantity'] : '';
    }

    /**
     * 基础物料
     * @param int $sm_id 销售物料ID
     * @return string
     */
    public $detail_basic_material = '基础物料';
    /**
     * detail_basic_material
     * @param mixed $sm_id ID
     * @return mixed 返回值
     */
    public function detail_basic_material($sm_id)
    {
        $render = app::get('dealer')->render();

        $smMdl    = app::get('dealer')->model('sales_material');
        $sbmMdl   = app::get('dealer')->model('sales_basic_material');
        $bmMdl    = app::get('material')->model('basic_material');
        $bmExtMdl = app::get('material')->model('basic_material_ext');

        $bmSmList = $sbmMdl->getList('*', ['sm_id' => $sm_id]);
        $bmIdList = array_column($bmSmList, 'bm_id');

        $bmList = $bmMdl->getList('*', ['bm_id|in' => $bmIdList]);
        $bmList = array_column($bmList, null, 'bm_id');

        $bmExtList = $bmExtMdl->getList('*', ['bm_id|in' => $bmIdList]);
        $bmExtList = array_column($bmExtList, null, 'bm_id');

        $smInfo                        = $smMdl->db_dump(['sm_id' => $sm_id]);
        $render->pagedata['smInfo']    = $smInfo;
        $render->pagedata['bmSmList']  = $bmSmList;
        $render->pagedata['bmList']    = $bmList;
        $render->pagedata['bmExtList'] = $bmExtList;
        $render->pagedata['title']     = '基础商品列表';

        // 前后端分离：结构化下发（不再 fetch legacy admin/series/products.html 模板）
        // 字段对齐模板取数：material_bn / material_name / cost / number / rate（+ specifications）
        if ($this->vue_mode) {
            $items = array();
            foreach ($bmSmList as $sm) {
                $bmId = $sm['bm_id'];
                $item = array(
                    'material_name'  => $bmList[$bmId]['material_name'] ?? '',
                    'material_bn'    => $bmList[$bmId]['material_bn'] ?? '',
                    'cost'           => $bmExtList[$bmId]['cost'] ?? '',
                    'specifications' => $bmExtList[$bmId]['specifications'] ?? '',
                );
                if ($smInfo['sales_material_type'] == '1') {
                    $item['number'] = 1;
                    $item['rate']   = '100.00';
                } elseif ($smInfo['sales_material_type'] == '2') {
                    $item['number'] = $sm['number'];
                    $item['rate']   = $sm['rate'];
                } else {
                    $item['number'] = $sm['number'];
                    $item['rate']   = '';
                }
                $items[] = $item;
            }

            return array('title' => '基础商品列表', 'items' => $items);
        }

        return $render->fetch('admin/series/products.html');
    }

    /**
     * 订单操作记录
     * @param int $sm_id
     * @return string
     */
    public $detail_show_log = '操作记录';
    /**
     * detail_show_log
     * @param mixed $sm_id ID
     * @return mixed 返回值
     */
    public function detail_show_log($sm_id)
    {
        $omeLogMdl = app::get('ome')->model('operation_log');
        $logList   = $omeLogMdl->read_log(array('obj_id' => $sm_id, 'obj_type' => 'sales_material@dealer'), 0, -1);
        $finder_id = $_GET['_finder']['finder_id'];

        // 前后端分离：结构化日志（不再拼 legacy「查看快照」HTML 链接）
        // snapshot_log_id 非空时供 FE 跳 legacy act=show_history
        if ($this->vue_mode) {
            $logs = array();
            foreach ($logList as $v) {
                $logs[] = array(
                    'operate_time'    => date('Y-m-d H:i:s', $v['operate_time']),
                    // 与 legacy 日志模板（admin/bbb_show_log.html）四列对齐：操作时间 / 操作人 / 行为 / 操作内容
                    'op_name'         => $v['op_name'],
                    'operation'       => $v['operation'],
                    'memo'            => $v['memo'],
                    'snapshot_log_id' => $v['operation'] == '销售物料编辑' ? $v['log_id'] : '',
                );
            }
            return $logs;
        }

        foreach ($logList as $k => $v) {
            $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);

            if ($v['operation'] == '销售物料编辑') {
                $logList[$k]['memo'] = "<a href='index.php?app=dealer&ctl=admin_material_sales&act=show_history&p[0]={$v['log_id']}&finder_id={$finder_id}' target=\"_blank\">查看快照</a>";
            }
        }
        $render                   = app::get('dealer')->render();
        $render->pagedata['logs'] = $logList;
        return $render->fetch('admin/bbb_show_log.html');
    }

}
