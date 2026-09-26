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
 * 产品线列表
 * @author wangjianjun@shopex.cn
 * @version 2024.04.12
 */
class dealer_ctl_admin_series_endorse_products extends desktop_controller
{

    /**
     * 品牌授权商品列表查询项方法
     * @param Post
     * @return String
     */

    public function index()
    {
        $base_filter = [];
        // 根据权限，只展示当前账号有权限的商品
        $cosList = kernel::single('organization_cos')->getCosList('', ['shop']);
        if (!$cosList[0]) {
            // 无权限时用空数组，避免 finder_filter 中 implode 对标量报错
            $base_filter['shop_id'] = [0];
        } elseif ($cosList[0] && $cosList[1] != '_ALL_') {
            $base_filter['shop_id'] = [0];

            $shopCosList = array_filter((array) $cosList[1], function ($item) {
                return isset($item['cos_type']) && $item['cos_type'] == 'shop';
            });
            $cosCodeArr = array_column($shopCosList, 'cos_code');
            if ($cosCodeArr) {
                $shopMdl  = app::get('ome')->model('shop');
                $shopList = $shopMdl->getList('shop_id', ['shop_bn|in' => $cosCodeArr]);
                if ($shopList) {
                    $base_filter['shop_id'] = array_column($shopList, 'shop_id');
                }
            }
        }
        $params = array(
            'title'               => '商品代发设置',
            'use_buildin_set_tag' => false,
            'use_buildin_filter'  => false,
            'use_buildin_export'  => false,
            'use_buildin_filter'  => true,
            'use_buildin_recycle' => false,
            'base_filter'         => $base_filter,
            'actions'             => [
                array('label' => app::get('dealer')->_('批量设置发货方式'), 'submit' => "index.php?app=dealer&ctl=admin_series_endorse_products&act=batchSetShopyjdfType&view=".$_GET['view'], 'target' => 'dialog::{width:690,height:310,title:\'批量设置发货方式\'}'),
            ],
            'object_method'       => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        );
        $this->finder('dealer_mdl_series_endorse_products', $params);
    }

    /**
     * 销售物料列表分栏菜单
     * 
     * @param Null
     * @return Array
     */
    public function _views()
    {
        #不是销售列表时_隐藏Tab
        if ($_GET['act'] != 'index') {
            return array();
        }

        $base_filter = [];
        // 根据权限，只展示当前账号有权限的商品
        $cosList = kernel::single('organization_cos')->getCosList('', ['shop']);
        if (!$cosList[0]) {
            // 无权限时用空数组，避免 finder_filter 中 implode 对标量报错
            $base_filter['shop_id'] = [0];
        } elseif ($cosList[0] && $cosList[1] != '_ALL_') {
            $base_filter['shop_id'] = [0];

            $shopCosList = array_filter((array) $cosList[1], function ($item) {
                return isset($item['cos_type']) && $item['cos_type'] == 'shop';
            });
            $cosCodeArr = array_column($shopCosList, 'cos_code');
            if ($cosCodeArr) {
                $shopMdl  = app::get('ome')->model('shop');
                $shopList = $shopMdl->getList('shop_id', ['shop_bn|in' => $cosCodeArr]);
                if ($shopList) {
                    $base_filter['shop_id'] = array_column($shopList, 'shop_id');
                }
            }
        }

        $sepMdl = app::get('dealer')->model('series_endorse_products');

        $sub_menu = array(
            0 => ['label' => app::get('base')->_('全部'), 'filter' => $base_filter, 'optional' => false],
            1 => ['label' => app::get('base')->_('代发货'), 'filter' => array_merge($base_filter, ['is_shopyjdf_type' => 2]), 'optional' => false],
            2 => ['label' => app::get('base')->_('自发货'), 'filter' => array_merge($base_filter, ['is_shopyjdf_type' => 1]), 'optional' => false],
        );

        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $sepMdl->finder_count($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=dealer&ctl=admin_series_endorse_products&act=index&view=' . $k;
        }

        return $sub_menu;
    }

    //批量设置发货方式
    /**
     * batchSetShopyjdfType
     * @return mixed 返回值
     */
    public function batchSetShopyjdfType()
    {
        $this->_request = kernel::single('base_component_request');
        $sepInfo        = $this->_request->get_post();
        if (!$sepInfo && isset($_GET['p']) && $_GET['p'][0]) {
            $sepInfo = [
                'sep_id' => [$_GET['p'][0]],
            ];
        }

        if ($sepInfo['isSelectedAll'] == '_ALL_') {
            echo '不支持全部设置!';exit;
        }
        if (empty($sepInfo['sep_id'])) {
            echo '请选择数据!';exit;
        }

        $time_list = $this->get_time_list();

        $this->pagedata['from_time_list'] = $time_list;
        $this->pagedata['end_time_list']  = $time_list;
        $this->pagedata['sep_id_list']    = implode(',', $sepInfo['sep_id']);
        $this->pagedata['from_date']      = date('Y-n-j', strtotime('+1 day'));
        $this->pagedata['view']           = isset($_GET['view']) ? $_GET['view'] : 0;
        $this->display('admin/series/endorse/products.html');
    }

    //批量设置发货方式
    /**
     * doBatchSetShopyjdfType
     * @return mixed 返回值
     */
    public function doBatchSetShopyjdfType()
    {
        // Array (
        //     [sep_id_list] => 12,11,10,9,4,3,2,1
        //     [is_shopyjdf_type] => 2
        //     [_DTYPE_DATE] => Array (
        //             [0] => from_date
        //             [1] => end_date
        //         )
        //     [from_date] => 2024-5-22
        //     [from_time_h] => 00
        //     [from_time_i] => 00
        //     [end_date] => 2024-5-31
        //     [end_time_h] => 00
        //     [end_time_i] => 00
        // )
        $this->begin("index.php?app=dealer&ctl=admin_series_endorse_products&act=index&view=" . $_GET['view']);

        $all_sep_id = $_POST['sep_id_list'];
        if (!empty($all_sep_id)) {
            $all_sep_id = explode(',', $all_sep_id);
        }
        if (empty($all_sep_id)) {
            $this->end(false, '提交数据有误!');
        }

        if (!$_POST['from_date']) {
            $this->end(false, '请设置开始时间');
        } elseif (strtotime($_POST['from_date'] . ' ' . $_POST['from_time_h'] . ':' . $_POST['from_time_i'] . ':00') < time()) {
            $this->end(false, '开始时间必须大于当前时间');
        }
        $fromTime = strtotime($_POST['from_date'] . ' ' . $_POST['from_time_h'] . ':' . $_POST['from_time_i'] . ':00');

        $endTime = '';
        if ($_POST['end_date']) {
            $endTime = strtotime($_POST['end_date'] . ' ' . $_POST['end_time_h'] . ':' . $_POST['end_time_i'] . ':00');
        }
        // // 代发模式必须要设置结束时间
        // if ($_POST['is_shopyjdf_type'] == '2') {
        //     if (!$_POST['end_date']) {
        //         $this->end(false, '请设置结束时间');
        //     }
        //     $endTime = strtotime($_POST['end_date'] . ' ' . $_POST['end_time_h'] . ':' . $_POST['end_time_i'] . ':00');
        // } elseif ($_POST['is_shopyjdf_type'] == '1') {
        //     $endTime = '';
        // } else {
        //     $this->end(false, '发货方式无效');
        // }

        if ($endTime && $endTime <= $fromTime) {
            $this->end(false, '结束时间必须大于开始时间');
        }
        $mdl = app::get('dealer')->model('series_endorse_products');

        $list = $mdl->getList('*', ['sep_id|in' => $all_sep_id]);
        $list = array_column($list, null, 'sep_id');

        $upInfo = [
            'is_shopyjdf_type' => $_POST['is_shopyjdf_type'],
            'from_time'        => $fromTime,
            'end_time'         => $endTime ? $endTime : null,
        ];
        $mdl->update($upInfo, ['sep_id|in' => $all_sep_id]);
        // 自动添加销售物料
        $mdl->saveSalesMaterial($all_sep_id);

        $memo = '设置';
        if ($upInfo['is_shopyjdf_type'] == '2') {
            $memo .= '代发货';
        } elseif ($upInfo['is_shopyjdf_type'] == '1') {
            $memo .= '自发货';
        }
        $memo .= '模式,生效时间:' . date('Y-m-d H:i', $fromTime) . ',结束时间:';
        $upInfo['end_time'] && $memo .= date('Y-m-d H:i', $endTime);

        // 保存操作记录
        $omeLogMdl = app::get('ome')->model('operation_log');
        $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
        foreach ($list as $sep_id => $snapshoot) {
            $log_id = $omeLogMdl->write_log('set_shop_yjdfType@dealer', $sep_id, $memo);
            if ($log_id && $snapshoot) {
                $snapshoot = json_encode($snapshoot, JSON_UNESCAPED_UNICODE);
                $tmp       = ['log_id' => $log_id, 'snapshoot' => $snapshoot];
                $shootMdl->insert($tmp);
            }
        }
        $this->end(true, app::get('base')->_('设置成功'));
    }

    private function get_time_list()
    {
        $h = $i = array();

        for ($a = 0; $a < 24; $a++) {
            $h[$a] = str_pad($a, 2, 0, STR_PAD_LEFT);
        }
        for ($a = 0; $a < 60; $a++) {
            $i[$a] = str_pad($a, 2, 0, STR_PAD_LEFT);
        }
        return array('h' => $h, 'i' => $i);
    }

    /**
     * 查看快照
     */
    public function show_history($log_id)
    {

        $logSnapshootMdl = app::get('ome')->model('operation_log_snapshoot');
        //日志
        $log = $logSnapshootMdl->db_dump(['log_id' => $log_id]);
        $row = json_decode($log['snapshoot'], 1);

        $this->pagedata['from_date']   = $row['from_time'] ? date('Y-n-j', $row['from_time']) : '';
        $this->pagedata['from_time_h'] = $row['from_time'] ? date('H', $row['from_time']) : '';
        $this->pagedata['from_time_i'] = $row['from_time'] ? date('i', $row['from_time']) : '';
        $this->pagedata['end_date']    = $row['end_time'] ? date('Y-n-j', $row['end_time']) : '';
        $this->pagedata['end_time_h']  = $row['end_time'] ? date('H', $row['end_time']) : '';
        $this->pagedata['end_time_i']  = $row['end_time'] ? date('i', $row['end_time']) : '';

        $from_time_list = [
            'h' => [$this->pagedata['from_time_h']],
            'i' => [$this->pagedata['from_time_i']],
        ];
        $end_time_list = [
            'h' => [$this->pagedata['end_time_h']],
            'i' => [$this->pagedata['end_time_i']],
        ];
        $this->pagedata['from_time_list']   = $from_time_list;
        $this->pagedata['end_time_list']    = $end_time_list;
        $this->pagedata['is_shopyjdf_type'] = $row['is_shopyjdf_type'];
        $this->pagedata['history']          = true;
        $this->singlepage('admin/series/endorse/products.html');
    }

}
