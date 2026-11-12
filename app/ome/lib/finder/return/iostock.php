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
class ome_finder_return_iostock{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "售后入库详情";
    
    function __construct($app)
    {
        $this->app = $app;
    }

    function detail_basic($item_id){
    	$render = app::get('ome')->render();
        $filter = array('item_id'=>$item_id);

        $Oreturn_iostock = $this->app->model('return_iostock');
        $Oreship = $this->app->model('reship');
        $Oreturn_product = $this->app->model('return_product');
        $Oorders = $this->app->model('orders');
        $Omembers = $this->app->model('members');
        $Ocorp = $this->app->model('dly_corp');
        $oPam = app::get('pam')->model('account');

        $return_iostock = $Oreturn_iostock->getList('*',$filter,0,1);

        $reship_filter = array('reship_id'=>$return_iostock[0]['reship_id']);
        $order_filter = array('order_id'=>$return_iostock[0]['order_id']);
        $member_filter = array('member_id'=>$return_iostock[0]['member_id']);
        $corp_filter = array('corp_id'=>$return_iostock[0]['return_logi_name']);
        $pam_filter = array('account_id' => $return_iostock[0]['op_id']);

        $reship = $Oreship->getList('reship_bn',$reship_filter,0,1);
        $orders = $Oorders->getList('order_bn',$order_filter,0,1);
        $members = $Omembers->getList('name',$member_filter,0,1);
        $corps = $Ocorp->getList('name',$corp_filter,0,1);
        $account = $oPam->getList('login_name', $pam_filter,0,1);


        $columns = $Oreturn_iostock->get_schema();
        $return_iostock[0]['is_check'] = $columns['columns']['is_check']['type'][$return_iostock[0]['is_check']];
        $return_iostock[0]['order_id'] = $orders[0]['order_bn'];
        $return_iostock[0]['reship_id'] = $reship[0]['reship_bn'];
        $return_iostock[0]['member_id'] = $members[0]['name'];
        $return_iostock[0]['return_logi_name'] = $corps[0]['name'];
        $return_iostock[0]['op_id'] = $account[0]['login_name'];

        $render->pagedata['return_iostock'] = $return_iostock[0];

        return $render->fetch('admin/return_iostock/detail/basic.html');
    }

}
