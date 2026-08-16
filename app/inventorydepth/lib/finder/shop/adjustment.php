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
class inventorydepth_finder_shop_adjustment {
    var $addon_cols = 'shop_id,request,simple,shop_iid,shop_bn,release_stock,mapping,bind,shop_type,bidding_type';
    
    static $_saleMaterials = array();
    static $_shopCache = array();
    
    function __construct($app)
    {
        $this->app = $app;

        $this->_render = app::get('inventorydepth')->render();
    }

    public $column_source_shop = '来源店铺';
    public $column_source_shop_order = 15;
    public $column_source_shop_width = 120;
    public function column_source_shop($row)
    {
        $shop_id = $row[$this->col_prefix.'shop_id'];
        
        // 使用缓存避免重复查询
        if (!isset(self::$_shopCache[$shop_id])) {
            $shop = $this->app->model('shop')->dump(array('shop_id' => $shop_id));
            self::$_shopCache[$shop_id] = $shop['name'] ? $shop['name'] : '-';
        }
        
        return self::$_shopCache[$shop_id];
    }



    public $column_operator = '操作';
    public $column_operator_order = 1;
    public $column_operator_width = 140;
    public function column_operator($row)
    {
        $shop = $this->app->model('shop')->dump(array('shop_id' => $row[$this->col_prefix.'shop_id']));
        $finder_id = $_GET['_finder']['finder_id'];

        $return = ''; // 初始化变量
        $src = app::get('desktop')->res_full_url.'/bundle/download.gif';
        # 同步货品
        if($shop['node_type'] == 'taobao' && $shop['business_type'] != 'maochao') {
        
            if ($row[$this->col_prefix.'simple'] == 'true' || $shop['business_type'] == 'fx') {
            $id = $this->app->model('shop_items')->select()->columns('id')
                    ->where('iid=?',$row[$this->col_prefix.'shop_iid'])
                    ->where('shop_id=?',$row[$this->col_prefix.'shop_id'])
                    ->instance()->fetch_one();

            $return .= <<<EOF
            <a style="margin:5px;padding:5px;background:url('{$src}') no-repeat scroll center center;" href='index.php?app=inventorydepth&ctl=shop&act=download_page&id={$id}&downloadType=iid' target="dialog::{title:'同步货品【{$row["shop_product_bn"]}】',onClose:function(){window.finderGroup['{$finder_id}'].refresh();}}" title="同步货品【{$row['shop_product_bn']}】"></a>
EOF;
            } else {
            $return .= <<<EOF
            <a style="margin:5px;padding:5px;background:url('{$src}') no-repeat scroll center center;" href='index.php?app=inventorydepth&ctl=shop&act=download_page&id={$row["id"]}&downloadType=sku_id' target="dialog::{title:'同步货品【{$row["shop_product_bn"]}】',onClose:function(){window.finderGroup['{$finder_id}'].refresh();}}" title="同步货品【{$row['shop_product_bn']}】"></a>
EOF;
            }
        }

        $id = $row['id'];
        $iid = $row[$this->col_prefix.'shop_iid'];
        $shop_id = $row[$this->col_prefix.'shop_id'];
        $shop_bn = $row[$this->col_prefix.'shop_bn'];
        $shop_bn = addslashes(str_replace('+','%2B',$shop_bn));
        $sku_id = $row['shop_sku_id'];
        $shop_type = $row[$this->col_prefix.'shop_type'];

        $src = app::get('desktop')->res_full_url.'/bundle/upload.gif';
        $href = "index.php?app=inventorydepth&ctl=shop_adjustment&act=releasePage&p[0]={$row['id']}";
        $confirm_notice = "确定对【{$row['shop_product_bn']}】发布？";
        $title = "正在发布【{$row['shop_product_bn']}】";
        
        //是否显示[回写]按钮
        // if(inventorydepth_shop_api_support::stock_get_not_support($shop_type)){
            $return .= <<<EOF
            <a style="margin:5px;padding:5px;background:url('{$src}') no-repeat scroll center center;" title='发布' onclick="javascript:if(confirm('{$confirm_notice}')){new Event(event).stop();new Dialog('{$href}',{title:'{$title}'});}"></a>
EOF;
        /* }else{
            $return .= <<<EOF
            <a style="margin:5px;padding:10px;"></a>
EOF;
        }*/
        
        //增加销售物料、基础物料的手工转换按钮
        if ($row[$this->col_prefix.'mapping'] == '0' ) {
            $src = app::get('desktop')->res_full_url.'/bundle/afresh.gif';
            $return .= "<a style='margin:5px;padding:10px;background:url({$src}) no-repeat scroll center center;' href='index.php?app=inventorydepth&ctl=shop_adjustment&act=trans&sku_id={$sku_id}&shop_id={$shop_id}&finder_id={$finder_id}' target=\"dialog::{width:1000,height:600,title:'转换成本地物料信息',onClose:function(){window.finderGroup['{$finder_id}'].refresh();}}\" title='转换成本地物料信息'></a>";
        }

        return $return;
    }

    public $column_request = '回写库存';
    public $column_request_order = 2;
    public function column_request($row)
    {
        $request = $row[$this->col_prefix.'request'];
        if ($request == 'true') {
            $word = $this->app->_('开启');
            $color = 'green';
            $title = '点击关闭该货品自动回写库存功能';
            $href = 'index.php?app=inventorydepth&ctl=shop_skus&act=set_request&p[0]=false&p[1]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'];
        }else{
            $word = $this->app->_('关闭');
            $color = '#a7a7a7';
            $title = '点击开启该货品自动回写库存功能';
            $href = 'index.php?app=inventorydepth&ctl=shop_skus&act=set_request&p[0]=true&p[1]='.$row['id'].'&finder_id='.$_GET['_finder']['finder_id'];
        }

        return <<<EOF
        <a style="background-color:{$color};float:left;text-decoration:none;" href="{$href}"><span title="{$title}" style="color:#eeeeee;padding:2px;">&nbsp;{$word}&nbsp;</span></a>
EOF;
    }


    private $js_shop_stock = false;
    public $column_shop_stock = '店铺库存';
    public $column_shop_stock_order = 89;
    public function column_shop_stock($row)
    {
        $id = $row['id'];
        $iid = $row[$this->col_prefix.'shop_iid'];
        $shop_id = $row[$this->col_prefix.'shop_id'];
        $shop_bn = $row[$this->col_prefix.'shop_bn'];
        $shop_bn = addslashes(str_replace('+','%2B',$shop_bn));
        $sku_id = $row['shop_sku_id'];
        $shop_type = $row[$this->col_prefix.'shop_type'];
        if ($this->js_shop_stock === false) {
            $this->js_shop_stock = true;
            $return = <<<EOF
            <script>
                void function(){
                    function shop_stock_request(data){
                        new Request.JSON({
                            url:"index.php?app=inventorydepth&ctl=shop_adjustment&act=getShopStock",
                            method:"post",
                            data:{"iid":data,"shop_id":"{$shop_id}","shop_bn":"{$shop_bn}","shop_type":"{$shop_type}"},
                            onComplete:function(rsp){
                                if(rsp.status=='fail'){console.log(rsp.msg);return;}
                                if(rsp.status=='succ'){
                                    rsp.data.each(function(item,index){
                                        var id = item.id;
                                        if (\$defined(\$("sku-shop-stock-"+id))){
                                            \$("sku-shop-stock-"+id).setHTML(item.num);
                                        }
                                    });
                                }
                            }
                        }).send();
                    }
                    \$('main').addEvent('domready',function(){
                        var data = [];
                        var uniqueIids = [];
                        
                        // 收集所有iid并去重
                        \$ES('.sku-shop-stock').each(function(i){
                            var iid = i.get("iid");
                            if (uniqueIids.indexOf(iid) === -1) {
                                uniqueIids.push(iid);
                            }
                        });
                        
                        // 按20个为一组处理去重后的数据
                        for (var i = 0; i < uniqueIids.length; i++) {
                            data.push(uniqueIids[i]);
                            if (data.length >= 20) {
                                shop_stock_request(data);
                                data = [];
                            }
                        }
                        if (data.length>0) {
                            shop_stock_request(data);
                        }

                    });
                }();
            </script>
EOF;
        }

        $return .= <<<EOF
        <div class='sku-shop-stock' sku_id="{$sku_id}" iid="{$iid}" id="sku-shop-stock-{$id}"></div>
EOF;
        return $return;
    }

    public $column_actual_stock = '店铺可售库存';
    public $column_actual_stock_order = 90;
    public function column_actual_stock($row)
    {
        $id = $row['id'];
        $url='index.php?app=inventorydepth&ctl=shop_adjustment&act=showActualStock&id='.$id;
        return <<<EOF
        <div id="actual-stock-{$id}" onmouseover='bindFinderColTip(event)' onclick="new Dialog('{$url}',{width:800, height:500, title:'{$row['shop_product_bn']}店铺可售库存'});" rel='' style='padding:2px;height:16px;float:left;'>&nbsp;0&nbsp;</div>
EOF;
    }

    public $column_release_stock = '发布库存';
    public $column_release_stock_order = 91;
    private $js_release_stock = false;
    public function column_release_stock($row)
    {
        $release_stock = $row[$this->col_prefix.'release_stock'];
        
        $id = $row['id'];
        $iid = $row[$this->col_prefix.'shop_iid'];
        $shop_id = $row[$this->col_prefix.'shop_id'];
        $shop_bn = $row[$this->col_prefix.'shop_bn'];
        $shop_bn = addslashes(str_replace('+','%2B',$shop_bn));
        $sku_id = $row['shop_sku_id'];
        $bn = $row['shop_product_bn'];
        if ($this->js_release_stock === false) {
            $this->js_release_stock = true;
            $return = <<<EOF
            <script>
                void function(){
                    function release_stock_request(data){
                        new Request.JSON({
                            url:"index.php?app=inventorydepth&ctl=shop_adjustment&act=getReleaseStock",
                            method:"post",
                            data:{"ids":data,"shop_id":"{$shop_id}","shop_bn":"{$shop_bn}"},
                            onComplete:function(rsp){
                                if(rsp.status=='fail'){console.log(rsp.msg);return;}
                                if(rsp.status=='succ'){
                                    rsp.data.each(function(item,index){
                                        var id = 'release-stock-'+item.id;
                                        if (\$defined(\$(id))){
                                            \$(id).set('value',item.quantity);
                                        }
                                        id = 'actual-stock-'+item.id;
                                        if (\$defined(\$(id))){
                                            \$(id).setHTML('<a click="script:void()">'+item.actual_stock+'</a>');
                                            if(item.actual_product_stock){
                                            var actual_product_stock = item.actual_product_stock;

                                            var pkg_pro_html='';
                                            if(actual_product_stock.length > 0){
                                                pkg_pro_html += '<table><thead><th>基础物料编码</th><th>可售库存</th><thead><tbody>';
                                                for(j=0;j<actual_product_stock.length;j++){
                                                    pkg_pro_html += '<tr><td style=\'text-align:left;\'>'+actual_product_stock[j].bn+'</td><td style=\'text-align:left;\'>'+actual_product_stock[j].stock+'</td></tr>';
                                                }
                                                pkg_pro_html += '</tbody></table>';
                                            }
                                            \$(id).set('rel',pkg_pro_html);
                                            }
                                        }

                                        id = 'regulation-'+item.id;
                                        if (\$defined(\$(id))){
                                            \$(id).setHTML(item.reguhtml);
                                        }

                                    });
                                }
                            }
                        }).send();
                    }
                    \$('main').addEvent('domready',function(){
                        var data = [];
                        \$ES('.release-stock').each(function(i){
                            data.push(i.get("skuid"));
                        });
                        if (data.length>0) {
                            release_stock_request(data);
                        }

                    });
                }();
            </script>
EOF;
        }

        $return .= <<<EOF
        <input type='text' skuid='{$id}' id='release-stock-{$id}' class='release-stock' name='release_stock' value='{$release_stock}' size=8 onchange='javascript:var _this = this;var id=this.getParent(".row").getElement(".sel").get("value");
            W.page("index.php?app=inventorydepth&ctl=shop_adjustment&act=update_release_stock",{
                data:{
                    id:id,
                    release_stock:this.value
                },
                onComplete:function(resp){
                    resp = JSON.decode(resp);
                    if (resp.error) {
                        _this.set("value",{$release_stock});
                        MessageBox.error(resp.error);return;
                    }
                }
            });
        '/>
EOF;
        return $return;
    }

    public $column_regulation = '库存更新规则';
    public $column_regulation_order = 71;
    public function column_regulation($row)
    {
        $id = $row['id'];
        return <<<EOF
            <div id="regulation-{$id}"></div>
EOF;
    }

     public $column_bind ='商品类型';
     public $column_bind_order=70;
     public function column_bind($row, $list){
        //获取销售物料列表
        //$this->_getSaleMaterialList($list);
        //$shop_product_bn = $row['shop_product_bn'];
        
        //sales_material_type
        if($row[$this->col_prefix.'bind']=='1'){
            //if(self::$_saleMaterials[$shop_product_bn]['sales_material_type'] == 7){
            //    return '福袋组合';
            //}
            
            return '组合';
        }elseif($row[$this->col_prefix.'bind']=='2'){
            return '多选一';
        }elseif($row[$this->col_prefix.'bind'] == '7'){
            return '福袋组合';
        }else{
            return '普通';
        }
     }

    public $detail_operation_log = '操作日志';
    public function detail_operation_log($sku_id)
    {
        $optLogModel = app::get('inventorydepth')->model('operation_log');
        $filter = array('obj_type' => 'sku','obj_id' => $sku_id);
        $optLogList = $optLogModel->getList('*',$filter,0,200);
        foreach ($optLogList as &$log) {
            $log['operation'] = $optLogModel->get_operation_name($log['operation']);
        }

        $this->_render->pagedata['optLogList'] = $optLogList;
        return $this->_render->fetch('finder/adjustment/operation_log.html');
    }
    
    //出价类型
    public $column_bidding_type = '出价类型';
    public $column_bidding_type_order = 99;
    public function column_bidding_type($row)
    {
        if($row[$this->col_prefix.'shop_type'] != 'dewu'){
            return '';
        }
        
        $typeName = '';
        if ($row[$this->col_prefix.'bidding_type'] == 14) {
            $typeName = '品牌直发';
        }elseif($row[$this->col_prefix.'bidding_type'] == 12){
            $typeName = '品牌专供';
        }elseif($row[$this->col_prefix.'bidding_type'] == 7){
            $typeName = '极速现货';
        }else{
            $typeName = '普通现货';
        }
        
        return $typeName;
    }
    
    /**
     * 批量获取销售物料信息
     *
     * @param $list
     * @return boolean
     */
    private function _getSaleMaterialList($list)
    {
        //check
        if(self::$_saleMaterials){
            return true;
        }
        
        $salesMaterialObj = app::get('material')->model('sales_material');
        
        //list
        $sales_material_bns = array_column($list, 'shop_product_bn');
        $dataList = $salesMaterialObj->getList('sales_material_bn,sales_material_type',array('sales_material_bn'=>$sales_material_bns));
        
        //cache
        self::$_saleMaterials = array_column($dataList, null, 'sales_material_bn');
        
        //unset
        unset($dataList);
        
        return true;
    }
}
