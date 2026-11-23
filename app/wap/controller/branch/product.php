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
 * 门店供货管理
 *
 * @author wangbiao@shopex.cn
 * @version 0.1
 */
class wap_ctl_branch_product extends wap_controller
{
    var $delivery_link    = array();
    var $branch_id        = array();
    
    function __construct($app)
    {
        parent::__construct($app);
        
        $this->delivery_link['index']      = app::get('wap')->router()->gen_url(array('ctl'=>'store','act'=>'index'), true);
        $this->delivery_link['mine']       = app::get('wap')->router()->gen_url(array('ctl'=>'user','act'=>'mine'), true);
        
        $this->pagedata['delivery_link']   = $this->delivery_link;
        
        //管辖的仓库
        $is_super    = kernel::single('desktop_user')->is_super();
        if(!$is_super)
        {
            $branchObj     = kernel::single('o2o_store_branch');
            $branch_ids    = $branchObj->getO2OBranchByUser(true);
            if(empty($branch_ids))
            {
                $this->pagedata['link_url']     = $this->delivery_link['index'];
                $this->pagedata['error_msg']    = '操作员没有管辖的仓库';
                echo $this->fetch('auth_error.html');
                exit;
            }
            
            $this->branch_id    = $branch_ids[0];
        }
    }
    
    /**
     * is_bind 1:已绑定   0:未绑定
     */
    function _views_supply($curr_view)
    {
        $storeObj         = app::get('o2o')->model('store');
        
        $base_filter    = array('branch_id'=>$this->branch_id);
        
        $page              = intval($_POST['page']) ? intval($_POST['page']) : 0;
        $limit             = 10;//默认显示10条
        $offset            = $limit * $page;
        
        $sub_menu = array(
                'all' => array('label'=>app::get('base')->_('全部'), 'href'=>app::get('wap')->router()->gen_url(array('ctl'=>'branch_product','act'=>'index'), true)),
                'unrelation' => array('label'=>app::get('base')->_('未关联'), 'href'=>app::get('wap')->router()->gen_url(array('ctl'=>'branch_product','act'=>'unrelation'), true)),
                'relation' => array('label'=>app::get('base')->_('已关联'), 'href'=>app::get('wap')->router()->gen_url(array('ctl'=>'branch_product','act'=>'relation'), true)),
        );
        
        foreach($sub_menu as $k=>$v)
        {
            //Ajax加载下一页数据,只处理本页
            if($_POST['flag'] == 'ajax' && $curr_view != $k)
            {
                continue;
            }
            
            $sub_menu[$k]['offset']    = $offset;
            $sub_menu[$k]['limit']     = $limit;
            
            if($k == $curr_view){
                $sub_menu[$k]['curr_view'] = true;
            }else{
                $sub_menu[$k]['curr_view'] = false;
            }
        }
        
        return $sub_menu;
    }
    
    /**
     * 供货管理
     */
    function index()
    {
        $this->store_type    = 'all';
        
        $sub_menu    = $this->_views_supply($this->store_type);
        $offset   = $sub_menu[$this->store_type]['offset'];
        $limit    = $sub_menu[$this->store_type]['limit'];
        
        $branchProObj    = app::get('o2o')->model('branch_product');
        
        //filter
        $where    = 'WHERE a.visibled=1';
        
        if($_POST['sel_type'] && $_POST['sel_keywords'])
        {
            $kw = trim($_POST['sel_keywords']);
            switch ($_POST['sel_type'])
            {
                case 'item_bn':
                    $where    .= " AND a.material_bn=".$branchProObj->db->quote($kw);
                    break;
                case 'item_name':
                    $where    .= " AND a.material_name like ".$branchProObj->db->quote('%'.$kw.'%');
                    break;
            }
        }
        
        //count
        $c_sql    = "SELECT count(*) AS num FROM sdb_material_basic_material as a ". $where;
        $count    = $branchProObj->db->selectrow($c_sql);
        $count    = $count['num'];
        
        $pageSize    = ceil($count / $limit);
        
        //门店供货列表
        $sql    = "SELECT a.bm_id, a.material_bn, a.material_name FROM sdb_material_basic_material as a ". $where ." 
                   ORDER BY a.bm_id DESC LIMIT ". $offset .", ". $limit;
        $dataList    = $branchProObj->db->select($sql);
        if($dataList)
        {
            foreach ($dataList as $key => $val)
            {
                //供货记录
                $row    = $branchProObj->dump(array('branch_id'=>$this->branch_id, 'bm_id'=>$val['bm_id']), 'id');
                if($row)
                {
                    $dataList[$key]['is_relate']    = true;
                }
                else 
                {
                    $dataList[$key]['is_relate']    = false;
                }
            }
        }
        
        $this->pagedata['dataList']    = $dataList;
        $this->pagedata['sub_menu']    = $sub_menu;
        $this->pagedata['pageSize']    = $pageSize;
        
        $this->pagedata['link_url']    = $sub_menu[$this->store_type]['href'];
        
        if($offset > 0)
        {
            //Ajax加载更多
            $this->display('store/supply_more.html');
        }
        else 
        {
            $this->display('store/supply.html');
        }
    }
    
    //未关联
    function unrelation()
    {
        $this->store_type    = 'unrelation';
        
        $sub_menu    = $this->_views_supply($this->store_type);
        $offset   = $sub_menu[$this->store_type]['offset'];
        $limit    = $sub_menu[$this->store_type]['limit'];
        
        $branchProObj    = app::get('o2o')->model('branch_product');
        
        //filter
        $where    = 'WHERE a.visibled=1 AND b.id is null';
        
        if($_POST['sel_type'] && $_POST['sel_keywords'])
        {
            $kw = trim($_POST['sel_keywords']);
            switch ($_POST['sel_type'])
            {
                case 'item_bn':
                    $where    .= " AND a.material_bn=".$branchProObj->db->quote($kw);
                    break;
                case 'item_name':
                    $where    .= " AND a.material_name like ".$branchProObj->db->quote('%'.$kw.'%');
                    break;
            }
        }
        
        //count
        $c_sql    = "SELECT count(*) AS num FROM sdb_material_basic_material as a 
                     LEFT JOIN sdb_o2o_branch_product AS b ON a.bm_id=b.bm_id ". $where;
        $count    = $branchProObj->db->selectrow($c_sql);
        $count    = $count['num'];
        
        $pageSize    = ceil($count / $limit);
        
        //门店供货列表
        $sql    = "SELECT a.bm_id, a.material_bn, a.material_name FROM sdb_material_basic_material as a 
                   LEFT JOIN sdb_o2o_branch_product AS b ON a.bm_id=b.bm_id ". $where ."
                   ORDER BY a.bm_id DESC LIMIT ". $offset .", ". $limit;
        $dataList    = $branchProObj->db->select($sql);
        
        $this->pagedata['dataList']    = $dataList;
        $this->pagedata['sub_menu']    = $sub_menu;
        $this->pagedata['pageSize']    = $pageSize;
        
        $this->pagedata['active']      = $this->store_type;
        $this->pagedata['link_url']    = $sub_menu[$this->store_type]['href'];
        $this->pagedata['relate_url']  = app::get('wap')->router()->gen_url(array('ctl'=>'branch_product','act'=>'saveRelate'), true);
        
        if($offset > 0)
        {
            //Ajax加载更多
            $this->display('store/supply_more.html');
        }
        else 
        {
            $this->display('store/supply_unrelation.html');
        }
    }
    
    //已关联
    function relation()
    {
        $this->store_type    = 'relation';
        
        $sub_menu    = $this->_views_supply($this->store_type);
        $offset   = $sub_menu[$this->store_type]['offset'];
        $limit    = $sub_menu[$this->store_type]['limit'];
        
        $branchProObj    = app::get('o2o')->model('branch_product');
        
        //filter
        $where    = "WHERE a.branch_id=". $this->branch_id;
        
        if($_POST['sel_type'] && $_POST['sel_keywords'])
        {
            $kw = trim($_POST['sel_keywords']);
            switch ($_POST['sel_type'])
            {
                case 'item_bn':
                    $where    .= " AND b.material_bn=".$branchProObj->db->quote($kw);
                    break;
                case 'item_name':
                    $where    .= " AND b.material_name like ".$branchProObj->db->quote('%'.$kw.'%');
                    break;
            }
        }
        
        //count
        $c_sql    = "SELECT count(*) AS num FROM sdb_o2o_branch_product as a
                     LEFT JOIN sdb_material_basic_material AS b ON a.bm_id=b.bm_id ". $where;
        $count    = $branchProObj->db->selectrow($c_sql);
        $count    = $count['num'];
        
        $pageSize    = ceil($count / $limit);
        
        //门店供货列表
        $sql    = "SELECT a.id, a.bm_id, a.status, b.material_bn, b.material_name FROM sdb_o2o_branch_product as a 
                   LEFT JOIN sdb_material_basic_material AS b ON a.bm_id=b.bm_id ". $where ." 
                   ORDER BY a.id DESC LIMIT ". $offset .", ". $limit;
        $dataList    = $branchProObj->db->select($sql);
        
        $this->pagedata['dataList']    = $dataList;
        $this->pagedata['sub_menu']    = $sub_menu;
        $this->pagedata['pageSize']    = $pageSize;
        
        $this->pagedata['active']      = $this->store_type;
        $this->pagedata['link_url']    = $sub_menu[$this->store_type]['href'];
        $this->pagedata['config_url']  = app::get('wap')->router()->gen_url(array('ctl'=>'branch_product','act'=>'setConfig'), true);
        
        if($offset > 0)
        {
            //Ajax加载更多
            $this->display('store/supply_more.html');
        }
        else 
        {
            $this->display('store/supply_relation.html');
        }
    }
    
    /**
     * 关联商品
     */
    function saveRelate()
    {
        $redirect    = app::get('wap')->router()->gen_url(array('ctl'=>'branch_product','act'=>'unrelation'), true);
        $bm_ids      = array_filter(array_map('intval', (array)$_POST['bm_id']));
        if(empty($bm_ids))
        {
            echo json_encode(array('error'=>true, 'message'=>'请选择货品', 'redirect'=>null));
            exit;
        }
        
        $branchProObj    = app::get('o2o')->model('branch_product');
        
        //先判断是否存在供货关系
        $rs_b_p = $branchProObj->getList('bm_id', array('branch_id'=>$this->branch_id, 'bm_id'=>$bm_ids));
        if(!empty($rs_b_p)){
            $exist_bm_ids = array();
            foreach ($rs_b_p as $var_b_p){
                $exist_bm_ids[] = $var_b_p['bm_id'];
            }
        }
        
        if(!empty($exist_bm_ids)){
            //移除bm_ids中存在供货关系的bm_id
            foreach ($bm_ids as $bk => &$var_b_i){
                if(in_array($var_b_i,$exist_bm_ids)){
                    unset($bm_ids[$bk]);
                }
            }
            unset($var_b_i);
        }
        
        if(empty($bm_ids)){
            echo json_encode(array('error'=>true, 'message'=>'没有可供货关联的商品', 'redirect'=>null));
            exit;
        }
        
        //插入数据
        $branch_id    = intval($this->branch_id);
        foreach ($bm_ids as $var_bm_id)
        {
            $var_bm_id = intval($var_bm_id);
            $insert_sql    = "insert into sdb_o2o_branch_product (`branch_id`,`bm_id`) values (". $branch_id .",". $var_bm_id .")";
            $branchProObj->db->exec($insert_sql);
        }
        
        //[批量创建]淘宝门店关联宝贝
        $storeItemLib    = kernel::single('tbo2o_store_items');
        $result          = $storeItemLib->batchCreate($bm_ids, $this->branch_id, $errormsg);
        
        echo json_encode(array('success'=>true, 'message'=>'货品关联成功', 'redirect'=>$redirect));
        exit;
    }
    
    /**
     * 门店供货配置
     */
    function setConfig()
    {
        $back_url    = app::get('wap')->router()->gen_url(array('ctl'=>'branch_product','act'=>'relation'), true);
        
        $branchProObj    = app::get('o2o')->model('branch_product');
        $proStoreObj     = app::get('ome')->model('branch_product');
        $storeObj = app::get('o2o')->model('store');
        
        if($_POST)
        {
            $id    = intval($_POST['id']);
            
           
            
           
            //门店信息
            $storeInfo = $storeObj->dump(array('branch_id'=>$item['branch_id']), 'store_id,store_bn,store_mode');
            
            //保存门店物料库存
            $store_num     = intval($_POST['store_num']);
            $store_item    = $proStoreObj->dump(array('branch_id'=>$item['branch_id'], 'product_id'=>$item['bm_id']), 'id');
            if($store_item){
                //更新
                $updateData = array('store'=>$store_num);
                
                //门店类型
                if($storeInfo['store_mode']){
                    $updateData['store_bn'] = $storeInfo['store_bn'];
                    $updateData['store_mode'] = $storeInfo['store_mode'];
                }
                
                $result       = $proStoreObj->update($updateData, array('id'=>$store_item['id']));
            }else {
                //新增
                $save_data    = array('branch_id'=>$item['branch_id'], 'product_id'=>$item['bm_id'], 'store'=>$store_num, 'last_modified'=>time());
                
                //门店类型
                if($storeInfo['store_mode']){
                    $save_data['store_bn'] = $storeInfo['store_bn'];
                    $save_data['store_mode'] = $storeInfo['store_mode'];
                }
                
                $result       = $proStoreObj->insert($save_data);
            }
            unset($item, $store_item,$save_data);
            
            if(!$result)
            {
                echo json_encode(array('error'=>true, 'message'=>'门店库存信息保存失败', 'redirect'=>null));
                exit;
            }
            
            echo json_encode(array('success'=>true, 'message'=>'保存成功', 'redirect'=>$back_url));
            exit;
        }
        
        $id    = intval($_GET['id']);
        if(empty($id))
        {
            $this->pagedata['link_url']     = $back_url;
            $this->pagedata['error_msg']    = '无效的操作';
            echo $this->fetch('error.html');
            exit;
        }
        
       
        
        //基础物料信息
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $materialInfo    = $basicMaterialObj->dump(array('bm_id'=>$item['bm_id']), 'bm_id, material_name, material_bn');
        
        //库存信息
        $store          = $proStoreObj->dump(array('product_id'=>$item['bm_id'], 'branch_id'=>$this->branch_id), 'store');
        $store['store']    = intval($store['store']);//防止空数组array_merge不能合并
        unset($store['id']);
        
        $item    = array_merge($item, $materialInfo, $store);
        $this->pagedata['item']        = $item;
        $this->pagedata['link_url']    = app::get('wap')->router()->gen_url(array('ctl'=>'branch_product','act'=>'setConfig'), true);
        
        $this->display('store/supply_config.html');
    }
}
