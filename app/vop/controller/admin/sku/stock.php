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
 * 唯品会平台库存列表
 *
 * @author wangbiao@shopex.cn
 * @version 2025.05.28
 */
class vop_ctl_admin_sku_stock extends desktop_controller
{
    var $title = '平台库存列表';
    var $workground = 'console-vipshop-jit';
    
    private $_appName = 'vop';
    private $_mdl = null; //model类
    protected $_jitOrderLib = null;
    
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    
    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */

    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get($this->_appName)->model('sku_stock');
        
        $this->_jitOrderLib = kernel::single('console_inventory_orders');
        
        //primary_id
        $this->_primary_id = 'id';
        
        //primary_bn
        $this->_primary_bn = 'barcode';
    }
    
    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $actions = array();
        
        //filter
        $base_filter = array();
        
        //button
        $buttonList = array();
        
        //download
        $buttonList['dowloadStock'] = array(
            'label' => '查询唯品会购物车冻结',
            'href' => $this->url .'&act=pullVopCartStock&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
            'target' => "dialog::{width:550,height:350,title:'拉取唯品会购物车库存冻结数据'}",
        );
        
        //view
        $_GET['view'] = (empty($_GET['view']) ? '0' : $_GET['view']);
        switch ($_GET['view'])
        {
            case '0':
                $actions[] = $buttonList['dowloadStock'];
                break;
            default:
                //---
        }
        
        //导出权限
        $use_buildin_export = false;
        
        //params
        $orderby = 'id DESC';
        $params = array(
            'title' => $this->title,
            'base_filter' => $base_filter,
            'actions'=> $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => $use_buildin_export,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'orderBy' => $orderby,
        );
        
        $this->finder('vop_mdl_sku_stock', $params);
    }
    
    /**
     * _views
     * @return mixed 返回值
     */
    public function _views()
    {
        //filter
        $base_filter = array();
        
        //menu
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'), 'filter'=>$base_filter, 'optional'=>false),
        );
        
        foreach($sub_menu as $k => $v)
        {
            if (isset($v['filter'])){
                $v['filter'] = array_merge($base_filter, $v['filter']);
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['href'] = 'index.php?app='. $_GET['app'] .'&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$k;
            
            //第一个TAB菜单没有数据时显示全部
            if($k == 0){
                //count
                $sub_menu[$k]['addon'] = $this->_mdl->count($v['filter']);
                if($sub_menu[$k]['addon'] == 0){
                    unset($sub_menu[$k]);
                }
            }else{
                //count
                $sub_menu[$k]['addon'] = $this->_mdl->viewcount($v['filter']);
            }
        }
        
        return $sub_menu;
    }
    
    /**
     * 查询唯品会购物车冻结
     */
    public function pullVopCartStock()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        // 获取绑定的唯品会店铺列表
        $shopObj = app::get('ome')->model('shop');
        $filter = [
            'shop_type' => 'vop',
            'filter_sql' => 'node_id IS NOT NULL AND node_id != ""',
        ];
        $shopList = $shopObj->getList('shop_id,shop_bn,name AS shop_name,shop_type,node_id', $filter);
        
        $this->pagedata['shopList'] = $shopList;
        
        //开始时间(默认为昨天)
        //$start_time = date('Y-m-d', time());
        $start_time = '';
        $this->pagedata['start_time'] = $start_time;
        
        //help帮助信息
        $this->pagedata['help_msg'] = '*温馨提醒：最多只能拉取近三个月内的T-1日数据。结束时间是今天零点的时间。';
        
        //店铺编码
        $this->pagedata['selectListName'] = '店铺编码';
        
        //post url
        $this->pagedata['postUrl'] = $this->url .'&act=ajaxVopCartStock';
        
        //check
        if(empty($shopList)){
            die('没有绑定唯品会店铺');
        }
        
        $this->display('admin/vop/download_datalist.html');
    }
    
    /**
     * ajax查询唯品会购物车冻结
     */
    public function ajaxVopCartStock()
    {
        $shopObj = app::get('ome')->model('shop');
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        $jitOrderLib = kernel::single('console_inventory_orders');
        $codeBaseLib = kernel::single('material_codebase');
        
        //check
        if(empty($_POST['shop_bn'])){
            $retArr['err_msg'] = array('请先选择店铺编码');
            echo json_encode($retArr);
            exit;
        }
        
        //page
        $nextPage = isset($_POST['nextPage']) && $_POST['nextPage'] > 1 ? $_POST['nextPage'] : 1;
        
        //setting
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'total' => 0,
            'next_page' => 0,
            'err_msg' => array(),
        );
        
        // 获取绑定的唯品会店铺
        $filter = [
            'shop_type' => 'vop',
            'shop_bn' => $_POST['shop_bn'],
            'filter_sql' => 'node_id IS NOT NULL AND node_id != ""',
        ];
        $shopInfo = $shopObj->dump($filter, '*');
        if(empty($shopInfo)){
            $retArr['err_msg'] = array('唯品会店铺不符合,无法拉取数据');
            echo json_encode($retArr);
            exit;
        }
        
        //total
        $total = $basicMaterialObj->count([]);
        
        //基础物料列表
        $filter = [];
        $limit = 100;
        $offset = ($nextPage - 1) * $limit;
        $orderby = 'bm_id ASC';
        $materialList = $basicMaterialObj->getList('bm_id,material_bn', $filter, $offset, $limit, $orderby);
        
        //check
        if(empty($materialList)){
            $current_num = 0;
            $current_succ_num = 0;
            $current_fail_num = 0;
            
            $retArr['itotal'] += $current_num; //本次拉取记录数
            $retArr['isucc'] += $current_succ_num; //处理成功记录数
            $retArr['ifail'] += $current_fail_num; //处理失败记录数
            $retArr['total'] = $total; //数据总记录数
            $retArr['next_page'] = 0; //下一页页码(如果为0则无需拉取)
            
            echo json_encode($retArr);
            exit;
        }
        
        //获取基础物料关联的条形码
        $materialList = $codeBaseLib->getMergeMaterialCodes($materialList);
        
        //批量查询唯品会商品库存并保存
        $result = $jitOrderLib->downloadVopSkuStock($shopInfo, $materialList);
        
        //setting
        $nextPage++;
        if($result['rsp'] == 'succ'){
            $current_num = count($materialList);
            $current_succ_num = count($materialList);
            $current_fail_num = 0;
        }else{
            $current_num = count($materialList);
            $current_succ_num = 0;
            $current_fail_num = count($materialList);
        }
        
        $retArr['itotal'] += $current_num; //本次拉取记录数
        $retArr['isucc'] += $current_succ_num; //处理成功记录数
        $retArr['ifail'] += $current_fail_num; //处理失败记录数
        $retArr['total'] = $total; //数据总记录数
        $retArr['next_page'] = $nextPage; //下一页页码(如果为0则无需拉取)
        
        echo json_encode($retArr);
        exit;
    }
}