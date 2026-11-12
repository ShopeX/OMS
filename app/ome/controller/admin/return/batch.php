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
 * 批量操作设置（legacy 入口，保留至另行批准下线；SCRUM-7771）
 *
 * 写链路已收口（skill: capability-backed-write）：本控制器不再自己做校验与持久化，
 * 只保留页面渲染、店铺列表读取与 begin()/end() 响应语义，写动作委托给
 * ome_capability_return_batch（与 Vue API 控制器共用同一 capability）。
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
     * @param   int $batch_id 待编辑记录 ID（0 = 新增）
     * @return  void
     * @access  public
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
            $uploadedImage = isset($_FILES['picurl']) && is_array($_FILES['picurl']) ? $_FILES['picurl'] : null;

            try {
                $capability = new ome_capability_return_batch();
                $capability->save($_POST, $uploadedImage);
            } catch (InvalidArgumentException $exception) {
                $this->end(false, $exception->getMessage());
                return;
            } catch (Throwable $exception) {
                $this->end(false, '保存失败');
                return;
            }

            $this->end(true, '设置成功');
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
