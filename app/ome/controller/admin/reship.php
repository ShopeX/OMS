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

class ome_ctl_admin_reship extends desktop_controller{
    var $name = "退货单";
    var $workground = "invoice_center";

    function index(){
       $this->finder('ome_mdl_reship',array(
            'title' => '退货单管理',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
       ));
    }


    /**
     * @sunjing@shopex.cn
     * @DateTime  2017-12-20T09:57:40+0800
     * 
     * 换货订单生成
     * @return
     */
    function create_change_order($reship_id){

        $reshipobj = app::get('ome')->model('reship');
        $reship_detail = $reshipobj->dump(array('reship_id'=>$reship_id,'is_check'=>'1','return_type'=>'change','change_order_id'=>0),'change_order_id,return_id,reship_bn,reship_id');
        
        //$tmall_detail = kernel::single('ome_service_aftersale')->get_return_type(array('return_id'=>$reship_detail['return_id']));
        //if ($tmall_detail && $reship_detail && $tmall_detail['refund_type'] == 'change'){
        
        //check
        if ($reship_detail){
            $this->pagedata['reship_detail'] = $reship_detail;
            $this->pagedata['reship_id'] = $reship_detail['reship_id'];
            unset($reship_detail);
            $this->display('admin/return_product/rchange/create_change_order.html');
        }else{
            echo '无换货订单需要生成!';exit;
        }
    }

    /**
     * 生成换货订单
     * 
     */
    public function do_create_change_order($reship_id){

        $reshipObj= app::get('ome')->model('reship');
        $orderObj = app::get('ome')->model('orders');
        $reshipLib = kernel::single('ome_reship');
        $oOperation_log = $this->app->model('operation_log');

        $reship_detail = $reshipObj->dump(array('reship_id'=>$reship_id,'change_order_id'=>0),'*');

        $order_detail = $orderObj->dump(array('order_id'=>$reship_detail['order_id']),'order_bn');
        $result = array('rsp'=>'fail','msg'=>'创建失败!');
        if ($reship_detail){
            $rs = $reshipObj->create_order($reship_detail);
            if ($rs){

                //kernel::single('console_reship')->change_freezeproduct($reship_id,'-',$reship_detail['changebranch_id']);//生成订单后释放库存
                //库存管控 生成订单后释放库存
                kernel::single('console_reship')->releaseChangeFreeze($reship_id);

                $oOperation_log->write_log('reship@ome',$reship_id,'新建换货订单,换货订单号:'.$rs['order_bn']);
                $order = array(
                    'order_id'        => $rs['order_id'],
                    'shop_id'         => $reship_detail['shop_id'],
                    'pay_status'      => '1',
                    'pay_money'       => $rs['total_amount'],
                    'currency'        => 'CNY',
                    'reship_order_bn' => $order_detail['order_bn'],
                );

                $reshipLib->payChangeOrder($order);

                $result = array('rsp'=>'succ','msg'=>'换货订单创建成功');
            }
        }
        echo json_encode($result);

    }

    /**
     * 退货拒绝留言.
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    function refuse_message($reship_id=null)
    {
        set_time_limit(0);
        if ($_POST) {
            $this->begin();
            $reshipObj = $this->app->model('reship');
            $refuse_message = $_POST['refuse_message'];

            $return_id = $_POST['return_id'];
            $reship_id = $_POST['reship_id'];

            if ($_FILES['refuse_proof']['size']<=0) {
                    $this->end(false,'请上传凭证图片!');
            }
            $return_model = $this->app->model('return_product_tmall');
            $return_tmall = $return_model->dump(array('return_id' => $return_id));



            if($_FILES['refuse_proof']['size'] != 0){
                if ($_FILES ['refuse_proof'] ['size'] > 512000) {
                    $this->end(false,'上传文件不能超过500K!');
                }

                $type = $type = array ('gif','jpg','png');
                $imgext = strtolower ( $this->fileext ( $_FILES ['refuse_proof'] ['name'] ) );
                if ($_FILES['refuse_proof'] ['name'])
                    if (! in_array ( $imgext, $type )) {
                        $text = implode ( ",", $type );
                        $this->end(false,"您只能上传以下类型文件{$text}!");
                    }
                $ss = kernel::single ( 'base_storager' );
                $id = $ss->save_upload ( $_FILES ['refuse_proof'], "file", "", $msg ); //返回file_id;
                $refuse_memo['image'] = $ss->getUrl ( $id, "file" );

                $rh = fopen($_FILES['refuse_proof']['tmp_name'],'rb');
                $imagebinary = fread($rh, filesize($_FILES['refuse_proof']['tmp_name']));
                fclose($rh);
                $imagebinary = base64_encode($imagebinary);

            }


            $aftersale_service = kernel::single('ome_service_aftersale');
            $data = array(
                'leave_message'     => $refuse_message,
                'leave_message_pics'=> $imagebinary,
                'dispute_id'        =>  $return_tmall['return_bn'],
                'seller_refuse_reason_id'=>$_POST['seller_refuse_reason_id'],
            );

            $rs = $aftersale_service->returngoods_refuse($data);
            $reship_detail = $reshipObj->dump(array('reship_id'=>$reship_id),'reship_bn,return_type,is_check,branch_id,changebranch_id,reship_id');
            if ($reship_detail['is_check'] == '1'){
                $wms_id = kernel::single('ome_branch')->getWmsIdById($reship_detail['branch_id']);
                $branch = kernel::single('ome_branch')->getBranchInfo($reship_detail['branch_id'], 'branch_bn,storage_code,owner_code');
                $data = array(
                    'reship_bn' =>  $reship_detail['reship_bn'],
                    'branch_bn' => $branch['branch_bn'],
                    'owner_code' => $branch['owner_code'],
                  'reship_id' => $reship_detail['reship_id'],
                );
                $result = kernel::single('console_event_trigger_reship')->cancel($wms_id, $data, true);

            }
            $oOperation_log = app::get('ome')->model('operation_log');//写日志

            if (!$rs || $rs['rsp'] == 'fail' || ($result &&$result['rsp']=='fail')) {
                $oOperation_log->write_log('return@ome',$return_id,'拒绝确认收货失败,原因:'.$rs['msg']);
                $this->end(false,$rs['msg']);
            }
            //更新退货单状态
            $reshipObj->update(array('is_check'=>'5','t_end'=>time()),array('reship_id'=>$reship_id));

            //判断是否是已确认拒绝如果是需要释放冻结库存
            kernel::single('console_reship')->releaseChangeFreeze($reship_id);
            // 退货单取消后的service扩展点
            foreach(kernel::servicelist('console.service.reship.cancel.after') as $object) {
                if(method_exists($object, 'cancel_reship_after')) {
                    $object->cancel_reship_after($reship_id);
                }
            }
            $memo = '拒绝确认收货';
            if($return_id){
                $oOperation_log->write_log('return@ome',$return_id,$memo);
                $data = array ('return_id' => $return_id, 'status' => '5', 'last_modified' => time () );
                $oProduct = app::get('ome')->model ( 'return_product' );
                $oProduct->update_status ( $data );
            }
            $oOperation_log->write_log('reship@ome',$reship_id,$memo);

            $this->end(true,'成功');
        }
        $reshipObj = app::get('ome')->model('reship');
        $reship_detail = $reshipObj->dump(array('reship_id'=>$reship_id),'return_id');

        $return_id = $reship_detail['return_id'];

        $aftersaleObj = kernel::single('ome_service_aftersale');
        $return_tmall_detail =$aftersaleObj->get_return_type(array('return_id' => $return_id));
        if ($return_tmall_detail['refund_type'] == 'change'){
            //$refuse_reason = $return_tmall_detail['refusereason'];
            if ($refuse_reason){
                $refuse_reason = json_decode($refuse_reason,true);
            }else{
                $refuse_reason = $aftersaleObj->refuse_reason($return_id);
            }
            $this->pagedata['refuse_reason'] = $refuse_reason;
        }
        $this->pagedata['reship_id'] = $reship_id;
        $this->pagedata['return_id'] = $return_id;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->display('admin/reship/refuse_message.html');
    }

    function fileext($filename) {
        return substr ( strrchr ( $filename, '.' ), 1 );
    }

    /**
     * @sunjing@shopex.cn
     * @DateTime  2017-12-28T18:44:46+0800
     * @return
     */

    public function force_refuse($reship_id){

        if(!$reship_id)
            die("单据号传递错误！");
        $reshipObj = app::get('ome')->model('reship');
        $reship = $reshipObj->dump(array('reship_id'=>$reship_id),'reship_bn,return_id,return_type,changebranch_id,is_check,change_status,reship_id');

        if($reship){

            console_reship::cancel($reship,'强制取消');

            $oOperation_log = app::get('ome')->model('operation_log');//写日志
            if($reship['return_id']){
                $oOperation_log->write_log('return@ome',$reship['return_id'],$memo);
                $data = array ('return_id' => $reship['return_id'], 'status' => '5', 'last_modified' => time () );
                $oProduct = app::get('ome')->model ( 'return_product' );
                $oProduct->update_status ( $data );
            }

        }
    }

    /**
     * 关闭换货订单状态
     * @author: <sunjing@shopex.cn>
     * @Date:2018-05-03
     * @return  bool
     */
    function close_change($reship_id){

        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');
        $reship_id = intval($reship_id);
        $reshipObj = app::get('ome')->model('reship');
        $finder_id = $_GET['finder_id'];

        $oOperation_log = app::get('ome')->model('operation_log');//写日志
        $result = $reshipObj->update(array('change_status'=>'2'),array('reship_id'=>$reship_id,'change_status'=>'0'));
        $oOperation_log->write_log('reship@ome',$reship_id,'关闭换货单生成');
        if($result){
            $res = kernel::single('console_reship')->releaseChangeFreeze($reship_id);
            if ($res[0]) {
                $oOperation_log->write_log('reship@ome', $reship_id, '关闭换货单生成后释放预占');
                $this->end(true,'关闭换货单成功!');
            } else {
                $this->end(false,'关闭换货单失败:'.$res[1]['msg']);
            }
        }else{
            $this->end(false,'关闭换货单失败');
        }


    }

    /**
     * 添加memo
     * @param mixed $reship_id ID
     * @return mixed 返回值
     */
    public function addmemo($reship_id){

        $this->pagedata['reship_id'] = $reship_id;
        $this->display('admin/reship/memo.html');
    }

    /**
     * doaddmemo
     * @return mixed 返回值
     */
    public function doaddmemo(){
        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');
        $reship_id = intval($_POST['reship_id']);
        $reshipObj = app::get('ome')->model('reship');
        $logMdl = app::get('ome')->model('operation_log');//写日志
        $memo = $_POST['memo'];
        $result = $reshipObj->update(array('memo'=>$_POST['memo']),array('reship_id'=>$reship_id));
        $logMdl->write_log('reship@ome',$reship_id,'添加退货备注:'.$memo.'');
        $this->end(true,'备注添加成功!');
    }
}

?>
