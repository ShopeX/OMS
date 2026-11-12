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
 * @author chenping<chenping@shopex.cn>
 */

class inventorydepth_finder_task_skus {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $addon_cols = 'shop_id';

    function __construct($app)
    {
        $this->app = $app;

        $this->_render = app::get('inventorydepth')->render();
    }

    

    public $column_request = '回写库存';
    public $column_request_order = 2;
    public function column_request($row)
    {
        $request = $row['request'];
       //error_log(var_export($row,1),3,__FILE__.'.log');
        if ($request == 'true') {
            $word = $this->app->_('开启');
            $color = 'green';
            $title = '点击关闭该货品自动回写库存功能';
            $href = 'index.php?app=inventorydepth&ctl=shop_skus&act=set_request&p[0]=false&p[1]='.$sku['id'].'&finder_id='.$_GET['_finder']['finder_id'];
        }else{
            $word = $this->app->_('关闭');
            $color = '#a7a7a7';
            $title = '点击开启该货品自动回写库存功能';
            $href = 'index.php?app=inventorydepth&ctl=shop_skus&act=set_request&p[0]=true&p[1]='.$sku['id'].'&finder_id='.$_GET['_finder']['finder_id'];
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
        $iid = $row['shop_iid'];
        $shop_id = $row['shop_id'];
        $shop_bn = $row['shop_bn'];
        $shop_bn = addslashes(str_replace('+','%2B',$shop_bn));
        $sku_id = $row['shop_sku_id'];
        $shop_type = $row['shop_type'];
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
                        \$ES('.sku-shop-stock').each(function(i){
                            if(data.length>=20){
                                shop_stock_request(data);
                                data = [];
                            }
                            data.push(i.get("iid"));
                        });
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
        $pkg_list='';
        if($row['bind'] == '1'){
            #查询本地捆绑明细
            return <<<EOF
            <div id="actual-stock-{$id}" onmouseover='bindFinderColTip(event)' rel='' style='padding:2px;height:16px;float:left;'>&nbsp;0&nbsp;</div>

EOF;
        }
        return <<<EOF
        <div id="actual-stock-{$id}" rel='' style='padding:2px;height:16px;float:left;'>&nbsp;0&nbsp;</div>

EOF;
    }

    public $column_release_stock = '发布库存';
    public $column_release_stock_order = 91;
    private $js_release_stock = false;
    public function column_release_stock($row)
    {
        $id = $row['id'];
        $iid = $row['shop_iid'];
        $shop_id = $row['shop_id'];
        $shop_bn = $row['shop_bn'];
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
                                            \$(id).setHTML(item.actual_stock);
                                            if(item.actual_product_stock){
                                            var actual_product_stock = item.actual_product_stock;

                                            var pkg_pro_html='';
                                            if(actual_product_stock.length > 0){
                                                pkg_pro_html += '<table><thead><th>货号</th><th>名称</th><th>可售库存</th><thead><tbody>';
                                                for(j=0;j<actual_product_stock.length;j++){
                                                    pkg_pro_html += '<tr><td style=\'text-align:left;\'>'+actual_product_stock[j].bn+'</td><td style=\'text-align:left;\'>'+actual_product_stock[j].product_name+'</td><td style=\'text-align:left;\'>'+actual_product_stock[j].stock+'</td></tr>';
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

   
}
