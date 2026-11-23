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

class console_ctl_admin_cpfr extends desktop_controller
{
    public function index()
    {
        $actions = array(
                 
        );
        $actions[] = array(
                'label'  => '导入模板',
                'href'   => $this->url.'&act=downloadCpfrTmpl',
                'target' => '_blank',
        );
        $actions[] = array(
                'label'  => '导入配货',
                'href'   => $this->url . '&act=cpfrImport',
                'target' => "dialog::{width:550,height:350,title:'导入配货'}",
        );
        $params = array(
            'title'                  => '配货单列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'actions'                => $actions,
            'orderBy'                => 'cpfr_id desc',
        );
        
        $this->finder('console_mdl_cpfr', $params);
    }
    
    /**
     * 一键补发
     *
     * @return void
     * @author
     **/
    public function cpfrImport()
    {
        $this->display('admin/stock/cpfr_import.html');
    }
    
    /**
     * 下载渠道库存模板
     *
     * @return void
     * @author
     **/
    public function downloadCpfrTmpl()
    {
        set_time_limit(0);
        @ini_set('memory_limit', '512M');
    

        $data = array(
            array(
                'A1' => '*:SKUID',
                'B1' => '发货仓库',
                'C1' => '*:配货数量',
                'D1' => '*:店仓代码',
                'F1' => '*:备注',
            ),
            array(
                'A2' => 'test001',
                'B2' => 'qimen',
                'C2' => '1',
                'D2' => 'md001',
                'F2' => '',
            ),
            array(
                'A3' => 'test002',
                'B3' => 'qimen',
                'C3' => '2',
             
                'D3' => 'md002',
                'F3' => '',
            ),
        );
        
       
        kernel::single('omecsv_phpexcel')->newExportExcel($data, '配货单导入', 'xls');
    }
    
    /**
     * 导入一键补发数据
     *
     * @return void
     * @author
     **/
    public function importCpfrData()
    {
        ini_set('memory_limit','1G');
        $filename = $_FILES['import_file']['tmp_name'];
        if (!$filename) {
            $this->splash('error', null, '未上传文件');
        }
        
        if (!in_array(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION), ['xls', 'xsl', 'xlsx'])) {
            $this->splash('error', null, '请上传xls文件类型');
        }
        // 读取文件
        try {
            kernel::single('omecsv_io_io')->fgethandle($filename,$data);
            if (!$data[0]) {
                $this->splash('error', null, '文件内容为空');
            }
            
            
            foreach ($data as $key => $value) {
                unset($data[$key]);
    
               
                if ($value[0] == '*:SKUID') {
                    $title = $value;
                    break;
                }
            }
            
           
            //导入的bn
            $bn_list = array_filter(array_column($data, 0));
            
            if (!$bn_list) {
                $this->splash('error', null, '基础物料编码不能为空');
            }
            
           
            $branch_bns = array_column($data, 1);

            $branch_lists = app::get('ome')->model('branch')->getList('branch_bn,branch_id', array('branch_bn' => $branch_bns,'check_permission'=>'false'));

            if(count($branch_lists)>1){
                $this->splash('error', null, '出库仓只支持一个');

            }

            $branch_list = array_column($branch_lists, null, 'branch_bn');

            $branch_idlist = array_column($branch_lists, null, 'branch_id');

            $branch_ids = array_column($branch_lists, 'branch_id');

            $store_bns = array_column($data, 3);

            $store_list = app::get('o2o')->model('store')->getList('store_id,store_bn,branch_id', array('store_bn' => $store_bns));

            $store_list = array_column($store_list, null, 'store_bn');
            
            $bm_list = app::get('material')->model('basic_material')->getList('material_name,material_bn,bm_id', array('material_bn' => $bn_list));
            if (!$bm_list) {
                $this->splash('error', null, '基础物料编码不存在');
            }
            $bm_list = array_column($bm_list, null, 'material_bn');
            
            $bm_id_list = array_column($bm_list, 'bm_id');
            
            // 库存判断
            $bpMdl = app::get('ome')->model('branch_product');
            
            $product_store =$bpMdl->getList('product_id,branch_id,store,store_freeze', array('product_id' =>$bm_id_list, 'branch_id' => $branch_ids));
        
            $product_store = array_column($product_store, null, 'product_id');

            $bp_list = array();
            
            $msg = [];
            
            $items       = array();
            
            foreach ($data as $key => $value) {
                
                $row         = $value;
                
                $material_bn = array_shift($row);
                $branch_bn = array_shift($row);
                $num = array_shift($row);
                $store_bn = array_shift($row);
                
                $store = $store_list[$store_bn] ? $store_list[$store_bn] : '';

                if(!$store){
                    $this->splash('error', null, sprintf('[%s]门店不存在', $store_bn));
                }
                  
                if (!is_numeric($num) || $num <= 0) {
                    $num = 0;
                }
                
                $bm = $bm_list[$material_bn];
                if (!$bm) {
                    $this->splash('error', null, sprintf('[%s]基础物料不存在', $material_bn));
                }

                $pstores = $product_store[$bm['bm_id']];

                if($pstores['store']<$num){
                    $this->splash('error', null, sprintf('[%s]库存不足', $material_bn));
                }
                
                $branch_id = $branch_list[$branch_bn]['branch_id'];
                $to_branch_id = $store['branch_id'];
                $items[$to_branch_id][$material_bn]['num']         = $num;
                $items[$to_branch_id][$material_bn]['branch_id']   = $branch_id;
                $items[$to_branch_id][$material_bn]['product_id']  = $bm['bm_id'];
                $items[$to_branch_id][$material_bn]['store_bn']    = $store_bn;
                $items[$to_branch_id][$material_bn]['to_branch_id']= $store['branch_id'] ? $store['branch_id'] : 0;
                
            }
        

            $operator = kernel::single('desktop_user')->get_name();

            $branch_id = $branch_lists[0]['branch_id'];
            $branchs= $branch_idlist[$branch_id];
           

             // 插入到配货表
            $cpfr_data = array(
                'cpfr_bn'        => uniqid(date('YmdHi')),
                'cpfr_name'      => $_FILES['import_file']['name'],
                'store_total'    => count($items),
                'create_time'    => time(),
                'operator'       => $operator,
                'branch_id'      => $branch_id,
                'branch_bn'      => $branchs['branch_bn'],

            );
            $cpfr_data_items = array();
            $sku_total = $num_total = $original_total = 0;

            foreach($items as $k=>$item){
                
                $cpfrMdl = app::get('console')->model('cpfr');
                
                foreach ($item as $bn => $r) {

                    $cpfr_data_items[] = array(
                        'bn'           => $bn,
                        'product_id'   => $r['product_id'],
                        'num'          => $r['num'],
                        'store_bn'     => $r['store_bn'],
                        'to_branch_id' => $r['to_branch_id'],
                        
                    );
                    
                    if ($r['num'] < 0) {
                        unset($items[$store_bn][$bn]);
                        continue;
                    }
                    $num_total += $r['num'];
                    $sku_total++;
                }
                     
            }
            
            $cpfr_data['sku_total'] = $sku_total;
            $cpfr_data['original_total']=$cpfr_data['num_total'] = $num_total;

         
            if (!$cpfrMdl->insert($cpfr_data)) {
                $this->splash('error', null, '单据添加失败');
            }
            $cpfrItemMdl = app::get('console')->model('cpfr_items');
            foreach($cpfr_data_items as &$v){
                $v['cpfr_id'] = $cpfr_data['cpfr_id'];
            }
            $sql = ome_func::get_insert_sql($cpfrItemMdl, $cpfr_data_items);
            
            if (!kernel::database()->exec($sql)) {
                $this->splash('error', null, '渠道库存明细生成失败');
            }
                
            $msg       = implode('<br/>', $msg);
            $finder_id = $_GET['finder_id'];
            header("content-type:text/html; charset=utf-8");
            echo <<<JS
                <script>
                    alert("上传成功");
                    parent.\$E('#import-form .error').set('html',"部分导入失败：<br/>$msg");


                    if ("$msg") {
                        parent.\$E('#import-form .error').show();
                    } else {
                        parent.\$E('#import-form').getParent('.dialog').retrieve('instance').close();

                        if (window.finderGroup && window.finderGroup["$finder_id"]) {
                            window.finderGroup["$finder_id"].refresh();
                        }else{
                            parent.location.reload();
                        }
                    }

                </script>
JS;
        
        } catch (Exception $e) {
            $this->splash('error', null, '文件读取失败:' . $e->getMessage());
            
        }
    }
    
    public function sortRegions($region_names){
        $region_names = explode(',',$region_names);
        sort($region_names);
        $region_names = implode(',',$region_names);
        return $region_names;
    }
    
    /**
     * 获取配货单明细
     */
    public function getItems($cpfr_id)
    {
        if(empty($cpfr_id)){
            return '';
        }
        
        $cpfrItemsObj = app::get('console')->model('cpfr_items');
        
        //配货单明细
        $dataList = $cpfrItemsObj->getList('*', array('cpfr_id'=>$cpfr_id));
        if(empty($dataList)){
            return '';
        }
        
        //format
        $branchList = array();
        foreach ($dataList as $key => $val)
        {
            $to_branch_id = intval($val['to_branch_id']);
            
            if(empty($branchList[$to_branch_id])){
                $sql = "SELECT branch_id,branch_bn,name FROM sdb_ome_branch WHERE branch_id=".$to_branch_id;
                $tempInfo = $cpfrItemsObj->db->selectrow($sql);
                if($tempInfo){
                    $branchList[$to_branch_id] = $tempInfo;
                }
            }
            
            $dataList[$key]['branch_name'] = $branchList[$to_branch_id]['name'];
        }
        
        echo(json_encode($dataList));
    }

    public function singleConfirm($cpfr_id){
        $cpfrMdl = app::get('console')->model('cpfr');

        $cpfrs = $cpfrMdl->db_dump(array('cpfr_id'=>$cpfr_id),'cpfr_id,cpfr_bn,bill_status,adjust_type');

        if(!$cpfrs || !in_array($cpfrs['bill_status'],array('1')) || $cpfrs['adjust_type']!='import'){

            $this->splash('error', $this->url, '确认失败:没有需要操作的配货单');
        }

        $data = [
            'cpfr_bn'   =>  $cpfrs['cpfr_bn'],
            'cpfr_id'   =>  $cpfrs['cpfr_id'],
        ];
        $rs = kernel::single('console_replenish')->createAppropriation($data,$error_msg);
        
        if(!$rs) {
            $this->splash('error', $this->url, '确认失败:'.$error_msg);
        }
        $this->splash('success', $this->url, '确认成功');

    }
}
