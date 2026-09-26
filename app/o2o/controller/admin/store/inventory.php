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

class o2o_ctl_admin_store_inventory extends desktop_controller{
    var $name = "门店库存列表";
    var $workground = "o2o_center";
    
    function index()
    {
        $params = [];
        
        //列表新增门店搜索
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            
            $panel->setId('o2o_branch_finder_top');
            $panel->setTmpl('admin/finder/finder_store_panel_filter.html');
            
            $panel->show('o2o_mdl_branch_products', $params);
        }
        
        // 门店权限控制
        $base_filter = array('b_type' => '2');
        
        //商品可视状态
        if (!isset($_POST['visibility'])) {
            $base_filter['visibility'] = 'true';
        }elseif(empty($_POST['visibility'])){
            unset($_POST['visibility']);
        }
        
        // 多选时「全部」空选项会一起提交，需去掉空值，避免 SQL：IN (,2)
        $posted_branch_ids = array();
        if (isset($_POST['branch_id'])) {
            $posted_branch_ids = array_values(array_filter((array) $_POST['branch_id'], function ($v) {
                return $v !== '' && $v !== null && $v !== '_NULL_';
            }));
            // 同步清洗 $_POST，防止 finder 再次把空值合并进 filter
            if (!empty($posted_branch_ids)) {
                $_POST['branch_id'] = $posted_branch_ids;
            } else {
                unset($_POST['branch_id']);
            }
        }

        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            // 普通管理员：先设置默认无权限
            $base_filter['branch_id'] = array('false');
            
            // 获取有权限的门店（只获取门店类型且管控库存的）
            $mdlOmeBranch = app::get('ome')->model('branch');
            $branchList = $mdlOmeBranch->getList('branch_id', array(
                'b_type' => '2',
                'is_ctrl_store' => '1'
            ), 0, -1);
            
            if (!empty($branchList)) {
                $user_branch_ids = array_column($branchList, 'branch_id');
                
                // 如果用户选择了门店，验证权限（兼容单选 / 多选 branch_id[]）
                if (!empty($posted_branch_ids)) {
                    $selected = array_values(array_intersect($posted_branch_ids, $user_branch_ids));
                    $base_filter['branch_id'] = !empty($selected) ? $selected : array('false');
                } else {
                    // 没选择门店，显示所有有权限的门店
                    $base_filter['branch_id'] = $user_branch_ids;
                }
            }
        } else {
            // 超管：如果选择了门店就显示选择的，否则显示所有（兼容多选）
            if (!empty($posted_branch_ids)) {
                $base_filter['branch_id'] = $posted_branch_ids;
            }
        }
        
        $actions = array(
            array(
                'label'=>app::get('ome')->_('全部导出'),
                'class'=>'export',
                'icon'=>'add.gif',
                'href'=>'index.php?app=o2o&ctl=admin_store_inventory&act=export',
                'target'=>'dialog::{width:400,height:170,title:\'导出\'}'
            ),
        );
        
        $this->finder('o2o_mdl_branch_products',array(
            'title'=>'门店库存列表',
            'base_filter' => $base_filter,
            'actions' => $actions,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'finder_cols' => material_basic_material_master::mergeFinderCols(
                'column_branch_name,column_bn,column_product_name,column_barcode,column_spec_info,column_material_spu,column_weight,column_unit,store,store_freeze,column_available_store,arrive_store,column_vop_sku_stock',
                'column_bn'
            ),
            'object_method'=>array('count'=>'countlist','getlist'=>'getlists')
        ));
    }
    
    /**
     * export
     * @return mixed 返回值
     */
    public function export()
    {
        $branch_id = $_GET['branch_id'];
        $bn = $_GET['bn'];
        $_actual_store_search = $_GET['_actual_store_search'];
        $actual_store = $_GET['actual_store'];
        $_enum_store_search = $_GET['_enum_store_search'];
        $enum_store = $_GET['enum_store'];
        $this->pagedata['branch_id'] = $branch_id;
        $this->pagedata['bn'] = $bn;
        $this->pagedata['_actual_store_search'] = $_actual_store_search;
        $this->pagedata['actual_store'] = $actual_store;
        $this->pagedata['_enum_store_search'] = $_enum_store_search;
        $this->pagedata['enum_store'] = $enum_store;

        $this->page('admin/store/inventory/export.html');
    }
}
?>