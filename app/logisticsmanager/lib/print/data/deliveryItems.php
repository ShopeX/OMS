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
 * @author ykm 2015-12-25
 * @describe deliveryItems打印数据整理
 */
class logisticsmanager_print_data_deliveryItems  {
    private $mField = array(
        'item_id',
        'delivery_id',
        'product_id',
        'number',
        'bn',
        'shop_product_id',
    );
    private $type;
    private $deliveryIds;
    private $branchId; //仓库ID 只支持单仓库打印
    private $productIds = array();
    private $deliveryItems = array();

    /**
     * deliveryItems
     * @param mixed $oriData ID
     * @param mixed $corp corp
     * @param mixed $field field
     * @param mixed $type type
     * @return mixed 返回值
     */

    public function deliveryItems(&$oriData, $corp, $field, $type) {
        $pre = __FUNCTION__ . '.';
        $this->type = $type;
        $this->deliveryIds = array_keys($oriData);
        foreach($oriData as $ori) {
            $this->branchId = $ori['branch_id'];
            break;
        }
        $objItem = app::get($type)->model('delivery_items');
        $strField = kernel::single('logisticsmanager_print_data')->getSelectField($this->mField, $field, $objItem);
        $itemData = $objItem->getList($strField, array('delivery_id'=>array_keys($oriData)));
        $middle = array();
        foreach($itemData as $item) {
            if ($item['shop_product_id'] == '-1' && 'off' == app::get('ome')->getConf('ome.delivery.print.gift')) {
                continue;
            }


            $middle[$item['delivery_id']][] = $item['item_id'];
            $this->deliveryItems[$item['item_id']] = $item;
            if(!in_array($item['product_id'], $this->productIds)) {
                $this->productIds[] = $item['product_id'];
            }
        }
        #检查打印项是否包含货位,如有，则按货位正序排序
        if(in_array('pos',$field)){
            $this->asort_post($middle);
        }
        foreach($oriData as $k => &$val) {
            foreach($field as $f) {
                if(isset($this->deliveryItems[$middle[$k][0]][$f])) {
                    $tmpArr = array();
                    foreach($middle[$k] as $itemId) {
                        $item = $this->deliveryItems[$itemId];
                        $tmpArr[$itemId] = $item[$f];
                    }
                    $val[$pre . $f] = $tmpArr;
                } elseif(method_exists($this, $f)) {
                    $val[$pre . $f] = $this->$f($middle[$k]);
                } else {
                    $val[$pre . $f] = '';
                }
            }
        }
    }

    private function order_count($itemIds) {
        $num = 0;
        foreach($itemIds as $itemId) {
            $num += $this->deliveryItems[$itemId]['number'];
        }
        return $num;
    }

    private function wxshipin_product_info($itemIds) {
        $productInfo = array();
        foreach ($itemIds as $itemId) {
            $item = $this->deliveryItems[$itemId];
            $productData = $this->getProductData($item['delivery_id']);
            $product = $productData[$item['product_id']];
            $productInfo[] = array(
                'name' => isset($product['name']) ? $product['name'] : '',
                'count' => intval($item['number']),
                'code' => isset($item['bn']) ? $item['bn'] : '',
                'description' => isset($product['spec_info']) ? $product['spec_info'] : '',
            );
        }
        return $productInfo;
    }

    private function lastField_count($itemIds) {
        return ' 共 ' . $this->order_count($itemIds) . ' 件';
    }

    private function spec_info($itemIds) {
        $specInfo = array();
        foreach($itemIds as $itemId) {
            $item = $this->deliveryItems[$itemId];
            $productData = $this->getProductData($item['delivery_id']);
            $specInfo[$itemId] = $productData[$item['product_id']]['spec_info'];
        }
        return $specInfo;
    }

    private function barcode($itemIds) {
        $barcode = array();
        foreach($itemIds as $itemId) {
            $item = $this->deliveryItems[$itemId];
            $productData = $this->getProductData($item['delivery_id']);
            $barcode[$itemId] = $productData[$item['product_id']]['barcode'];
        }
        return $barcode;
    }

    private function goodsbn($itemIds) {
        $goodsBn = array();
        $goodData = $this->getProductGoods();
        foreach($itemIds as $itemId) {
            $item = $this->deliveryItems[$itemId];
            $goodsBn[$itemId] = $goodData[$item['product_id']]['bn'];
        }
        return $goodsBn;
    }

    private function brand($itemIds) {
        $brand = array();
        $brandData = $this->getProductBrand();
        foreach($itemIds as $itemId) {
            $item = $this->deliveryItems[$itemId];
            $brand[$itemId] = $brandData[$item['product_id']]['brand_name'];
        }
        return $brand;
    }

    private function addon($itemIds) {
        $addon = array();
        foreach($itemIds as $itemId) {
            $addon[$itemId] = '';
            $item = $this->deliveryItems[$itemId];
            $productData = $this->getProductData($item['delivery_id']);
            $_arr_addon = explode('、', $productData[$item['product_id']]['spec_info']);
            if(!empty($_arr_addon)){
                $_new_addon = array();
                foreach($_arr_addon as $val){
                    $_new_addon[] = trim($val);
                }
                if(!empty($_new_addon)){
                    $addon[$itemId] = ' ' . implode("-",$_new_addon);
                }
            }
        }
        return $addon;
    }

    private function name($itemIds) {
        $name = array();
        foreach($itemIds as $itemId) {
            $item = $this->deliveryItems[$itemId];
            $productData = $this->getProductData($item['delivery_id']);
            $name[$itemId] = $productData[$item['product_id']]['name'];
        }
        return $name;
    }
    private function asort_post(&$itemIds = false){
        foreach($itemIds as $key=>$val){
            #item_id和货位的键值对
            $itemid_pos = $this->pos($val); 
            if(empty($itemid_pos)){
               continue;
            } 
            asort($itemid_pos);
            $itemIds[$key] = array_keys($itemid_pos);
        }
        return true;
    }
    private function pos($itemIds) {
        $pos = array();
        $posData = $this->getProductPos();
        foreach ($itemIds as $itemId) {
            $item = $this->deliveryItems[$itemId];
            $pos[$itemId] = $posData[$item['product_id']] ?  implode('|',$posData[$item['product_id']]) : '';
        }
        return $pos;
    }
    private function allpos($itemIds) {
        $pos = array();
        $posData = $this->getProductPos();
        foreach ($itemIds as $itemId) {
            $item = $this->deliveryItems[$itemId];
            $pos[$itemId] = $posData[$item['product_id']] ? implode('|', $posData[$item['product_id']]) : '';
        }
        return $pos;
    }

    //获取数据表 products 数据
    private function getProductData($delivery_id) {
        static $is_print_front;
        static $pData = array();
        if(!empty($pData)) {
            if($is_print_front) {
                return $pData[$delivery_id];
            }
            return $pData;
        }
        $deliCfgLib = kernel::single('ome_delivery_cfg');
        $is_print_front = (1 == $deliCfgLib->getValue('ome_delivery_is_printship')) ? true : false;
        $pData = $this->setProductData($is_print_front);
        if($is_print_front) {
            return $pData[$delivery_id];
        }
        return $pData;
    }

    private function setProductData($is_print_front) {
        $pData = $tmpPData = array();
        $tmpPData = $this->getOriginalProduct();
        if($is_print_front) {
            $doModel = app::get($this->type)->model('delivery_order');
            $sql = 'select d.delivery_id,i.product_id,i.name,i.addon from sdb_ome_order_items as i left join ' . $doModel->table_name(true) . ' as d on (i.order_id = d.order_id) where i.`delete` = "false" and d.delivery_id in ("' . implode('","', $this->deliveryIds) . '")';
            $rows = $doModel->db->select($sql);
            foreach($rows as $row) {
                $pData[$row['delivery_id']][$row['product_id']] = $tmpPData[$row['product_id']];
                $pData[$row['delivery_id']][$row['product_id']]['name'] = $row['name'];
                $pData[$row['delivery_id']][$row['product_id']]['spec_info'] = ome_order_func::format_order_items_addon($row['addon']);
            }
        } else {
            $pData = $tmpPData;
        }
        return $pData;
    }

    private function getOriginalProduct() {
        static $tmpPData;
        if(!empty($tmpPData)) {
            return $tmpPData;
        }
        $field = 'product_id,goods_id,name,spec_info,weight,barcode';
        $product = app::get('ome')->model('products')->getList($field, array('product_id'=>$this->productIds));
        foreach($product as $val) {
            $tmpPData[$val['product_id']] = $val;
        }
        return $tmpPData;
    }

    //通过product获取对应的goods表数据
    private function getProductGoods() {
        static $gData = array();
        if(!empty($gData)) {
            return $gData;
        }
        $productData = $this->getOriginalProduct();
        $goodsIds = array();
        foreach($productData as $product) {
            if(!in_array($product['goods_id'], $goodsIds)) {
                $goodsIds[] = $product['goods_id'];
            }
        }
        $field = 'goods_id,brand_id,bn';
        $goods = app::get('ome')->model('goods')->getList($field, array('goods_id'=>$goodsIds));
        $goodsData = array();
        foreach($goods as $val) {
            $goodsData[$val['goods_id']] = $val;
        }
        foreach($productData as $productId => $product) {
            $gData[$productId] = $goodsData[$product['goods_id']];
        }
        return $gData;
    }
    
    //通过product对应的goods获取对应的brand数据
    private function getProductBrand() {
        static $aBrand = array();
        if($aBrand) {
            return $aBrand;
        }
        $goods = $this->getProductGoods();
        $brandId = array();
        foreach($goods as $val) {
            if(!in_array($val['brand_id'], $brandId)) {
                $brandId[] = $val['brand_id'];
            }
        }
        $field = 'brand_id, brand_name';
        $brandData = app::get('ome')->model('brand')->getList($field, array('brand_id'=>$brandId));
        $bidBrand = array();
        foreach($brandData as $val) {
            $bidBrand[$val['brand_id']] = $val;
        }
        foreach($goods as $k => $val) {
            $aBrand[$k] = $bidBrand[$val['brand_id']];
        }
        return $aBrand;
    }

    //通过product和仓库ID获取存储位置
    private function getProductPos() {
        static $hasPos = true;
        if(!$this->branchId || !$hasPos) {
            return false;
        }
        static $pos = array();
        if(!empty($pos)) {
            return $pos;
        }
        $bppModel = app::get('ome')->model('branch_product_pos');
        $bppList = $bppModel->getList('product_id,pos_id',array('product_id'=>$this->productIds, 'branch_id'=>$this->branchId));
        if(empty($bppList)) {
            $hasPos = false;
            return false;
        }
        $posIds = array();
        foreach($bppList as $bpp) {
            $posIds[] = $bpp['pos_id'];
        }
        $posModel = app::get('ome')->model('branch_pos');
        $posList = $posModel->getList('pos_id,store_position',array('pos_id'=>array_unique($posIds), 'branch_id'=>$this->branchId));
        $bpos = array();
        foreach ($posList as $key=>$value) {
            $bpos[$value['pos_id']] .= $value['store_position'].' ';
        }
        foreach($bppList as $bpp) {
            $pos[$bpp['product_id']][] = $bpos[$bpp['pos_id']];
        }
        return $pos;
    }
}
