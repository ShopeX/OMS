<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2012 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 * @version osc---hanbingshu sanow@126.com
 * @date 2012-08-02
 */
 class tgstockcost_finder_costselect
 {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

     var $column_control = '总仓单位平均成本';
     function column_control($row){
         list($prodcut_id,$branch_id) = explode('-',$row['id']);
         $entityCostList = kernel::single('ome_entity_branch_product')->getBranchCountCostPrice(intval($branch_id), intval($prodcut_id));
         $unitCost = $entityCostList[$branch_id][$prodcut_id]['unit_cost'];
         return $unitCost > 0 ? '￥'. $unitCost : '￥0.00';
     }
     
//	function detail_unit_cost($row)
//	{
//		$pbID = explode('-',$row);
//		$product_id = $pbID[0];
//		$branch_id = $pbID[1];
//		$setting_stockcost_cost = app::get("ome")->getConf("tgstockcost.cost");
//		if($setting_stockcost_cost == '4'){//先进先出
//			$fifo_mdl = app::get("tgstockcost")->model("fifo");
//			$fifo_data = $fifo_mdl->getList("*",array("branch_id"=>$branch_id,"product_id"=>$product_id),0,-1," id asc");
//		}
//		elseif($setting_stockcost_cost=='2' || $setting_stockcost_cost=='3'){//固定成本 或者平均
//			$dailystock = app::get("ome")->model("dailystock");
//			$daily_data = $dailystock->getList("stock_date,unit_cost",array('branch_id'=>$branch_id,'product_id'=>$product_id,'is_change'=>1),0,-1," id asc");
//			if(!$daily_data){
//				$branch_product = app::get("ome")->model("branch_product");
//				 $daily_data = $branch_product->getList('unit_cost',array('branch_id'=>$branch_id,'product_id'=>$product_id));
//				 $daily_data[0]['stock_date'] = date('Y--m-d',time());
//			}
//		}
//		else{
//			return "不计成本!,没有数据";
//		}
//		$render = app::get("tgstockcost")->render();
//		$render->pagedata['setting_stockcost_cost'] = $setting_stockcost_cost;
//		$render->pagedata['fifo_data'] = $fifo_data;
//		$render->pagedata['daily_data'] = $daily_data;
//		return  $render->fetch("admin/cost/detial.html");
//	}
 }
