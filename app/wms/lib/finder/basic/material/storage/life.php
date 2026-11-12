<?php
/**
 +----------------------------------------------------------
 * 保质期批次管理
 +----------------------------------------------------------
 * Author: wangbiao@shopex.cn
 * Time: 2015-08-15 $
 * [Ecos!] (C)2003-2015 Shopex Inc.
 +----------------------------------------------------------
 */

class wms_finder_basic_material_storage_life
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols = 'guarantee_period,date_type,status,balance_num,in_num,out_num,freeze_num';//调用字段
    
    var $date_type    = array(1=>'天', '月', '年');
    
    /*------------------------------------------------------ */
    //-- 编辑
    /*------------------------------------------------------ */
    var $column_edit  = '操作';
    var $column_edit_order = 5;
    var $column_edit_width = '70';
    function column_edit($row)
    {
    	$return = '<a href="index.php?app=wms&ctl=admin_material_storagelife&act=editor&p[0]='.$row['bmsl_id'].'&finder_id='.$_GET['_finder']['finder_id'].'"
                    target="dialog::{width:700,height:500,title:\'编辑保质期有效日期\'}">延保</a>';
		
    	if(intval($row[$this->col_prefix . 'status'])==1 && intval($row[$this->col_prefix . 'balance_num'])==0){
    		$return .= ' | <a href="index.php?app=wms&ctl=admin_material_storagelife&act=deactive&p[0]='.$row['bmsl_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">关闭</a>';
    	}
		if(intval($row[$this->col_prefix . 'status'])==2){
			$return .= ' | <a href="index.php?app=wms&ctl=admin_material_storagelife&act=active&p[0]='.$row['bmsl_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">激活</a>';
		}
		
        return $return;
    }
    
    /*------------------------------------------------------ */
    //-- 入库数量
    /*------------------------------------------------------ */
    var $column_in_num  = '入库数量';
    var $column_in_num_order = 5;
    var $column_in_num_width = '60';
    function column_in_num($row){
  		$in_num=$row[$this->col_prefix . 'in_num'];
  		if(intval($row[$this->col_prefix . 'status'])==2){
  			$in_num="-";
  		}
    	return $in_num;
    }
    
    /*------------------------------------------------------ */
    //-- 出库数量
    /*------------------------------------------------------ */
    var $column_out_num  = '出库数量';
    var $column_out_num_order = 5;
    var $column_out_num_width = '60';
    function column_out_num($row){
    	$out_num=$row[$this->col_prefix . 'out_num'];
    	if(intval($row[$this->col_prefix . 'status'])==2){
    		$out_num="-";
    	}
    	return $out_num;
    }
    
    /*------------------------------------------------------ */
    //-- 剩余数量
    /*------------------------------------------------------ */
    var $column_balance_num  = '剩余数量';
    var $column_balance_num_order = 5;
    var $column_balance_num_width = '60';
    function column_balance_num($row){
    	$balance_num=$row[$this->col_prefix . 'balance_num'];
    	if(intval($row[$this->col_prefix . 'status'])==2){
    		$balance_num="-";
    	}
    	return $balance_num;
    }
    
    /*------------------------------------------------------ */
    //-- 预占数量
    /*------------------------------------------------------ */
    var $column_freeze_num  = '预占数量';
    var $column_freeze_num_order = 5;
    var $column_freeze_num_width = '60';
    function column_freeze_num($row){
    	$freeze_num=$row[$this->col_prefix . 'freeze_num'];
    	if(intval($row[$this->col_prefix . 'status'])==2){
    		$freeze_num="-";
    	}
    	return $freeze_num;
    }
    
    /*------------------------------------------------------ */
    //-- 详细列表
    /*------------------------------------------------------ */
    var $detail_material    = '保质期批次详情';
    function detail_material($bmsl_id)
    {
        $render     = app::get('wms')->render();
        
        $basicMaterialStorageLifeObj = app::get('material')->model('basic_material_storage_life');
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        $data       = array();
        $data       = $basicMaterialStorageLifeObj->dump(array('bmsl_id'=>$bmsl_id), '*');
        
        #基础物料
        $item_basic_material    = $basicMaterialObj->dump(array('bm_id'=>$data['bm_id']), 'material_name');
        $data    = array_merge($data, $item_basic_material);
        
        #仓库
        $branch    = app::get('ome')->model('branch')->dump($data['branch_id'], 'name');
        
        $data['branch_name']  = $branch['name'];
        $data['type']         = ($data['type'] == '2' ? '成品' : '半成品');
        $data['visibled']     = ($data['visibled'] == '2' ? '停售' : '可售');
        $data['date_type']    = $this->date_type[$data['date_type']];
        $render->pagedata['item']    = $data;
        
        return $render->fetch('admin/material/detail_storage_life.html');
    }
    
    /*------------------------------------------------------ */
    //-- 操作日志
    /*------------------------------------------------------ */
    var $detail_history = '操作日志';
    function detail_history($bmsl_id)
    {
        $render    = app::get('wms')->render();
        $oOperation_log  = app::get('ome')->model('operation_log');
        
        $history    = $oOperation_log->read_log(array('obj_id'=>$bmsl_id, 'obj_type'=>'basic_material_storage_life@material'), 0, -1);
        foreach($history as $k=>$v)
        {
            $history[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        
        $render->pagedata['history'] = $history;
        return $render->fetch('admin/material/detail_history_storage_life.html');
    }
    
    /*------------------------------------------------------ */
    //-- 格式化保质期时长
    /*------------------------------------------------------ */
    var $column_guarantee_period = '保质期时长';
    var $column_guarantee_period_width = '80';
    var $column_guarantee_period_order = 30;
    function column_guarantee_period($row)
    {
        return $row[$this->col_prefix . 'guarantee_period'] . $this->date_type[$row[$this->col_prefix . 'date_type']];
    }

}
