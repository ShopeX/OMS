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
 * 账单控制层
 *
 * @author 334395174@qq.com
 * @version 0.1
 */

class financebase_ctl_admin_shop_settlement_bill extends desktop_controller
{

    // 流水单
	public function index(){

        $params = array(
            'actions'=>[],
            'title'=>'店铺收支明细',
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
        );
        $this->finder('financebase_mdl_base', $params);
    }

    //基础数据
    public function base(){
        
        if(!isset($_POST['time_from'])) $_POST['time_from'] = date('Y-m-01', strtotime(date("Y-m-d")));
        if(!isset($_POST['time_to'])) $_POST['time_to'] = date('Y-m-d', strtotime("$_POST[time_from] +1 month -1 day"));
        kernel::single('financebase_base')->set_params($_POST)->display();
    }

    /**
     * 获取平台配置（统一配置，一处修改，处处生效）
     * 
     * 字段说明：
     * - name: 平台显示名称，用于卡片标题和弹层标题
     * - icon: 平台图标文字，显示在卡片中央
     * - shop_filter: 店铺筛选条件数组，用于筛选店铺列表
     *   - 格式：array('shop_type' => array(...), 'business_type' => array(...))
     *   - shop_type: 店铺类型数组，如 array('taobao','tmall')
     *   - business_type: 业务类型数组（可选），如 array('maochao')
     *   - 'all': 特殊值，表示支持所有店铺类型
     * - order: 排序权重，数值越小排序越靠前
     * - template_file: 模板文件配置
     *   - 单个模板：字符串格式，如 'alipay.xlsx'
     *   - 多个模板：数组格式，如 array('下载正向模版' => 'xxx.xlsx', '下载逆向模版' => 'yyy.xlsx')
     *   - 数组格式时，key 为下载链接显示文字，value 为模板文件名
     * - template_fields: 模板字段说明（可选），显示在弹层顶部
     * - file_format: 支持的文件格式（可选），只配置括号内容，如 '.xls、 .xlsx'
     */
    private function getPlatformConfig() {
        return array(
            'alipay' => array(
                'name' => '支付宝账单导入',
                'icon' => '支付宝',
                'shop_filter' => array('shop_type' => array('taobao','tmall')),
                'order' => '100',
                'template_file' => 'alipay.xlsx'
            ),
            'jd' => array(
                'name' => '京东日账单导入',
                'icon' => '京东日账单',
                'shop_filter' => array('shop_type' => array('360buy')),
                'order' => '200',
                'template_file' => '360buy.xlsx'
            ),
            'luban' => array(
                'name' => '抖音账单导入',
                'icon' => '抖音',
                'shop_filter' => array('shop_type' => array('luban')),
                'order' => '600',
                'template_file' => 'luban.xlsx'
            ),
            'youzan' => array(
                'name' => '有赞账单导入',
                'icon' => '有赞',
                'shop_filter' => array('shop_type' => array('youzan')),
                'order' => '700',
                'template_file' => 'youzan.xlsx'
            ),
            'pinduoduo' => array(
                'name' => '拼多多账单导入',
                'icon' => '拼多多',
                'shop_filter' => array('shop_type' => array('pinduoduo')),
                'order' => '800',
                'template_file' => 'pinduoduo.xlsx'
            ),
            'wechatpay' => array(
                'name' => '微信账单导入',
                'icon' => '微信',
                'shop_filter' => array('shop_type' => array('wx','weixinshop','website','youzan')),
                'order' => '900',
                'template_file' => 'wechatpay.xlsx'
            ),
            'wxpay' => array(
                'name' => '微信小店账单导入',
                'icon' => '微信小店',
                'shop_filter' => array('shop_type' => array('wx','youzan','wxshipin')),
                'order' => '1300',
                'template_file' => 'wxpay.xlsx'
            ),
            'jdwallet' => array(
                'name' => '京东钱包导入',
                'icon' => '京东钱包',
                'shop_filter' => array('shop_type' => array('360buy')),
                'order' => '1400',
                'template_file' => 'jdwallet.xlsx',
                'template_fields' => '注意：文件必须包含"结算表"和"资金表"两个工作表',
                'file_format' => '.xls、 .xlsx',
            ),
            'guobu' => array(
                'name' => '国补金额导入',
                'icon' => '国补',
                'shop_filter' => 'all', // 特殊处理，使用所有店铺类型
                'order' => '1500',
                'template_file' => 'guobu.xlsx',
                'template_fields' => '模板字段：订单号、国补金额、SKU、数量、账单日期',
            ),
            'xhs' => array(
                'name' => '小红书账单导入',
                'icon' => '小红书',
                'shop_filter' => array('shop_type' => array('xhs')),
                'order' => '1600',
                'template_file' => 'xhs.xlsx'
            ),
            'miaosuda' => array(
                'name' => '喵速达账单导入',
                'icon' => '喵速达',
                'shop_filter' => array('shop_type' => array('taobao'), 'business_type' => array('maochao')),
                'order' => '1700',
                'template_file' => 'miaosuda.xlsx'
            ),
            'tmyp' => array(
                'name' => '天猫优品结算单导入',
                'icon' => '天猫优品',
                'shop_filter' => array('shop_type' => array('taobao'), 'tbbusiness_type' => array('B')),
                'order' => '1800',
                'template_file' => 'tianmaoyoupin.xlsx'
            ),
            'jdecard' => array(
                'name' => '京东E卡结算单导入',
                'icon' => '京东E卡',
                'shop_filter' => array('shop_type' => array('360buy')),
                'order' => '1900',
                'template_file' => 'jdecard.xlsx'
            ),
            'jdguobu' => array(
                'name' => '京东国补结算单导入',
                'icon' => '京东国补',
                'shop_filter' => array('shop_type' => array('360buy')),
                'order' => '2000',
                'template_file' => 'jdguobu.xlsx'
            ),
            'weimobr' => array(
                'name' => '微盟零售账单导入',
                'icon' => '微盟零售',
                'shop_filter' => array('shop_type' => array('weimobr')),
                'order' => '2100',
                'template_file' => array(
                    '下载正向模版' => 'weimobr/sales.xlsx',
                    '下载逆向模版' => 'weimobr/refunds.xlsx'
                )
            ),
        );
    }

    //收支单导入 - 账单导入
    public function bill_import(){
        $platformConfig = $this->getPlatformConfig();
        
        // 生成卡片配置
        $settingTabs = array();
        foreach($platformConfig as $type => $config) {
            $settingTabs[] = array(
                'name' => $config['name'],
                'type' => $type,
                'icon' => $config['icon'],
                'order' => $config['order'],
                'template_file' => $config['template_file'],
                'template_fields' => isset($config['template_fields']) ? $config['template_fields'] : '',
                'file_format' => isset($config['file_format']) ? $config['file_format'] : '.csv、 .xls、 .xlsx'
            );
        }
        
        // 按order排序
        usort($settingTabs, function($a, $b) {
            return intval($a['order']) - intval($b['order']);
        });
        
        $this->pagedata['settingTabs'] = $settingTabs;
        
        // 检查账期设置
        // app::get('finance')->setConf('finance_setting_init_time',[]);
        $init_time = app::get('finance')->getConf('finance_setting_init_time');
        $this->pagedata['init_time'] = $init_time;
        
        // 将平台配置传递给前端，统一配置管理
        $this->pagedata['platformConfig'] = $platformConfig;
        
        // 直接输出JavaScript配置，避免Smarty模板问题
        $this->pagedata['platformConfigJs'] = json_encode($platformConfig);
        
        // 获取 finder_id 并传递给模板（用于权限验证）
        // 使用与 desktop_controller::page() 相同的逻辑生成 finder_id
        $_GET['finder_id'] || $_GET['finder_id'] = ($_GET['_finder']['finder_id'] ? : ($_GET['find_id'] ? : substr(md5($_SERVER['QUERY_STRING']),5,6)));
        $this->pagedata['finder_id'] = $_GET['finder_id'];

        // 卡片布局不再需要预先加载店铺列表，改为按需通过AJAX加载
        $this->page('admin/bill/import.html');
    }

    // 待核销列表
    public function unverification()
    {

        $this->title = '待核销列表';
        $base_filter = array('status'=>1);
        $actions = array();


        $params = array(
            'title'=>$this->title,
            'actions' => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>false,
            'base_filter' => $base_filter,
       );

       $this->finder('financebase_mdl_bill_unverification',$params);
    }



    // 导出设置页
    public function export(){
  
        $this->pagedata['shop_list'] = financebase_func::getShopList(financebase_func::getShopType());
        $this->pagedata['billCategory']= app::get('financebase')->model('expenses_rule')->getBillCategory();
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->display('admin/bill/export.html');
    }

    // 检查是否允许导出
    public function checkExport(){
        $mdlBill = app::get('financebase')->model('bill');
        $bill_start_time = strtotime($_POST['time_from']);
        $bill_end_time = strtotime($_POST['time_to'])+86400;
        $filter = array('shop_id'=>$_POST['shop_id'],'trade_time|between'=>array($bill_start_time,$bill_end_time));
        $_POST['bill_status'] == 'succ' and $filter['disabled'] = 'false';
        $_POST['bill_status'] == 'fail' and $filter['disabled'] = 'true';
        $total_num = $mdlBill->count($filter);
        echo $total_num?1:0;
    }

    // 导出csv
    public function doExport($shop_id,$time_from,$time_to,$bill_status='all'){

        set_time_limit(0);
        $oFunc = kernel::single('financebase_func');

        $node_type_ref = $oFunc->getConfig('node_type');
        $page_size = $oFunc->getConfig('page_size');

        $params['shop_id'] = trim($shop_id);
        $params['bill_start_time'] = strtotime($time_from);
        $params['bill_end_time'] = strtotime($time_to)+86400;
        $params['bill_status'] = $bill_status;

        $mdlBill = app::get('financebase')->model('bill');
        $filter = array('shop_id'=>$params['shop_id'],'trade_time|between'=>array($params['bill_start_time'],$params['bill_end_time']));
        if(isset($_GET['bill_category'])) {
            if($_GET['bill_category'] != 'all') {
                $undefined = app::get('financebase')->getConf('expenses.rule.undefined');
                if ($_GET['bill_category'] == $undefined['bill_category']) {
                    $filter['bill_category'] = "";
                } else {
                    $filter['bill_category'] = $_GET['bill_category'];
                }
            }
        }
        $bill_status == 'succ' and $filter['disabled'] = 'false';
        $bill_status == 'fail' and $filter['disabled'] = 'true';
        $total_num = $mdlBill->count($filter);
        if(!$total_num) exit('无数据');
        $shopInfo = app::get('ome')->model('shop')->getList('node_type',array('shop_id'=>$params['shop_id']),0,1);


        if($shopInfo)
        {
            $file_name = sprintf("%s账单_(%s至%s)",$node_type_ref[$shopInfo[0]['node_type']],$time_from,$time_to);
            $class_name = sprintf("financebase_data_bill_%s",$node_type_ref[$shopInfo[0]['node_type']]);
            if (ome_func::class_exists($class_name) && $instance = kernel::single($class_name)){
                $csv_title = $instance->getTitle();
                $csv_title['bill_category'] = '具体类别';
                $csv_title['order_bn'] = '订单号';
                $csv_title['order_create_date'] = '订单创建时间';

                header('Content-Type: application/vnd.ms-excel;charset=utf-8');
                header("Content-Disposition:filename=" . $file_name . ".csv");

                $fp = fopen('php://output', 'a');
                $csv_title_value = array_values($csv_title);
                foreach ($csv_title_value as &$v) $v = mb_convert_encoding($v, 'GBK', 'UTF-8');//$oFunc->strIconv($v,'utf-8','gbk');
                fputcsv($fp, $csv_title_value);
                 
                $id = 0;
                while (true) {

                    $data = $instance->getExportData($filter,$page_size,$id);
                  
                    if($data){
                        foreach ($data as &$v) {
                            if(!$v['bill_category']) {
                                $v['bill_category'] = '未识别类型';
                            }
                            $tmp = array();
                            foreach ($csv_title as $title_key => $title_val) {
                                $tmp[] = isset($v[$title_key]) ? mb_convert_encoding($v[$title_key], 'GBK', 'UTF-8') : '';
                            }
                            fputcsv($fp, $tmp);
                        }
                    }else{
                        break;
                    }

                }

                exit;
                
            }else{
                exit("未找到此节点类型:".$shopInfo[0]['node_type']);
            }
        }
    
    }

    // 重新匹配页面
    public function rematch(){
        $this->pagedata['request_url'] = $this->url .'&act=doMatch';
        /* if($_POST['bill_category'] != 'all' && empty($_POST['id'])) {
            die('具体类别有选择需要一页页选择，不支持全选');
        } */
        $_POST = array_filter($_POST);
        $this->dialog_batch('financebase_mdl_base', false, 100, 'incr');
    }

    public function doMatch(){
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取发货单号
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择收支明细';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        //data - 获取数据时包含 platform_type 字段
        $dataList = app::get('financebase')->model('base')->getList('id,unique_id,shop_id,trade_no,platform_type', $filter, $offset, $limit, 'id asc');
        
        //check
        if(empty($dataList)){
            echo 'Error: 没有获取到发货单';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
    
        //list - 直接使用 platform_type 来定位 worker 类
        foreach ($dataList as $key => $value) {
            // 检查 platform_type 是否存在
            if(empty($value['platform_type'])) {
                $retArr['ifail'] ++;
                $retArr['err_msg'][] = $value['trade_no'].':缺少平台类型';
                continue;
            }
            
            // 使用 platform_type 直接拼接 worker 类名
            $worker = "financebase_data_bill_".$value['platform_type'];
            
            // 检查类是否存在
            if (!ome_func::class_exists($worker)) {
                $retArr['ifail'] ++;
                $retArr['err_msg'][] = $value['trade_no'].':平台类型['.$value['platform_type'].']无此类型的方法';
                continue;
            }
            
            $params = [];
            $params['shop_id'] = $value['shop_id'];
            $params['ids'] = [$value['unique_id']];
            $cursor_id = '';
            $errmsg = '';
            kernel::single($worker)->rematch($cursor_id,$params,$errmsg);
            if($errmsg) {
                $retArr['ifail'] ++;
                $retArr['err_msg'][] = $value['trade_no'].':'.$errmsg;
            } else {
                $retArr['isucc']++;
            }
        }
        echo json_encode($retArr),'ok.';
        exit;
    }

    //  重新设置订单号
    public function resetOrderBn($id)
    {
        $bill_info = app::get('financebase')->model("bill")->getList('order_bn,id,credential_number,trade_no,money',array('id'=>$id,'status|lthan'=>2),0,1);
        $this->pagedata['bill_info'] = $bill_info[0];
        $this->singlepage("admin/bill/reset_orderbn.html");
    }

    //  保存设置订单号
    public function saveOrderBn()
    {
        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_bill&act=index');
        $oBill = app::get('financebase')->model("bill");
     
        $id = intval($_POST['id']);
        $order_bn = trim($_POST['order_bn']);
        if(!$id)
        {
            $this->end(false, "ID不存在");
        }

        $bill_info = app::get('financebase')->model("bill")->getList('order_bn,bill_bn',array('id'=>$id,'status|lthan'=>2),0,1);

        if(!$bill_info)
        {
            $this->end(false, "流水单不存在");
        }

        if(!$order_bn)
        {
            $this->end(false, "订单号不存在");
        }

        $bill_info = $bill_info[0];
        $bill_bn = $bill_info['bill_bn'];

        if($order_bn == $bill_info['order_bn'])
        {
            $this->end(false, "订单号没有改变");
        }


        if($oBill->update(array('order_bn'=>$order_bn),array('id'=>$id,'status|lthan'=>2)))
        {

            $this->end(true, app::get('base')->_('保存成功'));
        }
        else
        {
            $this->end(false, app::get('base')->_('保存失败'));
        }
    }

    // 导出未匹配订单号
    public function exportUnMatch(){

        $oFunc = kernel::single('financebase_func');

        $this->pagedata['platform_list'] = $oFunc->getShopPlatform();
       
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->display('admin/bill/export_unmatch.html');
    }

    public function doUnMatchExport($platform_type = 'alipay'){
        set_time_limit(0);

        $oFunc = kernel::single('financebase_func');
        $mdlBill = app::get('financebase')->model('bill');
        
        $platform_list = $oFunc->getShopPlatform();
        $page_size = $oFunc->getConfig('page_size');
        $filter = array('status'=>0,'platform_type'=>$platform_type);

        $class_name = sprintf("financebase_data_bill_%s",$platform_type);

        $file_name = sprintf("%s平台未匹配订单号[%s]",$platform_list[$platform_type],date('Y-m-d'));

        $shop_list = financebase_func::getShopList(financebase_func::getShopType());

        $shop_list = array_column($shop_list,null,'shop_id');



        if (ome_func::class_exists($class_name) && $instance = kernel::single($class_name)){

            $csv_title = $instance->getTitle();
            $csv_title['shop_id'] = '所属店铺';
            $csv_title['bill_bn'] = '单据编号';
            $csv_title['order_bn'] = '订单号';

            header('Content-Type: application/vnd.ms-excel;charset=utf-8');
            header("Content-Disposition:filename=" . $file_name . ".csv");

            $fp = fopen('php://output', 'a');
            $csv_title_value = array_values($csv_title);
            foreach ($csv_title_value as &$v) $v = $oFunc->strIconv($v,'utf-8','gbk');
            fputcsv($fp, $csv_title_value);

            $id = 0;
            while (true) {

                $data = $instance->getExportData($filter,$page_size,$id);

                if($data){
                    foreach ($data as &$v) {
                        $tmp = array();
                        $v['shop_id'] = isset($shop_list[$v['shop_id']]) ? $shop_list[$v['shop_id']]['name'] : '';
                        foreach ($csv_title as $title_key => $title_val) {
                            $tmp[] = isset($v[$title_key]) ? $oFunc->strIconv($v[$title_key],'utf-8','gbk')."\t" : '';
                        }
                        fputcsv($fp, $tmp);
                    }
                }else{
                    break;
                }

            }

            exit;

        }
 
    }

    // 获取店铺列表（用于弹层）
    public function getShopList(){
        $type = $_GET['type'];
        
        if(!$type) {
            echo 'Error: 缺少类型参数';
            return;
        }
        
        $platformConfig = $this->getPlatformConfig();
        
        if(!isset($platformConfig[$type])) {
            echo 'Error: 不支持的类型';
            return;
        }
        
        $config = $platformConfig[$type];
        
        // 根据配置获取店铺列表
        if($config['shop_filter'] === 'all') {
            $shopList = financebase_func::getShopList(financebase_func::getShopType());
        } else {
            $shop_type = isset($config['shop_filter']['shop_type']) ? $config['shop_filter']['shop_type'] : '';
            // 将整个 shop_filter 作为第三个参数传递，支持更灵活的筛选条件
            $shopList = financebase_func::getShopList($shop_type, '', $config['shop_filter']);
        }
        
        // 直接输出店铺选项HTML
        foreach($shopList as $shop) {
            echo '<option value="' . $shop['shop_id'] . '">' . $shop['name'] . '</option>';
        }
    }

    // 导入未匹配订单号
    public function importUnMatch(){

        $oFunc = kernel::single('financebase_func');

        $this->pagedata['platform_list'] = $oFunc->getShopPlatform();
       
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->display('admin/bill/import_unmatch.html');
    }

    public function doUnMatchImport()
    {

        $this->begin('index.php?app=financebase&ctl=admin_shop_settlement_bill&act=index');

        $platform_type = $_POST['platform_type'] ? $_POST['platform_type'] : 'alipay';

        if( $_FILES['import_file']['name'] && $_FILES['import_file']['error'] == 0 ){
            $file_type = substr($_FILES['import_file']['name'],strrpos($_FILES['import_file']['name'],'.')+1);
            if(in_array($file_type, array('csv','xls','xlsx'))){

                $ioType = kernel::single('financebase_io_'.$file_type);
                $oProcess = kernel::single('financebase_data_bill_'.$platform_type);
                $oFunc = kernel::single('financebase_func');


                $page_size = $oFunc->getConfig('page_size');

                $file_name = $_FILES['import_file']['tmp_name'];
                $file_info = $ioType->getInfo($file_name); 
                $total_nums = $file_info['row'];
                $page_nums = ceil($total_nums / $page_size);

                for ($i=1; $i <= $page_nums ; $i++) {
                    $offset = ($i - 1) * $page_size;
                    $data = $ioType->getData($file_name,0,$page_size,$offset,true); 

                    $oProcess->updateOrderBn($data);
                }

                $this->end(true, app::get('base')->_('更新成功'));
            }else{
                $this->end(false, app::get('base')->_('不支持此文件'));
            }

        }else{
            $this->end(false, app::get('base')->_('没有导入成功'));
        }
    }


    // 查看核销详情
    public function detailVerification($order_bn)
    {
        echo $order_bn;
    }

    /**
     * 店铺收支单编辑
     *
     * @return void
     * @author 
     **/
    public function edit($id)
    {

        $bill = app::get('financebase')->model('bill')->dump($id);

        $this->pagedata['bill'] = $bill;

        $this->display('admin/shop/settlement/bill/edit.html');
    }

    /**
     * 店铺收支单保存
     *
     * @return void
     * @author 
     **/
    public function save()
    {
        $this->begin();

        $affect_rows = 0;
        if ($_POST['bill_category']) {
            $affect_rows = app::get('financebase')->model('bill')->update(array ('disabled'=>'false','bill_category' => $_POST['bill_category'],'split_status'=>'0'), array ('id'=>intval($_POST['id'])));

        }

        $this->end($affect_rows);
    }

}
