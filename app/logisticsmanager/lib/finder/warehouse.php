<?php
// COLUMN_IN_HEAD constant used in finder properties
if (!defined('COLUMN_IN_HEAD')) {
    define('COLUMN_IN_HEAD', 'HEAD');
}

class logisticsmanager_finder_warehouse
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = "id";
    public $column_control = '操作';
    public $column_control_width = '60';
    public $column_control_order = COLUMN_IN_HEAD;
    
    /**
     * column_control
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_control($row)
    {
        $id = $row[$this->col_prefix . 'id'];
        
        $button = "<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=logisticsmanager&ctl=admin_warehouse&act=edit&p[0]={$id}&finder_id={$_GET['_finder']['finder_id']}',{width:800,height:600,title:'区域仓编辑'}); \">编辑</a>";
        
        return $button;
    }
    
    
    public $detail_shop = '前端店铺同步记录';
    
    /**
     * detail_basic
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function detail_basic($id)
    {
        $warehouseshopObj = app::get('logisticsmanager')->model('warehouse_shop');
        $warehouselist    = $warehouseshopObj->getlist('*', array('warehouse_id' => $id));
        
        $shopObj = app::get('ome')->model('shop');
        $render  = app::get('logisticsmanager')->render();
        
        foreach ($warehouselist as $k => $v) {
            $shop_detail                    = $shopObj->dump(array('shop_id' => $v['shop_id']), 'name');
            $warehouselist[$k]['shop_name'] = $shop_detail['name'];
        }
        $render->pagedata['warehouselist'] = $warehouselist;
        
        $warehouseshopObj = app::get('logisticsmanager')->model('warehouse_address');
        $addressList      = $warehouseshopObj->getlist('*', array('warehouse_id' => $id));
        
        
        $render                          = app::get('logisticsmanager')->render();
        $render->pagedata['addressList'] = $addressList;
        
        return $render->fetch('admin/warehouse/detail_basic.html');
    }
    
    public $column_regions = '覆盖区域';
    public $column_regions_width = '60';
    
    /**
     * column_regions
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_regions($row)
    {
        $region_names = $row['region_names'];
        if (!empty($row['one_level_region_names'])) {
            $region_names = $row['one_level_region_names'];
        }
        return $region_names;
    }
    
}
