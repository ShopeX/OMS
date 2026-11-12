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

class ome_finder_goods{

    // Vue 模式标志
    private $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_control = '商品操作';
    function column_control($row){
        return '<a href="index.php?app=ome&ctl=admin_goods_editor&act=edit&p[0]='.$row['goods_id'].'" target="_blank">编辑</a>';
    }
    
    var $addon_cols = 'picurl';
    var $column_picurl = '预览图片';
    var $column_picurl_width = 60;
    var $column_picurl_order = 20;
    function column_picurl($rows){
        $img_src = $rows[$this->col_prefix.'picurl'];
		if(!$img_src)return '';
		return "<a href='$img_src' class='img-tip pointer' target='_blank'
		        onmouseover='bindFinderColTip(event);'>
		<span>&nbsp;pic</span></a>";
    }

    var $detail_basic = '基本信息';
   function detail_basic($goods_id){
   
            $render = app::get('ome')->render();
            $oGoods = app::get('ome')->model('goods');
            $oCat = app::get('ome')->model('goods_cat');
            $oType = app::get('ome')->model('goods_type');
            $oBrand = app::get('ome')->model('brand');
            $goods = $oGoods->dump($goods_id,'*','default');
          
            $cat = $oCat->dump($goods['category']['cat_id'],'cat_name');
            $goods['cat_name'] = $cat['cat_name'];
            $type = $oType->dump($goods['type']['type_id'],'name');
            $goods['type_name'] = $type['name'];
            $brand = $oBrand->dump($goods['brand']['brand_id'],'brand_name');
            $goods['brand_name'] = $brand['brand_name'];
            if($goods['spec']){
                $all_spec_id = array_keys($goods['spec']);
                #规格名相同，有些有规格、有些没有规格，会导致商品详情显示时错位
                foreach($goods['product'] as $key=>$product){
                    $_spec_id  = $product['spec_desc']['spec_value'];
                    $spec_id = array_keys($_spec_id);
                    $diff = array_diff($all_spec_id,$spec_id);
                    if(!empty($diff)){
                        foreach($diff as $id){
                            #同一规格列下，如果规格值缺失的，为避免显示错位，全部手工置为空,
                            $goods['product'][$key]['spec_desc']['spec_value'][$id] = ''; 
                        }
                    }
                }
            }
            $render->pagedata['goods'] = $goods;
            return $render->fetch('admin/goods/detail/detail_basic.html');
    }

    var $detail_log = '操作日志';
    function detail_log($goods_id) {
        $render = app::get('ome')->render();
        $logObj = app::get('ome')->model('operation_log');
        $goodslog = $logObj->read_log(array('obj_id'=>$goods_id,'obj_type'=>'goods@ome'), 0, -1);
        foreach($goodslog as $k=>$v){
            $goodslog[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['goodslog'] = $goodslog;
        return $render->fetch('admin/goods/detail/log.html');
    }
}
