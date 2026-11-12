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
class console_finder_appropriation{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function __construct($app)
    {
        $this->app = $app;
        
    }
    
    var $addon_cols = 'process_status,from_branch_id,to_branch_id';
    var $column_confirm='操作';
    var $column_confirm_width = "120";
    function column_confirm($row){
        $appropriation_type = app::get('ome')->getConf('taoguanallocate.appropriation_type');
        if (!$appropriation_type) $appropriation_type = 'directly';
        $finder_id = $_GET['_finder']['finder_id'];

        $id = $row['appropriation_id'];
    
        $process_status = $row[$this->col_prefix.'process_status'];
        $from_branch_id = $row[$this->col_prefix.'from_branch_id'];
        $to_branch_id = $row[$this->col_prefix.'to_branch_id'];
        if($_GET['ctl']=='admin_transfer'){
 
        $checkbutton = <<<EOF
        &nbsp;&nbsp;<a class="lnk" href="javascript:if(confirm('审批单据')) {W.page('index.php?app=console&ctl=admin_transfer&act=finish&p[0]=$id&finder_id=$finder_id');};">审批</a> &nbsp;&nbsp;
EOF;
       
 
        $confirmbutton= <<<EOF
        &nbsp;&nbsp;<a class="lnk" href="javascript:if(confirm('审核单据')) {W.page('index.php?app=console&ctl=admin_transfer&act=check&p[0]=$id&finder_id=$finder_id');};">审核</a> &nbsp;&nbsp;
EOF;
        }
        $branchObj     = kernel::single('o2o_store_branch');
        $is_super = kernel::single('desktop_user')->is_super();
        $branch_ids    = $branchObj->getO2OBranchIds();
        $button = '';
        if(in_array($process_status,array('0'))){
            
            if($is_super || (!$is_super && in_array($from_branch_id,$branch_ids)) ){
                //$button.=$checkbutton;
            }
            
        }
        if(in_array($process_status,array('1'))){
            if($is_super || (!$is_super && in_array($to_branch_id,$branch_ids)) ){
                $button.=$confirmbutton;
            }
        }
        return $button;
    }


    public $detail_items = '调拨单明细';
    public function detail_items($appropriation_id)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        
        $render = $this->app->render();

        $items = app::get('taoguanallocate')->model('appropriation_items')->select()->columns('*')->where('appropriation_id=?',$appropriation_id)->instance()->fetch_all();
        foreach ($items as $key => $item) {
            $items[$key]['spec_info'] = &$spec[$item['product_id']];
            $items[$key]['barcode'] = &$barcode[$item['product_id']];
            $items[$key]['frome_branch_store'] = $item['from_branch_num'];
            $items[$key]['to_branch_store'] = $item['to_branch_num'];

            $product_id[] = $item['product_id'];
        }

        if ($items)
        {
            $productList    = $basicMaterialSelect->getlist_ext('bm_id, material_bn, specifications', array('bm_id'=>$product_id));
            
            foreach ($productList as $product)
            {
                #查询关联的条形码
                $product['barcode']    = $basicMaterialBarcode->getBarcodeById($product['product_id']);
                
                $spec[$product['product_id']] = $product['specifications'];
                $barcode[$product['product_id']] = $product['barcode'];
            }
        }

        if ($items[0]) {
            $from_branch_id = $items[0]['from_branch_id']; $to_branch_id = $items[0]['to_branch_id'];

            $branches = app::get('ome')->model('branch')->getList('name,branch_id',array('branch_id'=>array($from_branch_id,$to_branch_id)));

            foreach ($branches as $key => $branch) {
                if ($from_branch_id == $branch['branch_id']) {
                    $render->pagedata['from_branch_name'] = $branch['name'];
                }

                if ($to_branch_id == $branch['branch_id']) {
                    $render->pagedata['to_branch_name'] = $branch['name'];
                }
            }
        }

        $oAppropriation = app::get('taoguanallocate')->model("appropriation");
        $appropriation_info = $oAppropriation->dump(array('appropriation_id'=>$appropriation_id),'*');
        $render->pagedata['appropriation_info'] = $appropriation_info;
        $render->pagedata['items'] = $items;
        return $render->fetch('admin/appropriation/detail/items.html');
    }

}
?>
