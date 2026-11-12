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
 * 分销退货单列表
 *
 * @author
 * @version 2024.04.24
 */
class dealer_ctl_admin_reship extends desktop_controller
{
    var $name = "代发退货单";
    var $workground = "aftersale_center";
    
    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */

    public function __construct($app)
    {
        parent::__construct($app);
    }
    
    //显示列表
    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $_GET['view'] = intval($_GET['view']);
        $base_filter = $this->getFilters();
        $params = array(
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'base_filter'            => $base_filter,
        );
        $params['use_buildin_export'] = false;
        $params['title']              = '代发退货单';
        $actions = array();
        $params['actions'] = $actions;
      
        $this->finder('dealer_mdl_reship', $params);
    }
    
    /**
     * 公共filter条件
     * 
     * @return array
     */
    public function getFilters()
    {
        $base_filter = array();
        //@todo：写个公共方法获取当前用户的组织权限和所有贸易公司ID；
        $base_filter['betc_id|than'] = 0; //贸易公司ID
        $base_filter['cos_id|than'] = 0; //组织权限
        
        return $base_filter;
    }
    
    public function _views()
    {
        $reshipMdl = app::get('dealer')->model('reship');
        
        //filter
        $base_filter = $this->getFilters();
        
        //menu
        $sub_menu = array(
            0  => array('label' => __('全部'), 'filter' =>array(), 'optional' => false),
            1 => array('label'=>app::get('base')->_('未处理'),'filter'=>array('is_check'=>'0'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('已审核'), 'filter'=>array('is_check'=>'1'),'optional'=>false),
            3 => array('label'=>app::get('base')->_('完成'),'filter'=>array('is_check' => '7'),'optional'=>false),
            4 => array('label'=>app::get('base')->_('拒绝'),'filter'=>array('is_check' => '5'),'optional'=>false),
        );
        
        $i = 0;
        foreach($sub_menu as $k => $v)
        {
            if (isset($v['filter'])){
                $v['filter'] = array_merge($base_filter, $v['filter']);
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $reshipMdl->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app='. $_GET['app'] .'&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$i++;
        }
        
        return $sub_menu;
    }

    /**
     * confirm
     * @param mixed $reship_id ID
     * @return mixed 返回值
     */
    public function confirm($reship_id)
    {
        $reshipLib = kernel::single('ome_reship');
        
        $url = 'index.php?app=dealer&ctl=admin_reship&act=index';
        
        //验证数据
        if(empty($reship_id)){
            $this->splash('error', $url, '没有可操作的退换货单');
        }
        
        //执行审核
        $error_msg = '';
        $is_rollback = true; //遇到错误,是否回滚更新的数据(默认为:回滚)
        $params = array('reship_id'=>$reship_id, 'status'=>1);
        $confirm = $reshipLib->confirm_reship($params, $error_msg, $is_rollback);
        
        //check
        if(!$is_rollback){
            //不用回滚,直接报错
            if(!$confirm) {
                $this->splash('error', $url, $error_msg);
            }else{
                $this->splash('success', $url, '审核退换货单成功。');
            }
        }else{
            $this->begin($url);
            
            if(!$confirm) {
                $this->end(false, $error_msg);
            }
            
            $this->end(true, '审核退换货单成功!');
        }
    }
}
