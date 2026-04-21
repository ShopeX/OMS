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
class ome_ctl_admin_return_batch extends desktop_controller {

    var $workground = "setting_tools";
   
    function index()
    {
        $params['use_buildin_recycle']=false;
        $params['title'] = '批量设置';
        $params['actions'] = array(
                  array(
                    'label' => '添加设置',
                    'href' => 'index.php?app=ome&ctl=admin_return_batch&act=set&p[0]=',
                    'target' => "dialog::{width:600,height:400,title:'添加设置'}",
                  ),
           );
        
        $this->finder ( 'ome_mdl_return_batch' , $params );
    }

    
    /**
     * 批量设置
     * @param   
     * @return  
     * @access  public
     * @author cyyr24@sina.cn
     */
    function set($batch_id = 0)
    {
        $oShop = $this->app->model('shop');
        $shop_list = $oShop->getlist('*',array('shop_type'=>'taobao'));
        if ($shop_list) {
            foreach ($shop_list as $k=>$shop ) {
                if ($shop['node_id'] == '') {
                    unset($shop_list[$k]);
                }
            }
        }
        $oReturn_batch = $this->app->model('return_batch');
        $return_batch = array();
        if ($batch_id) {
            $return_batch = $oReturn_batch->dump($batch_id);
        }
        if ($_POST) {
            $this->begin();
            $data = $_POST;
            if ($data['shop_id'] == '') {
                $this->end(false,'请选择店铺！');
            }
            if (in_array($data['batch_type'],array('refuse','refuse_return'))) {
                if (!$data['batch_id'] && $_FILES ['picurl']['size']==0) {
                    $this->end(false,'请上传图片!');
                }
            }
            
            if($_FILES ['picurl']['size'] != 0){
                if ($_FILES ['picurl'] ['size'] > 512000) {
                    $this->end(false,'上传文件不能超过500K!');
                }

                $type = array ('gif','jpg','png');
                $imgext = strtolower ( substr ( strrchr ( $_FILES ['picurl'] ['name'], '.' ), 1 ) );
                if ($_FILES ['picurl'] ['name'])
                    if (! in_array ( $imgext, $type )) {
                        $text = implode ( ",", $type );
                        $this->end(false,"您只能上传以下类型文件{$text}!");
                    }
                $ss = kernel::single ( 'base_storager' );
                $id = $ss->save_upload ( $_FILES ['picurl'], "file", "", $msg ); //返回file_id;
                $picurl = $ss->getUrl ( $id, "file" );
                
           }
            
            $batchdata = array(
                'shop_id'   => $data['shop_id'],
                'batch_type'=> $data['batch_type'],
                'memo'      => $data['memo'],
                'is_default'=> $data['is_default'],
            );
            if ($picurl) {
                $batchdata['picurl'] = $picurl;
                $batchdata['imgext'] = $imgext;
            }
            if ($data['batch_id']) {
                $batchdata['batch_id'] = $data['batch_id'];
            }
            if ($batchdata['is_default'] == 'true') {#默认只能有一个
                $filter = array('shop_id'=>$data['shop_id'],'batch_type'=>$data['batch_type'],'is_default'=>'true');
                $oReturn_batch->update(array('is_default'=>'false'),$filter);
            }
            $oReturn_batch->save($batchdata);
            $this->end(true,'设置成功');
        }
        
        
        $batch_type = array('refuse'=>'拒绝退款','accept_refund'=>'同意退款','accept_return'=>'同意退货','refuse_return'=>'拒绝退货');
        $this->pagedata['batch_type'] = $batch_type;
        $this->pagedata['shop'] = $shop_list;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->pagedata['return_batch'] = $return_batch;
        $this->display('admin/return_product/set/batch.html');
    }
   
}

?>
