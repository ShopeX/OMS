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

class archive_data extends eccommon_analysis_abstract implements eccommon_analysis_interface{

    function __construct(&$app)
    {
        parent::__construct($app);
        $this->_render = kernel::single('desktop_controller');
        $this->_render->pagedata['report'] = 'month';
        $year = $month = array();
        for($i='2010';$i<=date('Y');$i++){
            $year[] = $i;
        }
        for($i='1';$i<='12';$i++){
            $month[] = $i;
        }

        $this->_render->pagedata['year'] = $year;
        $this->_render->pagedata['month'] = $month;
        $this->_render->pagedata['from_selected'] = explode('-',$_POST['time_from']);
        $this->_render->pagedata['to_selected'] = explode('-',$_POST['time_to']);
        $shopObj = app::get('ome')->model("shop");
        $shopList = $shopObj->getList('shop_id,name,shop_type');
        $this->_render->pagedata['shopList'] = $shopList;
        $this->_extra_view = array('archive' => 'search.html');
    }
    
    public function finder(){

        $archorderObj = $this->app->model('orders');
        $archive_count = $archorderObj->countlist($_POST);
        $this->set_extra_view($this->_extra_view);

        $base_query_string = 'time_from='.$filter['time_from'].'&time_to='.$filter['time_to'];
        return array(
            'model' => 'archive_mdl_orders',
            'params' => array(
                'actions'=>array(
                    array(
                        'label'=>app::get('archive')->_('申请售后'),
                        'class'=>'export',
                        'icon'=>'add.gif',
                        'href'=>'index.php?app=archive&ctl=return&act=create',
                        'target'=>'{width:600,height:400,title:\'申请售后\'}'),
                     array(
                        'label'=>app::get('archive')->_('快速归档'),
                        'href'=>'index.php?app=archive&ctl=order&act=index',
                      ),
                    array(
                        'label'=>app::get('archive')->_('生成报表'),
                        'class'=>'export',
                        'href'=>'index.php?app=archive&ctl=order&act=search&action=export',
                        'target'=>'{width:600,height:300,title:\'生成报表\'}'
                      ),
                ),
                'title'=>'归档共<script> $$(".count2")[0].setText("'.$archive_count.'");</script>',
                'use_buildin_recycle'=>false,
                'use_buildin_selectrow'=>false,
                'use_buildin_filter'=>false,
                'base_query_string'=>$base_query_string,
                 'finder_cols' => 'order_bn,shop_id,member_id,ship_name,ship_area,total_amount,op_id,group_id,process_status,is_cod,pay_status,ship_status,createtime,paytime'
            ),
        );
    }

    public function headers(){
        $this->_render->pagedata['title'] = $this->_title;
        $this->_render->pagedata['time_from'] = $this->_params['time_from'];
        $this->_render->pagedata['time_to'] = $this->_params['time_to'];
        $this->_render->pagedata['today'] = date("Y-m-d");
        $this->_render->pagedata['yesterday'] = date("Y-m-d", time()-86400);
        if(isset($this->analysis_config)){
            $this->_render->pagedata['this_week_from'] = date("Y-m-d", time()-(date('w')?date('w')-$this->analysis_config['setting']['week']:7-$this->analysis_config['setting']['week'])*86400);
        }else{
            $this->_render->pagedata['this_week_from'] = date("Y-m-d", time()-(date('w')?date('w')-1:6)*86400);
        }
        $this->_render->pagedata['this_week_to'] = date("Y-m-d", strtotime($this->_render->pagedata['this_week_from'])+86400*7-1);
        $this->_render->pagedata['last_week_from'] = date("Y-m-d", strtotime($this->_render->pagedata['this_week_from'])-7*86400);
        $this->_render->pagedata['last_week_to'] = date("Y-m-d", strtotime($this->_render->pagedata['last_week_from'])+86400*7-1);
        $this_month_t = date('t');
        $this->_render->pagedata['this_month_from'] = date("Y-m-" . 01);
        $this->_render->pagedata['this_month_to'] = date("Y-m-" . $this_month_t);
        $last_month_t = date('t', strtotime("last month"));
        $this->_render->pagedata['last_month_from'] = date("Y-m-" . 01, strtotime("last month"));
        $this->_render->pagedata['last_month_to'] = date("Y-m-" . $last_month_t, strtotime("last month"));
        $this->_render->pagedata['layout'] = $this->layout;

        if($this->report_type == 'true'){
            $this->_render->pagedata['report'] = $this->_params['report'];
            $this->_render->pagedata['report_type'] = $this->report_type;
            $this->_render->pagedata['month'] = array(1,2,3,4,5,6,7,8,9,10,11,12);
            for($i = 2000;$i<=date("Y",time());$i++){
                $year[] = $i;
            }
            $this->_render->pagedata['year'] = $year;
            $this->_render->pagedata['from_selected'] = explode('-',$this->_params['time_from']);
            $this->_render->pagedata['to_selected'] = explode('-',$this->_params['time_to']);
        }

        if($this->type_options['display'] == 'true'){
            $this->_render->pagedata['type_display'] = 'true';
            $this->_render->pagedata['typeData'] = $this->get_type();
            $type_selected = array(
                                'branch_id'=>$this->_params['branch_id'],
                            );
            $this->_render->pagedata['type_selected'] = $type_selected;
        }
    }

    public function set_params($params) 
    {
        
        $this->_params = $_POST;
        return $this;
    }

    
}

?>