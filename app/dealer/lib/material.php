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
 * 销售物料Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2024.05.07
 */
class dealer_material extends dealer_abstract
{
    public $_salesMaterialMdl = null;
    
    public function __construct()
    {
        $this->_salesMatMdl = app::get('dealer')->model('sales_material');
        $this->_salesBasicMatMdl = app::get('dealer')->model('sales_basic_material');
        
        $this->_basicMaterialObj = app::get('material')->model('basic_material');
        $this->_basicMaterialExtObj = app::get('material')->model('basic_material_ext');
    }
    
    /**
     * 据来源店铺及销售物料货号获取销售物料信息
     *
     * @param $shop_id
     * @param $sales_material_bn
     * @return array
     */
    public function getSaleMaterialInfo($shop_id, $sales_material_bn)
    {
        $filter = array('sales_material_bn'=>$sales_material_bn, 'shop_id'=>array($shop_id, '_ALL_'));
        
        //dump
        $salesMaterialInfo = $this->_salesMatMdl->db_dump($filter, '*');
        if(empty($salesMaterialInfo)){
            return array();
        }
        
        return $salesMaterialInfo;
    }
    
    /**
     * 据来源店铺及销售物料ID获取销售物料信息
     *
     * @param $shop_id
     * @param $sales_material_bn
     * @return array
     */
    public function getSaleMaterialInfoByIds($shop_id, $sm_id)
    {
        $filter = array('sm_id'=>$sm_id, 'shop_id'=>array($shop_id, '_ALL_'));
        
        //dump
        $salesMaterialInfo = $this->_salesMatMdl->db_dump($filter, '*');
        if(empty($salesMaterialInfo)){
            return array();
        }
        
        return $salesMaterialInfo;
    }
    
    /**
     * 根据来源店铺及货号获取销售物料信息
     *
     * @param $sm_id
     * @return array
     */
    public function getBasicMatBySmIds($smIds)
    {
        $smList = $this->_salesBasicMatMdl->getList('id,sm_id,bm_id,number,rate', array('sm_id'=>$smIds), 0, -1);
        if(empty($smList)){
            return array();
        }
        
        $bmIds = array();
        foreach($smList as $key => $salesBasicMInfo)
        {
            $bm_id = $salesBasicMInfo['bm_id'];;
            
            $bmIds[$bm_id] = $bm_id;
            
            $bmAndSmRates[$bm_id] = $salesBasicMInfo;
        }
        
        $bmList = $this->_basicMaterialObj->getList('bm_id,material_name,material_bn', array('bm_id'=>$bmIds), 0, -1);
        if(empty($bmList)){
            return array();
        }
        
        $dataList = array();
        foreach($bmList as $key => $basicMaterialInfo)
        {
            $bm_id = $basicMaterialInfo['bm_id'];
            
            $dataList[$bm_id] = array_merge($basicMaterialInfo, $bmAndSmRates[$bm_id]);
        }
        
        return $dataList;
    }
    
    /**
     * 根据促销总价格计算每个物料的贡献金额值
     *
     * @param $sale_price
     * @param $bm_bns
     * @return true
     */
    public function calProSaleMatPriceByRate($sale_price, &$bm_bns)
    {
        if($sale_price <= 0){
            foreach($bm_bns as $k =>$bm_bn){
                $bm_bns[$k]['rate_price'] = 0.00;
            }
            
            return true;
        }
        
        $less_price = $sale_price;
        $count_sku = count($bm_bns);
        $i = 1;
        foreach($bm_bns as $k => $bm_bn)
        {
            if($i == $count_sku){
                $bm_bns[$k]['rate_price'] = $less_price;
            }else{
                $tmp_rate = $bm_bn['rate']/100;
                $bm_bns[$k]['rate_price'] = bcmul($sale_price, $tmp_rate, 2);
                
                $less_price = bcsub($less_price, $bm_bns[$k]['rate_price'], 2);
            }
            
            $i++;
        }
        
        return true;
    }
    
    /**
     * 根据优惠价格计算每个物料的贡献金额值
     *
     * @param $pmt_price
     * @param $bm_bns
     * @return array|true
     */
    public function getPmtPriceByRate($pmt_price, $bm_bns)
    {
        if($pmt_price <= 0){
            foreach($bm_bns as $k =>$bm_bn){
                $bm_bns[$k]['rate_price'] = 0.00;
            }
            
            return true;
        }
        
        $less_price = $pmt_price;
        $count_sku = count($bm_bns);
        $rate_bn = array();
        $i = 1;
        foreach($bm_bns as $k => $bm_bn)
        {
            if($i == $count_sku){
                $rate_bn[$bm_bn['material_bn']]['rate_price'] = $less_price;
            }else{
                $tmp_rate = $bm_bn['rate'] / 100;
                $rate_bn[$bm_bn['material_bn']]['rate_price'] = bcmul($pmt_price, $tmp_rate, 2);
                
                $less_price = bcsub($less_price,  $rate_bn[$bm_bn['material_bn']]['rate_price'], 2);
            }
            
            $rate_bn[$bm_bn['material_bn']]['number'] = $bm_bn['number'];
            
            $i++;
        }
        
        return $rate_bn;
    }
    
    /**
     * 根据促销总价格计算每个物料的贡献金额值
     *
     * @param $price
     * @param $bm_bns
     * @return array|true
     */
    public function getProPriceByRate($price, $bm_bns)
    {
        if($price <= 0){
            foreach($bm_bns as $k =>$bm_bn){
                $bm_bns[$k]['rate_price'] = 0.00;
            }
            
            return true;
        }
        
        $less_price = $price;
        $count_sku = count($bm_bns);
        $rate_bn = array();
        $i = 1;
        foreach($bm_bns as $k => $bm_bn)
        {
            if($i == $count_sku){
                $rate_bn[$bm_bn['material_bn']]['rate_price'] = $less_price;
            }else{
                $tmp_rate = $bm_bn['rate'] / 100;
                $rate_bn[$bm_bn['material_bn']]['rate_price'] = bcmul($price, $tmp_rate, 2);
                
                $less_price = bcsub($less_price, $rate_bn[$bm_bn['material_bn']]['rate_price'], 2);
            }
            
            $rate_bn[$bm_bn['material_bn']]['number'] = $bm_bn['number'];
            
            $i++;
        }
        
        return $rate_bn;
    }
}
