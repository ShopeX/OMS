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
* 规则应用类
*
* @author chenping
* @version 2012-6-7 14:22
*/
class inventorydepth_ctl_regulation_skus extends desktop_controller
{
    var $workground = 'resource_center';
    var $defaultWorkground = 'resource_center';

    function __construct($app)
    {
        parent::__construct($app);
    }


    public function index()
    {
       $base_filter = array();

        if($_POST['task_id']) {
            $_SESSION['task_id'] = $_POST['task_id'];
        } elseif($_GET['filter']['task_id']) {
            $_SESSION['task_id'] = $_GET['filter']['task_id'];
        }
        $base_filter['task_id'] = $_SESSION['task_id'];

        $task = $this->app->model('task')->dump(array('task_id'=>$_SESSION['task_id']),'task_name,shop_id');
        $base_filter['shop_id'] = $task['shop_id'];
        $title = "<span style='color:red;'>".$task['task_name']."</span>货品管理";
        $actions = array(
                'title' => $title,
                'actions' => array(
                       array('label'=>$this->app->_('批量开启回写库存'),'submit'=>'index.php?app=inventorydepth&ctl=regulation_skus&act=set_request&p[0]=true','target'=>'refresh'),
                       array('label'=>$this->app->_('批量关闭回写库存'),'submit'=>'index.php?app=inventorydepth&ctl=regulation_skus&act=set_request&p[0]=false','target'=>'refresh'),
                     array('label'=>$this->app->_('发布库存'),'submit'=>'index.php?app=inventorydepth&ctl=regulation_skus&act=releasePage','target'=>'dialog::{title:\'批量发布\'}'),
                       array(
                            'label' => '批量选择规则应用',
                            'submit' => 'index.php?app=inventorydepth&ctl=regulation_skus&act=selectRegulation&p[0]='.$base_filter['task_id'],
                            'target' => 'dialog::{width:400,height:200,title:\'批量选择规则应用\'}',
                        ),
                    ),
                'use_buildin_filter' => true,
                'use_buildin_recycle' => false,
                'base_filter' => $base_filter,
                'object_method' => array(
                'count'=>'countList',
                'getlist'=>'getFinderList',

                ),
            );
            $this->finder(
                'inventorydepth_mdl_task_skus',
                $actions

            );
    }




    /**
     * 新建活动
     *
     * @return void
     * @author
     **/
    public function add($condition = 'stock')
    {
        $this->title = $this->app->_('新建活动');


        # 在没有应用编号的情况下，临时编号
        $this->pagedata['init_bn'] = uniqid();

        $applyObj = app::get('inventorydepth')->model('regulation_apply');
        $apply_list = $applyObj->getList('*',array('condition'=>'stock','type'=>'2'));
        $this->pagedata['apply_list'] = $apply_list;
        unset($apply_list);


        $this->pagedata['title'] = $this->title;


        $this->singlepage('regulation/task.html');


    }







    /**
     * 保存规则应用
     *
     * @return void
     * @author
     **/
    public function save()
    {
        $this->begin();
        $post = $this->_request->get_post();
        $data = $this->check_params($post,$msg);
        if ($data === false) {
            $this->end(false,$msg);
        }

        $applyModel = $this->app->model('task');

        $result = $applyModel->save($data);

        $url = $this->gen_url(array('act'=>'index'));
        $msg = $result ? $this->app->_('保存成功') : $this->app->_('保存失败');
        $this->end($result,$msg);
    }

    /**
     * @description 检查提交参数是否合法
     * @access public
     * @param void
     * @return void
     */
    public function check_params($post,&$msg)
    {

        return $post;
    }

    /**
     * 生成URL
     *
     * @return void
     * @author
     **/
    private function gen_url($params=array(),$full=false)
    {
        $params['app'] = isset($params['app']) ? $params['app'] : $this->app->app_id;
        $params['ctl'] = isset($params['ctl']) ? $params['ctl'] : 'regulation_task';
        $params['act'] = isset($params['act']) ? $params['act'] : 'index';

        return kernel::single('desktop_router')->gen_url($params,$full);
    }

    public function import_goods($task_id){
        $this->pagedata['task_id'] = $task_id;
        $this->display('regulation/import_task.html');
    }

    public function exportTemplate(){
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=货品导入模板.".date('Ymd').".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $oObj = $this->app->model('regulation_apply');
        $title = $oObj->exportTemplate();
        echo '"'.implode('","',$title).'"';
        $data[0]        = array('sales001','普通');

        $data[1]        = array('sales002','捆绑');

        foreach ($data as $items)
        {
            foreach ($items as $key => $val)
            {
                $items[$key]    = kernel::single('base_charset')->utf2local($val);
            }

            echo "\n";
            echo '"'.implode('","',$items).'"';
        }

    }

    public function selectRegulation($task_id){

        $skuObj = app::get('inventorydepth')->model('task_skus');
        $taskObj = app::get('inventorydepth')->model('task');
        $task_detail = $taskObj->dump(array('task_id'=>$task_id),'shop_id');
        if(isset($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_'){

            $sku_list = $skuObj->getFinderList('*', array('task_id'=>$task_id,'shop_id'=>$task_detail['shop_id']));
            $ids = array();
            foreach($sku_list as $sku){
                $ids[] = $sku['sid'];
            }

        }else{
            $ids = $_POST['sid'];
        }
        $regulationObj = app::get('inventorydepth')->model('regulation_apply');
        $now_time = time();
        $regulation_list = $regulationObj->db->select("SELECT id,heading FROM sdb_inventorydepth_regulation_apply WHERE end_time>".$now_time." AND  `condition`='stock' AND shop_id  like '%".$task_detail['shop_id']."%'");

        $this->pagedata['regulation_list'] = $regulation_list;
        unset($regulation_list);
        $this->pagedata['ids'] = serialize($ids);
        $this->pagedata['task_id'] = $task_id;
        $this->page('regulation/selectrug.html');
    }

    public function saveRegulation(){

        $ids = $_POST['ids'] ? unserialize($_POST['ids']) : array();
        $task_id = $_POST['task_id'];

        if ($ids){
            $regulation_id = $_POST['regulation_id'];
            $applyObj = $this->app->model('regulation_apply');
            $apply = $applyObj->dump(array('id'=>$regulation_id),'*');
            $skusObj = $this->app->model('task_skus');
            $sku_list = $skusObj->getlist('product_id,product_type',array('task_id'=>$task_id,'sid'=>$ids));
            if ($sku_list && $apply){
                $goods = $pkg = array();

                foreach($sku_list as $sku){
                    $product_type = $sku['product_type'];
                    if ($product_type == 'product') {
                        $goods[] = $sku['product_id'];
                    }else{
                        $pkg[] = $sku['product_id'];
                    }

                }

                $old_apply_goods = $apply['apply_goods'] ? explode(',',$apply['apply_goods']) : array();

                $tmp = array('_ALL_');
                $apply_goods = array_merge($goods,$old_apply_goods);
                $apply_goods = array_diff($apply_goods,$tmp);

                $data = array();
                $data['apply_goods'] = implode(',',array_unique($apply_goods));
                if ($data){
                    $applyObj->update($data,array('id'=>$regulation_id));
                }
            }
        }

        $result = array('rsp'=>'succ','msg'=>'设置成功');
        echo json_encode($result);

    }
    /**
     * 回写设置
     *
     * @return void
     * @author
     **/
    public function set_request($config = 'true',$id = null)
    {



        /* shop_id下面没有用到，且$taskObj未定义，所以注释掉
        if(empty($_POST['sid'])){
           $_POST['task_id']    = $_SESSION['task_id'];
           $task_detail = $taskObj->dump(array('task_id'=>$task_id),'shop_id');
            $shop_id = $task_detail['shop_id'];
        }
        */

        if ($_POST) {

            $skus_list = app::get('inventorydepth')->model('task_skus')->getFinderList('*', $_POST);
            $ids = array();
            foreach($skus_list as $skus){
                if ($skus['id']) $ids[] = $skus['id'];
            }

            if($ids){
                // 京东引凤对接：business_mode=brand_self 时，禁止开启回写库存
                if ($config === 'true' || $config === true) {
                    $brandSelfSkus = $this->app->model('shop_skus')->getList('id', array('id' => $ids, 'business_mode' => 'brand_self'));
                    if ($brandSelfSkus) {
                        $this->splash('error', 'javascript:finderGroup["'.$_GET['finder_id'].'"].refresh.delay(400,finderGroup["'.$_GET['finder_id'].'"]);', $this->app->_('引凤货品（brand_self）不允许开启自动回写库存'));
                        return;
                    }
                }

                $this->app->model('shop_skus')->update(array('request'=>$config), array('id'=>$ids));

                // 记录操作日志
                $optLogModel = app::get('inventorydepth')->model('operation_log');
                $optLogModel->batch_write_logs('sku',$ids,'stockset',($config=='true' ? '开启库存回写:来源为活动' : '关闭库存回写:来源为活动'));
            }


            $this->splash('success','javascript:finderGroup["'.$_GET['finder_id'].'"].refresh.delay(400,finderGroup["'.$_GET['finder_id'].'"]);',$this->app->_('设置成功'));
        }else{
            $this->splash('error','javascript:finderGroup["'.$_GET['finder_id'].'"].refresh.delay(400,finderGroup["'.$_GET['finder_id'].'"]);',$this->app->_('请选择SKU'));
        }
    }

    /**
     * 发布页
     *
     * @return void
     * @author
     **/
    public function releasePage($id = null,$release_stock = null)
    {

        $_POST['task_id'] = $_SESSION['task_id'];
        $post = http_build_query($_POST);

        $this->pagedata['post'] = $post;

        $this->display('regulation/release_task.html');
    }

    /**
     * 发布
     *
     * @return void
     * @author
     **/
    public function releaseUpload()
    {
        $this->begin();

        if (!$_POST['task_id'])  $this->end(false,$this->app->_('请选择活动任务!'));

        $taskSkuMdl = app::get('inventorydepth')->model('task_skus');
        $task_detail = $taskSkuMdl->dump(array('task_id'=>$_POST['task_id']),'shop_id');
        $filter = array(
            'filter_sql' =>'{table}node_id is not null and {table}node_id !=""',
        );

        $filter['shop_id'] = $task_detail['shop_id'];

        $shops = app::get('inventorydepth')->model('shop')->getList('shop_id,shop_bn,node_type,name',$filter);

        $offset = 0; $limit = 50;
        do {
            $rows = $taskSkuMdl->getList('product_id', $_POST, $offset, $limit);

            if (!$rows) break;

            $sm_id = array_column($rows, 'product_id');
            foreach ($shops as $shop) {
                $params = array ('offset' => '0', 'limit' => $limit, 'shop_id' => $shop['shop_id'],'sm_id' => $sm_id);
                $params['operInfo'] = kernel::single('inventorydepth_func')->getDesktopUser();

                kernel::single('inventorydepth_queue')->insert_stock_update_queue("【{$shop['name']}】活动商品库存回写",$params);
            }

            $offset += $limit;
        } while (true);

        $this->end(true,$this->app->_('成功插入队列!'));
    }

}
