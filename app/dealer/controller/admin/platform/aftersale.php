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

class dealer_ctl_admin_platform_aftersale extends desktop_controller {

    public function index() {
       
        $actions = array();

        if(in_array($_GET['view'],array('1','3'))){


            $actions[] = array(
                'label' => '批量转换',
                'submit' => 'index.php?app=dealer&ctl=admin_platform_aftersale&act=syncpush',
                'target'=>'dialog::{width:600,height:300,title:\'批量转换\'}"'
            );
        }
        $params = array(
                'title'=>'平台原始售后单',
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>false,
                'use_buildin_export'=>false,
                //'use_buildin_import'=>true,
                'use_buildin_recycle'=>false,
                'actions'=>$actions,
                'orderBy' => 'plat_aftersale_id desc',
        );
        
        $this->finder('dealer_mdl_platform_aftersale', $params);
    
    }


    public function _views() {

        $modelAftersale = app::get('dealer')->model('platform_aftersale');


        $sub_menu[0] = array('label' => app::get('base')->_('全部'), 'filter' => array(), 'optional' => false);
        $sub_menu[1] = array('label' => app::get('base')->_('未转换'), 'filter' => array('sync_status'=>'0'), 'optional' => false);
        $sub_menu[2] = array('label' => app::get('base')->_('转换成功'),'filter' => array('sync_status' => '1'),'optional' => false);
        $sub_menu[3] = array('label' => app::get('base')->_('转换失败'),'filter' => array('sync_status' => '2'),'optional' => false);
       
        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $v['addon'] ? $v['addon'] : $modelAftersale->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=dealer&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&flt=' . $_GET['flt'] . '&view=' . $k . $s;
        }

        return $sub_menu;
    }

   
   public function syncpush()
     {

         $this->pagedata['request_url'] = 'index.php?app=dealer&ctl=admin_platform_aftersale&act=ajaxsyncpush';
         $this->pagedata['autotime']    = '500';

         //$_POST['sync_status'] =array('1');   
         $_POST = array_merge($_POST, $_GET);
   


         parent::dialog_batch('dealer_mdl_platform_aftersale',true,100,'inc');
     }

    public function ajaxsyncpush()
     {
         parse_str($_POST['primary_id'], $primary_id);

         if (!$primary_id) { echo 'Error: 请先选择数据';exit;}
        

         $retArr = array(
             'itotal'  => 0,
             'isucc'   => 0,
             'ifail'   => 0,
             'err_msg' => array(),
         );

         $aftersaleMdl = app::get('dealer')->model('platform_aftersale');
         $aftersaleMdl->filter_use_like = true;

         $aftersales = $aftersaleMdl->getList('*',$primary_id['f'],$primary_id['f']['offset'],$primary_id['f']['limit']);

         $retArr['itotal'] = count($aftersales);

         foreach ($aftersales as $v) {

            $rs = kernel::single('dealer_event_trigger_aftersale')->push($v['plat_aftersale_id']);

            if ($rs) {
                $retArr['isucc']++;
            } else {
                $retArr['ifail']++;
            }
         }
         
         echo json_encode($retArr),'ok.';exit;
    }
}