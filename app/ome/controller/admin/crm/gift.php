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
/**
 * User: jintao
 * Date: 2016/3/18
 */
class ome_ctl_admin_crm_gift extends desktop_controller
{

    /**
     * _views
     * @return mixed 返回值
     */

    public function _views()
    {
        if ($_GET['act'] == 'rule') {
            $nowTime     = time();
            $ruleObj     = $this->app->model('gift_rule');
            $base_filter = array();
            $sub_menu    = array(
                0 => array('label' => app::get('base')->_('全部'), 'filter' => $base_filter, 'optional' => false),
                1 => array('label' => app::get('base')->_('开启'), 'filter' => array('status' => '1'), 'optional' => false),
                2 => array('label' => app::get('base')->_('关闭'), 'filter' => array('status' => '0'), 'optional' => false),
                3 => array('label' => app::get('base')->_('进行中'), 'filter' => array('status' => '1', 'start_time|sthan' => $nowTime, 'end_time|bthan' => $nowTime), 'optional' => false),
                4 => array('label' => app::get('base')->_('未开始'), 'filter' => array('start_time|than' => $nowTime), 'optional' => false),
                5 => array('label' => app::get('base')->_('已过期'), 'filter' => array('end_time|lthan' => $nowTime), 'optional' => false),
            );
            $i = 0;
            foreach ($sub_menu as $k => $v) {
                if (!IS_NULL($v['filter'])) {
                    $v['filter'] = array_merge($v['filter'], $base_filter);
                }

                $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
                $sub_menu[$k]['addon']  = $ruleObj->count($v['filter']);
                $sub_menu[$k]['href']   = 'index.php?app=' . $_GET['app'] . '&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&view=' . $i++;
            }
            return $sub_menu;
        }

    }

    /**
     * rule
     * @param mixed $act act
     * @return mixed 返回值
     */
    public function rule($act)
    {
        $this->finder('ome_mdl_gift_rule', array(
            'title'               => '促销规则列表',
            'actions'             => array(
                array(
                    'label' => '添加促销规则',
                    'href'  => 'index.php?app=ome&ctl=admin_crm_gift&act=addAndEdit&p[0]=add&shop_id=' . $_GET['shop_id'] . '&view=' . $view,
                ),
                array(
                    'label'   => '删除',
                    'submit'  => 'index.php?app=ome&ctl=admin_crm_gift&act=deleteRule&finder_id=' . $_GET['finder_id'],
                    'confirm' => '是否确认删除?',
                    'target'  => 'refresh',
                ),
                array(
                    'label'  => '促销规则货品导入模板',
                    'href'   => 'index.php?app=ome&ctl=admin_crm_gift&act=exportTemplate',
                    'target' => '_blank',
                ),
            ),
            'orderBy'             => 'status DESC,priority DESC,id DESC',
            'use_buildin_recycle' => false,
        ));
    }

    /**
     * 新增/编辑页面显示
     */
    public function addAndEdit()
    {
        $sales_material_obj = app::get('material')->model('sales_material');
        $shopObj            = app::get('ome')->model('shop');
        $shop_id            = $_GET['shop_id'];
        $id                 = intval($_GET['id']);

        //$shops_name = $shopObj->getList('shop_id,name',array('node_id|noequal'=>''));
        $shops_name = $shopObj->getList('shop_id,name', array('s_type' => 1));
        $shops      = $shops_name;

        $rule = array(
            'start_time' => strtotime(date('Y-m-d')),
            'status'     => 1,
            'shop_id'    => $shop_id,
            'time_type'  => 'pay_time',
            'lv_id'      => 0,
            'filter_arr' => array(
                'order_amount' => array(
                    'type' => 0,
                ),
                'buy_goods'    => array(
                    'type' => 0,
                ),
            ),
        );

        $rs        = app::get('eccommon')->model('regions')->getList('local_name', array('region_grade' => 1, 'region_id|sthan' => 3320));
        $provinces = $rs;

        //订单类型
        $order_types   = array();
        $order_types[] = array("type" => "normal", "name" => "普通订单");
        $order_types[] = array("type" => "presale", "name" => "预售订单");

        $rule_obj = $this->app->model('gift_rule');

        //修改规则信息
        $goods_name = '';
        if ($id > 0) {
            $rule               = $rule_obj->dump($id);
            $rule['filter_arr'] = json_decode($rule['filter_arr'], true);

            $goods_bn = $rule['filter_arr']['buy_goods']['goods_bn'];
            $count    = count($goods_bn);
            if (!is_array($goods_bn)) {
                $goods_bn = array($goods_bn, '', '', '', '', '', '', '', '', '');
            }
            $rule['filter_arr']['buy_goods']['goods_bn'] = $goods_bn;

            if ($rule['shop_ids']) {
                $rule['shop_ids'] = explode(',', $rule['shop_ids']);
            } else {
                $rule['shop_ids'] = array($rule['shop_id']);
            }

            foreach ($shops as $ks => &$vs) {
                if (in_array($vs['shop_id'], $rule['shop_ids'])) {
                    $vs['checked'] = 'true';
                } else {
                    $vs['checked'] = 'false';
                }
            }

            if (isset($rule['filter_arr']['province'])) {
                foreach ($provinces as $keys => &$vals) {
                    if (in_array($vals['local_name'], $rule['filter_arr']['province'])) {
                        $vals['checked'] = 'true';
                    } else {
                        $vals['checked'] = 'false';
                    }
                }
            }

            if (isset($rule['filter_arr']['order_type'])) {
                foreach ($order_types as $keys_ot => &$vals_ot) {
                    if (in_array($vals_ot['type'], $rule['filter_arr']['order_type'])) {
                        $vals_ot['checked'] = 'true';
                    } else {
                        $vals_ot['checked'] = 'false';
                    }
                }
                unset($vals_ot);
            }

            if (is_array($rule['filter_arr']['member_uname'])) {
                $rule['filter_arr']['member_uname'] = implode(',', $rule['filter_arr']['member_uname']);
            }

            $sm_infos = array();
            foreach ($rule['filter_arr']['buy_goods']['goods_bn'] as $kes => $ves) {
                $m_objs     = $sales_material_obj->dump(array('sales_material_bn' => $ves), 'sm_id');
                $sm_infos[] = $m_objs['sm_id'];
            }

            $this->pagedata['pgid']        = implode(',', $sm_infos);
            $this->pagedata['replacehtml'] = <<<EOF
<div id='hand-selected-product'>已选择了{$count}个销售物料,<a href='javascript:void(0);' onclick='product_selected_show();'>查看选中的销售物料.</a></div>
EOF;

        } else {
            $rule['filter_arr']['buy_goods']['goods_bn'] = array('', '', '', '', '', '', '', '', '', '');
        }

        //已经设定的赠品组合
        $gifts = array();
        if ($rule['gift_ids']) {
            $gift_ids = explode(',', $rule['gift_ids']);
            $gift_num = explode(',', $rule['gift_num']);

            foreach ($gift_ids as $k => $v) {
                $gift_id_num[$v] = $gift_num[$k];
            }

            $gifts = app::get('crm')->model('gift')->getList('*,"checked" as checked', array('gift_id' => $gift_ids));
            foreach ($gifts as $k => $v) {
                $gifts[$k]['gift_name'] = mb_substr($v['gift_name'], 0, 22, 'utf-8');
                $gifts[$k]['num']       = $gift_id_num[$v['gift_id']];
            }
        } else {
            //$gifts = $this->app->model('shop_gift')->getList('*',array(),0,5);
        }

        $rule['start_time_hour'] = 0;
        if ($rule['start_time']) {
            $rule['start_time_hour'] = (int) date('H', $rule['start_time']);
        }

        $rule['end_time_hour'] = 0;
        if ($rule['end_time']) {
            $rule['end_time_hour'] = (int) date('H', $rule['end_time']);
        }

        $this->pagedata['conf_hours']  = array_merge(array('00', '01', '02', '03', '04', '05', '06', '07', '08', '09'), range(10, 23));
        $this->pagedata['order_types'] = $order_types;
        $this->pagedata['provinces']   = $provinces;
        $this->pagedata['goods_name']  = $goods_name;
        $this->pagedata['shops']       = $shops;
        $this->pagedata['gifts']       = $gifts;
        $this->pagedata['rule']        = $rule;
        $this->pagedata['view']        = $_GET['view'];
        $this->pagedata['beigin_time'] = date("Y-m-d", time());
        $this->pagedata['end_time']    = date('Y-m-d', strtotime('+15 days'));
        $this->page('admin/gift/rule_edit.html');
    }

    /**
     * logs
     * @return mixed 返回值
     */
    public function logs()
    {
        $actions     = array();
        $base_filter = array();
        $this->finder('ome_mdl_gift_logs', array(
            'title'               => '赠品发送记录',
            'actions'             => $actions,
            'base_filter'         => $base_filter,
            'orderBy'             => 'id DESC',
            'use_buildin_recycle' => false,
            'use_buildin_export'  => true,
            'use_buildin_filter'  => true,
            'use_view_tab'        => false,
        ));
    }

    //赠品设置操作和显示
    /**
     * 设置gift
     * @return mixed 返回操作结果
     */
    public function setgift()
    {
        //判断是否保存操作
        if (isset($_POST['set_gift_erp'])) {
            $set_gift_taobao = $set_gift_erp = 'on';
            //淘宝赠品启用状态
            if (empty($_POST['set_gift_taobao']) || $_POST['set_gift_taobao'] == 'off') {
                $set_gift_taobao = 'off';
                app::get('ome')->setConf('ome.preprocess.tbgift', 'false');
            } else {
                app::get('ome')->setConf('ome.preprocess.tbgift', 'true');
            }
            //本地赠品启用状态
            if (empty($_POST['set_gift_erp']) || $_POST['set_gift_erp'] == 'off') {
                $set_gift_erp = 'off';
                $this->app->setConf('gift.on_off.cfg', 'off');
            } else {
                $this->app->setConf('gift.on_off.cfg', 'on');
            }
            //本地赠品启用状态
            if (empty($_POST['gift_order_create_deal'])) {
                $this->app->setConf('gift.order.create.deal', 'false');
            } else {
                $this->app->setConf('gift.order.create.deal', 'true');
            }
            //本地赠品的出错处理
            if (empty($_POST['erp_gift_error_setting']) || $_POST['erp_gift_error_setting'] == 'off') {
                //关闭出错,审单发货
                $this->app->setConf('gift.error.ways', 'off');
            } else {
                $this->app->setConf('gift.error.ways', 'on');
            }
            $arr = array(
                'set_gift_taobao' => $set_gift_taobao,
                'set_gift_erp'    => $set_gift_erp,
                'op_user'         => kernel::single('desktop_user')->get_name(),
                'create_time'     => time(),
            );
            $url = 'index.php?app=ome&ctl=admin_crm_gift&act=setgift';
            $this->begin($url);
            $this->app->model('gift_set_logs')->insert($arr);
            $this->end(true, '保存成功');
        }

        //是否启用淘宝赠品(兼容很早以前的)
        $set_gift_taobao     = 'off';
        $taobao_gift_setting = app::get('ome')->getConf('ome.preprocess.tbgift');
        if ($taobao_gift_setting == 'true') {
            $set_gift_taobao = 'on';
        }

        $this->pagedata['set_gift_taobao']        = $set_gift_taobao;
        $this->pagedata['set_gift_erp']           = $this->app->getConf('gift.on_off.cfg');
        $this->pagedata['erp_gift_error_setting'] = $this->app->getConf('gift.error.ways');
        $this->pagedata['gift_order_create_deal'] = $this->app->getConf('gift.order.create.deal');

        $extra_view = array('ome' => 'admin/gift/set.html');

        $actions     = array();
        $base_filter = array();
        $this->finder('ome_mdl_gift_set_logs', array(
            'title'               => '赠品设置',
            'actions'             => $actions,
            'base_filter'         => $base_filter,
            'orderBy'             => 'id DESC',
            'use_buildin_recycle' => false,
            'use_buildin_filter'  => false,
            'use_view_tab'        => false,
            'top_extra_view'      => $extra_view,
            'use_buildin_setcol'  => false,
            'use_buildin_refresh' => false,
        ));

    }

    /**
     * 规则保存
     */
    public function save_rule()
    {

        $this->begin('index.php?app=ome&ctl=admin_crm_gift&act=rule');
        $gift_rule_obj      = app::get('ome')->model('gift_rule');
        $sales_material_obj = app::get('material')->model('sales_material');

        $data                  = $_POST;
        $data['filter_arr']    = $_POST['filter_arr'];
        $data['gift_ids']      = $_POST['gift_id'];
        $data['gift_num']      = $_POST['gift_num'];
        $data['shop_ids']      = $_POST['shop_ids'];
        $data['start_time']    = strtotime($_POST['start_time'] . ' ' . $_POST['start_time_hour'] . ':00:00');
        $data['end_time']      = strtotime($_POST['end_time'] . ' ' . $_POST['end_time_hour'] . ':00:00');
        $data['modified_time'] = time();

        if (is_array($data['shop_ids']) && count($data['shop_ids']) > 10) {
            $this->end(false, '最多只能选择十个店铺');
        }

        $data['shop_id']  = $data['shop_ids'][0];
        $data['shop_ids'] = empty($data['shop_ids']) ? '' : implode(',', $data['shop_ids']);

        //if($data['filter_arr']['buy_goods']['goods_bn']){
        //    foreach($data['filter_arr']['buy_goods']['goods_bn'] as $v){
        //        $v = strtoupper($v);
        //    }
        //}

        $sales_material_bns = array();
        if (!empty($data['sm_id'])) {
            foreach ($data['sm_id'] as $ka => $va) {
                $sales_material_bns[] = $sales_material_obj->dump(array('sm_id' => $va), 'sales_material_bn');
            }
        }

        $data['filter_arr']['buy_goods']['goods_bn'] = array();
        foreach ($sales_material_bns as $k => $v) {
            $data['filter_arr']['buy_goods']['goods_bn'][] = $v['sales_material_bn'];
        }

        //添加的指定会员
        if ($data['filter_arr']['member_uname']) {
            $unameStr                           = preg_replace("/(\n)|(\s)|(\t)|(\')|(')|(，)/", ',', $data['filter_arr']['member_uname']);
            $memberUname                        = explode(',', $unameStr);
            $data['filter_arr']['member_uname'] = $memberUname;
        }

        $data['filter_arr'] = json_encode($data['filter_arr']);

        if (!$data['id']) {
            $data['create_time'] = time();
        }

        //清理gift_num
        foreach ($data['gift_num'] as $k => $v) {
            if (!in_array($k, $data['gift_ids'])) {
                unset($data['gift_num'][$k]);
            }
        }

        $data['gift_ids'] = implode(',', $data['gift_ids']);
        $data['gift_num'] = implode(',', $data['gift_num']);

        if ($data['id']) {
            // 数据快照
            $rule_detail = $gift_rule_obj->dump($data['id'], '*');

            $opObj = app::get('ome')->model('operation_log');
            $opObj->write_log('crm_edit@ome', $data['id'], serialize($rule_detail));
            unset($rule_detail);

        }

        if ($gift_rule_obj->save($data)) {
            $this->end(true, '添加成功');
        } else {
            $this->end(false, '添加失败');
        }

    }

    //复制赠品规则
    /**
     * copy_rule
     * @return mixed 返回值
     */
    public function copy_rule()
    {
        $mdl_ome_gift_rule = app::get('ome')->model('gift_rule');
        $copy_data         = $mdl_ome_gift_rule->dump($_GET["id"]);
        unset($copy_data["id"]);
        $time_str                   = time();
        $copy_data["create_time"]   = $time_str;
        $copy_data["modified_time"] = $time_str;
        $mdl_ome_gift_rule->insert($copy_data);
        echo "<script>parent.MessageBox.success('命令已经被成功发送！！');parent.finderGroup['{$_GET[finder_id]}'].refresh();</script>";
        exit;
    }

    /**
     * priority
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function priority($id = 0)
    {
        if ($_POST) {
            $this->begin("index.php?app=ome&ctl=admin_crm_gift&act=rule");
            $shopGiftObj           = app::get('ome')->model('gift_rule');
            $data                  = $_POST;
            $data['priority']      = intval($_POST['priority']);
            $data['modified_time'] = time();
            if ($shopGiftObj->save($data)) {
                $this->end(true, '添加成功');
            } else {
                $this->end(false, '添加失败');
            }
        }

        //修改规则信息
        if ($id > 0) {
            $rule = $this->app->model('gift_rule')->dump($id);

            $rule['start_time'] = date("Y-m-d", $rule['start_time']);
            $rule['end_time']   = date("Y-m-d", $rule['end_time']);
        }

        $this->pagedata['rule'] = $rule;
        $this->pagedata['view'] = $_GET['view'];
        $this->display('admin/gift/priority.html');
    }

    /**
     * object_rows
     * @return mixed 返回值
     */
    public function object_rows()
    {

        if ($_POST['data']) {
            if ($_POST['app_id']) {
                $app = app::get($_POST['app_id']);
            } else {
                $app = $this->app;
            }

            $obj        = $app->model($_POST['object']);
            $schema     = $obj->get_schema();
            $textColumn = $_POST['textcol'] ? $_POST['textcol'] : $schema['textColumn'];
            $textColumn = explode(',', $textColumn);
            $_textcol   = $textColumn;
            $textColumn = $textColumn[0];

            $keycol = $_POST['key'] ? $_POST['key'] : $schema['idColumn'];

            //统一做掉了。
            if ($_POST['data'][0] === '_ALL_') {
                if (isset($_POST['filter']['advance']) && $_POST['filter']['advance']) {
                    $arr_filters = explode(',', $_POST['filter']['advance']);
                    foreach ($arr_filters as $obj_filter) {
                        $arr                      = explode('=', $obj_filter);
                        $_POST['filter'][$arr[0]] = $arr[1];
                    }
                    unset($_POST['filter']['advance']);
                }

                $all_filter    = !empty($obj->__all_filter) ? $obj->__all_filter : array();
                $filter        = !empty($_POST['filter']) ? $_POST['filter'] : $all_filter;
                $arr_list      = $obj->getList($keycol, $filter);
                $_POST['data'] = array_map('current', $arr_list);
            }

            $items = $obj->getList('*', array($keycol => $_POST['data']));
            if($_POST['get_sales_material']==1){
                echo json_encode($items);
                exit();
            }
            $name  = $items[0][$textColumn];
            if ($_POST['type'] == 'radio') {
                if (strpos($textColumn, '@') !== false) {
                    list($field, $table, $app_) = explode('@', $textColumn);
                    if ($app_) {
                        $app = app::get($app_);
                    }
                    $mdl    = $app->model($table);
                    $schema = $mdl->get_schema();
                    $row    = $mdl->getList('*', array($schema['idColumn'] => $items[0][$keycol]));
                    $name   = $row[0][$field];

                }
                echo json_encode(array('id' => $items[0][$keycol], 'name' => $name));
                exit;
            }

            $this->pagedata['_input'] = array('items' => $items,
                'idcol'                                   => $schema['idColumn'],
                'keycol'                                  => $keycol,
                'textcol'                                 => $textColumn,
                '_textcol'                                => $_textcol,
                'name'                                    => $_POST['name'],
            );
            $this->pagedata['_input']['view_app'] = 'desktop';
            $this->pagedata['_input']['view']     = $_POST['view'];
            if ($_POST['view_app']) {
                $this->pagedata['_input']['view_app'] = $_POST['view_app'];
            }

            if (strpos($_POST['view'], ':') !== false) {
                list($view_app, $view)                = explode(':', $_POST['view']);
                $this->pagedata['_input']['view_app'] = $view_app;
                $this->pagedata['_input']['view']     = $view;

            }

            $this->display('admin/gift/input-row.html');
        }
    }

    /**
     * 查找er_common
     * @return mixed 返回结果
     */
    public function finder_common()
    {
        if(isset($_GET['is_bind'])){
            $base_filter['is_bind']=$_GET['is_bind'];
        }
        $params = array(
            'title'                  => app::get('desktop')->_('列表'),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_buildin_setcol'     => true,
            'use_buildin_refresh'    => true,
            'finder_aliasname'       => 'finder_common',
            'alertpage_finder'       => true,
            'use_buildin_tagedit'    => false,
            'base_filter' => $base_filter,
        );

        //人工预占库存根据branch_id显示商品列表用
        if ($_GET['app_id'] == "material" && $_GET["object"] == "basic_material" && $_GET["name"] == "bm_id") {
            $params["base_filter"] = array("bm_id" => "-1");
            $bid = isset($_GET["filter"]["branch_id"]) ? intval($_GET["filter"]["branch_id"]) : 0;
            if ($bid > 0) {
                $params["base_filter"] = [
                    'filter_sql' => ' bm_id IN(SELECT product_id FROM sdb_ome_branch_product WHERE branch_id = '.$bid.')',
                ];
            }
        }

        if ($_GET['findercount']) {
            $params['object_method']['count'] = $_GET['findercount'];
        }
        if ($_GET['findergetlist']) {
            $params['object_method']['getlist'] = $_GET['findergetlist'];
        }
        $params['slaes_material_type'] = $_GET['slaes_material_type'];
        if (substr($_GET['name'], 0, 7) == 'adjunct') {
            $params['orderBy'] = 'goods_id desc';
        }

        $this->finder($_GET['app_id'] . '_mdl_' . $_GET['object'], $params);
    }

    /**
     * @description 显示选用的货品
     * @access public
     * @param void
     * @return void
     */
    public function showProducts()
    {
        $salesMaterialObj = app::get('material')->model('sales_material');

        $sm_id = kernel::single('base_component_request')->get_post('sm_id');

        if ($sm_id) {
            //前端店铺_规则应用传值
            if (!is_array($sm_id)) {
                $sm_id = explode(',', $sm_id);
            }

            $this->pagedata['_input'] = array(
                'name'     => 'sm_id',
                'idcol'    => 'sm_id',
                '_textcol' => 'sales_material_name',
            );

            $list = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn', array('sm_id' => $sm_id, 'sales_material_type|noequal' => 3), 0, -1, 'sm_id asc');

            $this->pagedata['_input']['items'] = $list;
        }

        $this->display('admin/gift/show_products.html');
    }

    /**
     * @description AJax加载选择销售物料模板
     * @param $id    int
     * @param $shop_ids    String
     * @return void
     */
    public function ajax_sales_material_html($id = '', $shop_ids = '')
    {
        #规则应用详细信息
        if ($id) {
            $sales_material_obj = app::get('material')->model('sales_material');
            $gift_obj           = app::get('ome')->model('gift_rule');
            $data               = $gift_obj->dump(array('id' => $id));

            $gift_rule = json_decode($data['filter_arr'], true);

            $sm_infos = array();
            foreach ($gift_rule['buy_goods']['goods_bn'] as $ks => $vs) {
                $m_objs     = $sales_material_obj->dump(array('sales_material_bn' => $vs));
                $sm_infos[] = $m_objs['sm_id'];
            }

            $googs_bns = implode(',', $sm_infos);

            # 获取ID范围
            if ($googs_bns && $googs_bns != '_ALL_') {

                $data['pgid'] = explode(',', $googs_bns);

                $count                         = count($data['pgid']);
                $this->pagedata['replacehtml'] = <<<EOF
<div id='hand-selected-product'>已选择了{$count}个销售物料,<a href='javascript:void(0);' onclick='product_selected_show();'>查看选中的销售物料.</a></div>
EOF;
            }
        }

        #店铺
        $shop_id = array();
        if ($shop_ids && $shop_ids != '_ALL_') {
            $shop_id_list = explode(',', $shop_ids);
            foreach ($shop_id_list as $key => $val) {
                if (empty($shop_id_list)) {
                    unset($shop_id_list[$key]);
                }
            }

            $shop_id = $this->app->model('shop')->getList('shop_id', array('shop_id' => $shop_id_list));
            $shop_id = array_map('current', $shop_id);
        }

        $this->pagedata['data']       = $data;
        $this->pagedata['pgid']       = $googs_bns;
        $this->pagedata['in_shop_id'] = ($shop_id ? implode(',', $shop_id) . ',_ALL_' : '');
        $this->display('admin/gift/select_sales_material.html');
    }

    /**
     * show_operation
     * @return mixed 返回值
     */
    public function show_operation()
    {
        $log_id             = $_GET['log_id'];
        $id                 = $_GET['id'];
        $sales_material_obj = app::get('material')->model('sales_material');
        $shopObj            = app::get('ome')->model('shop');
        $logObj             = app::get('ome')->model('operation_log');
        $operation_history  = $logObj->read_log(array('obj_id' => $id, 'obj_type' => 'gift_rule@ome', 'log_id' => $log_id), 0, 1);
        $operation_history  = current($operation_history);
        $region_detail      = $operation_history['memo'];
        $rule               = unserialize($region_detail);

        $shops_name = $shopObj->getList('shop_id,name');
        $shops      = $shops_name;

        $rs        = app::get('eccommon')->model('regions')->getList('local_name', array('region_grade' => 1, 'region_id|sthan' => 3320));
        $provinces = $rs;

        //订单类型
        $order_types   = array();
        $order_types[] = array("type" => "normal", "name" => "普通订单");
        $order_types[] = array("type" => "presale", "name" => "预售订单");

        //修改规则信息
        $goods_name = '';

        $rule['filter_arr'] = json_decode($rule['filter_arr'], true);

        $goods_bn = $rule['filter_arr']['buy_goods']['goods_bn'];
        $count    = count($goods_bn);
        if (!is_array($goods_bn)) {
            $goods_bn = array($goods_bn, '', '', '', '', '', '', '', '', '');
        }
        $rule['filter_arr']['buy_goods']['goods_bn'] = $goods_bn;

        if ($rule['shop_ids']) {
            $rule['shop_ids'] = explode(',', $rule['shop_ids']);
        } else {
            $rule['shop_ids'] = array($rule['shop_id']);
        }

        foreach ($shops as $ks => &$vs) {
            if (in_array($vs['shop_id'], $rule['shop_ids'])) {
                $vs['checked'] = 'true';
            } else {
                $vs['checked'] = 'false';
            }
        }

        if (isset($rule['filter_arr']['province'])) {
            foreach ($provinces as $keys => &$vals) {
                if (in_array($vals['local_name'], $rule['filter_arr']['province'])) {
                    $vals['checked'] = 'true';
                } else {
                    $vals['checked'] = 'false';
                }
            }
        }

        if (isset($rule['filter_arr']['order_type'])) {
            foreach ($order_types as $keys_ot => &$vals_ot) {
                if (in_array($vals_ot['type'], $rule['filter_arr']['order_type'])) {
                    $vals_ot['checked'] = 'true';
                } else {
                    $vals_ot['checked'] = 'false';
                }
            }
            unset($vals_ot);
        }

        $sm_infos = array();
        foreach ($rule['filter_arr']['buy_goods']['goods_bn'] as $kes => $ves) {
            $m_objs     = $sales_material_obj->dump(array('sales_material_bn' => $ves), 'sm_id');
            $sm_infos[] = $m_objs['sm_id'];
        }

        $this->pagedata['pgid']        = implode(',', $sm_infos);
        $this->pagedata['replacehtml'] = <<<EOF
<div id='hand-selected-product'>已选择了{$count}个销售物料,<a href='javascript:void(0);' onclick='product_selected_show();'>查看选中的销售物料.</a></div>
EOF;

        //已经设定的赠品组合
        $gifts = array();
        if ($rule['gift_ids']) {
            $gift_ids = explode(',', $rule['gift_ids']);
            $gift_num = explode(',', $rule['gift_num']);

            foreach ($gift_ids as $k => $v) {
                $gift_id_num[$v] = $gift_num[$k];
            }

            $gifts = app::get('crm')->model('gift')->getList('*,"checked" as checked', array('gift_id' => $gift_ids));
            foreach ($gifts as $k => $v) {
                $gifts[$k]['gift_name'] = mb_substr($v['gift_name'], 0, 22, 'utf-8');
                $gifts[$k]['num']       = $gift_id_num[$v['gift_id']];
            }
        }

        $rule['start_time_hour'] = 0;
        if ($rule['start_time']) {
            $rule['start_time_hour'] = (int) date('H', $rule['start_time']);
        }

        $rule['end_time_hour'] = 0;
        if ($rule['end_time']) {
            $rule['end_time_hour'] = (int) date('H', $rule['end_time']);
        }

        $this->pagedata['conf_hours']  = array_merge(array('00', '01', '02', '03', '04', '05', '06', '07', '08', '09'), range(10, 23));
        $this->pagedata['order_types'] = $order_types;
        $this->pagedata['provinces']   = $provinces;
        $this->pagedata['goods_name']  = $goods_name;
        $this->pagedata['shops']       = $shops;
        $this->pagedata['gifts']       = $gifts;
        $this->pagedata['rule']        = $rule;
        $this->pagedata['view']        = $_GET['view'];
        $this->pagedata['beigin_time'] = date("Y-m-d", time());
        $this->pagedata['end_time']    = date('Y-m-d', strtotime('+15 days'));
        $this->singlepage('admin/gift/rule_history.html');
    }

    /**
     * 删除Rule
     * @return mixed 返回值
     */
    public function deleteRule()
    {
        $this->begin('');
        $ids     = array_map('intval', (array)$_POST['id']);
        $giftObj = app::get('ome')->model('gift_rule');
        if (!empty($ids)) {
            foreach ($ids as $id) {
                $gift = $giftObj->dump(array('id' => $id, 'status' => '1'), 'title');
                if ($gift) {
                    $this->end(false, '规则' . $gift['title'] . ':状态为开启状态不可以删除!');
                    exit;
                } else {
                    $giftObj->db->exec("DELETE FROM sdb_ome_gift_rule WHERE id=" . $id);
                }
            }
        }
        $this->end(true);
    }

    /**
     * import_goods
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function import_goods($id)
    {
        $this->pagedata['id'] = $id;
        $this->display('admin/gift/import_goods.html');
    }

    /**
     * exportTemplate
     * @return mixed 返回值
     */
    public function exportTemplate()
    {
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=促销规则货品导入模板." . date('Ymd') . ".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $oObj  = $this->app->model('gift_rule');
        $title = $oObj->exportTemplate();
        echo '"' . implode('","', $title) . '"';
        $data[0] = array('sales001');

        foreach ($data as $items) {
            foreach ($items as $key => $val) {
                $items[$key] = kernel::single('base_charset')->utf2local($val);
            }

            echo "\n";
            echo '"' . implode('","', $items) . '"';
        }

    }

}
