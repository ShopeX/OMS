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


class invoice_ctl_admin_order_setting extends desktop_controller
{
    /**
     * 设置列表
     */
    function index()
    {
        $this->title    = '开票信息配置' ;
        $params = array(
           'title'=>$this->title,
          'allow_detail_popup'=>true,
          'use_buildin_recycle'=>false,
          'orderBy' =>'mode asc'
        );
        
        $params['actions'] =array(
         array(
           'label'=>'添加新配置',
           'href'=>'index.php?app=invoice&ctl=admin_order_setting&act=add_order_setting',
           'target' => 'dialog::{width:700,height:660,title:\'新建发票内容\'}',
         ),
        );
        
        $this->finder('invoice_mdl_order_setting', $params);
    }
    
    function add_order_setting(){
       $this->page('admin/edit.html');
    }
    
    /**
     * 编辑
     */
    function editor($id)
    {
        $this->pagedata['item'] = app::get('invoice')->model('order_setting')->db_dump($id);

        $this->display("admin/edit.html");
    }
    
    /**
     * 保存数据
     */
    function save()
    {
        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');
        
        $shopMdl = app::get('ome')->model('shop');
        $settingMdl = app::get('invoice')->model('order_setting');

        $data = $_POST['item'];

        if ($data['sid']) {
          $settingOrigin = $settingMdl->db_dump($data['sid'],'mode,channel_id');

          $data['mode']       = $settingOrigin['mode'];
          $data['channel_id'] = $settingOrigin['channel_id'];
        }
        
        //电子发票
        if($data['mode'] == '1'){
            if(!$data['shopids']){
                $this->end(false, '保存失败,请选择应用开票店铺');
            }
            
            //一个店铺只能出来在一个渠道里
            foreach ($data['shopids'] as $shop_id)
            {
                $settingInfo = $settingMdl->db_dump(array('sid|noequal'=>intval($data['sid']),'filter_sql'=>" FIND_IN_SET('{$shop_id}',shopids) "));
                if ($settingInfo) {
                    $shop = $shop_id == '_ALL_' ? array('name'=>'全选') : $shopMdl->db_dump(array('shop_id'=>$shop_id),'name');
                    
                    $this->end(false, sprintf('店铺[%s]已配置',$shop['name']));
                }
            }
            
            $channe_info = app::get('invoice')->model('channel')->db_dump($data['channel_id']);
            
            if(!$channe_info){
               $this->end(false, '添加失败:店铺开票渠道被删除');
            }
            
            //只有淘宝的电子发票，是和店铺关联的
            if($channe_info['shop_id']){
                $data['shop_id'] = $channe_info['shop_id'];
            
                $shop_info = $shopMdl->db_dump($channe_info['shop_id']);
                $data['billing_shop_node_id'] = $shop_info['node_id'];
            }
            
            $data['shopids']    = implode(',', $data['shopids']);
            $data['mode']       = '1';
            $data['channel_id'] = $data['channel_id'];
        }else{
          //纸质发票
          if ($settingMdl->db_dump(array('mode'=>'0','sid|noequal'=>intval($data['sid'])))) {
            $this->end(false, '创建失败，已存在一条纸质发票设置');
          }
      }

         $data['dateline']      = time();
       
         if(empty($data['bank']) || empty($data['bank_no']) || empty($data['telphone'])){
            $this->end(false, '发票设置填写有误，请检查！');
         }

      $result = $settingMdl->save($data);

      $this->end($result, '新建成功');
    }
    
    /**
     * summary
     */
    public function loadChannel($sid)
    {
        $settingMdl = app::get('invoice')->model('order_setting');
        
        // 线上店铺列表
        $filter = array(
            's_type' => '1',
        );
        $shopList = app::get('ome')->model('shop')->getList("*", $filter);
        
        $this->pagedata['shopList'] = $shopList;

        $item = $settingMdl->db_dump($sid);
        $item['shopids'] = explode(',',$item['shopids']);
        $this->pagedata['item'] = $item;

        $usedShopList = array();
        foreach ($settingMdl->getList('shopids',array('sid|noequal'=>$sid)) as $value) {
            $usedShopList = array_merge($usedShopList,explode(',', $value['shopids']));
        }

        $this->pagedata['usedShopList'] = $usedShopList;
        $this->display('admin/setting/einvoice.html');
    }

    /**
     * summary
     */
    public function loadChannelConfig($sid,$channel_id)
    {
        $channel = app::get('invoice')->model('channel')->db_dump($channel_id);
        if ($channel['channel_type'] != 'bw') {
           echo '';exit;
        }

        $item = app::get('invoice')->model('order_setting')->db_dump($sid);

        $item['skpdata'] = unserialize($item['skpdata']);

        $this->pagedata['item'] = $item;

        $this->display(sprintf('admin/setting/channel/%s.html',$channel['channel_type']));
    }

    /**
     * 管理发票内容
     */
    function manage()
    {
        header("cache-control:no-store,no-cache,must-revalidate");
        
        $oItem      = kernel::single('invoice_mdl_order_setting');
        $row        = $oItem->getList('sid', '', 0, 1);
        $row        = $row[0];

        $this->pagedata['item'] = $row;
        
        $this->page('admin/manage.html');
    }
    
    /**
     * 增加发票内容
     */
    function add()
    {
        $this->begin();

        $title      = trim($_POST['title']);
        
        $oItem      = kernel::single('invoice_mdl_order_setting');
        $row        = $oItem->getList('sid, title', '',0,1);
        $row        = $row[0];

        if(!empty($row['title']))
        {
            array_push($row['title'], $title);
        }
        else
        {
            $row['title'][]      = $title;
        }
        
        $result    = $oItem->save($row);
        if($result)
        {
            $this->setCache();
            $this->end(true, '新建成功');
        }
        else
        {
            $this->end(false, '新建失败');
        }
    }
    
    /**
     * 删除发票内容
     */
    function del()
    {
        $this->begin('index.php?app=invoice&ctl=admin_order_setting&act=manage');
        
        $key        = intval($_GET['key']);
        
        if(empty($_GET['key']) || !is_numeric($_GET['key']))
        {
            $this->end(false, '无效操作，请检查');
        }

        //data
        $data       = array();
        $oItem      = kernel::single('invoice_mdl_order_setting');
        $row        = $oItem->getList('sid, title', '', 0, 1);
        $row        = $row[0];
        
        unset($row['title'][$key]);

        $result    = $oItem->save($row);
        if($result)
        {
            $this->setCache();
            $this->end(true, '删除成功');
        }else{
            $this->end(false, '删除失败');
        }
    }
    
    /**
     * ajax删除发票内容
     */
    function remove()
    {
        $key = intval($_POST['key']);
        $data = array('res'=>'succ', 'msg'=>'');
        
        if(empty($_POST['key']) && $_POST['key']!='0')
        {
            $data       = array('res'=>'fail','msg'=>'无效操作，请检查');
            echo json_encode($data);exit;
        }

        //
        $oItem      = kernel::single('invoice_mdl_order_setting');
        $row        = $oItem->getList('sid, title', '', 0, 1);
        $row        = $row[0];
        
        unset($row['title'][$key]);
        $result         = $oItem->save($row);

        if(!$result){
            $data       = array('res'=>'fail','msg'=>'删除不成功');
            echo json_encode($data);exit;
        }
        
        $this->setCache();
        echo json_encode($data);
    }
    
    /**
     * 发票设置[生成缓存]
     */
    function setCache($data=null)
    {
        if(empty($data))
        {
            $data   = array();
            $oItem  = $this->app->model('order_setting');
            $data   = $oItem->getList('*');
        }
        
        $this->app->setConf('invoice.order_setting', $data);
    }
}