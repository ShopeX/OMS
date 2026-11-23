<?php
class ome_ctl_admin_sv_process extends desktop_controller{
    var $name = "质检";
    var $workground = "aftersale_center";

   function index(){
        $filter['recieved'] ="true";
        switch ($_GET['flt']){
            case 1:
                $title='';
                break;
            case 2:
                $title='未';
                $filter['verify'] ="false";
                break;
            case 3:
                $title='已';
                $filter['verify'] ="true";
                break;
        }

        // 获取操作员管辖仓库
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids){
                $filter['branch_id'] = $branch_ids;
            }else{
                $filter['branch_id'] = 'false';
            }
        }

        $params = array(
            'title'=>$title.'质检',
            'base_filter' => $filter,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            );

        $this->finder('ome_mdl_return_process', $params);
    }

     /*
     * 质检货品
     *@param $por_id
     */
    function edit($por_id = 0)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        
        $oProduct_pro = $this->app->model('return_process');
        $pro_items =$this->app->model('return_process_items');
        $oProduct = $this->app->model('return_product');
        $oOrder = $this->app->model('orders');
        $oProblem_type = $this->app->model('return_product_problem_type');
        $oProblem = $this->app->model('return_product_problem');
        
        $goodsObj = $this->app->model('goods');
        $productSerialObj = $this->app->model('product_serial');
        $serialLogObj = $this->app->model('product_serial_log');
        $memo='';
        if($_POST){
            $this->begin('index.php?app=ome&ctl=admin_sv_process&act=edit&p[0]='.$_POST['por_id']);
            if (!$_POST['process_id']){
                $this->end(false, app::get('base')->_('请先扫描货号'));
            }
            $is_problem=$_POST['is_problem'];
            if($is_problem=='true'){
                $problem_type = $_POST['problem_type']!='' ? implode(',',$_POST['problem_type']) : '';
                $problem_belong = $_POST['problem_belong']!='' ? implode(',',$_POST['problem_belong']) : '';
            }else{
                $problem_type = $_POST['unproblem_type']!='' ? implode(',',$_POST['unproblem_type']) : '';
                $problem_belong = $_POST['unproblem_belong']!='' ? implode(',',$_POST['unproblem_belong']) : '';
            }
            $opInfo = kernel::single('ome_func')->getDesktopUser();
            foreach((array)$_POST['process_id'] as $key=>$val){
                if($key && $key>0){
                    $data = array(
                        'item_id'=>$key,
                        'memo'=>$_POST['memo'],
                        'acttime'=>time(),
                        'op_id'=>kernel::single('desktop_user')->get_id(),
                        'is_check'=>'true',
                        'por_id'=>$_POST['por_id'],
                        'is_problem'=>$is_problem,
                        'problem_type'=>$problem_type,
                        'problem_belong'=>$problem_belong,
                        'store_type'=>$_POST['store_type'],
                        'branch_id'=>$_POST['instock_branch']
                    );
                    $pro_items->save($data);

                    $proitems = $pro_items->dump(array('item_id'=>$data['item_id']), 'return_id,order_id,product_id');
                    //售后服务类型插入关联表
                    $problem_type = $_POST['problem_type'];
                    if ($problem_type)
                    foreach ($problem_type as $k=>$v){
                        $problem_type_data = array(
                            'return_id' =>$proitems['return_id'],
                            'por_id' =>$data['por_id'],
                            'item_id' =>$data['item_id'],
                            'order_id' =>$proitems['order_id'],
                            'product_id' =>$proitems['product_id'],
                            'problem_id' => $v
                        );
                        $oProblem_type->save($problem_type_data);
                        $problem_type_strs .= $oProblem->getCatName($v)."、";
                    }
                    $problem_type_data2 = array(
                        'item_id'=>$data['item_id'],
                        'problem_type'=>$problem_type_strs,
                    );
                    $pro_items->save($problem_type_data2);

                    //为了售后问题类型的统计添加的字段(problem_id)，并且给该字段赋值
                    //begin
                    $oProduct_problem_id = array(
                        'return_id'=>$_POST['return_id'],
                        'problem_id'=>$_POST['problem_type'][0],
                    );
                    $oProduct->save($oProduct_problem_id);
                    //end
                }

                if($val && $val !=''){
                    $serialData = $productSerialObj->dump(array('serial_number'=>$val));
                    if($serialData && $serialData['item_id']>0){
                        $serialData['status'] = ($_POST['store_type']>0) ? 2 : 0;
                        $productSerialObj->save($serialData);

                        $logData['item_id'] = $serialData['item_id'];
                        $logData['act_type'] = 1;
                        $logData['act_time'] = time();
                        $logData['act_owner'] = $opInfo['op_id'];
                        $logData['bill_type'] = 1;
                        $logData['bill_no'] = $_POST['por_id'];
                        $logData['serial_status'] = $serialData['status'];
                        $serialLogObj->save($logData);
                        unset($serialData,$logData);
                    }
                }
            }

            $return_id=$_POST['return_id'];
            $oProduct_pro->changeverify($_POST['por_id'],$return_id);

            $return_product_detail = $oProduct_pro->dump(array('por_id'=>$_POST['por_id']), 'verify');

            if ($return_product_detail['verify'] == 'true'){
                if(!kernel::single('ome_return_process')->do_iostock($_POST['por_id'],1,$msg)){
                    $this->end(false, app::get('base')->_('质检入库失败'),  'index.php?app=ome&ctl=admin_sv_process&act=edit&p[0]='.$_POST['por_id'], array('msg'=>$msg));
                }
                
                //售后申请状态更新同步
                if ($return_status_service = kernel::servicelist('service.aftersale')){
                    foreach($return_status_service as $object=>$instance){
                        if(method_exists($instance,'update_status')){
                            $instance->update_status($return_id);
                        }
                    }
                }
            }

            $this->end(true, app::get('base')->_('质检成功'));
        }

        $serial['merge'] = $this->app->getConf('ome.product.serial.merge');
        $serial['separate'] = $this->app->getConf('ome.product.serial.separate');
        $this->pagedata['serial'] = $serial;

        $oBranch = app::get('ome')->model('branch');
        $this->pagedata['isExistOfflineBranch'] = $oBranch->isExistOfflineBranch() ? 1 : 0;
        $this->pagedata['isExistOnlineBranch'] = $oBranch->isExistOnlineBranch() ? 1 : 0;
       
        $oProduct_pro_detail = $oProduct_pro->product_detail($por_id);
        $forNum = array();
        foreach ($oProduct_pro_detail['items'] as $key => $val)
        {
            $p    = $basicMaterialObj->dump(array('bm_id'=>$val['product_id']), 'bm_id');
            
            $p['barcode']    = $basicMaterialBarcode->getBarcodeById($p['bm_id']);
            $p['goods_id']    = $p['bm_id'];#基础物料_无goods
            
            $g = $goodsObj->dump($p['goods_id'], 'serial_number');
            $oProduct_pro_detail['items'][$key]['barcode'] = $val['barcode'] = $p['barcode'];
            $oProduct_pro_detail['items'][$key]['serial_number'] = $val['serial_number'] = $g['serial_number'];

            /* 退货数量 */
            if($oProduct_pro_detail['items'][$val['bn']]){
                $oProduct_pro_detail['items'][$val['bn']]['num'] += $val['num'];
            }else{
                $oProduct_pro_detail['items'][$val['bn']] = $val;
            }

            /* 校验数量 */
            if($val['is_check'] == 'true'){
                $oProduct_pro_detail['items'][$val['bn']]['checknum'] += $val['num'];
                $oProduct_pro_detail['items'][$key]['checknum'] = $val['num'];
            }

            if($g['serial_number'] == 'false'){
                $oProduct_pro_detail['items'][$val['bn']]['itemIds'][] = $val['item_id'];
                unset($oProduct_pro_detail['items'][$key]);
            }elseif($val['is_check'] == 'false'){
                /* 退货数量 */
                if($forNum[$val['bn']]){
                    $forNum[$val['bn']] += 1;
                    $oProduct_pro_detail['items'][$key]['fornum'] = $forNum[$val['bn']];
                }else{
                    $oProduct_pro_detail['items'][$key]['fornum'] = 1;
                    $forNum[$val['bn']] = 1;
                }
            }
        }
        $list = $oProblem->getList('problem_id,problem_name');
        $oProduct_pro_detail['problem_type'] = $list;
        $this->pagedata['pro_detail']=$oProduct_pro_detail;

        if (!is_numeric($oProduct_pro_detail['attachment'])){
            $this->pagedata['attachment_type'] = 'remote';
        }
        $this->pagedata['order'] = $oOrder->dump($oProduct_pro_detail['order_id']);
        $this->singlepage("admin/sv_charge/process_edit.html");
    }
  /*校验货号*/
    function checks(){
        $bn = $_GET['bn'];
        $por_id = $_GET['por_id'];
        $pro_items =$this->app->model('return_process_items');
        $cur_item = $pro_items->dump(array('bn'=>$bn,'por_id'=>$por_id,'is_check'=>'false'),'item_id');
        if(!$cur_item){
            $data['message']='此货号已校验完毕或无此货号！';
            $data['status']=1;
        }else{
            $data['status']=2;
            $data['item_id']=$cur_item['item_id'];
        }
        echo json_encode($data);
     }

     function show_check($item_id){
         $oPro_items = $this->app->model('return_process_items');
         $Oproblem = $this->app->model('return_product_problem');
         $pro_item = $oPro_items->dump(array('item_id'=>$item_id),'*');
         $problem_belong='';
         $check= array();

//         foreach(explode(',',$pro_item['problem_belong']) as $k1=>$v1){
//                $Oproblem_detail=$Oproblem->dump($v1,'name');
//                $problem_belong.=$Oproblem_detail['name'].',';
//            }
//
//            foreach(explode(',',$pro_item['problem_type']) as $k2=>$v2){
//                $Oproblem_detail=$Oproblem->dump($v2,'name');
//                $problem_type.=$Oproblem_detail['name'].',';
//            }
         $problem_type = $pro_item['problem_type'];
         $StoreType=$Oproblem->get_store_type($pro_item['store_type']);
         $check['is_problem']  = $is_problem;
         $check['problem_belong']  = $problem_belong;
         $check['problem_type']  = $problem_type;
         $check['StoreType']  = $StoreType;
         $this->pagedata['check'] = $check;

         $this->display("admin/sv_charge/process_show.html");
     }
     
       function getOfflineBranch(){
            $wms_id = kernel::single('wms_branch')->getBranchByselfwms();
            $branch_id = intval($_POST['branch_id']);
            $oBranch = app::get('ome')->model('branch');
            $data = $oBranch->db->select('select branch_id,name from sdb_ome_branch where attr=\'false\' AND parent_id='.$branch_id);
            echo json_encode($data);
     }
     
     function getOnlineBranch(){
        $wms_id = kernel::single('wms_branch')->getBranchByselfwms();
        $oBranch = app::get('ome')->model('branch');
        $branch_id = intval($_POST['branch_id']);
        $data = $oBranch->db->select('select branch_id,name from sdb_ome_branch where  branch_id='.$branch_id);
      
        echo json_encode($data);
     }
}
?>
