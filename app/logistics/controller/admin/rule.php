<?php
    class logistics_ctl_admin_rule extends desktop_controller {
        var $workground = 'setting_tools';
        var $defaultWorkground = 'setting_tools';

        /**
        *显示仓库
        */
        function index(){
            $this->ruleList();
        }

        function ruleList() {
            $title = "物流公司优先设置规则";
            $action = array(
                array(
                    'label'  => '新建规则',
                    'href'   => 'index.php?app=logistics&ctl=admin_rule&act=branchSel',
                    'target' => 'dialog::{width:800,height:400,title:\'新建规则\'}',
                ),

                array(
                'label'  => '删除规则',
                'submit'   => 'index.php?app=logistics&ctl=admin_rule&act=deleteRule',
                'target' => 'dialog::{width:500,height:200,title:\'删除规则\'}',
                ),
                array(
                    'label'  => '复用仓库规则',
                    'submit'   => 'index.php?app=logistics&ctl=admin_rule&act=copyRule',
                    'target' => 'dialog::{width:600,height:400,title:\'选择仓库\'}',
                ),
                array(
                'label'  => '解除关联',
                'href'   => 'index.php?app=logistics&ctl=admin_rule&act=unbindRule',
                'target' => 'dialog::{width:400,height:200,title:\'选择仓库\'}',
                ),
            );


            $params=array(
                'title'               => $title,
                'base_filter'         => array(),
                'actions'             => $action,
                'use_buildin_filter'=>true,
                'use_buildin_recycle' => false,
            );

            $this->finder('logistics_mdl_rule',$params);
        }

        /**
         * 选择仓库
         *
         * @return void
         * @author 
         */
        public function branchSel()
        {
            
            $this->display('admin/rule_branchsel.html');
        }

        //新增规则弹窗展示页
        function addRule(){
            $branch_id = $_GET['branch_id'];

            //电子面单来源类型
            $channelObj = app::get("logisticsmanager")->model('channel');
            $rows = $channelObj->getList("channel_id,channel_type",array('status'=>'true'));
            $channelType = array();
            foreach($rows as $val) {
                $channelType[$val['channel_id']] = $val['channel_type'];
                unset($val);
            }
            unset($rows);

            //物流公司信息
            $braObj = app::get('ome')->model('branch');
            
            // 判断是否为门店仓
            $branchInfo = $braObj->db_dump(array('branch_id'=>$branch_id,'check_permission'=>'false'), 'b_type');
            $isStoreBranch = ($branchInfo && $branchInfo['b_type'] == '2');
            
            if ($isStoreBranch) {
                // 门店仓：获取所有可用的物流公司
                $mdl_ome_dly_corp = app::get('ome')->model('dly_corp');
                $dly_corp = $mdl_ome_dly_corp->getList('corp_id,name,type,weight,tmpl_type,channel_id,d_type,corp_model', array('disabled'=>'false'));
                
                // 如果门店参与O2O，优先显示商家配送类型的物流公司
                if (app::get('o2o')->is_installed()) {
                    $o2oStore = app::get('o2o')->model('store')->getList('store_id', array('branch_id'=>$branch_id,'is_o2o'=>'1'), 0, 1);
                    if (!empty($o2oStore)) {
                        // 重新排序：商家配送优先，然后按权重从大到小排序
                        usort($dly_corp, function($a, $b) {
                            // 首先按corp_model排序：seller优先
                            if ($a['corp_model'] == 'seller' && $b['corp_model'] != 'seller') {
                                return -1;
                            }
                            if ($a['corp_model'] != 'seller' && $b['corp_model'] == 'seller') {
                                return 1;
                            }
                            // corp_model相同时，按weight从大到小排序
                            return $b['weight'] - $a['weight'];
                        });
                    }
                }
            } else {
                // 大仓：使用原有逻辑获取物流公司
                $dly_corp = $braObj->get_corp($branch_id,'');
            }
            
            array_push($dly_corp,array('corp_id'=>'-1','name'=>'人工审单'));
            $dlyCorpNormal = $electronIds = array();
            foreach($dly_corp as $key=>$val) {
                if($val['tmpl_type'] != 'electron') {
                    $dlyCorpNormal[] = $val;
                } else {
                    if($channelType[$val['channel_id']] == 'wlb') {
                        $electronIds[] = $val['corp_id'];
                        $dly_corp[$key]['name'] .= '(电)';
                    } else {
                        $dlyCorpNormal[] = $val;
                        $dly_corp[$key]['name'] .= '('.$channelType[$val['channel_id']].')';
                    }
                }
            }

            $this->pagedata['dly_corp'] = $dly_corp;
            $this->pagedata['dlyCorpNormal'] = $dlyCorpNormal;
            $this->pagedata['dlyCorpNormalJson'] = json_encode($dlyCorpNormal);
            $this->pagedata['electronIds'] = json_encode($electronIds);
            $this->pagedata['dly_corp_list'] = json_encode($dly_corp);
            $this->pagedata['elecIds'] = $electronIds;
            unset($dly_corp);
            $this->pagedata['branch_id'] = $branch_id;
            //店铺列表
            $this->pagedata['shops'] = app::get('ome')->model('shop')->getList('name,shop_id', array('s_type'=>'1'), 0, -1, 'name ASC');
            $mdl_rule_shop = app::get('logistics')->model('rule_shop');
            $rs_has_shops = $mdl_rule_shop->getList("*",array("branch_id"=>$branch_id));
            if (!empty($rs_has_shops)){//同一个仓库已用店铺不可再选
                $arr_has_shop = array();
                foreach ($rs_has_shops as $var_has_shop){
                    $arr_has_shop[] = $var_has_shop["shop_id"];
                }
                foreach ($this->pagedata['shops'] as &$var_shop){
                    if (in_array($var_shop["shop_id"],$arr_has_shop)){
                        $var_shop["disabled"] = true;
                    }
                }
                unset($var_shop);
            }
            $this->page('admin/create_rule.html');
        }

        /**
        *获取仓库规则信息
        */
        function getBranchRule(){
            $branch_id = $_GET['branch_id'];
            if($branch_id){
                $branch_rule = $this->app->model('branch_rule')->getlist('type',array('branch_id'=>$branch_id),0,1);
                if($branch_rule){
                    echo json_encode($branch_rule[0]);
                }
            }
        }

        //保存规则
        function saveRule(){
            $this->begin();
            $data = $_POST;
            //同一仓库只能有一个规则名
            $rule = $this->app->model('rule')->getlist('rule_id',array('rule_name'=>$data['rule_name'],'branch_id'=>$data['branch_id']));
            if($rule){
                $this->end(false,'规则名称已存在');
            }
            if($data["set_type"] == "shop"){ //按店铺
                if ($data["shop_corp_id"] == "0"){ //指定物流公司 没选择状态
                    $this->end(false,'请选择指定物流公司');
                }
            }else{//按任意重量 按重量区间 判断区域是否已存在
                $regionRule = $this->app->model('rule')->chkBranchRegion($data['branch_id'],$data['p_region_id'],'');
                if($regionRule){
                    $this->end(false,'此仓库区域已有相同的规则建立');
                }
            }
            $branch_rule = array(
                'branch_id' => $data['branch_id'],
                'type'      => 'custom',
                'parent_id' => 0,
            );
            // 保存规则类型
            $result = $this->app->model('branch_rule')->save($branch_rule);
            if (!$result) {
                $this->end(false, '仓库规则应用失败');
            }
            //创建规则
            $rule_id = $this->app->model('rule')->createRule($data);
            if($rule_id){
                $this->end(true,'保存成功','index.php?app=logistics&ctl=admin_rule&act=ruleList&branch_id='.$data['branch_id']);
            }
        }
        /**
        * 查询规则名称是否存在
        */
        function checkRuleName(){
            $rule_name = trim($_GET['rule_name']);
            $branch_id = $_GET['branch_id'];
            $rule = $this->app->model('rule')->getlist('rule_id',array('rule_name'=>$rule_name,'branch_id'=>$branch_id));
            if($rule){
                echo json_encode(array('message'=>'已存在'));
            }
        }
        /**
        * 复制仓库规则
        */
        function copyRule(){
            if (!$_POST['rule_id'] || count($_POST['rule_id']) > 1) {
                die('只能选一条规则进行复用');
            }

            $this->pagedata['rule_id'] = array_pop($_POST['rule_id']);

            $rule_branch_id = array();
            $branchRuleMdl = $this->app->model('branch_rule');
            foreach ($branchRuleMdl->getList('branch_id',array('type'=>'custom')) as $value) {
                $rule_branch_id[] = $value['branch_id'];
            }

            $this->pagedata['rule_branch_id'] = $rule_branch_id;

            $this->display('admin/copyrule.html');
        }

        /**
         * summary
         *
         * @return void
         * @author 
         */
        public function doCopyRule()
        {
            $this->begin();

            $rule_id = $_POST['rule_id'];
            $branch = $_POST['branch'];

            if (!$rule_id) $this->end(false,'规则不存在');
            if (!$branch) $this->end(false,'请先选择复用到的仓库');

            $rule = $this->app->model('rule')->db_dump($rule_id);
            if (!$rule) $this->end(false,'规则不存在');

            $branchRuleMdl = $this->app->model('branch_rule');
            foreach ($branch as $branch_id) {
                $branch_rule = $branchRuleMdl->db_dump(array('branch_id'=>$branch_id));
                if ($branch_rule['type'] == 'custom') {
                    continue;
                }

                $branch_rule = array(
                    'branch_id' => $branch_id,
                    'type' => 'other',
                    'parent_id' => $rule['branch_id'],
                );

                $branchRuleMdl->save($branch_rule);
            }

            // $rule_data = array();
            // $rule_data['branch'] = $_POST['branch'];
            // $rule_data['branch_id'] = $_POST['branch_id'];

            // $this->app->model('rule')->updateRule($rule_data);
            $this->end(true,'设置成功');
        }

        /**
        * 解绑仓库规则
        */
        function unbindRule(){
            // if($_POST['oper']=='edit'){
            //     $this->begin('index.php?app=logistics&ctl=admin_rule&act=ruleList&branch_id='.$_POST['branch_id'].'&_finder[finder_id]='.$_GET['finder_id'].'');
            //     $rule_data = array();
            //     $rule_data['branch'] = 0;
            //     $rule_data['branch_id'] = $_POST['branch_id'];

            //     $this->app->model('rule')->updateRule($rule_data);
            //     $this->end(true,'解除成功');

            // }else{
                // $branch_id = $_GET['branch_id'];
                // $this->pagedata['branch_id'] = $branch_id;
                // $this->pagedata['finder_id'] = $_GET['finder_id'];
                
                // 查询已经有规则仓库
                $rule_branch_id = array();
                $branchRuleMdl = $this->app->model('branch_rule');
                foreach ($branchRuleMdl->getList('branch_id',array('type'=>'custom')) as $value) {
                    $rule_branch_id[] = $value['branch_id'];
                }

                $this->pagedata['rule_branch_id'] = $rule_branch_id;
                $this->page('admin/unbindrule.html');
            // }
        }

        /**
         * summary
         *
         * @return void
         * @author 
         */
        public function doUnbindRule()
        {
                $this->begin();

                $branch = $_POST['branch'];
                if (!$branch) $this->end(false,'请先选择仓库');

                $branchRuleMdl = $this->app->model('branch_rule');
                foreach ($branch as $branch_id) {
                    $branchRuleMdl->delete(array('branch_id'=>$branch_id,'type'=>'other'));
                }

                $this->end(true,'解除成功');
        }

        /**
        * 删除一级地区确认

        */
        function confirmDeleteRule(){
            $this->display('admin/confirmDeleteRule.html');
        }


        /**
        *删除规则
        */
        function doDeleteRule(){
            $this->begin();
            $data = $_POST;
            //根据set_type区分出shop按店铺来的rule_id
            $arr_rule_id = explode(',',$data['rule_id']);
            foreach($arr_rule_id as $rk=>$v){
                $old = $this->app->model('rule')->db_dump(array('rule_id'=>$v), 'branch_id');
                if(!$this->app->model('rule')->db_dump(array('branch_id'=>$old['branch_id'],'rule_id|noequal'=>$v), 'rule_id')) {
                    $branch_rule = array(
                        'branch_id' => $old['branch_id'],
                        'type'      => 'custom',
                    );
                    $this->app->model('branch_rule')->delete($branch_rule);
                }
            }
            $mdl_rule_obj = app::get('logistics')->model('rule_obj');
            $rs_rules = $mdl_rule_obj->getList("*",array("rule_id|in"=>$arr_rule_id,"set_type"=>"shop"));
            if (!empty($rs_rules)){//shop类型不会出现rule_obj表 一个rule_id对应一条以上记录的
                $use_shop_ruleids = array();
                foreach ($rs_rules as $var_rule){
                    $use_shop_ruleids[] = $var_rule["rule_id"];
                }
                //直接删除shop类型rule
                $this->app->model('rule')->deleteShopRule($use_shop_ruleids);
                $use_other_ruleids = array();
                foreach ($arr_rule_id as $var_rule_id){
                    if (!in_array($var_rule_id,$use_shop_ruleids)){
                        $use_other_ruleids[] = $var_rule_id;
                    }
                }
                //重现获取$data['rule_id'] 这时已排除了set_type=shop按店铺的类型
                $data['rule_id'] = "";
                if (!empty($use_other_ruleids)){
                    $data['rule_id'] = implode(",", $use_other_ruleids);
                }
            }
            if ($data['rule_id']){
                if($data['deleteareaflag']=='0'){
                    $this->app->model('rule')->deleteRule($data['rule_id'],'','default',1);
                    $this->app->model('rule')->deleteRule($data['rule_id'],'','other',0);
                }else{
                    $this->app->model('rule')->deleteRule($data['rule_id'],'','',1);
                }
                $rule_id = explode(',',$data['rule_id']);
                foreach($rule_id as $rk=>$v){
                    $this->app->model('rule')->delete(array('rule_id'=>$v));
                }
            }  
            $this->end(true,app::get('desktop')->_('删除成功'));
        }

        //删除确认提示
        function deleteRule(){
            $finder_id = $_GET['finder_id'];
            $data = $_POST;
            if(empty($data)){
                echo '请选择';
            }else{
                //默认不包含 按任意重量 和 按重量区间的记录
                $this->pagedata["has_weight_record"] = false;
                $mdl_rule_obj = app::get('logistics')->model('rule_obj');
                $rs_rules = $mdl_rule_obj->getList("*",array("rule_id|in"=>$data['rule_id'],"set_type|noequal"=>"shop"));
                if (!empty($rs_rules)){
                    $this->pagedata["has_weight_record"] = true;
                }
                $this->pagedata['data'] = implode(',',$data['rule_id']);
                $this->page('admin/deleteRule.html');
            }
        }


        function help(){
            echo '帮助';
        }



    }

?>
