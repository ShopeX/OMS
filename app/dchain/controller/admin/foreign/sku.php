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
 * @Author: xueding@shopex.cn
 * @Datetime: 2022/4/25
 * @Describe: 外部优仓商品映射
 */
class dchain_ctl_admin_foreign_sku extends desktop_controller
{
    public $name = '外部优仓商品映射';
    public $workground = "channel_center";
    
    public function _views()
    {
        $dchain = app::get('channel')->model('channel')->getList('channel_id,channel_name',
            array('channel_type' => 'dchain', 'node_id|noequal' => ''));
        
        $view = array();
        foreach ($dchain as $key => $value) {
            $filter                     = array('dchain_id' => $value['channel_id']);
            $view[$value['channel_id']] = array(
                'label'    => $value['channel_name'],
                'filter'   => $filter,
                'optional' => false,
                'addon'    => 'showtab',
                'href'     => 'index.php?app=dchain&ctl=admin_foreign_sku&act=index&view=' . $value['channel_id'] . '&dchain_id=' . $value['channel_id']
            );
        }
        
        return $view;
    }
    
    public function index()
    {
        $cur_dchain_id   = $_GET['dchain_id'];
        $finder_aliasname = 'foreign_sku';
        
        if (!$cur_dchain_id) {
            if ($tabview = app::get('dchain')->getConf('tabview.' . $_GET['app'] . $_GET['ctl'] . $_GET['act'] . 'dchain_mdl_foreign_sku.' . $finder_aliasname . '.' . $this->user->user_id)) {
                list($cur_dchain_id) = explode(',', $tabview);
            } else {
                $dchain = app::get('channel')->model('channel')->getList('channel_id,node_id',
                    array('channel_type' => 'dchain', 'node_id|noequal' => ''), 0, 1);
                if ($dchain) {
                    $cur_dchain_id = $dchain[0]['channel_id'];
                }
            }
        }
        if (!$dchain) {
            $dchain = app::get('channel')->model('channel')->getList('channel_id,node_id',
                array('channel_type' => 'dchain', 'channel_id' => $cur_dchain_id), 0, 1);
        }
        $omeShopMdl = app::get('ome')->model('shop');
        $shopDetail = $omeShopMdl->db_dump(array('node_id' => $dchain[0]['node_id']));
        
        $finder_id = substr(md5($_SERVER['QUERY_STRING']), 5, 6);
        
        $url = 'index.php?app=desktop&act=alertpages&goto=' . urlencode('index.php?app=dchain&ctl=admin_foreign_sku&act=findProduct&shop_id=' . $shopDetail['shop_id'] . '&dchain_id=' . $cur_dchain_id);
        $systemUrl = 'index.php?app=desktop&act=alertpages&goto=' . urlencode('index.php?app=dchain&ctl=admin_foreign_sku&act=findSystemProduct');
    
        $actions = array(
            array(
                'label'  => '批量同步',
                'submit' => 'index.php?app=dchain&ctl=admin_foreign_sku&act=sync_page&dchain_id=' . $cur_dchain_id,
                'target' => 'dialog::{width:700,height:160,title:\'批量同步\'}'
            ),
            array('label'  => '商货关联关系创建',
                  'submit' => 'index.php?app=dchain&ctl=admin_foreign_sku&act=sync_page&dchain_id=' . $cur_dchain_id.'&method=sync_mapping',
                  'target' => 'dialog::{width:700,height:160,title:\'商货关联关系创建\'}'
            ),
            array(
                'label'   => '平台货品分配',
                'onclick' => <<<JS
javascript:Ex_Loader('modedialog',function() {new finderDialog('{$url}',{params:{url:'index.php?app=dchain&ctl=admin_foreign_sku&act=handwork_allot',name:'id[]',postdata:'dchain_id=$cur_dchain_id'},onCallback:function(rs){MessageBox.success('分配成功');window.finderGroup['{$finder_id}'].refresh();}  });});
JS
            ),
            array(
                'label'   => '系统货品分配',
                'onclick' => <<<JS
javascript:Ex_Loader('modedialog',function() {new finderDialog('{$systemUrl}',{params:{url:'index.php?app=dchain&ctl=admin_foreign_sku&act=addSystemProduct',name:'id[]',postdata:'dchain_id=$cur_dchain_id'},onCallback:function(rs){MessageBox.success('分配成功');window.finderGroup['{$finder_id}'].refresh();}  });});
JS
            ),
        );
        
        $params = array(
            'title'                  => '优仓商品分配',
            'base_filter'            => array('dchain_id' => $cur_dchain_id, 'goods_status' => 'false'),
            'actions'                => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'finder_aliasname'       => $finder_aliasname,
        );
        
        $this->finder('dchain_mdl_foreign_sku', $params);
    }
    
    public function findProduct()
    {
        $filter = array();
        if (isset($_GET['shop_id'])) {
            $filter['shop_id'] = $_GET['shop_id'];
        }

        $filter['mapping'] = '1';

        $params = array(
            'title'                  => '平台商品列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_setcol'     => false,
            'use_buildin_refresh'    => false,
            'use_buildin_filter'     => true,
            'finder_cols'            => 'shop_sku_id,shop_iid,simple,shop_product_bn,shop_title,shop_barcode,mapping,bind,sync_map',
            'base_filter'            => $filter,
        );
        
        $this->finder('inventorydepth_mdl_shop_skus', $params);
    }
    
    public function handwork_allot()
    {
        $id        = $_POST['id'];
        $dchain_id = $_POST['dchain_id'];
        
        if (!$id) {
            $this->splash('error', null, '请先选择商品');
        }
        if (!$dchain_id) {
            $this->splash('error', null, '请先选择优仓');
        }
        $data = array();
        if ($id) {
            $foreignSkuMdl = app::get('dchain')->model('foreign_sku');
            $products = app::get('inventorydepth')->model('shop_skus')->getList('*', array('id' => $id));
            $shopIidList   = array_column($products, 'shop_iid');
            $foreignList   = $foreignSkuMdl->getList('shop_sku_id,shop_product_id', array('shop_product_id' => $shopIidList));
            $shopSkuId     = array_column($foreignList, 'shop_sku_id');
            $shopProductId = array_column($foreignList, 'shop_product_id');
            foreach ($products as $key => $value) {
                if (in_array($value['shop_iid'], $shopProductId)) {
                    if ($value['shop_sku_id']) {
                        if (in_array($value['shop_sku_id'], $shopSkuId)) {
                            continue;
                        }
                    }else{
                        continue;
                    }
                }
                $data[] = array(
                    'inner_sku'        => $value['shop_product_bn'],
                    'inner_product_id' => $value['product_id'],
                    'inner_type'       => $value['bind'] == '1' ? '1' : '0',
                    'shop_sku_id'      => $value['shop_sku_id'],
                    'shop_product_id'  => $value['shop_iid'],
                    'dchain_id'        => $dchain_id,
                );
            }
        }

        if ($data) {
            $sql = ome_func::get_insert_sql(app::get('dchain')->model('foreign_sku'), $data);
            kernel::database()->exec($sql);
        }
        
        $this->splash('success');
    }
    
    public function sync_page()
    {
        $dchain_id = $_GET['dchain_id'];
        
        $this->pagedata['input'] = json_encode($_REQUEST);
        
        $filter = array_merge($_REQUEST, array('dchain_id' => $dchain_id));
        
        $this->pagedata['total'] = app::get('dchain')->model('foreign_sku')->count($filter);
        
        
        $this->display('foreign/sku/sync_page.html');
    }
    
    public function do_sync($pageno = 1)
    {
        $limit = 30;
        
        $filter = json_decode($_POST['filter'], true);
        
        $list = app::get('dchain')->model('foreign_sku')->getList(
            'inner_sku as shop_product_bn,shop_sku_id,shop_product_id as shop_iid,outer_bar_code as shop_barcode,inner_type,inner_product_id as product_id',
            array('dchain_id' => $filter['dchain_id'], 'id' => $filter['id']),
            ($pageno - 1) * $limit,
            $limit
        );
        
        $omeShopMdl   = app::get('ome')->model('shop');
        $channelMdl   = app::get('channel')->model('channel');
        $dchainDetail = $channelMdl->db_dump(array('channel_id' => $filter['dchain_id']));
        $shopDetail   = $omeShopMdl->db_dump(array('node_id' => $dchainDetail['node_id']));
        $bnList       = array_column($list, 'shop_product_bn');
        if (isset($filter['method']) && $filter['method'] == 'sync_mapping') {
            foreach ($list as $k => $v) {
                //优仓商货关联数据
                $dchainMapping[] = [
                    'item_id'                       => $v['shop_iid'],
                    'sku_id'                        => $v['shop_sku_id'],
                    'sc_item_code'                  => $v['shop_product_bn'],
                    'need_sync_sc_item_inv_to_item' => 1,
                ];
            }
            $param['items'] = $dchainMapping;
            $result = kernel::single('erpapi_router_request')->set('dchain', $shopDetail['shop_id'])->product_item_mapping($param);
            if ($result['rsp'] == 'succ' && !empty($result['data'])) {
                kernel::single('dchain_branch_product')->updateMappingStatus($result['data'], $dchainDetail);
            }
        }else{
            kernel::single('dchain_event_trigger_dchain_product')->addProduct($list, $bnList, $shopDetail);
        }
        
        $data = array('count' => count($list));
        $this->splash('success', null, null, 'redirect', $data);
    }
    
    /**
     * 普通商品列表
     * @Author: xueding
     * @Vsersion: 2022/8/2 上午11:03
     */
    public function findSystemProduct()
    {
        $params = array(
            'title'                  =>'商品列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
        );
        
        $this->finder('material_mdl_sales_material', $params);
    }
    
    /**
     * 添加系统商品到优仓列表
     * @Author: xueding
     * @Vsersion: 2022/8/2 上午11:04
     */
    public function addSystemProduct()
    {
        $id        = $_POST['id'];
        $dchain_id = $_POST['dchain_id'];
        
        if (!$id) {
            $this->splash('error', null, '请先选择商品');
        }
        if (!$dchain_id) {
            $this->splash('error', null, '请先选择优仓');
        }
        
        $data = array();
        if ($id) {
            $foreignSkuMdl = app::get('dchain')->model('foreign_sku');
            $products      = app::get('material')->model('sales_material')->getList('*', array('sm_id' => $id));
            $bnList   = array_column($products, 'sales_material_bn');
            $foreignList   = $foreignSkuMdl->getList('inner_sku', array('inner_sku' => $bnList,'dchain_id'=>$dchain_id));
            $productBn     = array_column($foreignList, 'inner_sku');
            foreach ($products as $key => $value) {
                if (in_array($value['bn'], $productBn)) {
                    continue;
                }
                $data[] = array(
                    'inner_sku'        => $value['sales_material_bn'],
                    'inner_product_id' => $value['sm_id'],
                    'inner_type'       => '0',
                    'dchain_id'        => $dchain_id,
                );
            }
        }
        
        if ($data) {
            $sql = ome_func::get_insert_sql(app::get('dchain')->model('foreign_sku'), $data);
            kernel::database()->exec($sql);
        }
        
        $this->splash('success');
    }
}
