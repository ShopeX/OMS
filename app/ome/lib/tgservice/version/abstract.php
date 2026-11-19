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

abstract class ome_tgservice_version_abstract{

    public $deploy_info = array();

    private $domain = null;

    public function __construct(){
        $this->db = kernel::database();
        $this->shell = kernel::single('base_shell_webproxy');
        
        $deploy_info = array();
        if(empty($this->deploy_info)){
            require_once(ROOT_DIR.'/script/tgservice_version.php');
            $this->deploy_info = $deploy_info;
        }
    }

    public function update($params = array(),&$sass_params = array(),&$msg,&$is_callback = false){
        $old_version = app::get('ome')->getConf('tg_version');
        $old_version = $old_version?$old_version:'basic';
        $new_version = $params['release_version'];
        if(!$new_version){
            $msg = '缺少版本号';
            return false;
        }

        $old = $this->deploy_info['version'][$old_version];
        $new = $this->deploy_info['version'][$new_version];
        $old_diff = array_diff($old,$new);
        $new_diff = array_diff($new,$old);
        if(count($old_diff)){
            foreach($old_diff as $app){
                $this->shell->exec_command(sprintf("uninstall %s",$app));
            }
        }
        if(count($new_diff)){
            foreach($new_diff as $app){
                $this->shell->exec_command(sprintf("install %s",$app));
            }
        }

        if($old_version!=$new_version){
            if(isset($params['domain'])&&$params['domain']){
                $domain = sprintf('http://%s',$params['domain']);
            }else{
                $domain = null;
            }

            base_certificate::set_release_version($this->release_version);
            base_certificate::update_node($domain);
            app::get('ome')->setConf('tg_version',$params['release_version']);

            $sass_params = array(
                'method' => 'host.change_version',
                'server_name' => $params['domain'],
                'version_code' => $new_version,
            );
            $is_callback = true;
        }

        $msg = '更新成功';
        return true;
    }

    public function callback_tosass($data = array()){
        //更新SASS信息
        if (!class_exists('SaasOpenClient', false)) {

            require_once(ROOT_DIR . "/config/saasapi.php");
        }

        ob_clean();

        $api = new SaasOpenClient();
        $api->appkey = SASS_APP_KEY;
        $api->secretKey = SAAS_SECRE_KEY;
        $api->format = 'json';
        $method = $data['method'];
        unset($data['method']);
        $sass_params = $data;
        $retryNum = 0;
        while($retryNum < 3) {
            $result = $api->execute($method, $sass_params);
            if ($result->success != 'true') {
                $retryNum ++;
                $this->ilog("Call SASS center interface failed, Retry $retryNum numbers . method:".$method.".sass_params:".var_export($sass_params,true).',result:'.var_export($result,true));
                usleep(5000);
            } else {
                $retryNum += 100;
                $this->ilog("Call SASS center interface is OK. method:".$method.".sass_params:".var_export($sass_params,true));
            }
        }
    }

    public function main($operation = 'install',$params = array(),&$msg = '',$obj){
        $is_callback = false;
        $res = $obj->$operation($params,$params,$msg,$is_callback);
        if($res){
            if($is_callback){
                $obj->callback_tosass($params);
            }
            $this->ilog("Active process finished.");
            return true;
        }else{
            return false;
        }
    }

    public function install($params = array(),&$sass_params = array(),&$msg,&$is_callback = false){
        $this->domain = $params['domain'];
        if(!$this->init_app($params,$msg)){//安装初始化数据和基础app
            return false;
        }

        $this->updatePrintTmpl();//初始化打印模版
        $this->saveInventory();//初始化盘点
        $this->saveIostockType();//初始化出入库
        $this->tmplKvCacheClean();//清除模板缓存关于ectools组件的cache/template
        $this->flushPrintTmpl();//打印模板的数据库修改
        $this->flushDlyConf();//发货相关的配置刷新
        $this->migratePrintTmpl();//快递单模板、大头笔数据迁移
        $this->migrateEctools();//清除原来的eccommon地址信息表，将ectools的迁移过来
        $this->updateBranch();//初始化仓库绑定关系  更新仓库wms_id
        $this->updateWaybillPrintTmpl();//更新电子面单打印模板
        //添加初始化模板导出模板
        kernel::single('desktop_init')->addDefaultExportStandardTemplate();

        app::get('ome')->setConf('tg_version',$params['release_version']);
        base_certificate::set_release_version($this->release_version);
        base_certificate::update_node(sprintf('http://%s',$params['domain']));

        $certi_id = kernel::single('base_certificate')->certi_id();//应用证书号
        $node_id = kernel::single('base_shopnode')->node_id('ome'); //应用节点号
        $sass_params = array(
            'method'   => 'host.active',
            'order_id' => $params['order_id'],
            'host_id'  => $params['host_id'],
            'certi_id' => $certi_id,
            'node_id'  => $node_id,
        );
        $is_callback = true;

        $msg = '安装成功';
        return true;
    }

    public function init_app($data = array(),&$msg = ''){

        if(!defined('DB_USER') || !defined('DB_PASSWORD')){
            $msg = '数据库用户名或密码未设置';
            return false;
        }

        //创建数据库
        // $this->db->query(sprintf('CREATE DATABASE %s CHARACTER SET utf8 ;', DB_NAME));
        $this->db->query(sprintf('USE %s', DB_NAME));

        // $this->db->query(sprintf("GRANT ALL PRIVILEGES ON %s.* TO '%s'@'192.168.%%.%%' IDENTIFIED BY '%s'", DB_NAME, DB_REAL_USER, DB_REAL_PASSWORD));
        // $this->db->query("FLUSH PRIVILEGES");

        // $this->ilog("Create database user informatin done. (UserName => ".DB_REAL_USER.",PassWord => ".DB_REAL_PASSWORD.").");

        //导入初始数据
        $this->importData();

        $this->ilog("Import database OK.");
        cachemgr::init(false);
        $this->shell->exec_command('cacheclean');
        $this->shell->exec_command('kvstorerecovery');

        $this->shell->exec_command("update --ignore-download");

        //安装缺省的 APP,所有版本多有的
        $this->installDefaultApp();

        // $this->shell->exec_command("install inventorydepth");
        // $this->shell->exec_command("install tgstockcost");
        // $this->shell->exec_command("install logistics");
        // $this->shell->exec_command("install logisticsaccounts");
        // $this->shell->exec_command("install monitor");
        // $this->shell->exec_command("install wms");
        // $this->shell->exec_command("install eccommon");
        // $this->shell->exec_command("install console");
        // $this->shell->exec_command("install middleware");
        // $this->shell->exec_command("install wmsmgr");
        // $this->shell->exec_command("install siso");
        // $this->shell->exec_command("install rpc");
        // $this->shell->exec_command("install channel");
        // $this->shell->exec_command("install crm");
        // $this->shell->exec_command("install wangwang");
        // $this->shell->exec_command("install drm");

        $this->ilog("Update Ok.");

        kernel::single('base_certificate')->register(sprintf('http://%s',$data['domain']));

        // 更新产品版本
        if (strstr($data['domain'], '.tp-erp.taoex.com')) {
            app::get('desktop')->setConf('banner','TP-ERP');
            app::get('desktop')->setConf('logo','TP-ERP');
            app::get('desktop')->setConf('logo_desc','TP-ERP');   
        } elseif(strstr($data['domain'], '.erp.taoex.com')) {
            app::get('desktop')->setConf('banner','ShopEx-ERP');
            app::get('desktop')->setConf('logo','ShopEx-ERP');
            app::get('desktop')->setConf('logo_desc','ShopEx-ERP');
        }

        //套件处理
        if(strstr($data['domain'], 'eip-erp.shopexdrp.cn')) {
            $this->shell->exec_command('install bizcloud');
        }

        kernel::single('base_initial', 'taoexlib')->init();
        return true;
    }

    /**
     * 开通时使用，安装共用的APP
     *
     * @param void
     * @return void
     */
    private function installDefaultApp() {
        foreach((array) $this->deploy_info['default'] as $app){
            $this->shell->exec_command(sprintf("install %s",$app));
        }
    }

    private function updatePrintTmpl(){
        $this->db->exec("DELETE FROM sdb_ome_print_tmpl");
        $this->_default_dlytpl();
        $otmpl = array(
            'merge' => array(
                'name'=> '打印联合模板',
                'defaultPath' => '/admin/delivery/merge_print',
                'app' => 'ome',
                'printpage' => 'admin/delivery/print.html'
            ),
            'appropriation'=>array(
                'name' => '调拔单打印模板',
                'defaultPath' => '/admin/appropriation/printtemp',
                'app'=>'taoguanallocate',
                'printpage'=>'admin/print.html'
            ),
        );
        foreach ($otmpl as $key=>$value) {
            $this->deleteMerge($key);

            $printTxt = $this->getDefaultTmpl($value['app'],$value['defaultPath']);
            $data = array(
                'title' => '默认'.$value['name'],
                'type' => $key,
                'content' => addslashes($printTxt),
                'is_default' => 'true',
                'last_modified' => time(),
                'open' => 'true',
            );
            $this->saveOtmpl($data);
        }

        $this->ilog('更新联合打印模板');
    }

    private function tmplKvCacheClean(){
        $sql = "select `key` from sdb_base_kvstore where prefix ='cache/template'";
        $cache_template_tmp = $this->db->select($sql);
        if($cache_template_tmp){
            foreach($cache_template_tmp as $kk =>$vv){
                base_kvstore::instance('cache/template')->store($vv['key'],'',1);
            }
        }

        $sql = "delete from sdb_base_kvstore where prefix ='cache/template'";
        $this->db->exec($sql);
    }

    private function flushPrintTmpl(){
        $sql = "update sdb_ome_print_otmpl set content=replace(content,'app=ome','app=wms') where type='delivery' or type='stock' or type='merge'";
        $this->db->exec($sql);
    }

    private function flushDlyConf(){
        $all_settings =array(
            'ome.delivery.check' => 'wms.delivery.check',
            'ome.delivery.check_show_type' => 'wms.delivery.check_show_type',
            'ome.delivery.check_ident' => 'wms.delivery.check_ident',
            'ome.delivery.weight' => 'wms.delivery.weight',
        	'ome.delivery.weightwarn' => 'wms.delivery.weightwarn',
            'ome.delivery.minWeight' => 'wms.delivery.minWeight',
            'ome.delivery.maxWeight' => 'wms.delivery.maxWeight',
            'ome.delivery.cfg.radio' => 'wms.delivery.cfg.radio',
            'ome.delivery.min_weightwarn' => 'wms.delivery.min_weightwarn',
            'ome.delivery.max_weightwarn' => 'wms.delivery.max_weightwarn',
            'ome.delivery.maxpercent' => 'wms.delivery.maxpercent',
            'ome.delivery.minpercent' => 'wms.delivery.minpercent',
            'ome.delivery.problem_package' => 'wms.delivery.problem_package',
            'ome.groupCalibration.intervalTime' => 'wms.groupCalibration.intervalTime',
            'ome.groupDelivery.intervalTime' => 'wms.groupDelivery.intervalTime',
            'ome.delivery.status.cfg' => 'wms.delivery.status.cfg',
            'lastGroupCalibration' => 'lastGroupCalibration',
            'lastGroupDelivery' => 'lastGroupDelivery',
        );

        foreach($all_settings as $old => $new){
            $data ='';
            $new_data = array();
            if($old == 'ome.delivery.status.cfg'){
                $data = app::get('ome')->getConf($old);
                if($data){
                    foreach ($data['set'] as $k => $val){
                        if($k == 'single' || $k == 'multi'){
                            foreach($data['set'][$k] as $kk => $vv){
                                $new_k = str_replace('ome_','wms_',$kk);
                                $new_data['set'][$k][$new_k] = $vv;
                            }
                        }else{
                            $new_k = str_replace('ome_','wms_',$k);
                            $new_data['set'][$new_k] = $val;
                        }
                    }
                    app::get('wms')->setConf($new,$new_data);
                }
            }else{
                $data = app::get('ome')->getConf($old);
                if($data){
                    app::get('wms')->setConf($new,$data);
                }
            }
        }
    }

    private function migratePrintTmpl(){
        $sql = "insert into sdb_wms_print_tmpl select * from sdb_ome_print_tmpl";
        $this->db->exec($sql);

        $sql = "insert into sdb_wms_print_tag select * from sdb_ome_print_tag";
        $this->db->exec($sql);
    }

    private function migrateEctools(){
        /*
        $sql = "truncate table sdb_eccommon_regions";
        $this->db->exec($sql);

        $sql = "insert into sdb_eccommon_regions select * from sdb_ectools_regions";
        $this->db->exec($sql);
        */

        $this->shell->exec_command("uninstall ectools");
    }

    private function updateBranch(){
        $sql = "INSERT INTO `sdb_channel_channel` (`channel_id`, `channel_bn`, `channel_name`, `channel_type`, `config`, `crop_config`, `last_download_time`, `last_upload_time`, `active`, `disabled`, `last_store_sync_time`, `area`, `zip`, `addr`, `default_sender`, `mobile`, `tel`, `filter_bn`, `bn_regular`, `express_remark`, `delivery_template`, `order_bland_template`, `node_id`, `node_type`, `secret_key`, `memo`, `api_version`, `addon`) VALUES
        (1, 'selfwms', '默认自有仓储', 'wms', NULL, NULL, NULL, NULL, 'false', 'false', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'false', NULL, NULL, NULL, NULL, 'selfwms', 'selfwms', '', NULL, NULL, NULL);
        ";
        $this->db->exec($sql);

        $updatesql = "UPDATE sdb_ome_branch SET wms_id=1";
        $this->db->exec($updatesql);

        $sql = "INSERT INTO `sdb_channel_adapter` (`channel_id`, `adapter`) VALUES(1, 'selfwms');";
        $this->db->exec($sql);
    }
/////////////////////////////////////////////// 打印模版

    /**
     * 更新物流信息
     */
    private function _default_dlytpl(){
        $dlytpl_dir = ROOT_DIR."/app/ome/initial/dlytpl/";

        if($handle = opendir($dlytpl_dir)){
            while(false !== ($dtp = readdir($handle))){
                $path_parts = pathinfo($dtp);
                if($path_parts['extension'] == 'dtp'){
                    $file['tmp_name'] = ROOT_DIR."/app/ome/initial/dlytpl/".$dtp;
                    $file['name'] = $dtp;
                    $result = kernel::single("ome_print_tmpl")->upload_tmpl($file);
                }
            }

            closedir($handle);
        }
    }

    private function deleteMerge($type)
    {
        $sql = 'DELETE FROM `sdb_ome_print_otmpl` WHERE type=\''.$type.'\' AND  is_default=\'true\'';
        $this->db->exec($sql);
    }

    private function saveOtmpl($data)
    {
        $sql = 'INSERT INTO `sdb_ome_print_otmpl` (`'.implode('`,`',array_keys($data)).'`) VALUES(\''.implode('\',\'',array_values($data)).'\')';
        $this->db->exec($sql);
        $id = $this->db->lastinsertid();
        $path = 'admin/print/otmpl/'.$id;
        $sql = 'UPDATE `sdb_ome_print_otmpl` SET path=\''.$path.'\' WHERE id='.$id;
        $this->db->exec($sql);
    }

    // 获取打印类型
    private function getDefaultTmpl($app,$name)
    {
        $sql = 'SELECT content FROM sdb_ome_print_tmpl_diy WHERE app=\''.$app.'\' AND active=\'true\' AND tmpl_name=\''.$name.'\' ';
        $row = $this->db->selectrow($sql);
        # 读页面
        if ($row && false) {
            //去除JS 换成HTML的JS
            $file = ROOT_DIR.'/app/'.$app.'/view/'.$name.'.html';

            $contents = filterBody($row['content'],$file);
        }else{
            $file = ROOT_DIR.'/app/'.$app.'/view/'.$name.'.html';
            $contents =  file_get_contents($file);
        }

        return $contents;
    }

    private function filterBody($body,$file='')
    {
        $body = htmlspecialchars_decode($body);
        //过滤js
        $body = preg_replace('/<script[^>]*>([\s\S]*?)<\/script>/i',' ',$body);

        $contents =  file_get_contents($file);
        $re = preg_match_all('/<script[^>]*>([\s\S]*?)<\/script>/i',$contents,$matches);
        if ($re) {
            foreach ($matches[0] as $value) {
                $body .= $value;
            }
        }

        $body = htmlspecialchars($body);

        return $body;
    }
///////////////////////////////////////////////


    private function saveInventory()
    {
        $sql = 'SELECT eid FROM `sdb_taoguaninventory_encoded_state` WHERE name=\'inventory\' ';
        $row = $this->db->selectrow($sql);
        if(!$row){
            $sql = "INSERT INTO `sdb_taoguaninventory_encoded_state` (`name`,`head`,`currentno`,`bhlen`,`description`) VALUES('inventory','PD','0','4','盘点表')";
            $this->db->exec($sql);
        }

    }

    private function saveIostockType()
    {
        $sql = 'SELECT type_id FROM sdb_ome_iostock_type WHERE type_id=500 ';
        $row = $this->db->selectrow($sql);
        if(!$row){
            $sql = "INSERT INTO sdb_ome_iostock_type (`type_id`,`type_name`) VALUES('500','期初')";
            $this->db->exec($sql);
        }
    }

    /**
     * 导入数据，格式为 SQLyog 导出的SQL文件格式 (初始化数据)
     */
    private function importData() {
        $init_db = defined('initDB') ? constant('initDB') : '';
        $fp = ($init_db !== '') ? fopen(ROOT_DIR . '/script' . $init_db, 'r') : false;
        if ($fp) {
            $sql =  '';
            while($line = fgets($fp)) {
               $sql .= $line;
               if (preg_match('/;[\n\r\t]{0,}$/is', $line)) {

                   if (preg_match('/\*\/[\n\r\t]{0,}$/is', $line)) {

                       $sql = '';
                   } else {
                       //$sql = preg_replace('', '', $sql);
                       $this->db->query($sql);
                       $sql = '';
                   }
               }elseif (preg_match('/\*\/[\n\r\t]{0,}$/is', $line)) {

                   $sql = '';
               }
            }

            fclose($fp);
        } else {

            $this->ilog("Can't open Init SQL file");
        }
    }

    public function updateWaybillPrintTmpl(){
        $templateList = $rows = array();
        $tmplObj = app::get("ome")->model("print_tmpl");
        $templateObj = app::get("logisticsmanager")->model("express_template");
        $charsetObj = kernel::single('base_charset');
        $rows = $tmplObj->getList();
        foreach($rows as $val) {
            $tmplData = $prt_tmpl_data = array();
            $newTmplStr = $imgUrl = "";
            $width = $height = 0;

            //转换背景,计算宽、高
            $imgUrl = $this->getImgUrl($val['file_id']);
            if ($imgUrl) {
                list($widthImg, $heightImg) = getimagesize($imgUrl);
                $width = intval($widthImg*25.4/96);
                $height = intval($heightImg*25.4/96);
            } else {
                $width = intval($val['prt_tmpl_width']);
                $height = intval($val['prt_tmpl_height']);
                $imgUrl = 'NONE';
            }
            $newTmplStr .= "paper:".$width.",".$height.",".$imgUrl.";\n";

            //解析老模板数据
            $prt_tmpl_data = $this->xml_to_array(urldecode($val['prt_tmpl_data']));
            foreach($prt_tmpl_data['printer']['item'] as $k => $v) {
                //判断标签类型
                if ($v['ucode'] == 'text') {
                    $newTmplStr .= "report_label:";
                    $ucode = '';
                } else {
                    $newTmplStr .= "report_field:";
                    $ucode = $v['ucode'];
                }

                //解码标签名称和字体
                $v['name'] = $this->unescape($v['name']);
                $v['font'] = $this->unescape($v['font']);
                
                // 订单-物品数量 转 快递单-物品数量
                if($v['name'] == '订单-物品数量'){
                    $v['name'] = '快递单-物品数量';
                }

                //转换样式信息
                $font = ($v['font']=='undefined') ? '宋体' : $this->unescape($v['font']);
                $fontsize = intval($v['fontsize']*72*10/96);
                switch($v['align']) {
                    case 'center' :
                        $align = 1;
                        break;
                    case 'right' :
                        $align = 2;
                        break;
                    default:
                        $align = 0;
                        break;
                }

                //计算标签位置
                $position = array();
                $position = explode(':',$v['position']);
                $left = number_format($position[0]/96,6);
                $top = number_format($position[1]/96,6);
                $right = number_format(($left+$position[2]/96),6);
                $bottom = number_format(($top+$position[3]/96),6);

                //生成模板数据
                $newTmplStr .= $left.",".$top.",".$right.",".$bottom.",".$v['name'].",".$ucode.",0,".$font.",".$fontsize.",".$v['border'].",".$v['italic'].",0,0,0,".$align.",0;\n";

                //$tmplData[$k] = $v;
                unset($v);
            }
            $newTmplData = array();
            $newTmplData['template_id'] = $val['prt_tmpl_id'];
            $newTmplData['template_name'] = $val['prt_tmpl_title'];
            $newTmplData['template_type'] = 'normal';
            $newTmplData['status'] = $val['shortcut'];
            $newTmplData['template_width'] = $width;
            $newTmplData['template_height'] = $height;
            $newTmplData['file_id'] = $val['file_id'];
            $newTmplData['template_data'] = $newTmplStr;
            $templateObj->insert($newTmplData);
            unset($val);
        }

        //add extend waybill template
        $this->_addExtendWaybillTmpl();
    }

    private function _addExtendWaybillTmpl(){
        $dlytpl_dir = ROOT_DIR."/app/logisticsmanager/initial/tpl/";

        if($handle = opendir($dlytpl_dir)){
            while(false !== ($dtp = readdir($handle))){
                $path_parts = pathinfo($dtp);
                if($path_parts['extension'] == 'dtp'){
                    $file['tmp_name'] = $dlytpl_dir.$dtp;
                    $file['name'] = $dtp;
                    $result = kernel::single('logisticsmanager_print_tmpl')->upload_tmpl($file);
                }
            }
            closedir($handle);
        }
    }

    private function xml_to_array( $xml )
    {
        $reg = "/<(\\w+)[^>]*?>([\\x00-\\xFF]*?)<\\/\\1>/";
        if(preg_match_all($reg, $xml, $matches))
        {
            $count = count($matches[0]);
            $arr = array();
            for($i = 0; $i < $count; $i++)
            {
                $key = $matches[1][$i];
                $val = $this->xml_to_array( $matches[2][$i] );  // 递归
                if(array_key_exists($key, $arr))
                {
                    if(is_array($arr[$key]))
                    {
                        if(!array_key_exists(0,$arr[$key]))
                        {
                            $arr[$key] = array($arr[$key]);
                        }
                    }else{
                        $arr[$key] = array($arr[$key]);
                    }
                    $arr[$key][] = $val;
                }else{
                    $arr[$key] = $val;
                }
            }
            return $arr;
        }else{
            return $xml;
        }
    }

    private function unescape($str){
        $ret = '';
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++){
            if ($str[$i] == '%' && $str[$i+1] == 'u'){
                $val = hexdec(substr($str, $i+2, 4));
                if ($val < 0x7f) {
                    $ret .= chr($val);
                } else if($val < 0x800) {
                    $ret .= chr(0xc0|($val>>6)).chr(0x80|($val&0x3f));
                } else {
                    $ret .= chr(0xe0|($val>>12)).chr(0x80|(($val>>6)&0x3f)).chr(0x80|($val&0x3f));
                }
                $i += 5;
            } else if ($str[$i] == '%') {
                $ret .= urldecode(substr($str, $i, 3));
                $i += 2;
            } else {
                $ret .= $str[$i];
            }
        }
        return $ret;
    }

    private function getImgUrl($file){
        $ss = kernel::single('base_storager');
        $url = $ss->getUrl($file,"file");

        return $url;
    }

    /**
     * 日志
     */
    public function ilog($str) {

        $filename = ROOT_DIR . '/script/logs/' . date('Y-m-d') . '.log';
        $fp = fopen($filename, 'a');
        fwrite($fp, date("m-d H:i") . "\t" . $this->domain . "\t" . $str . "\n");
        fclose($fp);
    }

}
