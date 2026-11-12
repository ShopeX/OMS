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
 * 产品线finder
 * @author wangjianjun@shopex.cn
 * @version 2024.04.12
 */
class dealer_finder_series
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "status";
    /*
     * 操作按钮
     * $row 数组
     * @return string
     */
    public $column_edit       = '操作';
    public $column_edit_width = 225;
    public $column_edit_order = 1;
    public function column_edit($row)
    {
        $status    = $row[$this->col_prefix . "status"];
        $series_id = $row["series_id"];
        $find_id   = $_GET['_finder']['finder_id'];
        $series_bn = $row['series_code'];
        $btn_arr   = array();
        // //查看
        // $btn_arr[] = '<a href="index.php?app=dealer&ctl=admin_series&act=detail&p[0]=' . $series_id . '&finder_id=' . $_GET['_finder']['finder_id'] . '" target="_blank">查看</a>';
        //编辑
        $btn_arr[] = '<a href="index.php?app=dealer&ctl=admin_series&act=edit&p[0]=' . $series_id . '&finder_id=' . $_GET['_finder']['finder_id'] . '" target="_blank">编辑</a>';
        //启/停用
        if ($status == "active") {
            //当前启用状态 做停用
            // $btn_arr[] = <<<EOF
            //     <a href="javascript:if (confirm('你确定要停用当前的产品线吗？')){W.page('index.php?app=dealer&ctl=admin_series&act=setStatus&p[0]=$series_id&p[1]=false&finder_id=$find_id', $extend({method: 'get'}, JSON.decode({})), this);}void(0);" target="">关停</a>
            // EOF;
            $btn_arr[] = <<<EOF
                <a href="index.php?app=dealer&ctl=admin_series&act=closeSeries&p[0]=$series_id&finder_id=$find_id" target="dialog::{width:680,height:550,title:'关停产品线:$series_bn'}">关停</a>
            EOF;
        } else {
            //当前停用状态 做启用
            $btn_arr[] = <<<EOF
                <a href="javascript:if (confirm('你确定要启用当前的产品线吗？')){W.page('index.php?app=dealer&ctl=admin_series&act=setStatus&p[0]=$series_id&p[1]=true&finder_id=$find_id', $extend({method: 'get'}, JSON.decode({})), this);}void(0);" target="">开启</a>
            EOF;
        }
        // 添加物料
        $btn_arr[] = '<a href="index.php?app=dealer&ctl=admin_series&act=edit&p[0]=' . $series_id . '&p[1]=material&finder_id=' . $_GET['_finder']['finder_id'] . '" target="_blank">添加物料</a>';
        // 授权店铺
        $btn_arr[] = '<a href="index.php?app=dealer&ctl=admin_series&act=edit&p[0]=' . $series_id . '&p[1]=shop&finder_id=' . $_GET['_finder']['finder_id'] . '" target="_blank">授权店铺</a>';
        // 导入物料
        $btn_arr[] = '<a href="index.php?app=omecsv&ctl=admin_import&act=main&ctler=dealer_mdl_series_products&add=dealer&sid=' . $series_id . '&finder_id=' . $_GET['_finder']['finder_id'] . '" target="dialog::{width:500,height:250,title:\'' . app::get('desktop')->_('导入物料') . '\'}">导入物料</a>';
        return implode("&nbsp;", $btn_arr);
        //判断单独权限用 先留着
        //$use_buildin_edit = kernel::single('desktop_user')->has_permission('sales_material_edit');
        //if ($use_buildin_edit) {
    }

    public $column_status       = '状态';
    public $column_status_width = 60;
    public $column_status_order = 60;
    public function column_status($row, $list)
    {
        switch ($row[$this->col_prefix . 'status']) {
            case 'active':
                $status = '<span style="color:#109010">启用</span>';
                break;
            case 'close':
                $status = '<span style="color:#e60000">停用</span>';
                break;
            default:
                $status = $row[$this->col_prefix . 'status'];
                break;
        }
        return $status;
    }

    /**
     * 关联基础物料.
     * @param
     * @return
     * @access
     * @author maxiaochen@shopex.cn
     */
    public $column_included_material       = '关联基础物料';
    public $column_included_material_width = '150';
    public $column_included_material_order = '65';
    public function column_included_material($row, $list)
    {
        // 鼠标悬停，最多只展示50条
        $productsMdl = app::get('dealer')->model('series_products');
        $basicMdl    = app::get('material')->model('basic_material');

        $filter      = ['series_id'=>$row['series_id']];
        $bm_id_arr   = $productsMdl->getList('bm_id', $filter, 0, 50);
        $bm_id_arr   = array_column($bm_id_arr, 'bm_id');
        $bnList      = $basicMdl->getList('material_bn,material_name', array('bm_id'=>$bm_id_arr));
        $count       = $productsMdl->count($filter);
        
        $endorse = $detail = '';
        if ($bnList) {
            $endorse = $bnList[0]['material_name'].'（<span style="color:#e60000">'.$count.'</span>）';
            $detail = implode('<br>', array_column($bnList, 'material_name'));
        }
        // return "<span class='show_list' series_id=" . $row['series_id'] . " ><a title={$show} >" . $show . "</a></span>";
        return "<a><span class='show_list' series_id=" . $row['series_id'] . "><div class=\"desc-tip\" onmouseover=\"bindFinderColTip(event);\">" . $endorse . "<textarea style=\"display:none;\"><h4>关联基础物料($count)</h4>" . $detail . "</textarea></div></span></a>";
    }

    public $column_endorse       = '授权经销店铺';
    public $column_endorse_width = 100;
    public $column_endorse_order = 70;
    public function column_endorse($row, $list)
    {
        static $endorseList;
        if (!$endorseList) {
            $seMdl   = app::get('dealer')->model('series_endorse');
            $shopMdl = app::get('ome')->model('shop');

            $seriesIdArr       = array_column($list, 'series_id');
            $seriesEndorseList = $seMdl->getList('*', ['series_id|in' => $seriesIdArr]);
            $shopIdArr         = array_unique(array_column($seriesEndorseList, 'shop_id'));
            $shopNameList      = array_column($shopMdl->getList('shop_id,shop_bn,name'), null, 'shop_id');
            foreach ($seriesEndorseList as $k => $v) {
                $endorseList[$v['series_id']][] = $shopNameList[$v['shop_id']]['name'];
            }
        }
        $endorse = $detail = '';
        if (isset($endorseList[$row['series_id']]) && $endorseList[$row['series_id']]) {

            $count    = count($endorseList[$row['series_id']]);
            $endorse  = implode('、', $endorseList[$row['series_id']]);
            $detail   = implode('<br>', $endorseList[$row['series_id']]);
        }

        return '<span style="color:#0000ff"><div class="desc-tip" onmouseover="bindFinderColTip(event);">' . $endorse . '<textarea style="display:none;"><h4>授权经销店铺('.$count.')</h4>' . $detail . '</textarea></div></span>';
    }

    // /**
    //  * 产品线商品
    //  * @param int $series_id 产品线主键ID
    //  * @return string
    //  */
    // public $detail_series_products = '产品线商品';
    // public function detail_series_products($series_id)
    // {
    //     $render   = app::get('dealer')->render();
    //     $bmMdl    = app::get('material')->model('basic_material');
    //     $sepMdl   = app::get('dealer')->model('series_products');
    //     $bmIdList = $sepMdl->getList('bm_id', array('series_id' => $series_id));
    //     $bmIdList = array_unique(array_column($bmIdList, 'bm_id'));
    //     $bmList   = $bmMdl->getList('*', ['bm_id|in' => $bmIdList]);

    //     $render->pagedata['bmList'] = $bmList;
    //     return $render->fetch('admin/series/products.html');
    // }

    // /**
    //  * 产品线店铺
    //  * @param int $series_id 产品线主键ID
    //  * @return string
    //  */
    // public $detail_series_shop = '产品线店铺';
    // public function detail_series_shop($series_id)
    // {
    //     $render     = app::get('dealer')->render();
    //     $shopMdl    = app::get('ome')->model('shop');
    //     $sepMdl     = app::get('dealer')->model('series_endorse_products');
    //     $shopIdList = $sepMdl->getList('distinct shop_id, sep_id', array('series_id' => $series_id));
    //     $shopIdList = array_column($shopIdList, 'shop_id');
    //     $shopList   = $shopMdl->getList('*', ['shop_id|in' => $shopIdList]);

    //     $render->pagedata['shopList'] = $shopList;
    //     return $render->fetch('admin/series/shop.html');
    // }

    /**
     * 订单操作记录
     * @param int $series_id 产品线主键ID
     * @return string
     */
    public $detail_show_log = '操作记录';
    public function detail_show_log($series_id)
    {
        //操作日志
        $logObj = app::get('ome')->model('operation_log');
        //产品线日志
        $logList = $logObj->read_log(array('obj_id' => $series_id, 'obj_type' => 'series@dealer'), 0, -1);
        $finder_id = $_GET['_finder']['finder_id'];
        foreach ($logList as $k => $v) {
            $logList[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);

            if ($v['operation'] == '产品线编辑') {
                $logList[$k]['memo'] .= " <a href='index.php?app=dealer&ctl=admin_series&act=show_history&p[0]={$v['log_id']}&finder_id={$finder_id}' target=\"_blank\">查看快照</a>";
            }
        }
        $render                   = app::get('dealer')->render();
        $render->pagedata['logs'] = $logList;
        return $render->fetch('admin/series/show_log.html');
    }
}
