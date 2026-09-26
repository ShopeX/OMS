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

class wap_ctl_store extends wap_controller
{
    var $delivery_link    = array();
    
    function __construct($app)
    {
        parent::__construct($app);
        
        //供货管理
        $this->delivery_link['supply']     = app::get('wap')->router()->gen_url(array('ctl'=>'branch_product','act'=>'index'), true);
        
        //门店库存
        $this->delivery_link['stock']      = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'stock'), true);
        
        //设置
        $this->delivery_link['ajaxSetting']    = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'ajaxSetting'), true);

        //店铺数据
        $this->delivery_link['statistics']     = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'statistics'), true);
        
        $this->pagedata['delivery_link']   = $this->delivery_link;
    }
    
    function index()
    {

        $orderWaitPickupUrl  = app::get('wap')->router()->gen_url(array('ctl'=>'order','act'=>'index?view=wait_pickup'), true);

        $menu_list_new = array(
            'wap_order_index' => array('menuName' => '订单列表', 'linkUrl' => $this->delivery_link['order_index'], 'ico' => 'icon-order.png', 'key' => 1),
            'wap_aftersale_returnproduct'=>array('menuName'=>'门店退货', 'linkUrl'=>$this->delivery_link['aftersale_returnproduct'], 'ico'=>'icon-return.png', 'key'=>2),
            'wap_store_stock'=>array('menuName'=>'门店库存', 'linkUrl'=>$this->delivery_link['stock'], 'ico'=>'icon-order.png', 'key'=>3),
            'wap_store_selfpick' => array('menuName' => '自提订单', 'linkUrl' => $this->delivery_link['store_selfpick'], 'ico' => 'icon-ziti.png', 'key' => 4),
        );
        
        #管理员信息
        $userInfo    = kernel::single('ome_func')->getDesktopUser();
        
        #授权门店
        $is_super = kernel::single('desktop_user')->is_super();
        if(!$is_super){
            $branchObj  = kernel::single('o2o_store_branch');
            $branch_ids = $branchObj->getO2OBranchByUser(true);
            $storeLib   = kernel::single('wap_store_user');
            $storeInfo  = $storeLib->getCurrentStore($branch_ids);
            $userInfo   = $storeLib->mergeUserStoreInfo($userInfo, $storeInfo);
        }
        $this->pagedata['userInfo']    = $userInfo;

        //读取统计数据
        $wapDeliveryLib    = kernel::single('wap_delivery');
        if ($storeInfo) {
            $statistic = $wapDeliveryLib->getStoreStatistic($storeInfo);
        }
        
        #操作员拥有的导航栏目
        $show_menu    = array();
        foreach ($this->user_menu as $key => $val)
        {
            if(isset($menu_list_new[$val]))
            {
                $menu_list_new[$val]['count'] = isset($statistic[$val]) ? $statistic[$val] : 0;
                $key_id                = $menu_list_new[$val]['key'];
                $show_menu[$key_id]   = $menu_list_new[$val];
            }
        }
        
        //菜单以Key键值排序
        ksort($show_menu);
        $this->pagedata['menu_list']    = $show_menu;

        // 统计数字可点击跳转（需对应 WAP 权限）
        $statLinks = array();
        if (in_array('wap_order_index', $this->user_menu, true)) {
            $statLinks['wap_order_index'] = $this->delivery_link['order_wait_delivery'];
        }
        if (in_array('wap_aftersale_returnproduct', $this->user_menu, true)) {
            $statLinks['wap_aftersale_returnproduct'] = $this->delivery_link['aftersale_returnproduct'];
        }
        if (in_array('wap_store_selfpick', $this->user_menu, true)) {
            $statLinks['wap_store_selfpick'] = app::get('wap')->router()->gen_url(
                array('ctl' => 'store_selfpick', 'act' => 'index', 'view' => 'pending'),
                true
            );
        }
        $this->pagedata['stat_links'] = $statLinks;

        #if(empty($statistic) || $_SESSION['login_flag'])
        #{
        #    //task任务更新统计数据
        #    $wapDeliveryLib    = kernel::single('wap_delivery');
        #    $wapDeliveryLib->taskmgr_statistic('all');
        #    
        #    $statistic    = $wapDeliveryLib->fetchDataFromCache();
        #    
        #    unset($_SESSION['login_flag']);
        #}
        $this->pagedata['count_data']    = $statistic;
        
        $this->display('store/index.html');
    }
    
    function setting()
    {
        $userLib = kernel::single('desktop_user');
        $op_id = $userLib->get_id();

        //根据当前管理员获取负责管理的门店信息
        $branchObj     = kernel::single('o2o_store_branch');
        $branch_ids    = $branchObj->getO2OBranchByUser(true);
        if(empty($branch_ids))
        {
            $this->pagedata['link_url']     = $this->delivery_link['index'];
            $this->pagedata['error_msg']    = '当前店员没有门店的管理权限';
            echo $this->fetch('auth_error.html');
            exit;
        }
        
        //门店信息
        $storeObj      = app::get('o2o')->model('store');
        $store_item    = $storeObj->dump(array('branch_id'=>$branch_ids), 'store_id,store_bn,name,self_pick,distribution, is_ctrl_store');
        
        $this->pagedata['store']  = $store_item;
        $this->menu['logout']     = $this->delivery_link['logout'];
        $this->menu['confirm']    = $this->delivery_link['confirm'];
        $this->menu['ajaxSetting']    = $this->delivery_link['ajaxSetting'];
        $this->pagedata['menu']   = $this->menu;
        
        $this->display('store/setting.html');
    }

    function ajaxSetting()
    {
        $store_id        = intval($_POST['store_id']);
        $self_pick       = $_POST['self_pick'];
        $distribution    = $_POST['distribution'];
        $is_ctrl_store   = $_POST['is_ctrl_store'];
        
        if(empty($store_id))
        {
            echo json_encode(array('error'=>true, 'message'=>'无效操作', 'redirect'=>null));
            exit;
        }

        $self_pick    = ($self_pick == 'on' ? 1 : 2);
        $distribution    = ($distribution == 'on' ? 1 : 2);
        $is_ctrl_store   = ($is_ctrl_store == 'on' ? 1 : 2);
        
        $storeObj      = app::get('o2o')->model('store');
        $store_info    = $storeObj->dump(array('store_id'=>$store_id), 'store_id,store_bn,name,self_pick,distribution');
        if(!$store_info)
        {
            echo json_encode(array('error'=>true, 'message'=>'门店不存在', 'redirect'=>null));
            exit;
        }
        
        $update_data = array(
            'self_pick' => $self_pick,
            'distribution' => $distribution,
            'is_ctrl_store' => $is_ctrl_store,
        );
        $affect_row = $storeObj->update($update_data,array('store_id'=>$store_id));
        
        if(!$affect_row){
            echo json_encode(array('error'=>true, 'message'=>'设置失败', 'redirect'=>null));
            exit;
        }else{
            echo json_encode(array('success'=>true, 'message'=>'设置成功', 'redirect'=>$this->delivery_link['index']));
            exit;
        }
    }

    /**
     * [列表]门店库存
     */
    function stock()
    {
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $proStoreObj         = app::get('ome')->model('branch_product');
        
        $page              = intval($_POST['page']) ? intval($_POST['page']) : 0;
        $limit             = 10;//默认显示10条
        $offset            = $limit * $page;
        
        $where    = '1';
        
        $is_super    = kernel::single('desktop_user')->is_super();
        if(!$is_super)
        {
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            $branch_ids = $branchObj->getO2omanbranchs($branch_ids);
            if(empty($branch_ids))
            {
                $this->pagedata['link_url']     = $this->delivery_link['index'];
                $this->pagedata['error_msg']    = '操作员没有管辖的仓库';
                echo $this->fetch('auth_error.html');
                exit;
            }
            
            $where    .= " AND a.branch_id in(". implode(',', $branch_ids) .")";
        }
        
        //搜索条件
        if($_POST['sel_type'] && $_POST['sel_keywords'])
        {
            $keywords    = htmlspecialchars(trim($_POST['sel_keywords']));
            switch ($_POST['sel_type'])
            {
                case 'item_bn':
                    $where    .= " AND b.material_bn='". $keywords ."'";
                    break;
                case 'item_name':
                    $where    .= " AND b.material_name like'%". $keywords ."%'";
                    break;
            }
            $this->pagedata['sel_type']    = $_POST['sel_type'];
            $this->pagedata['sel_keywords']    = $keywords;
        }
        
        $c_sql    = "SELECT count(*) AS num FROM sdb_ome_branch_product AS a LEFT JOIN sdb_material_basic_material AS b ON a.product_id=b.bm_id WHERE ". $where;
        $count    = $proStoreObj->db->selectrow($c_sql);
        $count    = $count['num'];
        
        $sql    = "SELECT a.id, a.product_id as bm_id, a.branch_id, a.store, a.store_freeze, b.material_bn, b.material_name 
                   FROM sdb_ome_branch_product AS a 
                   LEFT JOIN sdb_material_basic_material AS b ON a.product_id=b.bm_id WHERE ". $where ." 
                   ORDER BY a.last_modified DESC LIMIT ". $offset .",". $limit;

        $dataList    = $proStoreObj->db->select($sql);
        
        //根据门店仓库ID、基础物料ID获取该物料门店仓库级的预占
        if($dataList)
        {
            foreach ($dataList as $key => $val)
            {
                $val['store_freeze']    = $basicMStockFreezeLib->getO2oBranchFreeze($val['bm_id'], $val['branch_id']);
                
                $dataList[$key]    = $val;
            }
        }
        
        $this->pagedata['dataList']    = $dataList;
        $this->pagedata['pageSize']    = ceil($count / $limit);
        $this->pagedata['link_url']    = $this->delivery_link['stock'];
        $this->pagedata['stock_url']   = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'editStock'), true);
        
        if($offset > 0)
        {
            //Ajax加载更多
            $this->display('store/stock_more.html');
        }
        else
        {
            $this->display('store/stock.html');
        }
    }
    
    /**
     * [编辑]门店库存
     */
    function editStock()
    {
        if(empty($_POST['store']) || !is_array($_POST['store']))
        {
            echo json_encode(array('error'=>true, 'message'=>'没有提交有效数据...', 'redirect'=>$this->delivery_link['stock']));
            exit;
        }
        
        $proStoreObj    = app::get('ome')->model('branch_product');
        $storeObj       = app::get('o2o')->model('store');
        $basic_materialMdl = app::get('material')->model('basic_material');
        $is_super    = kernel::single('desktop_user')->is_super();
        if(!$is_super)
        {
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            $branch_ids = $branchObj->getO2omanbranchs($branch_ids);
            if(empty($branch_ids))
            {
                echo json_encode(array('error'=>true, 'message'=>'操作员没有管辖的仓库', 'redirect'=>$this->delivery_link['stock']));
                exit;
            }
            
            $storeInfo   = $storeObj->dump(array('branch_id'=>$branch_ids), 'store_id, store_bn, name');
        }
        

        //检查数据
        $bm_store    = array();
        foreach ($_POST['store'] as $key => $val)
        {
            if(!empty($key) && trim($val) != '')
            {
                $row    = $proStoreObj->dump(array('id'=>$key, 'branch_id'=>$branch_ids), 'id, store,product_id');


                if($row && $val!=$row['store'])
                {
                    $product_id = $row['product_id'];

                    $material = $basic_materialMdl->db_dump(['bm_id'=>$product_id], 'bm_id,material_bn');
                    $bm_store[$key]    = array(
                        'store'     =>$val, 
                        'old_store' =>$row['store'],
                        'nums'      =>$val-$row['store'],
                        'bn'        => $material['material_bn'],
                    );
                }
            }
        }
        
        //保存数据
        if($bm_store)
        {
            $items = array();

            foreach ($bm_store as $key => $val)
            {
                $items[] = array(

                    'bn'    =>  $val['bn'],
                    'nums'  => $val['nums'],
                );
            }
            $items = json_encode($items);
            $adjustdata = array(
                'task_bn'       =>  $storeInfo['store_bn'].date('Ymdhis'),
                'store_bn'      =>  $storeInfo['store_bn'],
                'branch_bn'     =>  $storeInfo['store_bn'],
                'check_auto'    =>  '1',
                'items'         =>  $items,
                //'memo'          =>  '库存初始化',

            );
            $store_id = $storeInfo['store_id'];
            kernel::single('erpapi_router_response')->set_channel_id($store_id)->set_api_name('store.adjust.add')->dispatch($adjustdata);
        }
        
        echo json_encode(array('success'=>true, 'message'=>'调账成功', 'redirect'=>$this->delivery_link['stock']));
        exit;
    }

    //店铺数据
    /**
     * statistics
     * @return mixed 返回值
     */
    public function statistics(){

        $storeDaliyObj     = app::get('o2o')->model('store_daliy');
        $page              = intval($_POST['page']) ? intval($_POST['page']) : 0;
        $limit             = 1;
        $offset            = $limit * $page;
        
        //查询日期(默认一年之内)
        $last_today    = strtotime('-1 year', time());
        $start_date    = ($_POST['start_date'] ? $_POST['start_date'] : date('Y/m/d', $last_today));//开始日期
        $end_date      = ($_POST['end_date'] ? $_POST['end_date'] : date('Y/m/d', time()));//结束日期
        
        $this->pagedata['filter']    = array('start_date'=>$start_date, 'end_date'=>$end_date);
        
        //门店
        $is_super    = kernel::single('desktop_user')->is_super();
        if(!$is_super)
        {
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            if(empty($branch_ids))
            {
                $this->pagedata['link_url']     = $this->delivery_link['index'];
                $this->pagedata['error_msg']    = '管理员没有相应的门店权限';
                echo $this->fetch('auth_error.html');
                exit;
            }
            
            $branch_id = $branch_ids[0];
        }

        //门店信息
        $storeObj      = app::get('o2o')->model('store');
        $storeInfo     = $storeObj->dump(array('branch_id'=>$branch_id), 'store_bn');

        $where = "store_bn='".$storeInfo['store_bn']."'";
        
        //查询时间段
        if($end_date && $start_date)
        {
            $start_date    = strtotime(str_replace('/', '-', $start_date) . '00:00:00');
            $end_date      = strtotime(str_replace('/', '-', $end_date). '23:59:59');
            
            if($end_date > $start_date)
            {
                $where    .= " AND createtime>=". $start_date ." AND createtime<=". $end_date;
            }
        }
        
        //搜索条件：开始时间、结束时间
        $c_sql    = "SELECT count(*) AS num FROM sdb_o2o_store_daliy WHERE ". $where;
        $count    = $storeDaliyObj->db->selectrow($c_sql);
        $count    = $count['num'];
        
        $sql         = "SELECT * FROM sdb_o2o_store_daliy WHERE ". $where ." ORDER BY createtime DESC LIMIT ". $offset .",". $limit;
        $dataList    = $storeDaliyObj->db->select($sql);

        $this->pagedata['title']       = '店铺数据';
        $this->pagedata['dataList']    = $dataList;
        $this->pagedata['pageSize']    = ceil($count / $limit);
        $this->pagedata['link_url']    = $this->delivery_link['statistics'];
        
        if($offset > 0)
        {
            //Ajax加载更多
            $this->display('store/statistics_more.html');
        }
        else
        {
            //合计查询结果
            $sql    = "SELECT sum(order_sum) as order_sum, sum(sale_sum) as sale_sum, sum(confirm_num) as confirm_num,
                       sum(refuse_num) as refuse_num, sum(send_num) as send_num, sum(verified_num) as verified_num
                       FROM sdb_o2o_store_daliy WHERE ". $where;
            $selData = $storeDaliyObj->db->selectrow($sql);
            
            $statistics['update_time']     = ($dataList[0]['createtime'] ? date('Y-m-d', $dataList[0]['createtime']) : '');//最新统计时间
            $statistics['total_orders']    = intval($selData['order_sum']);
            $statistics['sale_sum']        = intval($selData['sale_sum']);
            $statistics['confirm_num']     = intval($selData['confirm_num']);
            $statistics['refuse_num']      = intval($selData['refuse_num']);
            $statistics['send_num']        = intval($selData['send_num']);
            $statistics['verified_num']    = intval($selData['verified_num']);
            $this->pagedata['statistics']  = $statistics;
            
            $this->display('store/statistics.html');
        }
    }

    
}