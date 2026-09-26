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

class o2o_ctl_admin_store extends desktop_controller
{

    public $name       = "门店管理";
    public $workground = "goods_manager";
    public $title      = '';
    public $url        = 'index.php?app=o2o&ctl=admin_store';

    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $obj_organizations_op           = kernel::single('organization_operation');
        $this->pagedata['organization'] = $obj_organizations_op->getGropById();
        $this->page('admin/organization/treeList.html');
    }

    /**
     * listing
     * @param mixed $store_classify store_classify
     * @return mixed 返回值
     */
    public function listing($store_classify = 'offline')
    {
        $this->title = '门店列表';

        $params = array(
            'title'                  => $this->title,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => true,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_view_tab'           => true,
            'base_filter'            => ['store_classify' => $store_classify],
            'actions'                => [
                [
                    'label'  => '添加门店',
                    'href'   => $this->url . '&act=add',
                    'target' => 'dialog::{width:660,height:480,title:\'添加门店\'}',
                ],
                [
                    'label'  => '批量设置',
                    'submit' => 'index.php?app=o2o&ctl=admin_store&act=batchCtrlStore',
                    'target' => 'dialog::{width:660,height:400,title:\'批量设置门店\'}',
                ],
                [
                    'label'  => '导入门店',
                    'href'   => sprintf('%s&act=import', $this->url),
                    'target' => 'dialog::{width:660,height:400,title:\'导入门店\'}',
                ],
                [
                    'label'  => '覆盖区域导入',
                    'href'   => sprintf('%s&act=importRegion', $this->url),
                    'target' => 'dialog::{width:660,height:420,title:\'覆盖区域导入\'}',
                ],
            ],

        );

        $this->finder('o2o_mdl_store', $params);
    }

    //展示页面获取下架组织信息
    /**
     * 获取ChildNode
     * @return mixed 返回结果
     */
    public function getChildNode()
    {
        $obj_organizations_op           = kernel::single('organization_operation');
        $this->pagedata['organization'] = $obj_organizations_op->getGropById($_POST['orgId']);
        $this->display('admin/organization/sub_treeList.html');
    }

    //展示所有下级
    /**
     * 获取AllChildNode
     * @return mixed 返回结果
     */
    public function getAllChildNode()
    {
        $obj_organizations_op = kernel::single('organization_operation');
        //获取所有下级组织数组
        $dataList = $obj_organizations_op->getAllChildNode($_POST['orgId'], 2);
        if ($dataList) {
            //格式化为html展示
            $html                         = $obj_organizations_op->getAllChildNodeHtml_store($dataList);
            $this->pagedata['store_html'] = $html;
        }
        $this->display('admin/organization/store_all_sub_treeList.html');
    }

    /*
     * 添加门店
     */

    public function add($org_id = null, $p_org_id = null)
    {
        // 获取总店
        $shopList = app::get('ome')->model('shop')->getList('shop_id,name', [
            's_type'    => '1',
            'node_type' => ['ecos.ecshopx'],
        ]);
        $this->pagedata['shopList'] = $shopList;
        // 获取服务端
        $serverList                   = app::get('o2o')->model('server')->getList('server_id,name');
        $this->pagedata['serverList'] = $serverList;

        $Htime = $Mtime = [];
        for ($i = 0; $i < 24; $i++) {
            $i = str_pad($i, 2, '0', STR_PAD_LEFT);

            $Htime[$i] = $i;
        }
        for ($i = 0; $i < 60; $i++) {
            $i = str_pad($i, 2, '0', STR_PAD_LEFT);

            $Mtime[$i] = $i;
        }

        $this->pagedata['Htime'] = $Htime;
        $this->pagedata['Mtime'] = $Mtime;

        // 获取经销商列表（cos_type='bs'）
        $dealerList = app::get('organization')->model('cos')->getList('cos_id,cos_name, cos_code', [
            'cos_type' => 'bs'
        ]);
        $this->pagedata['dealerList'] = $dealerList;

        $this->display("admin/system/store.html");
    }

    /*
     * 编辑门店
     */

    public function edit($store_id)
    {
       
        // 获取服务端
        $serverList                   = app::get('o2o')->model('server')->getList('server_id,name');
        $this->pagedata['serverList'] = $serverList;

        $Htime = $Mtime = [];
        for ($i = 0; $i < 24; $i++) {
            $i = str_pad($i, 2, '0', STR_PAD_LEFT);

            $Htime[$i] = $i;
        }
        for ($i = 0; $i < 60; $i++) {
            $i = str_pad($i, 2, '0', STR_PAD_LEFT);

            $Mtime[$i] = $i;
        }

        $this->pagedata['Htime'] = $Htime;
        $this->pagedata['Mtime'] = $Mtime;

        // 获取总店
        $shopList = app::get('ome')->model('shop')->getList('shop_id,name', [
            's_type'    => '1',
            'node_type' => ['ecos.ecshopx'],
        ]);
        $this->pagedata['shopList'] = $shopList;
        // 门店
        $store_prop = app::get('o2o')->model('store')->dump($store_id);

        $org = app::get('organization')->model('organization')->dump([
            'org_no' => $store_prop['store_bn'],
        ]);
        $this->pagedata['org'] = $org;

        list(, $store_prop['org_name']) = explode(':', $org['org_parents_structure']);

        $open_hours                                                        = explode('-', $store_prop['open_hours']);
        list($store_prop['from_time']['H'], $store_prop['from_time']['M']) = explode(':', $open_hours[0]);
        list($store_prop['to_time']['H'], $store_prop['to_time']['M'])     = explode(':', $open_hours[1]);

        $branchList = kernel::single('ome_interface_branch')->getList('*',[
            'branch_bn' => $store_prop['store_bn'],
            'b_type' => '2',
        ]);
        $store_prop['storage_code'] = $branchList[0]['storage_code'];

        // 获取门店关联的经销商信息
        if ($store_prop['store_bn']) {
            $orgMdl = app::get('organization')->model('organization');
            $storeOrg = $orgMdl->dump(['org_no' => $store_prop['store_bn']]);
            
            if ($storeOrg && $storeOrg['parent_id']) {
                // 查找父级组织
                $parentOrg = $orgMdl->dump(['org_id' => $storeOrg['parent_id']]);
                
                // 如果父级是经销商类型(org_type=3)，获取对应的cos_id
                if ($parentOrg && $parentOrg['org_type'] == 3) {
                    $cosMdl = app::get('organization')->model('cos');
                    // 注意：这里需要去掉BS_前缀来匹配cos_code
                    $dealerCode = str_replace('BS_', '', $parentOrg['org_no']);
                    $dealerCos = $cosMdl->dump(['cos_code' => $dealerCode], 'cos_id');
                    if ($dealerCos) {
                        $store_prop['dealer_cos_id'] = $dealerCos['cos_id'];
                    }
                }
            }
        }

        $this->pagedata['store_prop'] = $store_prop;

        // 获取经销商列表（cos_type='bs'）
        $dealerList = app::get('organization')->model('cos')->getList('cos_id,cos_name, cos_code', [
            'cos_type' => 'bs'
        ]);
        $this->pagedata['dealerList'] = $dealerList;

        $this->display("admin/system/store.html");

    }

   
    /**
     * 保存
     * @return mixed 返回操作结果
     */
    public function save()
    {
        $this->begin();

        list($rs, $msg) = kernel::single('o2o_store')->create($_POST);

        $this->end($rs, $msg);
    }

    /**
     * toRemoveStore
     * @param mixed $org_id ID
     * @return mixed 返回值
     */
    public function toRemoveStore($org_id)
    {
        $this->begin('index.php?app=o2o&ctl=admin_store&act=index');

        $org_id = intval($org_id);
        if (!$org_id) {
            $this->end(false, '参数错误！');
        }

        $orgObj           = app::get('organization')->model('organization');
        $storeObj         = app::get('o2o')->model('store');
        $orderObj         = app::get('ome')->model('orders');
        $deliveryObj      = app::get('ome')->model('delivery');
        $arr_store        = array();
        $tmp_shop_store   = array();
        $tmp_branch_store = array();
        $tmp_shops        = array();
        $tmp_branches     = array();

        $org_arr  = $orgObj->getList('org_id,org_no,parent_id', array('org_id' => $org_id));
        $org_info = $org_arr[0];

        $store_info = $storeObj->getList('store_id,store_bn,name,shop_id,branch_id', array('store_bn' => $org_info['org_no']), 0, 1);
        $store_id   = $store_info[0]['store_id'];
        $store_bn   = $store_info[0]['store_bn'];
        $store_name = $store_info[0]['name'];
        $shop_id    = $store_info[0]['shop_id'];
        $branch_id  = $store_info[0]['branch_id'];

        //检查门店关联的店铺是否有订单
        if ($shop_id) {
            $row = $orderObj->getList('shop_id', array('shop_id' => $shop_id));
            if ($row) {
                $error_msg = $store_name . '已有订单数据，不能删除';
                $this->end(false, $error_msg);
            }
        }

        //检查门店关联的仓库是否有发货单
        if ($branch_id) {
            $row = $deliveryObj->getList('branch_id', array('branch_id' => $branch_id));
            if ($row) {
                $error_msg = $store_name . '已有发货数据，不能删除';
                $this->end(false, $error_msg);
            }
        }

        $shopObj   = app::get('o2o')->model('shop');
        $branchObj = app::get('o2o')->model('branch');

        $shopObj->delete(array('shop_id' => $shop_id));
        $branchObj->delete(array('branch_id' => $branch_id));
        $storeObj->delete(array('store_id' => $store_id));

        //处理组织结构中的数据内容，删除店铺节点，更新父级节点的子节点标记
        $orgObj->delete(array('org_id' => $org_id));

        //处理原来选中的父节点
        if ($org_info['parent_id'] > 0) {
            $p_org_info = $orgObj->dump(array("org_id" => $org_info['parent_id']), "haschild");
            $child_arr  = $orgObj->getList("org_id", array("parent_id" => $org_info['parent_id'], 'org_type' => 2), 0, -1);
            if (count($child_arr) > 0) {
                $org_save_parent_data['haschild'] = $p_org_info['haschild'] | 2;
            } else {
                $org_save_parent_data['haschild'] = $p_org_info['haschild'] ^ 2;
            }
            $orgObj->update($org_save_parent_data, array('org_id' => $org_info['parent_id']));
        }

        $this->end(true, '删除门店成功！');
    }

    /**
     * 获取CoordinateByAddr
     * @return mixed 返回结果
     */
    public function getCoordinateByAddr()
    {
        $adr  = !empty($_GET['adr']) ? trim($_GET['adr']) : false;
        $city = !empty($_GET['city']) ? trim($_GET['city']) : false;
        if (!$adr || !$city) {
            echo 'false';
            exit;
        }

        $uri                = '/place/v2/search';
        $ak                 = app::get('o2o')->getConf('o2o.baidumap.ak');
        $sk                 = app::get('o2o')->getConf('o2o.baidumap.sk');
        $querystring_arrays = array(
            'q'         => $adr,
            'region'    => $city,
            'output'    => 'json',
            'ak'        => $ak,
            'timestamp' => time(),
        );
        $api_url = 'http://api.map.baidu.com' . $uri . '?' . http_build_query($querystring_arrays) . '&sn=' . $this->caculateAKSN($ak, $sk, $uri, $querystring_arrays);

        $http = new base_httpclient;
        echo $http->get($api_url);
        exit;
    }

    /**
     * baiduAPI sn生成器
     * @return void
     */
    protected function caculateAKSN($ak, $sk, $url, $querystring_arrays, $method = 'GET')
    {
        if ($method === 'POST') {
            ksort($querystring_arrays);
        }
        $querystring = http_build_query($querystring_arrays);
        return md5(urlencode($url . '?' . $querystring . $sk));
    }

    //批量导入门店
    public function import()
    {
        $this->display('admin/system/import.html');
    }

    /**
     * 覆盖区域导入页面
     */
    public function importRegion()
    {
        $this->display('admin/system/import_region.html');
    }

    /**
     * 执行覆盖区域导入
     */
    public function toImportRegion()
    {
        $summary = '';
        list($ok, $message) = kernel::single('o2o_store_coverage')->doImport(
            (array) ($_FILES['import_file'] ?? array()),
            $summary
        );

        if ($ok) {
            $this->splash('success', null, $message ?: $summary);
        }

        $this->splash('error', null, $message ?: $summary);
    }

    /**
     * 覆盖区域导入模板
     */
    public function exportRegionTemplate()
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=覆盖区域导入模板-' . date('Ymd') . '.csv');
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');

        $title = array('*:门店编码', '*:覆盖区域');
        foreach ($title as $key => $val) {
            $title[$key] = kernel::single('base_charset')->utf2local($val);
        }
        echo '"' . implode('","', $title) . '"';

        $samples = array(
            array('6101', '安徽-合肥市'),
            array('6200', '中国'),
            array('6102', '上海;安徽-合肥市'),
        );
        foreach ($samples as $items) {
            foreach ($items as $key => $val) {
                $items[$key] = kernel::single('base_charset')->utf2local($val);
            }
            echo "\n";
            echo '"' . implode('","', $items) . '"';
        }
    }

    public function to_import()
    {
        $this->finder('o2o_mdl_store');

        //echo "<script>parent.MessageBox.success('导入成功!');</script>";
    }

    //导出门店模板
    public function exportTemplate()
    {
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=导入门店模板-" . date('Ymd') . ".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');

        $o2oStoreObj = app::get('o2o')->model('store');
        $title       = $o2oStoreObj->exportTemplate();

        echo '"' . implode('","', $title) . '"';

        #模板案例
        /*
        $data[0] = array('20160088001', '上海徐汇店', '华东地区-上海', '启用', '上海-上海市-徐汇区', '移动端',
            '桂林路396号', '230001', '李先生', '021-34520001', '13812345678',
            '自营', '支持', '不支持', '不支持', '不需要', '116.43000|39.908000');
        $data[1] = array('20160088002', '华南区总店', '华南地区', '启用', '广西-北海市-海城区', 'WebPos',
            '大连路101号', '230001', '将先生', '021-34520002', '13912345678',
            '加盟', '不支持', '支持', '不支持', '需要', '192.43000|63.908000');
        */
        $data[0] = array('20160088001', '上海徐汇店', '华东地区-上海', '启用', '上海-上海市-徐汇区', '移动端',
            '桂林路396号', '230001', '李先生', '021-34520001', '13812345678',
            '自营', '', '上海-上海市-徐汇区');
        $data[1] = array('20160088002', '华南区总店', '华南地区', '启用', '广西-北海市-海城区', 'WebPos',
            '大连路101号', '230001', '将先生', '021-34520002', '13912345678',
            '加盟', '某某贸易公司', '中国');

        foreach ($data as $items) {
            foreach ($items as $key => $val) {
                $items[$key] = kernel::single('base_charset')->utf2local($val);
            }

            echo "\n";
            echo '"' . implode('","', $items) . '"';
        }
    }

    /**
     * 新增仓位
     * @param  [type] $store_id
     * @return [type]
     */
    public function position($store_id)
    {
        $storeMdl = app::get('o2o')->model('store');
        $stores   = $storeMdl->dump($store_id, '*');

        $this->pagedata['stores'] = $stores;

        $branch_types = kernel::single('o2o_store')->getBranchType();
        $typeoptions = [];
        foreach($branch_types as $v){
            $typeoptions[$v['type_code']]=$v['text'];
        }

        $this->pagedata['typeoptions'] = $typeoptions;
        $this->display("admin/system/position.html");
    }

    public function saveposition()
    {

        $this->begin();
        $store_id     = $_POST['store_id'];
        $branch_type  = $_POST['branch_type'];
        $storage_code = $_POST['storage_code'];

        $store = app::get('o2o')->model('store')->dump($store_id);
        if (!$store) {
            $this->end(false, '门店不存在');
        }

        $store['storage_code'] = $storage_code;
        list($rs, $msg) = kernel::single('o2o_store')->saveBranchType($store, $branch_type);

        app::get('ome')->model('operation_log')->write_log('storage_upsert@o2o', $store['store_id'], '仓位创建');

        $this->end($rs, $msg);
    }
    
    /**
     * 门店编辑快照展示
     * @param $log_id
     * @param $store_id
     * @date 2025-06-12 下午3:36
     */
    public function show_history($log_id, $store_id)
    {
        $logSnapshootMdl = app::get('ome')->model('operation_log_snapshoot');
        $log             = $logSnapshootMdl->db_dump(['log_id' => $log_id]);
        $store_prop      = json_decode($log['snapshoot'], 1);
        
        //若没有缓存 历史编辑读取当前店铺信息
        if (!$store_prop && $store_id) {
            $store_prop = app::get('o2o')->model('store')->dump($store_id);
        }
        
        // 获取服务端
        $serverList                   = app::get('o2o')->model('server')->getList('server_id,name');
        $this->pagedata['serverList'] = $serverList;
        
        // 获取总店
        $shopList                   = app::get('ome')->model('shop')->getList('shop_id,name', [
            's_type'    => '1',
            'node_type' => ['ecos.ecshopx'],
        ]);
        $this->pagedata['shopList'] = $shopList;
        
        
        $org                   = app::get('organization')->model('organization')->dump([
            'org_no' => $store_prop['store_bn'],
        ]);
        $this->pagedata['org'] = $org;
        
        list(, $store_prop['org_name']) = explode(':', $org['org_parents_structure']);
        
        $open_hours = explode('-', $store_prop['open_hours']);
        list($store_prop['from_time']['H'], $store_prop['from_time']['M']) = explode(':', $open_hours[0]);
        list($store_prop['to_time']['H'], $store_prop['to_time']['M']) = explode(':', $open_hours[1]);
        
        $branchList                   = kernel::single('ome_interface_branch')->getList('*', [
            'branch_bn' => $store_prop['store_bn'],
            'b_type'    => '2',
        ]);
        $store_prop['storage_code']   = $branchList[0]['storage_code'];
        
        // 获取门店关联的经销商信息（历史快照）
        if ($store_prop['store_bn']) {
            $orgMdl = app::get('organization')->model('organization');
            $storeOrg = $orgMdl->dump(['org_no' => $store_prop['store_bn']]);
            
            if ($storeOrg && $storeOrg['parent_id']) {
                // 查找父级组织
                $parentOrg = $orgMdl->dump(['org_id' => $storeOrg['parent_id']]);
                
                // 如果父级是经销商类型(org_type=3)，获取对应的cos_id
                if ($parentOrg && $parentOrg['org_type'] == 3) {
                    $cosMdl = app::get('organization')->model('cos');
                    // 注意：这里需要去掉BS_前缀来匹配cos_code
                    $dealerCode = str_replace('BS_', '', $parentOrg['org_no']);
                    $dealerCos = $cosMdl->dump(['cos_code' => $dealerCode], 'cos_id,cos_name');
                    if ($dealerCos) {
                        $store_prop['dealer_cos_id'] = $dealerCos['cos_id'];
                        $store_prop['dealer_cos_name'] = $dealerCos['cos_name'];
                    }
                }
            }
        }
        
        // 获取经销商列表（用于历史快照显示）
        $dealerList = app::get('organization')->model('cos')->getList('cos_id,cos_name', [
            'cos_type' => 'bs'
        ]);
        $this->pagedata['dealerList'] = $dealerList;
        
        $this->pagedata['store_prop'] = $store_prop;
        $this->singlepage('admin/system/store_history.html');
    }
    
    /**
     * 门店设置库存管控
     * @param $store_id
     * @date 2025-06-18 上午10:28
     */
    public function displayCtrlStore($store_id)
    {
        $storeMdl = app::get('o2o')->model('store');
        $store   = $storeMdl->db_dump($store_id, 'store_id,is_ctrl_store,store_bn,is_negative_store,is_o2o,accept_order_limit');
        $this->pagedata['store'] = $store;
        $this->display("admin/system/ctrl_store.html");
    }
    
    /**
     * 保存库存管控
     * @date 2025-06-18 上午10:28
     */
    public function setCtrlStore()
    {
        $this->begin();
        $store_id     = $_POST['store_id'];
        $is_ctrl_store  = $_POST['is_ctrl_store'];
        $is_negative_store = $_POST['is_negative_store'];
        $is_o2o = $_POST['is_o2o'] ?: '1';
        $accept_order_limit = isset($_POST['accept_order_limit']) ? trim($_POST['accept_order_limit']) : '0';
        if ($accept_order_limit === '' || !preg_match('/^\d+$/', $accept_order_limit)) {
            $this->end(false, '接单量必须为非负整数');
        }
        $accept_order_limit = (int) $accept_order_limit;
        
        $storeMdl = app::get('o2o')->model('store');
        $store = $storeMdl->db_dump($store_id);
        if (!$store) {
            $this->end(false, '门店不存在');
        }
        
        // 当管控库存选择"否"时，允许负库存也设置为"否" 校验是否有库存、冻结、在途
        if ($is_ctrl_store == '2') {
            $branchProductStore = app::get('ome')->model('branch_product')->db_dump(['branch_id' => $store['branch_id'], 'store|noequal' => '0']);
            if ($branchProductStore) {
                $this->end(false, '门店有库存不能更改设置！');
            }
    
            $branchProductStore = app::get('ome')->model('branch_product')->db_dump(['branch_id' => $store['branch_id'], 'store_freeze|noequal' => '0']);
            if ($branchProductStore) {
                $this->end(false, '门店有库存冻结不能更改设置！');
            }
    
            $branchProductStore = app::get('ome')->model('branch_product')->db_dump(['branch_id' => $store['branch_id'], 'arrive_store|noequal' => '0']);
            if ($branchProductStore) {
                $this->end(false, '门店有在途库存不能更改设置！');
            }
            $is_negative_store = '2';
        }
        
        // 更新门店设置
        $updateData = [
            'is_ctrl_store' => $is_ctrl_store,
            'is_negative_store' => $is_negative_store,
            'is_o2o' => $is_o2o,
            'accept_order_limit' => $accept_order_limit,
        ];
        
        $rs = $storeMdl->update($updateData, ['store_id' => $store_id]);
        if(!$rs) {
            $this->end(false, '保存失败！');
        }
        
        // 同步更新分支设置
        app::get('ome')->model('branch')->update([
            'is_ctrl_store' => $is_ctrl_store,
            'is_negative_store' => $is_negative_store
        ], ['branch_id' => $store['branch_id'], 'check_permission' => 'false']);

		// 当设置为不参加O2O时，如果该门店仓曾作为任意店铺的供应仓，则需要移除
		if ($is_o2o === '2' && $store['store_bn']) {
			$relation = app::get('ome')->getConf('shop.branch.relationship');
			if (is_array($relation) && $relation) {
				$ip = kernel::single("base_request")->get_remote_addr();
				$hasChange = false;
				foreach ($relation as $relShopBn => $shopBranchMap) {
					if (!is_array($shopBranchMap) || !$shopBranchMap) continue;
					$originalMap = $shopBranchMap;
					$changedForThisShop = false;
					foreach ($shopBranchMap as $relBranchId => $relBranchBn) {
						if ($relBranchBn === $store['store_bn']) {
							unset($relation[$relShopBn][$relBranchId]);
							$changedForThisShop = true;
							$hasChange = true;
						}
					}
					if ($changedForThisShop) {
						// 记录与 saveBranchRelation 一致的日志格式
						$shopRow = app::get('ome')->model('shop')->dump(['shop_bn' => $relShopBn], 'shop_id');
						$logShopId = $shopRow['shop_id'];
						$memo = '店铺' . $relShopBn . "供货仓关系原数据【" . json_encode($originalMap) . "】" . 'IP:' . $ip;
						$optLogModel = app::get('inventorydepth')->model('operation_log');
						$optLogModel->write_log('shop', $logShopId, 'supply_branches_set', $memo);
					}
				}
				if ($hasChange) {
					app::get('ome')->setConf('shop.branch.relationship', $relation);
				}
			}
		}
        
        // 记录操作日志
        $ctrlStoreName = ['1' => '是', '2' => '否'];
        $negativeStoreName = ['1' => '是', '2' => '否'];
        $o2oName = ['1' => '是', '2' => '否'];
        $msg = sprintf('【%s】管控库存，【%s】允许负库存，【%s】参加O2O，【%s】接单量', 
            $ctrlStoreName[$is_ctrl_store], 
            $negativeStoreName[$is_negative_store],
            $o2oName[$is_o2o],
            $accept_order_limit
        );
        app::get('ome')->model('operation_log')->write_log('store_upsert@o2o', $store['store_id'], $msg);
        
        $this->end(true, '保存成功');
    }
    
    /**
     * 批量设置门店库存管控
     * @date 2025-06-18 上午10:28
     */
    public function batchCtrlStore()
    {
        $storeMdl = app::get('o2o')->model('store');
        $arrStoreId = array();

        // finder 全选：POST isSelectedAll=_ALL_，需按当前列表筛选条件反查 store_id
        if (!empty($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_') {
            $baseFilter = array(
                'store_classify' => 'offline',
            );
            $filter = array_merge($baseFilter, $_POST);
            unset(
                $filter['isSelectedAll'],
                $filter['_finder'],
                $filter['finder_id'],
                $filter['finder_vid'],
                $filter['app'],
                $filter['ctl'],
                $filter['act'],
                $filter['_DTYPE_BOOL'],
                $filter['_DTYPE_NUMBER'],
                $filter['_DTYPE_TIME'],
                $filter['offset'],
                $filter['limit']
            );
            $storeMdl->filter_use_like = true;
            $selStore = $storeMdl->getList('store_id', $filter, 0, -1);
            foreach ($selStore as $val) {
                $arrStoreId[] = $val['store_id'];
            }
        } else {
            $arrStoreId = isset($_POST['store_id']) ? (array) $_POST['store_id'] : array();
        }

        $arrStoreId = array_values(array_filter(array_unique($arrStoreId)));
        if (empty($arrStoreId)) {
            echo '请选择要设置的门店';
            exit;
        }

        $this->pagedata['storeIds'] = $arrStoreId;
        $this->pagedata['selected_count'] = count($arrStoreId);

        $this->display("admin/system/batch_ctrl_store.html");
    }
    
    /**
     * 保存批量库存管控
     * @date 2025-06-18 上午10:28
     */
    public function saveBatchCtrlStore()
    {
        $store_ids = $_POST['store_ids'];
        $is_ctrl_store = $_POST['is_ctrl_store'];
        $is_negative_store = $_POST['is_negative_store'];
        $is_o2o = isset($_POST['is_o2o']) ? $_POST['is_o2o'] : '1';
        
        if (!$store_ids || !is_array($store_ids)) {
            $this->splash('error', null, '请选择要设置的门店');
            return;
        }
        
        $storeMdl = app::get('o2o')->model('store');
        $success_count = 0;
        $error_count = 0;
        $error_messages = [];
        $success_store_ids = [];
        
        foreach ($store_ids as $store_id) {
            $store = $storeMdl->dump($store_id);
            if (!$store) {
                $error_count++;
                $error_messages[] = "门店ID {$store_id}: 门店不存在";
                continue;
            }
            
            // 当管控库存选择"否"时，允许负库存也设置为"否" 校验是否有库存、冻结、在途
            if ($is_ctrl_store == '2') {
                $branchProductStore = app::get('ome')->model('branch_product')->dump(['branch_id' => $store['branch_id'], 'store|noequal' => '0']);
                if ($branchProductStore) {
                    $error_count++;
                    $error_messages[] = "门店 " . ($store['name'] ?: $store_id) . ": 有库存不能更改设置";
                    continue;
                }
        
                $branchProductStore = app::get('ome')->model('branch_product')->dump(['branch_id' => $store['branch_id'], 'store_freeze|noequal' => '0']);
                if ($branchProductStore) {
                    $error_count++;
                    $error_messages[] = "门店 " . ($store['name'] ?: $store_id) . ": 有库存冻结不能更改设置";
                    continue;
                }
        
                $branchProductStore = app::get('ome')->model('branch_product')->dump(['branch_id' => $store['branch_id'], 'arrive_store|noequal' => '0']);
                if ($branchProductStore) {
                    $error_count++;
                    $error_messages[] = "门店 " . ($store['name'] ?: $store_id) . ": 有在途库存不能更改设置";
                    continue;
                }
                $is_negative_store = '2';
            }
            
            // 更新门店设置
            $updateData = [
                'is_ctrl_store' => $is_ctrl_store,
                'is_negative_store' => $is_negative_store,
                'is_o2o' => $is_o2o,
            ];
            
            $rs = $storeMdl->update($updateData, ['store_id' => $store_id]);
            if(!$rs) {
                $error_count++;
                $error_messages[] = "门店 " . ($store['name'] ?: $store_id) . ": 保存失败";
                continue;
            }
            
            // 同步更新分支设置
            app::get('ome')->model('branch')->update([
                'is_ctrl_store' => $is_ctrl_store,
                'is_negative_store' => $is_negative_store
            ], ['branch_id' => $store['branch_id'], 'check_permission' => 'false']);

            // 当设置为不参加O2O时，如果该门店仓曾作为任意店铺的供应仓，则需要移除
            if ($is_o2o === '2' && isset($store['store_bn']) && $store['store_bn']) {
                $relation = app::get('ome')->getConf('shop.branch.relationship');
                if (is_array($relation) && $relation) {
                    $ip = kernel::single("base_request")->get_remote_addr();
                    $hasChange = false;
                    foreach ($relation as $relShopBn => $shopBranchMap) {
                        if (!is_array($shopBranchMap) || !$shopBranchMap) continue;
                        $originalMap = $shopBranchMap;
                        $changedForThisShop = false;
                        foreach ($shopBranchMap as $relBranchId => $relBranchBn) {
                            if ($relBranchBn === $store['store_bn']) {
                                unset($relation[$relShopBn][$relBranchId]);
                                $changedForThisShop = true;
                                $hasChange = true;
                            }
                        }
                        if ($changedForThisShop) {
                            // 记录与 saveBranchRelation 一致的日志格式
                            $shopRow = app::get('ome')->model('shop')->dump(['shop_bn' => $relShopBn], 'shop_id');
                            $logShopId = $shopRow['shop_id'];
                            $memo = '店铺' . $relShopBn . "供货仓关系原数据【" . json_encode($originalMap) . "】" . 'IP:' . $ip;
                            $optLogModel = app::get('inventorydepth')->model('operation_log');
                            $optLogModel->write_log('shop', $logShopId, 'supply_branches_set', $memo);
                        }
                    }
                    if ($hasChange) {
                        app::get('ome')->setConf('shop.branch.relationship', $relation);
                    }
                }
            }
            
            $success_count++;
            $success_store_ids[] = $store_id;
        }
        
        // 记录操作日志 - 每个门店单独一条，避免 obj_id(varchar50) 超长
        $ctrlStoreName = ['1' => '是', '2' => '否'];
        $negativeStoreName = ['1' => '是', '2' => '否'];
        $o2oName = ['1' => '是', '2' => '否'];
        $msg = sprintf('批量设置: 管控库存【%s】，允许负库存【%s】，参加O2O【%s】', 
            $ctrlStoreName[$is_ctrl_store], 
            $negativeStoreName[$is_negative_store],
            $o2oName[$is_o2o]
        );
        if (!empty($success_store_ids)) {
            app::get('ome')->model('operation_log')->batch_write_log(
                'store_upsert@o2o',
                array('store_id' => $success_store_ids),
                $msg
            );
        }
        
        if ($success_count > 0 && $error_count == 0) {
            $this->splash('success', null, "成功设置 {$success_count} 个门店");
        } else if ($success_count > 0 && $error_count > 0) {
            $message = "成功设置 {$success_count} 个门店，失败 {$error_count} 个";
            if (!empty($error_messages)) {
                $message .= "；错误详情：" . implode('；', array_slice($error_messages, 0, 3));
            }
            $this->splash('error', null, $message);
        } else {
            $message = "设置失败";
            if (!empty($error_messages)) {
                $message .= "：" . implode('；', array_slice($error_messages, 0, 3));
            }
            $this->splash('error', null, $message);
        }
    }

    /**
     * 门店覆盖区域设置
     * @param $store_id
     */
    public function storeRegion($store_id)
    {
        $storeMdl = app::get('o2o')->model('store');
        $store = $storeMdl->dump($store_id, 'store_id,store_bn,name,branch_id');
        
        if (!$store) {
            $this->splash('error', null, '门店不存在');
            return;
        }
        
        $this->pagedata['store'] = $store;
        
        // 检查是否已有覆盖区域设置
        $warehouseMdl = app::get('logisticsmanager')->model('warehouse');
        $existingWarehouse = kernel::single('o2o_store_coverage')->findWarehouseForStore($store);
        if (!empty($existingWarehouse['id'])) {
            $existingWarehouse = $warehouseMdl->dump(array('id' => $existingWarehouse['id']), '*');
        } else {
            $existingWarehouse = null;
        }
        
        $warehouse = array('region_ids' => '', 'region_names' => '');
        if ($existingWarehouse) {
            $warehouse['id'] = $existingWarehouse['id'];
            $warehouse['warehouse_name'] = $existingWarehouse['warehouse_name'];
            $regionIdsRaw = $existingWarehouse['region_ids'];
            // 兼容按路径分号分隔的存储格式，提取每条路径最后一个数字ID作为选中项
            $pathList = array_filter(explode(';', $regionIdsRaw));
            $leafIds = array();
            foreach ($pathList as $path) {
                $parts = array_values(array_filter(explode(',', $path), function ($v) { return is_numeric($v); }));
                if (!empty($parts)) {
                    $leafIds[] = end($parts);
                }
            }
            // 回退兼容：如果没有解析出叶子ID（例如仅选了根“中国”），则展开为所有省份
            if (empty($leafIds) && !empty($regionIdsRaw)) {
                $provinces = app::get('eccommon')->model('regions')->getList('region_id', array('region_grade' => 1, 'source' => 'systems'));
                foreach ($provinces as $prov) {
                    $leafIds[] = (string)$prov['region_id'];
                }
            }
            // 若覆盖范围包含所有省份，则回显为选择“中国”
            $regionsMdl = app::get('eccommon')->model('regions');
            $allProvinces = $regionsMdl->getList('region_id', array('region_grade' => 1, 'source' => 'systems'));
            $allProvinceIds = array_map(function($r){ return (string)$r['region_id']; }, $allProvinces);
            sort($allProvinceIds);
            $sortedLeafIds = $leafIds;
            sort($sortedLeafIds);

            if (!empty($sortedLeafIds) && $sortedLeafIds === $allProvinceIds) {
                $warehouse['region_ids'] = json_encode(array('CN'));
                $warehouse['region_names'] = '中国';
            } else {
                $warehouse['region_ids'] = json_encode($leafIds);
                // 根据提取到的叶子ID查询显示名称
                if (!empty($leafIds)) {
                    $regionList = $regionsMdl->getList('local_name', array('region_id' => $leafIds));
                    $regionNames = array();
                    foreach ($regionList as $region) {
                        $regionNames[] = $region['local_name'];
                    }
                    $warehouse['region_names'] = implode(',', $regionNames);
                }
            }
        }
        
        $this->pagedata['warehouse'] = $warehouse;
        
        $this->display('admin/store/store_region.html');
    }

    /**
     * 保存门店覆盖区域
     */
    public function saveRange()
    {
        $this->begin();

        if (empty($_POST['branch_id'])) {
            $this->end(false, '请选择仓库');
        }
        if (empty($_POST['p_region_id'])) {
            $this->end(false, '请选择覆盖区域');
        }

        $pRegionIdRaw = json_decode($_POST['p_region_id'], true);
        // 规范化区域ID路径，移除非数字节点（例如根节点“中国”的 CN）
        $normalizedPRegionId = array();
        if (is_array($pRegionIdRaw)) {
            foreach ($pRegionIdRaw as $path) {
                $ids = explode(',', $path);
                $ids = array_values(array_filter($ids, function ($v) { return is_numeric($v); }));
                if (!empty($ids)) {
                    $normalizedPRegionId[] = implode(',', $ids);
                }
            }
        }

        // 若仅选择“中国”（根节点），展开为所有省份ID
        if (empty($normalizedPRegionId) && !empty($pRegionIdRaw)) {
            $provinces = app::get('eccommon')->model('regions')->getList('region_id', array('region_grade' => 1, 'source' => 'systems'));
            foreach ($provinces as $prov) {
                $normalizedPRegionId[] = (string)$prov['region_id'];
            }
        }

        // 判定是否全国：前端若勾选中国会传 ["CN"]. 若未包含 CN，但已覆盖所有省份也视为全国
        $isNationwide = is_array($pRegionIdRaw) && in_array('CN', $pRegionIdRaw, true);

        // 若未显式传 CN，进一步判断是否覆盖所有省份
        if (!$isNationwide) {
            $provincesAll = app::get('eccommon')->model('regions')->getList('region_id', array('region_grade' => 1, 'source' => 'systems'));
            $allIds = array_map(function($r){ return (string)$r['region_id']; }, $provincesAll);
            sort($allIds);
            $selIds = array_values(array_unique(explode(',', implode(',', $normalizedPRegionId))));
            $selIds = array_filter($selIds, function($v){ return $v !== ''; });
            sort($selIds);
            if (!empty($selIds) && $selIds === $allIds) {
                $isNationwide = true;
            }
        }

        $pRegionId = $normalizedPRegionId;
        $operator     = kernel::single('desktop_user')->get_name();
        $warehouseObj = app::get('logisticsmanager')->model('warehouse');
        $branchObj    = app::get('ome')->model('branch');

        // 门店类型固定为2
        $b_type = isset($_POST['b_type']) ? $_POST['b_type'] : 2;

        $branch = $branchObj->dump(array('branch_id' => $_POST['branch_id'], 'check_permission' => 'false'), 'branch_bn');
        if (!$branch) {
            $this->end(false, '仓库不存在');
        }

        $id = $_POST['id'];

        $addressIds = explode(',', implode(',', $pRegionId));
        $oneLevelLocalAddressName = app::get('eccommon')->model('regions')->getList('local_name,region_grade', array('region_id' => $addressIds));
        $oneAddressName = '';
        $addressName    = '';
        if ($oneLevelLocalAddressName) {
            foreach ($oneLevelLocalAddressName as $value) {
                if ($value['region_grade'] == 1) {
                    $oneAddressName .= $value['local_name'] . ',';
                }
            }
            if (substr($oneAddressName, -1, 1) == ',') {
                $oneAddressName = substr($oneAddressName, 0, (strlen($oneAddressName) - 1));
            }
            $addressName = implode(',', array_column($oneLevelLocalAddressName, 'local_name'));
        }
        // 全国时，region_names 统一保存为“中国”
        if ($isNationwide) {
            $addressName = '中国';
        }

        $storeBn = trim((string) ($_POST['store_bn'] ?? ''));
        if ($storeBn === '') {
            $storeBn = $branch['branch_bn'];
        }

        if ($id) {
            $update_data = array(
                'branch_id'              => $_POST['branch_id'],
                'warehouse_name'         => 'STORE_' . $storeBn,
                'region_ids'             => implode(';', $pRegionId),
                'region_names'           => $addressName,
                'branch_bn'              => $storeBn,
                'one_level_region_names' => $oneAddressName,
                'b_type'                 => $b_type,
            );
            $warehouseObj->update($update_data, array('id' => $id));
        } else {
            $warehouse = $warehouseObj->dump(array('branch_id' => $_POST['branch_id'], 'b_type' => $b_type), 'id');
            if ($warehouse) {
                $this->end(false, '系统仓库ID重复，请重新填写');
            }

            // 允许覆盖范围重合，不再校验重合

            $autoWarehouseName = 'STORE_' . $storeBn;
            $warehouse = $warehouseObj->dump(array('warehouse_name' => $autoWarehouseName, 'b_type' => $b_type), 'id');
            if ($warehouse) {
                $this->end(false, '区域仓名称:' . $autoWarehouseName . '已存在');
            }

            $insert_data = array(
                'branch_id'              => $_POST['branch_id'],
                'branch_bn'              => $storeBn,
                'warehouse_name'         => $autoWarehouseName,
                'region_ids'             => implode(';', $pRegionId),
                'region_names'           => $addressName,
                'create_time'            => time(),
                'warn_num'               => isset($_POST['warn_num']) ? $_POST['warn_num'] : 5,
                'operator'               => $operator,
                'one_level_region_names' => $oneAddressName,
                'b_type'                 => $b_type,
            );

            $warehouseObj->insert($insert_data);
        }

        $this->end(true, '区域仓设置成功');
    }
}
