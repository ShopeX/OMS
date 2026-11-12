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

class purchase_finder_goods{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_goods_price = "供应商价格";
    
    function detail_goods_price($goods_id){
        
        $render = app::get('purchase')->render();
        if(!is_numeric($goods_id)) die('访问出错');
        //根据商品ID获取供应商列表
        $sql = ' SELECT a.name,a.supplier_id FROM `sdb_purchase_supplier` a
                 LEFT JOIN `sdb_purchase_supplier_goods` b ON a.supplier_id=b.supplier_id
                 WHERE b.bm_id='.$goods_id;
        $row = kernel::database()->select($sql);
        unset($sql);
        
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $product_id    = $basicMaterialSelect->getlist('bm_id', array('bm_id'=>$goods_id));
        $product_id_str    = implode(',', $product_id);
        
        //获取供应商此件商品的最高价格、历史价格及当前价格
        foreach ($row as $k=>$v){
            
            //最高、最低SQL
            $sql0 = " SELECT e.`supplier_id` ";
            $sql1 = " from `sdb_purchase_branch_product_batch` e 
WHERE e.`supplier_id`='".$v['supplier_id']."' and e.`product_id` in (". $product_id_str .")
    GROUP BY e.`supplier_id` ";
            //当前价格sql
            $sqlCurr = " SELECT e.`purchase_price` FROM `sdb_purchase_branch_product_batch` e
                       WHERE e.`supplier_id`='".$v['supplier_id']."' and e.`product_id` in 
                       (". $product_id_str .") ORDER BY e.`purchase_time` DESC LIMIT 0,1 ";
            
            //最高价格
            $highestField = ",max(e.purchase_price) as hight";
            $lowerestFild = ",min(e.purchase_price) as lowers ";
            $tempsql = $sql0.$highestField.$sql1;
            $highestPrice = kernel::database()->select($tempsql);
            //最低价格
            $tempsql = $sql0.$lowerestFild.$sql1;
            $lowerestPrice = kernel::database()->select($tempsql);
            //当前价格
            $currPrice = kernel::database()->select($sqlCurr);
            
            $v['highestprice'] = $highestPrice[0]['hight'];
            $v['lowerestprice'] = $lowerestPrice[0]['lowers'];
            $v['currPrice'] = $currPrice[0]['purchase_price'];
            $price_list[] = $v;
        }
        unset($row);
        $render->pagedata['supplier_price'] = $price_list;
        $render->pagedata['goods_id'] = $goods_id;
        return $render->fetch('admin/goods/supplier_price.html');
    }
}
?>