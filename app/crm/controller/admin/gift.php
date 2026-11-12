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

class crm_ctl_admin_gift extends desktop_controller{

    var $workground = 'channel_center';
    
    /** @var crm_capability_gift 写链路唯一实现（与 crm_ctl_api_gift 共用） */
    protected $capability;

    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */
    public function __construct($app)
    {
        parent::__construct($app);
        $this->_request = kernel::single('base_component_request');
        $this->capability = new crm_capability_gift();
    }

    /**
     * index
     * @return mixed 返回值
     */
    public function index(){

        $title = '赠品管理';

        $this->finder('crm_mdl_gift',array(
                'title'=>$title,
                'actions'=>array(             
                        array('label'=>app::get('crm')->_('新增'),'target'=>'dialog::{title:\'新增赠品\',width:\'400px\',height:\'300px\'}','href'=>$this->url."&act=addGift"),
                        array('label'=>app::get('crm')->_('删除'),'icon' => 'del.gif', 'confirm' =>'确定删除选中项？','submit'=>'index.php?app=crm&ctl=admin_gift&act=delGift',),
                ),
                'use_buildin_recycle'=>false,
                'orderBy' =>'gift_id DESC',
        ));
    }
    

    /**
     * 添加Gift
     * @return mixed 返回值
     */
    public function addGift(){
        $this->display('admin/gift/add.html');
    }

    /**
     * 获取CrmInfo
     * @return mixed 返回结果
     */
    public function getCrmInfo(){

        $channelObj = app::get('channel')->model('channel');

        $filter = array('channel_type'=>'crm','filter_sql'=>'(node_id is not null and node_id !="")');

        $crmdata = $channelObj->getChannelInfo('count(channel_id) as _count',$filter);

        return $crmdata[0]['_count'];
    }
    
    #构造一个商品列表页面
    /**
     * 获取ProductInfo
     * @return mixed 返回结果
     */
    public function getProductInfo(){
        
            $base_filter['visibility'] = 'true';
            $base_filter['product_id|notin'] = explode(',',$product_id);

            $params = array(
               'title'=>'商品列表',
               'base_filter' => $base_filter,
               'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'=>false,
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_filter'=>true,
                'use_buildin_setcol'=>true,
                'use_buildin_refresh'=>true,
                'orderBy' =>'product_id DESC',
                'alertpage_finder'=>true,
                'use_view_tab' => false,
            );
            return $this->finder('ome_mdl_products',$params);
    }

    function myfinder($object_name,$params){

         header("cache-control: no-store, no-cache, must-revalidate");

        $finder = kernel::single('crm_html',$this);

        foreach($params as $k=>$v){
            $finder->$k = $v;
        }
        $app_id = substr($object_name,0,strpos($object_name,'_'));
        $app = app::get($app_id);
        $finder->app = $app;
        $finder->work($object_name);    
    }

    /**
     * 保存Gift
     * @return mixed 返回操作结果
     */
    public function saveGift(){
        $sm_ids = $this->_request->get_post('sm_id');
    
        if(empty($sm_ids)){
            $this->splash('error', $this->url.'&act=index', '缺少物料');
            return;
        } 

        // 写链路委托 capability（单一实现；事务由 capability 持有）
        try {
            $this->capability->addGifts((array)$sm_ids);
        } catch (InvalidArgumentException $e) {
            $this->splash('error', $this->url.'&act=index', $e->getMessage());
            return;
        }

        $this->splash('success', $this->url.'&act=index', '操作成功');
    }

    /**
     * delGift
     * @return mixed 返回值
     */
    public function delGift(){
       $this->begin('index.php?app=crm&ctl=admin_gift&act=index');
       $isSelectedAll = $this->_request->get_post('isSelectedAll');
       $giftids = $this->_request->get_post('gift_id');

       if($isSelectedAll == '_ALL_'){
           $selectionMode = '_ALL_';
           $ids = array();
       }elseif($giftids){
           $selectionMode = '';
           $ids = (array)$giftids;
       }else{
           $this->end(false,$this->app->_('请选择赠品!'));
           return;
       }

       // 写链路委托 capability（事务归属由其按连接状态自动判定：legacy begin() 已开事务则自动交给外层）
       try {
           $this->capability->deleteGifts($ids, array(), $selectionMode);
       } catch (InvalidArgumentException $e) {
           $this->end(false, $e->getMessage());
           return;
       } catch (Throwable $e) {
           $this->end(false, $this->app->_('删除失败'));
           return;
       }

       $this->end(true, $this->app->_('删除成功'));
    }

    function setStatus($gid, $status) {
        if ($status == 'true') {
            $status = 1;
        } else {
            $status = 2;
        }

        kernel::database()->query("update  sdb_crm_gift set status='{$status}' where gift_id={$gid}");
        echo "<script>parent.MessageBox.success('设置已成功！');parent.finderGroup['{$_GET[finder_id]}'].refresh();</script>";
        exit;
    }

    function edit($gift_id=0)
    {
        if($_POST){
            $this->begin('index.php?app=crm&ctl=admin_gift&act=index');

            // 写链路委托 capability（校验/归一化/持久化集中；legacy 持事务 ⇒ 传 false）
            try {
                $this->capability->updateGift($_POST);
            } catch (InvalidArgumentException $e) {
                $this->end(false, $e->getMessage());
                return;
            }

            $this->end(true, '保存成功');
        }

        if($gift_id>0){
            $rs = $this->app->model('gift')->dump($gift_id);
            $this->pagedata['rs'] = $rs;
            $this->display('admin/gift/edit.html');
        }else{
            echo('gift_id error.');
        }
    }

}