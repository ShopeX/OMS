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
 * 订单组结构
 *
 * @author wangbiao@shopex.cn
 * @version 2024.07.22
 */
class dealer_platform_order_item
{
    /**
     * 检查通过
     */
    const __OPT_ALLOW = 0;
    
    /**
     * 需提示或其它
     */
    const __OPT_ALERT = 1;
    
    /**
     * 订单数据
     * @var Array
     */
    private $orders = array();
    
    /**
     * 析构
     *
     * @param Array $orders 订单组数据
     * @return void
     */
    function __construct($orders)
    {
        $this->orders = $orders;
        $this->orderNums = count($orders);
    }
    
    /**
     * 对当前结果进行处理
     *
     * @param $config
     * @return false
     */
    public function process($config)
    {
        return false;
    }
    
    /**
     * 检查订单组内容是否有效
     *
     * @param Array $orders 订单组
     * @return Boolean
     */
    public function vaild($config)
    {
        return false;
    }
    
    /**
     * 获取订单内容
     *
     * @return array
     */
    public function getOrders()
    {
        return $this->orders;
    }
    
    /**
     * 获取送货地址
     *
     * @return String
     */
    public function getShipArea()
    {
        foreach ($this->orders as $key => $order)
        {
            return $order['ship_area'];
            break;
        }
        
        return '';
    }
    
    /**
     * 获取shop_id
     *
     * @return string
     */
    public function getShopId()
    {
        foreach ($this->orders as $key => $order)
        {
            return $order['shop_id'];
            break;
        }
        
        return '';
    }
    
    /**
     * 获取订单条数
     *
     * @return Integer
     */
    public function getOrderNum()
    {
        if (empty($this->orders)) {
            return 0;
        } else {
            return count($this->orders);
        }
    }
    
    /**
     * 获取店铺类型
     *
     * @return string
     */
    public function getShopType()
    {
        foreach ($this->orders as $key => $order)
        {
            return $order['shop_type'];
            break;
        }
        
        return '';
    }
    
    /**
     * 获取订单重量
     *
     * @return number
     */
    public function getWeight()
    {
        //check
        if(empty($this->orders)){
            return 0;
        }
        
        $weight = 0;
        foreach ($this->orders as $key => $order)
        {
            $order_weight = $this->getOrderWeight($order['plat_order_id']);
            if ($order_weight==0){
                $weight=0;
                break;
            }else{
                $weight+= $order_weight;
            }
        }
        
        return $weight;
    }
    
    /**
     * 获取订单商品重量
     *
     * @param $plat_order_id
     * @param $type
     * @param $additional
     * @return float
     */
    public function getOrderWeight($plat_order_id=0, $type='', $additional='')
    {
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
        
        $weight = 0;
        foreach ($this->orders as $orderKey => $orderInfo)
        {
            //check
            if(empty($orderInfo['objects'])){
                continue;
            }
            
            foreach ($orderInfo['objects'] as $objKey => $objVal)
            {
                //PKG组合商品重量
                if($objVal['obj_type']=='pkg'){
                    $pkgInfo = $salesMaterialExtObj->dump(array('sm_id'=>$objVal['goods_id']), 'weight');
                    if($pkgInfo['weight']>0){
                        $weight += $pkgInfo['weight'] * $objVal['quantity'];
                        
                        continue;
                    }
                }
                
                //按order_items进行累加重量
                foreach($objVal['items'] as $itemKey => $itemVal)
                {
                    $products = $basicMaterialExtObj->dump(array('bm_id'=>$itemVal['product_id']), 'weight');
                    if($products['weight'] > 0){
                        $weight += $products['weight'] * $itemVal['quantity'];
                    }
                }
            }
        }
        
        $weight = round($weight,3);
        
        return $weight;
    }
}