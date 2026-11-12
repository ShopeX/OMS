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

class dealer_finder_shop_stocksync {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = 'config,shop_type,name,shop_bn,business_type';
 
    public $column_request = '自动回写库存';
    public $column_request_order = 2;
    public $column_request_width = 100;
    /**
     * column_request
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_request($row)
    {
        $request = kernel::single('inventorydepth_shop')->getStockConf($row['shop_id']);

        if ($request == 'true') {
            $word = '开启';
            $color = 'green';
            $title = '点击关闭向该店铺自动回写库存功能';
            $href = 'index.php?app=inventorydepth&ctl=shop&act=set_request&p[0]=false&p[1]='.$row['shop_id'].'&finder_id='.$_GET['_finder']['finder_id'];
        }else{
            $word = '关闭';
            $color = '#a7a7a7';
            $title = '点击开启向该店铺自动回写库存功能';
            $href = 'index.php?app=inventorydepth&ctl=shop&act=set_request&p[0]=true&p[1]='.$row['shop_id'].'&finder_id='.$_GET['_finder']['finder_id'];
        }

        //不支持库存回写的店铺类型
        if(in_array($row['shop_type'], inventorydepth_shop_api_support::$no_write_back_stock)) {
            return '<span title="该平台不支持库存回写">' . $word . '</span>';
        }
        
        return <<<EOF
        <a style="background-color:{$color};float:left;text-decoration:none;" href="{$href}"><span title="{$title}" style="color:#eeeeee;padding:2px;">&nbsp;{$word}&nbsp;</span></a>
EOF;
    }

    
    public $column_stock_regulation = '库存更新规则';
    public $column_stock_regulation_order = 29;
    /**
     * column_stock_regulation
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_stock_regulation($row)
    {
        $regulation_id = app::get('inventorydepth')->model('regulation_apply')->select()->columns('regulation_id')
                                        ->where('shop_id=?',$row['shop_id'])
                                        ->where('type=?','1')
                                        ->where('`condition`=?','stock')
                                        ->where('`using`=?','true')
                                        ->instance()->fetch_one();

        $rr = app::get('inventorydepth')->model('regulation')->select()->columns('regulation_id,heading')
                ->where('regulation_id=?',$regulation_id)
                ->where('`using`=?','true')
                ->instance()->fetch_row();
        if($rr){
        return <<<EOF
        <a href="index.php?app=inventorydepth&ctl=regulation&act=dialogEdit&p[0]={$rr['regulation_id']}&finder_id={$_GET['_finder']['finder_id']}" target="dialog::{title:'修改规则',width:800}">{$rr['heading']}</a>
EOF;
        }else{
            $src = app::get('desktop')->res_full_url.'/bundle/btn_add.gif';
            $shop_bn = $row[$this->col_prefix.'shop_bn'];
           return <<<EOF
            <div><a title="添加规则" target="dialog::{title:'添加规则'}" href="index.php?app=inventorydepth&ctl=regulation&act=dialogAdd&p[0]={$row['shop_id']}&p[1]={$shop_bn}&finder_id={$_GET['_finder']['finder_id']}"><img src={$src} ></a></div>
EOF;
        }
    }

    public $column_supply_branches = '供货仓';
    public $column_supply_branches_order = 90;
    /**
     * column_supply_branches
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_supply_branches($row) 
    {
        $branches = kernel::single('inventorydepth_shop')->getBranchByshop($row[$this->col_prefix.'shop_bn']);
        
        $branchList = app::get('ome')->model('branch')->getList('name',array('branch_bn'=>$branches));
        if ($branchList) {
            $branches = array_map('current', $branchList);
        }
        if ($branches) {
            $html = '<a href="index.php?app=inventorydepth&ctl=shop&act=displayBranchRelation&p[0]='.$row[$this->col_prefix . 'shop_bn'].'&p[1]='.$row['shop_id'].'&finder_id='.$_GET['_finder']['finder_id'].'" target="dialog::{width:800,height:440,title:\'设置绑定关系\'}"><span style="color:#0000ff">' . implode('</span>、<span style="color:#0000ff">', $branches) . '</span></a>';
            return '<div class="desc-tip" onmouseover="bindFinderColTip(event);">' . $html . '<textarea style="display:none;"><h3>店铺【<span style="color:red;">' . $row[$this->col_prefix . 'name'] . '</span>】供货仓库</h3>' . $html . '</textarea></div>';
        } else {
            $html = '<a href="index.php?app=inventorydepth&ctl=shop&act=displayBranchRelation&p[0]='.$row[$this->col_prefix . 'shop_bn'].'&p[1]='.$row['shop_id'].'&finder_id='.$_GET['_finder']['finder_id'].'" target="dialog::{width:800,height:440,title:\'设置绑定关系\'}"><div style="color:red;font-weight:bold;" onmouseover="bindFinderColTip(event);" rel="请先去仓库管理里绑定仓库与店铺关系，否则将影响库存回写！！！">无仓库供货</div></a>';
            return $html;
        }

    }

    public $detail_operation_log = '操作日志';
    /**
     * detail_operation_log
     * @param mixed $shop_id ID
     * @return mixed 返回值
     */
    public function detail_operation_log($shop_id)
    {
        $this->_render =  app::get('inventorydepth')->render();
        $optLogModel = app::get('inventorydepth')->model('operation_log');
        $filter = array('obj_type' => 'shop','obj_id' => $shop_id);
        $optLogModel->defaultOrder = ' log_id desc ';
        $optLogList = $optLogModel->getList('*',$filter,0,200);
        foreach ($optLogList as &$log) {
            $log['operation'] = $optLogModel->get_operation_name($log['operation']);
        }
        
        $this->_render->pagedata['optLogList'] = $optLogList;
        return $this->_render->fetch('finder/shop/operation_log.html');
    }

}