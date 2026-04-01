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
class inventorydepth_ctl_shop extends desktop_controller
{

    public $workground        = 'resource_center';
    public $defaultWorkground = 'resource_center';

    public function __construct($app)
    {
        parent::__construct($app);
        $this->_request = kernel::single('base_component_request');
    }

    /**
     * 店铺资源列表
     *
     * @return void
     * @author
     **/
    public function index()
    {
        //$base_filter = array('filter_sql' => '{table}node_id is not null and {table}node_id !=""', 's_type' => 1, 'delivery_mode'=>'self');
        $base_filter = array('filter_sql' => '{table}node_id is not null and {table}node_id !=""', 's_type' => 1);
        $params = array(
            'title'               => $this->app->_('店铺资源'),
            'actions'             => array(
                //0 => array('label'=>$this->app->_('开启回写库存'),'submit'=>'index.php?app=inventorydepth&ctl=shop&act=set_request&p[0]=true'),
                //1 => array('label'=>$this->app->_('关闭回写库存'),'submit'=>'index.php?app=inventorydepth&ctl=shop&act=set_request&p[0]=false'),
                //2 => array('label'=>$this->app->_('开启自动上下架'),'submit'=>'index.php?app=inventorydepth&ctl=shop&act=set_frame&p[0]=true'),
                //3 => array('label'=>$this->app->_('关闭自动上下架'),'submit'=>'index.php?app=inventorydepth&ctl=shop&act=set_frame&p[0]=false'),
            ),
            //'finder_cols' => 'shop_bn,name,last_store_sync_time',
            'use_buildin_recycle' => false,
            'base_filter'         => $base_filter,
        );

        $this->finder('inventorydepth_mdl_shop', $params);
    }

    /**
     * 回写设置
     *
     * @return void
     * @author
     **/
    public function set_request($config = 'true', $shop_id = null)
    {
        if ($shop_id) {
            $shop_id = array($shop_id);
        }

        if ($_POST['shop_id']) {
            $shop_id = $_POST['shop_id'];
        }

        if ($_POST['isSelectedAll'] == '_ALL_') {
            $shops   = $this->app->model('shop')->getList('shop_id', $_POST);
            $shop_id = array_map('current', $shops);
        }

        if ($shop_id) {
            foreach ($shop_id as $key => $value) {
                //app::get('ome')->setConf('request_auto_stock_' . $value, $config);
                kernel::single('inventorydepth_shop')->setStockConf($value, $config);

                // 记录操作日志
                $optLogModel = app::get('inventorydepth')->model('operation_log');
                $optLogModel->write_log('shop', $value, 'stockset', ($config == 'true' ? '开启库存回写' : '关闭库存回写'));
            }
            $this->splash('success', 'javascript:finderGroup["' . $_GET['finder_id'] . '"].refresh.delay(400,finderGroup["' . $_GET['finder_id'] . '"]);', $this->app->_('设置成功'));
        } else {
            $this->splash('error', 'javascript:finderGroup["' . $_GET['finder_id'] . '"].refresh.delay(400,finderGroup["' . $_GET['finder_id'] . '"]);', $this->app->_('请选择店铺'));
        }
    }

    /**
     * 绑定页面
     * @param $shop_bn
     */
    public function displayBranchRelation($shop_bn,$shop_id)
    {
        $this->pagedata['shop_bn']             =   $shop_bn;
        $this->pagedata['shop_id']             =   $shop_id;
        $this->display('shop/show_relation.html');
    
    }
    
    /**
     * 获取绑定数据
     */
    public function getBranchRelation()
    {
        $filter = array_filter($_POST['f']);
        $shop_bn = $filter['shop_bn'];
        
        $o2oStoreMdl = app::get('o2o')->model('store');
        $storeList = $o2oStoreMdl->getList('store_id,store_bn,name,branch_id', array(
            'is_ctrl_store' => '1',
            'is_o2o' => '1',
        ));
        $store_bns = array_column($storeList,'store_bn');
        $str_branch_bns = $store_bns ? '"' . implode('","', $store_bns) . '"' : '0';
        // 获取全部的仓库：
        // - 大仓(b_type=1)：发货仓且有效
        // - 门店(b_type=2)：仓编码在门店编码集合内（受 is_ctrl_store=1 且 is_o2o=1 的门店列表限制）
        $filter['filter_sql'] = "((b_type = 1 AND is_deliv_branch = 'true' AND disabled = 'false') OR (b_type = 2 AND branch_bn IN ($str_branch_bns)))";
        if($filter['select_shop_code']){
            $filter['branch_bn|has'] = $filter['select_shop_code'];
        }
        
        $branchMdl = app::get('ome')->model('branch');
        $branchList = $branchMdl->getList('branch_id,branch_bn,name', $filter);
        $branchList = array_column($branchList,null,'branch_bn');
        
        //已绑定数据
        $branchLeft = $branchList;
        $boundList = array();
        $relation = app::get('ome')->getConf('shop.branch.relationship');
        //排除掉不存在的
        if ($relation && $branchList) {
            foreach ($relation[$shop_bn] as  $branch_id=>$branch_bn) {
                //右边处理逻辑
                if ($branchList[$branch_bn]) {
                    $boundList[$branch_bn] = $branchList[$branch_bn];
                }
                
                //左边处理逻辑
                unset($branchLeft[$branch_bn]);
            }
        }
    
        $branchLeft = array_values($branchLeft);
        $boundList = array_values($boundList);
        $this->splash('success', null, null, 'redirect', ['data' => $branchLeft,'bound'=>$boundList]);
    }
    
    /**
     * 保存绑定关系
     */
    public function saveBranchRelation()
    {
        $this->begin($this->url.'&act=index');
        if(!is_array($_POST['branch_id'])){
            $_POST['branch_id'] = explode(',',$_POST['branch_id']);
        }
        $branch_ids = array_unique($_POST['branch_id']);
        $shop_bn = $_POST['shop_bn'];
        $shop_id = $_POST['shop_id'];
        $branchMdl = app::get('ome')->model('branch');
        $branchList = $branchMdl->getList('branch_id,branch_bn,name',array('branch_id'=>$branch_ids,'check_permission' => 'false'));
        //获取已绑定数据
        $relation = app::get('ome')->getConf('shop.branch.relationship');
        //记录编辑前关系日志
        $ip = kernel::single("base_request")->get_remote_addr();
        $memo  = '店铺'.$shop_bn."供货仓关系原数据【".json_encode($relation[$shop_bn])."】".'IP:'.$ip;
        $optLogModel = app::get('inventorydepth')->model('operation_log');
        $optLogModel->write_log('shop', $shop_id, 'supply_branches_set',$memo);
    
        $shopBranch = array();
        foreach($branchList as $k => $item){
            $shopBranch[$item['branch_id']] = $item['branch_bn'];
        }
        $relation[$shop_bn] = $shopBranch;
        app::get('ome')->setConf('shop.branch.relationship',$relation);
        $this->end(true, '保存成功');
    }

    /**
     * 上下架设置
     *
     * @return void
     * @author
     **/
    public function set_frame($config = 'true', $shop_id = null)
    {
        if ($shop_id) {
            $shop_id = array($shop_id);
        }

        if ($_POST['shop_id']) {
            $shop_id = $_POST['shop_id'];
        }

        if ($_POST['isSelectedAll'] == '_ALL_') {
            $shops   = $this->app->model('shop')->getList('shop_id', $_POST);
            $shop_id = array_map('current', $shops);
        }

        if ($shop_id) {
            foreach ($shop_id as $key => $value) {
                //app::get('ome')->setConf('request_auto_frame_' . $value, $config);
                kernel::single('inventorydepth_shop')->setFrameConf($value, $config);
            }
            $this->splash('success', 'javascript:finderGroup["' . $_GET['finder_id'] . '"].refresh.delay(400,finderGroup["' . $_GET['finder_id'] . '"]);', $this->app->_('设置成功'));
        } else {
            $this->splash('error', 'javascript:finderGroup["' . $_GET['finder_id'] . '"].refresh.delay(400,finderGroup["' . $_GET['finder_id'] . '"]);', $this->app->_('请选择店铺'));
        }
    }

    /**
     * 商品下载页
     *
     * @return void
     * @author
     **/
    public function download_page($downloadType = '', $shop_id = '')
    {
        $downloadType = $downloadType ? $downloadType : $_GET['downloadType'];
        $shop_id      = $shop_id ? $shop_id : $_GET['shop_id'];
        switch ($downloadType) {
            case 'shop':
                $url         = 'index.php?app=inventorydepth&ctl=shop&act=downloadByShop&p[0]=' . $shop_id;
                $shop        = $this->app->model('shop')->getList('shop_type,business_type', array('shop_id' => $shop_id), 0, 1);
                $shopfactory = inventorydepth_service_shop_factory::createFactory($shop[0]['shop_type'], $shop[0]['business_type']);
                if ($shopfactory) {
                    $loadList = $shopfactory->get_approve_status();
                }
                $this->pagedata['shop_id'] = $shop_id;

                kernel::single('inventorydepth_shop')->setShopSync($shop_id);
                break;
            case 'iid':
                $item = $this->app->model('shop_items')->getList('id,title', array('id' => $_GET['id']), 0, 1);
                if ($item) {
                    $loadList[$item[0]['id']] = array('name' => ($item ? 'ITEM:' . $item[0]['title'] : '空'));
                }
                $url = 'index.php?app=inventorydepth&ctl=shop&act=downloadByIId&p[0]=' . $_GET['id'];
                break;
            case 'sku_id':
                $sku = $this->app->model('shop_skus')->getList('id,shop_title', array('id' => $_GET['id']), 0, 1);
                if ($sku) {
                    $loadList[$sku[0]['id']] = array('name' => ($sku ? 'SKU:' . $sku[0]['shop_title'] : '空'));
                }
                $url = 'index.php?app=inventorydepth&ctl=shop&act=dowloadBySkuId&p[0]=' . $_GET['id'];
                break;
            case 'iids':
                $url = 'index.php?app=inventorydepth&ctl=shop&act=downloadByIIds&p[0]=' . $_GET['id'];
                break;
            default:
                $url = '';
                break;
        }
        $loadList = $loadList ? : [];
        $width = 0;
        if($loadList){
            $width = intval(100 / count($loadList));
        }
        
        $this->pagedata['url']          = $url;
        $this->pagedata['loadList']     = $loadList;
        $this->pagedata['width']        = $width;
        $this->pagedata['downloadType'] = $downloadType;

        if ($_GET['redirectUrl']) {
            $this->pagedata['redirectUrl'] = 'index.php?' . http_build_query($_GET['redirectUrl']);
        }

        $_POST['time'] = time();
        if ($_POST) {
            $inputhtml = '';
            $post      = http_build_query($_POST);
            $post      = explode('&', $post);
            foreach ($post as $p) {
                list($name, $value) = explode('=', $p);
                $params             = array(
                    'type'  => 'hidden',
                    'name'  => $name,
                    'value' => $value,
                );
                $inputhtml .= utils::buildTag($params, 'input');
            }
            $this->pagedata['inputhtml'] = $inputhtml;
        }
        $this->display('shop/download_page.html');
    }

    /**
     * 按店铺下载
     *
     * @return void
     * @author
     **/
    public function downloadByShop($shop_id)
    {
        if (!$shop_id) {
            $this->splash('error', null, $this->app->_('请选择店铺！'));
        }

        $page = $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
        $flag = $_GET['flag'];

        $shop = $this->app->model('shop')->db_dump($shop_id, 'name,shop_type,business_type');

        if (!inventorydepth_shop_api_support::items_all_get_support($shop['shop_type'])) {
            $this->splash('error', null, $this->app->_("暂不支持对店铺【{$shop['name']}】商品的同步!"));
        }

        $shopLib = kernel::single('inventorydepth_shop');

        $sync = $shopLib->getShopSync($shop_id);
        if ($sync['op_id'] != kernel::single('desktop_user')->get_id()) {
            $this->splash('error', null, $this->app->_("由于%s的操作，系统终止了您的请求!", kernel::single('desktop_user')->get_name()));
        }

        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type'], $shop['business_type']);
        if ($shopfactory == false) {
            $this->splash('error', null, $this->app->_("工厂生产类失败!"));
        }

        $approve_status = $shopfactory->get_approve_status($flag, $exist);
        if ($exist == false) {
            $this->splash('error', null, $this->app->_("标记异常!"));
        }

        if ($sync['lastmodify'] && $sync['lastmodify'] != 'false') {
            $approve_status['filter']['start_modified'] = date('Y-m-d H:i:s', $sync['lastmodify']);
            $approve_status['filter']['end_modified']   = date('Y-m-d H:i:s', $_POST['time']);
        }

        try {
            $result = $shopLib->downloadList($shop_id, $approve_status['filter'], $page, $errormsg);
        } catch (Exception $e) {
            $errormsg = '同步失败：网络异常';
        }

        $errormsg = is_array($errormsg) ? implode('<br/>', $errormsg) : $errormsg;

        if ($result === false) {
            $this->splash('error', null, $errormsg);
        } else {
            //可按店铺类型自定义每次查询的limit解决分销的问题
            $customLimit = $shopfactory->getCustomLimit();
            $used_limit  = ($customLimit > 0 ? $customLimit : inventorydepth_shop::DOWNLOAD_ALL_LIMIT);

            $loading        = $shopfactory->get_approve_status();
            $rate           = $loading ? 100 / count($loading) : 100;
            $totalResults   = $shopfactory->getTotalResults();
            $msg            = '同步完成';
            $downloadStatus = 'running';
            # 判断是否已经全部下载完
            if ($page >= ceil($totalResults / $used_limit) || $totalResults == 0) {
                $msg            = '全部下载完';
                $downloadStatus = 'finish';
                $downloadRate   = $rate * ($flag + 1);

                if ($_POST['time'] && count($loading) == ($flag + 1)) {
                    base_kvstore::instance('inventorydepth/batchframe')->store('downloadTime' . $shop_id, $_POST['time']);

                    $shopLib->setShopSync($shop_id, $_POST['time']);
                }
            } else {
                $downloadRate = $rate * $flag + $page * $used_limit / $totalResults * $rate;
            }
            $this->splash('success', null, $msg, 'redirect', array('errormsg' => $errormsg, 'totalResults' => $totalResults, 'downloadRate' => intval($downloadRate), 'downloadStatus' => $downloadStatus));
        }
    }

    /**
     * 通过IID下载
     *
     * @return void
     * @author
     **/
    public function downloadByIIds()
    {
        die('方法废弃');

        if (!$_POST['id'] && !$_POST['isSelectedAll']) {
            $this->splash('error', null, $this->app->_('请选择商品'));
        }

        # 验证是否包含非淘宝SKU
        $_POST['shop_type|noequal'] = 'taobao';

        $itemModel = $this->app->model('shop_items');
        $other     = $itemModel->getList('id', $_POST, 0, 1);
        if ($other) {
            $this->splash('error', null, $this->app->_('除淘宝店铺外，暂不支持其他店铺下载'));
        }
        unset($_POST['shop_type|noequal']);

        $_POST['shop_type'] = 'taobao';

        # 获取所有淘宝店铺
        set_time_limit(0);
        $itemModel->appendCols = '';
        $taobao_shops          = $itemModel->getList(' distinct shop_id', $_POST);
        foreach ($taobao_shops as $shop) {
            $offset           = 0;
            $limit            = 20;
            $_POST['shop_id'] = $shop['shop_id'];
            do {
                $taobao_iids = $itemModel->getList('iid', $_POST, $offset, $limit);

                if (!$taobao_iids) {
                    break;
                }

                $iids = array_map('current', $taobao_iids);

                kernel::single('inventorydepth_shop')->downloadByIId($iids, $shop['shop_id'], $errormsg);

                $offset += $limit;

            } while (true);
        }

        $this->splash('success', null);
    }

    /**
     * 通过IID下载单个
     *
     * @return void
     * @author
     **/
    public function downloadByIId($id = null)
    {

        if (!$id) {
            $this->splash('error', null, $this->app->_('请选择商品!'));
        }

        $item = $this->app->model('shop_items')->select()->columns('iid,shop_id,shop_type,shop_name')
            ->where('id=?', $id)->instance()->fetch_row();

        if (!$item) {
            $this->splash('error', null, $this->app->_('商品记录为空!'));
        }

        # 验证是否包含非淘宝SKU
        if (!inventorydepth_shop_api_support::items_get_support($item['shop_type'])) {
            $msg = '暂不支持对' . $item['shop_name'] . '店铺商品下载!';
            $this->splash('error', null, $msg);
        }

        $result = kernel::single('inventorydepth_shop')->downloadByIId($item['iid'], $item['shop_id'], $errormsg);

        $status         = $result ? 'success' : 'error';
        $downloadRate   = $result ? '100' : '0';
        $downloadStatus = $result ? 'finish' : 'running';

        $this->splash($status, null, $errormsg, 'redirect', array('downloadRate' => $downloadRate, 'downloadStatus' => $downloadStatus));

    }

    /**
     * 通过SKU_ID下载货品， 针对单个
     *
     * @param Int $id  货品记录ID
     * @return void
     * @author
     **/
    public function dowloadBySkuId($id = null)
    {

        if (!$id) {
            $this->splash('error', null, $this->app->_('请选择SKU'));
        }

        # 获取货品必要信息
        $sku = $this->app->model('shop_skus')->select()->columns('shop_id,shop_bn,shop_type,shop_sku_id,shop_name,shop_iid')
            ->where('id=?', $id)->instance()->fetch_row();

        if (!$sku) {
            $this->splash('error', null, $this->app->_('该货品不存在'));
        }

        # 验证货品对应的店铺是否支持接口
        if (!inventorydepth_shop_api_support::item_sku_get_support($sku['shop_type'])) {
            $msg = '暂不支持对' . $sku['shop_name'] . '店铺商品下载。';
            $this->splash('error', null, $msg);
        }

        $data = array(
            'sku_id' => $sku['shop_sku_id'],
            'iid'    => $sku['shop_iid'],
            'id'     => $id,
        );

        # 同步
        $result = kernel::single('inventorydepth_shop')->dowloadBySkuId($data, $sku['shop_id'], $errormsg);

        if ($result) {
            $status         = 'success';
            $msg            = $this->app->_('同步完成!');
            $downloadRate   = '100';
            $downloadStatus = 'finish';
        } else {
            $status         = 'error';
            $msg            = $errormsg;
            $downloadRate   = '0';
            $downloadStatus = 'running';
        }

        $this->splash($status, null, $msg, 'redirect', array('downloadRate' => $downloadRate, 'downloadStatus' => $downloadStatus));
    }

    /**
     * 上下架调整
     *
     * @return void
     * @author
     **/
    public function jump($type)
    {
        switch ($type) {
            case 'item':
                $this->pagedata['url'] = 'index.php?app=inventorydepth&ctl=shop_frame&act=index';
                break;
            case 'sku':
                $this->pagedata['url'] = 'index.php?app=inventorydepth&ctl=shop_adjustment&act=index';
                break;
            case 'frame':
                $this->pagedata['url'] = 'index.php?app=inventorydepth&ctl=shop_frame&act=index';
                break;
            case 'warning':
                $this->pagedata['url'] = 'index.php?app=inventorydepth&ctl=shop_batchframe&act=redownload';
                break;
            default:
                # code...
                break;
        }

        #过滤o2o门店店铺
        $shops = $this->app->model('shop')->getList('shop_id,shop_bn,name,shop_type,node_id', array('s_type' => 1), 0, -1);

        $s = array_intersect(inventorydepth_shop_api_support::$item_sku_get_shops, inventorydepth_shop_api_support::$items_all_get_shops, inventorydepth_shop_api_support::$items_get_shops);

        if (app::get('drm')->is_installed()) {
            $channelShopObj = app::get('drm')->model('channel_shop');
            $rows           = $channelShopObj->getList('shop_id');
            foreach ($rows as $val) {
                $channelShop[] = $val['shop_id'];
            }
        }

        $support_shops = $unsupport_shops = array();
        foreach ($shops as $key => $shop) {
            if (!in_array($shop['shop_id'], $channelShop) && in_array($shop['shop_type'], $s) && $shop['node_id']) {
                $support_shops[] = $shop;
            } else {
                $unsupport_shops[] = $shop;
            }
        }

        $this->pagedata['support_shops'] = $support_shops;

        $this->pagedata['unsupport_shops'] = $unsupport_shops;

        $this->pagedata['type'] = $type;

        $this->page('shop/shopjump.html');
    }
    
    //下载前端店铺拉取商品
    public function downloadAllGoods($shop_id)
    {
        $shop = app::get('ome')->model('shop');
        
        //shopInfo
        $shopInfo = $shop->db_dump(array('shop_id'=>$shop_id), '*');
        if(empty($shopInfo)){
            die('没有获取到店铺信息');
        }
        
        //设置店铺拉取的用户ID
        kernel::single('inventorydepth_shop')->setShopSync($shop_id);
        
        $this->pagedata['shopInfo'] = $shopInfo;
        $this->pagedata['goods_type'] = array(
                ['key'=>'','value'=>'全部'],
                ['key'=>'0','value'=>'上架'],
                ['key'=>'1','value'=>'下架'],
        );
        
        //开始时间(默认为一个月前)
        $start_time = date('Y-m-d', strtotime('-1 month'));
        $this->pagedata['start_time'] = $start_time;
        
        $this->display('shop/download/download_all_goods.html');
    }
    
    /**
     * Ajax拉取前端店铺商品
     */
    public function ajaxDownloadAllGoods()
    {
        $shop = app::get('ome')->model('shop');
        $shopLib = kernel::single('inventorydepth_shop');
        
        //result
        $retArr = array('itotal'=>0, 'isucc'=>0, 'ifail'=>0, 'total'=>0, 'err_msg'=>array());
        
        $post = $_POST;
        
        //post
        //parse_str($_POST['shopId'], $postdata);
        $shop_id = $post['shopId'];
        if (empty($shop_id)) {
            $retArr['err_msg'] = array('请先选择店铺');
            echo json_encode($retArr);
            exit;
        }
        
        //page
        $page = isset($post['nextPage']) && $post['nextPage'] > 1 ? $post['nextPage'] : 1;
        
        //filter
        $filter = array('shop_id'=>$shop_id);
        
        //商品状态(0上架 1下架)
        if($post['goodsType']==='0' || $post['goodsType']==='1'){
            $filter['goods_type'] = intval($post['goodsType']);
        }
        
        //拉取开始时间(年-月-日)
        if($post['startTime']){
            $filter['start_time'] = $post['startTime'];
        }
        
        //[得物平台]出价类型
        if($post['biddingType']){
            $filter['biddingType'] = $post['biddingType'];
        }
        
        //request
        try {
            $result = $shopLib->ajaxDownloadList($shop_id, $filter, $page);
        } catch (Exception $e) {
            $result['error_msg'] = '同步失败：网络异常';
        }
        
        if ($result['rsp'] != 'succ') {
            $retArr['err_msg'] = array($result['error_msg']);
        }else{
            $retArr['itotal'] += $result['succNums']; //本次拉取成功的数量
            $retArr['ifail'] += $result['failNums']; //本次拉取失败的数量
            $retArr['total'] = $result['total']; //需要拉取商品的总数
            
            //下一页
            $retArr['next_page'] = intval($result['next_page']);
        }
        
        echo json_encode($retArr);
        exit;
    }
    
    /**
     *云店门店绑定页面
     * @param $shop_bn
     * @param $shop_id
     * @date 2024-04-09 5:53 下午
     */
    public function displaShopOnoffline($shop_bn, $shop_id)
    {
        $this->pagedata['ome_shop_id'] = $shop_id;
        $this->pagedata['ome_shop_bn'] = $shop_bn;
        $this->display('shop/show_onoffline.html');
    }
    
    /**
     * 云店门店弹窗展示数据
     * @date 2024-04-09 5:54 下午
     */
    public function getShopOnoffline()
    {
        $filter      = array_filter($_POST['f']);
        $ome_shop_id = $filter['ome_shop_id'];
        
        $offlineList  = app::get('ome')->model('shop_onoffline')->getList('off_id', ['on_id' => $ome_shop_id]);
        $shopIds      = array_column($offlineList, 'off_id');
        $o2oStoreList = app::get('o2o')->model('store')->getList('shop_id,store_id,store_bn,name');
        $branchLeft   = [];
        $boundList    = [];
        foreach ($o2oStoreList as $k => $store) {
            if (in_array($store['shop_id'], $shopIds)) {
                $boundList[$store['store_bn']] = $store;//右侧
            } else {
                $branchLeft[$store['store_bn']] = $store;//左侧
            }
        }
        $branchLeft = array_values($branchLeft);
        $boundList  = array_values($boundList);
        $this->splash('success', null, null, 'redirect', ['data' => $branchLeft, 'bound' => $boundList]);
    }
    
    /**
     * 绑定云店门店关系
     * @date 2024-04-09 5:54 下午
     */
    public function saveShopOnoffline()
    {
        $this->begin($this->url . '&act=index');
        $ome_shop_id = $_POST['ome_shop_id'];
        $ome_shop_bn = $_POST['ome_shop_bn'];
        
        if (!is_array($_POST['shop_id']) && $_POST['shop_id']) {
            $_POST['shop_id'] = explode(',', $_POST['shop_id']);
        }
        $shop_ids = $_POST['shop_id'] ? array_unique($_POST['shop_id']) : [];
        
        $offlineList  = app::get('ome')->model('shop_onoffline')->getList('off_id', ['on_id' => $ome_shop_id]);
        $o2oStoreList = app::get('o2o')->model('store')->getList('shop_id,store_bn,name', ['shop_id' => array_column($offlineList, 'off_id')]);
        kernel::single('ome_shop_onoffline')->onlineSave($ome_shop_id, $shop_ids);
        
        //记录编辑前关系日志
        $ip          = kernel::single("base_request")->get_remote_addr();
        $memo        = '店铺' . $ome_shop_bn . "云店门店关系原数据【" . json_encode($o2oStoreList) . "】" . 'IP:' . $ip;
        $optLogModel = app::get('inventorydepth')->model('operation_log');
        $optLogModel->write_log('shop', $ome_shop_id, 'online_offline_set', $memo);
        $this->end(true, '保存成功');
    }
    
    /**
     * 多请求并发处理
     * @param mixed $downloadType
     * @param mixed $shop_id
     * @return void
     */
    public function downloadPageV2($downloadType = '', $shop_id = '')
    {
        $downloadType = $downloadType ? $downloadType : $_GET['downloadType'];
        $shop_id      = $shop_id ? $shop_id : $_GET['shop_id'];
        
        $this->pagedata['shop_id'] = $shop_id;
        
        $this->display('shop/download/search_step_1.html');
    }

    /**
     * 多请求并发处理
     * @param mixed $downloadType
     * @param mixed $shop_id
     * @return void
     */
    public function downloadAjaxV2($shop_id = '')
    {
        $post = $_POST; $pageNo = $_GET['pageNo'];

        $filter = array_filter($post);

        if ($filter['start_modified']){
            $filter['start_modified'] = date('Y-m-d 00:00:00', strtotime($filter['start_modified']));
        }

        if ($filter['end_modified']){
            $filter['end_modified'] = date('Y-m-d 23:59:59', strtotime($filter['end_modified']));
        }
        
        try {
            $result = kernel::single('inventorydepth_shop')->downloadList($shop_id, $filter, $pageNo, $errormsg);
        } catch (Exception $e) {
            $result = false;
            $errormsg = '同步失败：网络异常';
        }

        if ($result === false) {
            $this->splash('error', null, $errormsg);
        } 

        $this->splash('success');
    }

    public function downloadPagePromise($shop_id = '')
    {
        $post = $_POST;  unset($post['baseApiUrl'], $post['_DTYPE_DATE']);
        
        $inputhtml = '';
        
        foreach ($post as $key => $value) {
            $params = array(
                'type' => 'hidden',
                'name' => $key,
                'value' => $value,
            );

            $inputhtml .= utils::buildTag($params, 'input');
        }

        $this->pagedata['inputhtml'] = $inputhtml;
        
        $shop = app::get('ome')->model('shop')->dump($shop_id);
        if (!$shop) {
            $this->splash('error', null, "店铺不存在!");
        }
        
        $filter = array_filter($post);
        
        if ($filter['start_modified']){
            $filter['start_modified'] = date('Y-m-d 00:00:00', strtotime($filter['start_modified']));
        }

        if ($filter['end_modified']){
            $filter['end_modified'] = date('Y-m-d 23:59:59', strtotime($filter['end_modified']));
        }
        
        $obj = kernel::single('inventorydepth_rpc_request_shop_items');
        $result = $obj->items_all_get($filter,$shop_id,1,1);
        if (!$result){
            $this->splash('error', null, $obj->get_err_msg());
        }
        
        $totalCount = $result['totalResults'];

        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type'],$shop['business_type']);
        if ($shopfactory === false) {
            $this->splash('error', null, "店铺类型有误！!");
        }

        $customLimit = $shopfactory->getCustomLimit();

        parent::dialog_promise($totalCount, $customLimit);
    }

    /**
     * 多线程获取缓存商品
     * @Author: XueDing
     * @Date: 2024/11/21 3:56 PM
     * @return void
     */
    public function pageSync($shop_id = '')
    {
        $shop_id      = $shop_id ? $shop_id : $_GET['shop_id'];

        $this->pagedata['shop_id'] = $shop_id;
        $this->pagedata['time_from'] = date('Y-m-d', strtotime('last month'));
        $this->pagedata['time_to'] = date('Y-m-d');

        $this->display('shop/download/download_page_sync.html');
    }

    public function doSync($shop_id = '')
    {
        $post = $_POST; $pageNo = $_GET['pageNo'];

        $filter = array_filter($post);

        if ($filter['start_modified']){
            $filter['start_time'] = date('Y-m-d 00:00:00', strtotime($filter['start_modified']));
        }

        if ($filter['end_modified']){
            $filter['end_time'] = date('Y-m-d 23:59:59', strtotime($filter['end_modified']));
        }

        try {
            $result = kernel::single('inventorydepth_shop')->downloadCacheProductList($shop_id, $filter, $pageNo, $errormsg);
        } catch (Exception $e) {
            $result = false;
            $errormsg = '同步失败：网络异常';
        }

        if ($result['rsp'] == 'fail') {
            $this->splash('error', null, $errormsg);
        }

        $this->splash('success');
    }

    public function downloadPageSyncPromise($shop_id = '')
    {
        $post = $_POST;  unset($post['baseApiUrl'], $post['_DTYPE_DATE']);

        $inputhtml = '';

        foreach ($post as $key => $value) {
            $params = array(
                'type' => 'hidden',
                'name' => $key,
                'value' => $value,
            );

            $inputhtml .= utils::buildTag($params, 'input');
        }

        $this->pagedata['inputhtml'] = $inputhtml;

        $shop = app::get('ome')->model('shop')->dump($shop_id);
        if (!$shop) {
            $this->splash('error', null, "店铺不存在!");
        }

        $filter = array_filter($post);

        if ($filter['start_modified']){
            $filter['start_time'] = date('Y-m-d 00:00:00', strtotime($filter['start_modified']));
        }

        if ($filter['end_modified']){
            $filter['end_time'] = date('Y-m-d 23:59:59', strtotime($filter['end_modified']));
        }
        $invenShopLib = kernel::single('inventorydepth_shop');
        $result = $invenShopLib->queryCacheProduct($shop_id,$filter);
        if ($result['rsp'] == 'fail') {
            $this->splash('error', null, $result['err_msg']);
        }

        $totalCount = $result['data']['count'];

        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type'],$shop['business_type']);
        if ($shopfactory === false) {
            $this->splash('error', null, "店铺类型有误！!");
        }

        $customLimit = $shopfactory->getCustomLimit();
        //没有定义页码默认15条
        $customLimit = ($customLimit > 0 ? $customLimit : $invenShopLib::DOWNLOAD_ALL_LIMIT);

        parent::dialog_promise($totalCount, $customLimit);
    }

}
