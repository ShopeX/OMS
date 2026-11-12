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

#增加规则的
class crm_ctl_admin_gift_rulebase extends desktop_controller{

    /**
     * index
     * @return mixed 返回值
     */
    public function index(){
        $actions     = array(
            array(
                'label'=>'添加赠品规则',
                'href'=>'index.php?app=crm&ctl=admin_gift_rulebase&act=addRuleBase',
            ),
            array('label'=>app::get('crm')->_('删除'),'icon' => 'del.gif', 'confirm' =>'确定删除选中项？','submit'=>'index.php?app=crm&ctl=admin_gift_rulebase&act=delRuleBase',),
        );
        $base_filter = array();
        $this->finder('crm_mdl_gift_rule_base',array(
            'title'                 => '赠品规则',
            'actions'               => $actions,
            'base_filter'           => $base_filter,
            'orderBy'               => 'id DESC',
            'use_buildin_recycle'   => false,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => false,
            'use_view_tab'          => false,
        ));
    }

    /**
     * 添加RuleBase
     * @return mixed 返回值
     */
    public function addRuleBase() {
        $this->editRuleBase();
    }

    /**
     * editRuleBase
     * @return mixed 返回值
     */
    public function editRuleBase() {
        $id = $_GET['id'];
        $gifts = array();
        $bm_id = array();
        $sm_id = array();
        //修改规则信息
        if($id > 0){
            $ruleBase = app::get('crm')->model('gift_rule_base')->dump($id,'*');
            $ruleBase['filter_arr'] = json_decode($ruleBase['filter_arr'], true);
            $bm_id = $ruleBase['filter_arr']['buy_goods']['bm_id'] ? : [];
            $sm_id = $ruleBase['filter_arr']['buy_goods']['sm_id'] ? : [];
            if($ruleBase['gift_list']) {
                $giftList = unserialize($ruleBase['gift_list']);
                $giftRows = app::get('crm')->model('gift')->getList('*,"checked" as checked',array('gift_id'=>array_keys($giftList)));
                $keySort = array_keys($giftList);
                $gifts = array();
                foreach($giftRows as $k=>$v){
                    $v['id'] = $v['gift_id'];
                    $v['gift_name'] = $v['gift_name'];//mb_substr($v['gift_name'],0,22,'utf-8');
                    $v['num'] = $giftList[$v['id']];
                    $key = array_search($v['id'], $keySort);
                    $gifts[$key] = $v;
                }
                ksort($gifts);
            }
            //复制赠品规则
            if (isset($_GET['type']) && $_GET['type'] == 'copy') {
                list($micro, $time) = explode(" ", microtime());
                $microtime           = sprintf("%06d", $micro * 1000000);//转成六位数字（不足时前面补零）
                $position            = strrpos($ruleBase['rule_bn'], "_"); //找到最后一次的下划线位置
                $firstPart           = $position ? substr($ruleBase['rule_bn'], 0, $position) : $ruleBase['rule_bn']; //从开始截取到下划线前
                $ruleBase['rule_bn'] = sprintf('%s_%s', $firstPart, $microtime);
                unset($ruleBase['id']);
            }
        }
        $this->pagedata['type'] = isset($_GET['type']) ? $_GET['type'] : '';
        $this->pagedata['gifts'] = $gifts;
        $this->pagedata['rule'] = $ruleBase;
        $this->pagedata['bm_id'] = $bm_id;
        $sign = '基础物料';
        $func =  'product_selected_show';
        $domid = 'hand-selected-product';
        $count = count($bm_id);
        $this->pagedata['bcreplacehtml'] = <<<EOF
<div id='{$domid}'>已选择了{$count}{$sign} &nbsp;<a href='javascript:void(0);' onclick='{$func}();'>查看选中{$sign}</a></div>
EOF;
        $this->pagedata['sm_id'] = $sm_id;
        $sign  = '销售物料';
        $func  = 'sm_selected_show';
        $domid = 'hand-selected-sm';
        $count = count($sm_id);
        $this->pagedata['smreplacehtml']  = <<<EOF
    <div id='{$domid}'>已选择了{$count}{$sign} &nbsp;<a href='javascript:void(0);' onclick='{$func}();'>查看选中{$sign}</a></div>        
EOF;
        $order_types   = array();
        $order_types["normal"] = array("type" => "normal", "text" => "普通订单");
        $order_types["presale"] = array("type" => "presale", "text" => "预售订单");
        $this->pagedata['order_types'] = $order_types;
        $this->page('admin/gift/rule_edit_base.html');
    }

    /**
     * ajax_get_gifts
     * @return mixed 返回值
     */
    public function ajax_get_gifts(){
        // 参数处理
        $s_gift_bn   = trim($_POST['s_gift_bn']);   // 赠品编码
        $s_gift_name = trim($_POST['s_gift_name']); // 赠品名称
        $sel_goods   = explode(',', $_POST['sel_goods']);
        // 处理搜索条件
        if($s_gift_bn){
            $filter['gift_bn|has'] = $s_gift_bn;
        }
        if($s_gift_name){
            $filter['gift_name|has'] = $s_gift_name;
        }
        $filter['is_del'] = '0'; // 只获取开启状态的货品
        // 调用model获取商品信息
        $rs = $this->app->model('gift')->getList('gift_id as id,gift_bn,gift_name,gift_price', $filter);
        foreach($rs as $k=>$v){
            if(in_array($v['id'], $sel_goods)){
                unset($rs[$k]);
            }
        }
        echo(json_encode(array_values($rs)));
    }
    #显示选用的货品
    /**
     * showProducts
     * @return mixed 返回值
     */
    public function showProducts(){
        $bm_id = kernel::single('base_component_request')->get_post('bm_id');
        if ($bm_id) {
            $this->pagedata['_input'] = array(
                'name' => 'bm_id',
                'idcol' => 'bm_id',
                '_textcol' => 'material_bn',
            );

            $productModel = app::get('material')->model('basic_material');
            $list = $productModel->getList("bm_id,material_name,CONCAT('货号：',material_bn,'&nbsp;;&nbsp;货品名称：',material_name) as material_bn ",array('bm_id'=>$bm_id),0,-1,'bm_id asc');
            $this->pagedata['_input']['items'] = $list;
        }
        $this->display('admin/gift/show_products.html');
    }
    #显示所选的捆绑商品
    /**
     * showPkg
     * @return mixed 返回值
     */
    public function showPkg()
    {
        $sm_id = kernel::single('base_component_request')->get_post('sm_id');

        if ($sm_id) {
            $this->pagedata['_input'] = array(
                'name' => 'sm_id',
                'idcol' => 'sm_id',
                '_textcol' => 'sales_material_bn',
            );

            $smModel = app::get('material')->model('sales_material');
            $list = $smModel->getList("sm_id,sales_material_name,CONCAT('货号：',sales_material_bn,'&nbsp;;&nbsp;货品名称：',sales_material_name) as sales_material_bn",array('sm_id'=>$sm_id),0,-1,'sm_id asc');
            $this->pagedata['_input']['items'] = $list;
        }
        $this->display('admin/gift/show_pkg.html');
    }

    /**
     * 保存
     * @return mixed 返回操作结果
     */
    public function save(){
        // 启动事务
        $this->begin("index.php?app=crm&ctl=admin_gift_rulebase&act=index");
        // 不限量的情况 清除相关条件
        if(empty($_POST['filter_arr']['buy_goods']['limit_type']) ||  $_POST['filter_arr']['buy_goods']['limit_type'] ==0){
            unset($_POST['filter_arr']['buy_goods']['limit_time_day'],$_POST['filter_arr']['buy_goods']['limit_orders']);
        }

        // 订单达人
        if ($_POST['filter_arr']['is_host']['type'] == 'assign') {
            if (empty(trim($_POST['filter_arr']['is_host']['author']))) {
                $this->end(false,'指定达人订单，达人ID为必填');
            }
            $_POST['filter_arr']['is_host']['author'] = str_replace('，', ',', trim($_POST['filter_arr']['is_host']['author']));
            $_POST['filter_arr']['is_host']['room']   = str_replace('，', ',', trim($_POST['filter_arr']['is_host']['room']));
        } else {
            unset($_POST['filter_arr']['is_host']['author']);
            unset($_POST['filter_arr']['is_host']['room']);
        }

        // 引入赠品规则类
        $shopGiftObj = app::get('crm')->model('gift_rule_base');
        // 组合数据
        $data = $_POST;
        $data['filter_arr']    = $_POST['filter_arr'];
        $data['gift_ids']      = $_POST['gift_id'];
        $data['gift_num']      = $_POST['gift_num'];
        $data['modified_time'] = time();
        $data['filter_arr']['buy_goods']['bm_id']   = null;
        $data['filter_arr']['buy_goods']['sm_id'] = null;
        $data['filter_arr']['buy_goods']['goods_bn']     = null;
        if(!empty( $_POST['filter_arr']['buy_goods']['type']) ){

            $select_bm_id   = $_POST['bm_id'];
            $select_sm_id = $_POST['sm_id'];

            if($_POST['filter_arr']['buy_goods']['type'] == '1'){
                if(empty($select_sm_id)) {
                    $this->end(false,'已选择指定销售物料，请至少选择一个销售物料');
                }
                $obj_sales_material = app::get('material')->model('sales_material');
                foreach($select_sm_id as $id){
                    $salesMaterial  = $obj_sales_material ->getList('sales_material_bn,sm_id',array('sm_id'=>$id));
                    $data['filter_arr']['buy_goods']['sm_id'][] = $salesMaterial[0]['sm_id'];
                    $data['filter_arr']['buy_goods']['goods_bn'][] = trim(strtoupper($salesMaterial[0]['sales_material_bn']));
                }
                unset($data['sm_id']);
            } elseif($_POST['filter_arr']['buy_goods']['type'] == '2') {
                if(empty($select_bm_id)) {
                    $this->end(false,'已选择指定基础物料，请至少选择一个基础物料');
                }
                $obj_products = app::get('material')->model('basic_material');
                foreach($select_bm_id as $id){
                    $product_info  = $obj_products->getList('material_bn,bm_id',array('bm_id'=>$id));
                    $data['filter_arr']['buy_goods']['bm_id'][] = $product_info[0]['bm_id'];
                    $data['filter_arr']['buy_goods']['goods_bn'][] = trim(strtoupper($product_info[0]['material_bn']));
                }
                unset($data['bm_id']);
            } else {
                $this->end(false,'未知的类型');
            }

            if(empty($_POST['filter_arr']['buy_goods']['calculate_type'])){
                $this->end(false,'已选择指定商品类型，请选择计算方式');
            }
        }
        $data['filter_arr'] = json_encode($data['filter_arr']);

        if(!$data['id']) $data['create_time'] = time();

        // 清理gift_num
        foreach($data['gift_num'] as $k=>$v){
            if(!in_array($k, $data['gift_ids'])){
                unset($data['gift_num'][$k]);
            }
        }

        $data['gift_list'] = serialize($data['gift_num']);
        if($shopGiftObj->db_save($data)){
            // 数据快照
            if($data['id']){
                // 获取规则信息
                $sdf = $shopGiftObj->dump($data['id'], '*');
                // 获取商品信息
                $sdf['goodsInfo'] = app::get('crm')->model('gift')->getList('*',array('gift_id'=>$data['gift_ids']));

                $sdf = array(
                    'title'   => '赠品规则发生变动',
                    'content' => json_encode($sdf),
                    'task_id' => $data['id'],
                );
                $this->gift_rule_change($sdf);
            }
            
            //保存赠品规则记录
            $giftLib = kernel::single('crm_gift');
            $error_msg = '';
            $result = $giftLib->save_gift_rule_logs($data, $error_msg);
            
            $this->end(true,'添加成功');
        }else{
            $this->end(false,'添加失败,已经使用过该编码了');
        }
    }

    /**
     * gift_rule_change
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function gift_rule_change($sdf){
        $mdl_snapshot = app::get('crm')->model('snapshot');
        $data = array(
            'task_id' => $sdf['task_id'],
            'type'    => 3,
            'title'   => $sdf['title'],
            'content' => $sdf['content'],
            'op_user' => kernel::single('desktop_user')->get_name(),
            'create_time' => date('Y-m-d H:i:s'),
        );
        $mdl_snapshot->save($data);
        return true;
    }

    /**
     * delRuleBase
     * @return mixed 返回值
     */
    public function delRuleBase(){
        $this->begin('index.php?app=crm&ctl=admin_gift_rulebase&act=index');
        #校验与写链路抽到 crm_capability_gift_rulebase::deleteRuleBases()，与 Vue 侧 api_gift_rulebase 共用同一实现
        #legacy 已由 begin() 开启事务 ⇒ capability 自动识别后交给外层托管
        try{
            kernel::single('crm_capability_gift_rulebase')->deleteRuleBases(
                (array) (isset($_POST['id']) ? $_POST['id'] : array()),
                (isset($_POST['isSelectedAll']) && $_POST['isSelectedAll'] === '_ALL_') ? '_ALL_' : '',
            );
        }catch(Exception $e){
            $this->end(false,$e->getMessage());
            return;
        }

        $this->end(true, $this->app->_('删除成功'));
    }

    /**
     * view_rule
     * @return mixed 返回值
     */
    public function view_rule(){

        $rule = array(
            'filter_arr' => array(
                'order_amount' => array(
                    'type'=>0
                ),
                'buy_goods' => array(
                    'type'=>0
                ),
            ),
        );

        if(isset($_GET['snapshot_id'])){
            $snapshot_id        = floatval($_GET['snapshot_id']);
            $snapshot           = app::get('crm')->model('snapshot')->db_dump($snapshot_id);
            $rule               = json_decode($snapshot['content'], true);
            $rule['filter_arr'] = json_decode($rule['filter_arr'], true);
        }
        // 去除空的货号
        foreach($rule['filter_arr']['buy_goods']['goods_bn'] as $k=>$v){
            if( ! $v) unset($rule['filter_arr']['buy_goods']['goods_bn'][$k]);
        }
        // 已经设定的赠品组合
        $gifts = array();

        if($rule['gift_list']){

            $giftList = unserialize($rule['gift_list']);
            $keySort = array_keys($giftList);;
            foreach ($rule['goodsInfo'] as $key=>$val){
                $val['num']        = $giftList[$val['gift_id']];
                $val['gift_bn']    = $val['gift_bn'];
                $val['gift_name']  = $val['gift_name'];//mb_substr($val['gift_name'],0,22,'utf-8');
                $val['gift_price'] = $val['gift_price'];
                $k = array_search($val['gift_id'], $keySort);
                $gifts[$k] = $val;
            }
            ksort($gifts);
        }
        $this->pagedata['gifts'] = $gifts;
        $this->pagedata['rule']  = $rule;
        $this->display('admin/gift/rule_base_view.html');
    }
    
    /**
     * 复制赠品规则
     * @date 2024-10-08 2:28 下午
     */
    public function copy_rulebase()
    {
        $_GET['type'] = 'copy';
        $this->editRuleBase();
    }
}