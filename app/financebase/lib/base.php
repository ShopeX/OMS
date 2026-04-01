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

class financebase_base extends eccommon_analysis_abstract implements eccommon_analysis_interface{
    public $detail_options = array(
        'hidden' => false,
        'force_ext' => true,
    );
    public $graph_options = array(
        'hidden' => true,
    );
    public $type_options = array(
        'display' => 'false',
    );
    public $export_href;

    function __construct(&$app)
    {
        parent::__construct($app);
        $this->_render = kernel::single('desktop_controller');

        for($i=0;$i<=5;$i++){
            if ($i == 1) continue;
            $val = $i+1;
            $this->_render->pagedata['time_shortcut'][$i] = $val;
        }
        #店铺
        $shopdata = financebase_func::getOrgShopList(financebase_func::getShopType());

       
        $this->_render->pagedata['shopdata']= $shopdata;
        $this->_render->pagedata['shop_id']= $_POST['shop_id'] ? $_POST['shop_id'] : '0';
        $this->_render->pagedata['bill_status']= $_POST['bill_status'] ? $_POST['bill_status'] : 'all';
        $this->_render->pagedata['search_key'] = isset($_POST['search_key']) ? $_POST['search_key'] : '';
        $this->_render->pagedata['search_value'] = isset($_POST['search_value']) ? $_POST['search_value'] : '';
        $this->_render->pagedata['trade_type'] = isset($_POST['trade_type']) ? $_POST['trade_type'] : '';
        $this->_render->pagedata['billCategory']= app::get('financebase')->model('expenses_rule')->getBillCategory();
        $this->_extra_view = array('financebase' => 'admin/base.html');
    }

    /**
     * ext_detail
     * @param mixed $detail detail
     * @return mixed 返回值
     */
    public function ext_detail(&$detail){
        $params = $this->_params;
        $total = app::get('financebase')->model('base')->getTotal($params);
        
        $total_positive = $total['total_positive'] ? : 0;
        $total_negative = $total['total_negative'] ? : 0;
        
        
        $detail = array(
            '入账' => array(
                'name' => '入账',
                'flag' => array(),
                'memo' => '入账',
                'icon' => 'money.gif',
                'value' => $total_positive,
            ),
            '出账' => array(
                'name' => '出账',
                'flag' => array(),
                'memo' => '出账',
                'icon' => 'money.gif',
                'value' => $total_negative,
            ),
            '动账' => array(
                'name' => '动账',
                'flag' => array(),
                'memo' => '动账=入账-出账',
                'icon' => 'money.gif',
                'value' => $total_positive + $total_negative,
            )
        );
    }

    /**
     * 查找er
     * @return mixed 返回结果
     */
    public function finder(){
       	//$base_query_string = 'time_from='.$_POST['time_from'].'&time_to='.$_POST['time_to'];
		$this->export_href = 'index.php?app=finance&ctl=bill_order&act=index&action=export';



        $actions[] = array(
                    'label' => '导出对账单',
                    'submit' => 'index.php?app=financebase&ctl=admin_shop_settlement_bill&act=index&action=export',
                    'target' => "dialog::{width:500,height:200,title:'导出对账单'}",
                );


        $actions[] = array(
                    'label' => '重新匹配',
                    'submit' => 'index.php?app=financebase&ctl=admin_shop_settlement_bill&act=rematch',
                    'target' => "dialog::{width:500,height:200,title:'重新匹配'}",
                );

        $params =  array(
            'model' => 'financebase_mdl_base',
            'params' => array(
                'actions'=>$actions,
                'title'=>'店铺收支明细<script>if($$(".finder-list").getElement("tbody").get("html") == "\n" || $$(".finder-list").getElement("tbody").get("html") == "" ){$$(".export").set("href","javascript:;").set("onclick", "alert(\"没有可以生成的数据\")");}else{}</script>',
                'use_buildin_recycle'=>false,
                'use_buildin_selectrow'=>true,
                'use_buildin_filter'=>false,
                'use_buildin_setcol'=>true,

                // 'finder_aliasname'=>'base',
                // 'object_method'=>array('count'=>'count_order_bill','getlist'=>'getlist_order_bill'),
                'finder_cols'=>'column_edit,shop_id,trade_no,financial_no,out_trade_no,order_bn,trade_time,member,money,trade_type,remarks,bill_category',
                //'base_query_string'=>$base_query_string,
                //'base_filter'=>array('time_from'=>$this->_params['time_from'],'time_to'=>$this->_params['time_to'],'shop_id'=>$this->_params['shop_id']),
                'orderBy'=> 'id desc',
            ),
        );

        return $params;
    }


}