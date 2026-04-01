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


class ome_func
{
    /**
     * 地区字符串格式验证
     * 正则匹配地区是否为本系统的标准地区格式，标准格式原样返回，非标准格式试图转换标准格式返回，否则
     * @access static
     * @param string $area 待验证地区字符串
     * @return string 转换后的本系统标准格式地区
     */
    public function region_validate(&$area){
        $is_correct_area = $this->is_correct_region($area);
        if (!$is_correct_area){
            //非标准格式进行转换
            $this->local_region($area);
        }
    }

    /**
     * ECOS本地标准地区格式判断
     * @access public
     * @param string $area 地区字符串，如：malind:上海/徐汇区:22
     * @return boolean
     */
    public function is_correct_region($area){
        $pattrn = "/^([a-zA-Z]+)\:(\S+)\:(\d+)$/";
        if (preg_match($pattrn, $area)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 本系统标准地区格式转换
     * 正则匹配地区是否为本系统的标准地区格式，转换成功返回标准地区格式，转换失败原地区字符串返回
     * @access static
     * @param string $area 待转换地区字符串
     * @return string  转换后的本系统标准格式地区
    */
    public function local_region(&$area)
    {
        $regionObj = app::get('eccommon')->model('regions');
        
        $tmp_area = explode("/",$area);
        
        //地区初始值临时存储
        $ini_first_name = trim($tmp_area[0]); //省
        $ini_second_name = trim($tmp_area[1]); //市
        $ini_third_name = trim($tmp_area[2]); //区
        
        $ini_four_name = trim($tmp_area[3]); //镇、街道
        $ini_five_name = trim($tmp_area[4]); //村
        
        //$tmp_area2 = preg_replace("/省|市|县|区/","",$tmp_area);
        $tmp_area2 = preg_replace("/省|市|县|区|镇|乡/","",$tmp_area);
        $first_name = trim($tmp_area2[0]);
        
        //自治区兼容
        $tmp_first_name = $this->area_format($first_name);
        if ($tmp_first_name) $first_name = $tmp_first_name;
        
        $second_name = trim($tmp_area2[1]);
        $third_name = trim($tmp_area2[2]);
        
        //新增四、五级地区
        $four_name = trim($tmp_area2[3]); //镇、街道
        $five_name = trim($tmp_area2[4]); //村
        
        //获取省region_id
        $region_first = $region_second = $region_third = [];
        if ($first_name){
            //TODO：针对北京省份数据存在BOM头进行兼容
            if (strstr($first_name, '北京')){
                $bom_first_name = chr(239).chr(187).chr(191).$first_name;
                $region_first = $regionObj->dump(array('local_name|head'=>$bom_first_name,'region_grade'=>'1'), 'package,region_id,local_name');
                if (empty($region_first)){
                  $region_first = $regionObj->dump(array('local_name|head'=>$first_name,'region_grade'=>'1'), 'package,region_id,local_name');
                }
            }else{
                $region_first = $regionObj->dump(array('local_name|head'=>$first_name,'region_grade'=>'1'), 'package,region_id,local_name');
            }
            
            $first_name = $region_first['local_name'];
            
            if (!$first_name){
                $region_first = array(
                    'local_name' =>$ini_first_name,
                    'package' =>'mainland',
                    'region_grade' =>'1',
                );
                $region_first['source'] = 'platform';
                $regionObj->save($region_first);
                $first_name = $region_first['local_name'];
                $region_path = ",".$region_first['region_id'].",";
                
                //更新region_path字段
                $regionObj->update(array('region_path'=>$region_path), array('region_id'=>$region_first['region_id']));
            }
        }
        
        //获取市region_id
        if ($second_name){
            //精确查找
            $second_filter = array('local_name'=>trim($tmp_area[1]),'region_grade'=>'2','p_region_id'=>$region_first['region_id']);
            $region_second = $regionObj->dump($second_filter, 'package,region_id,p_region_id,local_name');
            
            if (empty($region_second['local_name'])){
                //模糊查找
                $second_filter = array('local_name|head'=>$second_name,'region_grade'=>'2','p_region_id'=>$region_first['region_id']);
                $region_second = $regionObj->dump($second_filter, 'package,region_id,p_region_id,local_name');
            }
            
            $second_name = $region_second['local_name'];
            if (!$second_name){
                $region_second = array(
                    'local_name' =>$ini_second_name,
                    'p_region_id' =>$region_first['region_id'],
                    'package' =>'mainland',
                    'region_grade' =>'2',
                );
                $region_second['source'] = 'platform';
                $regionObj->save($region_second);
                $second_name = $region_second['local_name'];
                $region_path = ",".$region_first['region_id'].",".$region_second['region_id'].",";
                
                //更新region_path字段
                $regionObj->update(array('region_path'=>$region_path), array('region_id'=>$region_second['region_id']));

                //添加二级地区后更新一级地区的
                $regionObj->update(array('haschild'=>1), array('region_id'=>$region_first['region_id']));
            }
        }
        
        //获取区、县region_id
        if ($third_name){
            if (!$region_second['region_id']){
                //先根据第三级查出所有第二级
                $filter = array('local_name|head'=>$third_name);
                
                $regions = $regionObj->getList('p_region_id', $filter, 0, -1);
                if ($regions){
                    foreach ($regions as $k=>$v){
                        $region_second_tmp = $regionObj->dump(array('region_id'=>$v['p_region_id'],'region_grade'=>'2'), 'region_path,package,region_id,p_region_id,local_name');
                        
                        $tmp = explode(",",$region_second_tmp['region_path']);
                        if (in_array($region_first['region_id'],$tmp)){
                            $region_second = $region_second_tmp;
                            $second_name = $region_second['local_name'];
                            break;
                        }
                    }
                }
            }
            
            //精确查找
            $third_filter = array('local_name'=>trim($tmp_area[2]),'region_grade'=>'3','p_region_id'=>$region_second['region_id']);
            $region_third = $regionObj->dump($third_filter, 'package,region_id,p_region_id,local_name');
            if (empty($region_third['local_name'])){
                //模糊查找
                $third_filter = array('local_name|head'=>$third_name,'region_grade'=>'3','p_region_id'=>$region_second['region_id']);
                
                $region_third = $regionObj->dump($third_filter, 'package,region_id,p_region_id,local_name');
            }
            
            $third_name = $region_third['local_name'];
            if (!$third_name){
                if ($region_second['region_id']) {
                    $region_third = array(
                        'local_name' =>$ini_third_name,
                        'p_region_id' =>$region_second['region_id'],
                        'package' =>'mainland',
                        'region_grade' =>'3',
                    );
                    $region_third['source'] = 'platform';
                    $regionObj->save($region_third);
                    
                    $third_name = $region_third['local_name'];
                    $region_path = ",".$region_first['region_id'].",".$region_second['region_id'].",".$region_third['region_id'].",";
                    
                    //更新region_path字段
                    $regionObj->update(array('region_path'=>$region_path), array('region_id'=>$region_third['region_id']));

                    //添加三级地区后更新二级地区的
                    $regionObj->update(array('haschild'=>1), array('region_id'=>$region_second['region_id']));
                }else{
                    $region_third = $regionObj->dump(array('local_name|head'=>$tmp_area[2],'p_region_id'=>$region_first['region_id']), 'package,region_id,p_region_id,local_name');
                    if ($region_third) {
                        $third_name = $tmp_area[2];
                    }
                }
            }
        }
        
        //[新增四级地区]获取镇、街道region_id
        if($four_name && $region_third['region_id']){
            //精确查找
            $four_filter = array('local_name'=>trim($tmp_area[3]), 'region_grade'=>'4', 'p_region_id'=>$region_third['region_id']);
            $region_four = $regionObj->dump($four_filter, 'package,region_id,p_region_id,local_name');
            if(empty($region_four['local_name'])){
                //模糊查找
                $four_filter = array('local_name|head'=>$four_name, 'region_grade'=>'4', 'p_region_id'=>$region_third['region_id']);
                $region_four = $regionObj->dump($four_filter, 'package,region_id,p_region_id,local_name');
            }
            
            $four_name = $region_four['local_name'];
            
            //新添加四级地区
            if(!$four_name){
                $region_four = array(
                        'local_name' =>$ini_four_name,
                        'p_region_id' =>$region_third['region_id'],
                        'package' =>'mainland',
                        'region_grade' =>'4',
                        'ordernum' => '99',
                );
                $regionObj->save($region_four);
                
                $four_name = $region_four['local_name'];
                
                //更新region_path字段
                $region_path = ','. $region_first['region_id'] .','. $region_second['region_id'] .','. $region_third['region_id'] .',';
                $region_path .= $region_four['region_id'] .',';
                $regionObj->update(array('region_path'=>$region_path), array('region_id'=>$region_four['region_id']));
                
                //更新三级地区有子记录
                $regionObj->update(array('haschild'=>1), array('region_id'=>$region_third['region_id']));
            }
        }
        
        //[新增五级地区]获取乡region_id
        if($five_name && $region_four['region_id']){
            //精确查找
            $five_filter = array('local_name'=>trim($tmp_area[4]), 'region_grade'=>'5', 'p_region_id'=>$region_four['region_id']);
            $region_five = $regionObj->dump($five_filter, 'package,region_id,p_region_id,local_name');
            if (empty($region_five['local_name'])){
                //模糊查找
                $five_filter = array('local_name|head'=>$five_name, 'region_grade'=>'5', 'p_region_id'=>$region_four['region_id']);
                $region_five = $regionObj->dump($five_filter, 'package,region_id,p_region_id,local_name');
            }
            $five_name = $region_five['local_name'];
            
            //新添加五级地区
            if (!$five_name){
                $region_five = array(
                        'local_name' =>$ini_five_name,
                        'p_region_id' =>$region_four['region_id'],
                        'package' =>'mainland',
                        'region_grade' =>'5',
                        'ordernum' => '99',
                );
                $region_five['source'] = 'platform';
                $regionObj->save($region_five);
                
                $five_name = $region_five['local_name'];
                
                //更新region_path字段
                $region_path = ','.$region_first['region_id'].','.$region_second['region_id'].','.$region_third['region_id'].',';
                $region_path .= $region_four['region_id'] . ',' . $region_five['region_id'] . ',';
                $regionObj->update(array('region_path'=>$region_path), array('region_id'=>$region_five['region_id']));
                
                //更新四级地区有子记录
                $regionObj->update(array('haschild'=>1), array('region_id'=>$region_four['region_id']));
            }
        }
        
        $return = false;
        
        //region_id
        if ($region_five['region_id']){
            //乡region_id
            $region_id = $region_five['region_id'];
            $package = $region_five['package'];
        }elseif($region_four['region_id']){
            //镇、街道region_id
            $region_id = $region_four['region_id'];
            $package = $region_four['package'];
        }elseif($region_third['region_id']){
            //区、县region_id
            $region_id = $region_third['region_id'];
            $package = $region_third['package'];
        }elseif ($region_second['region_id']){
            //市region_id
            $region_id = $region_second['region_id'];
            $package = $region_second['package'];
        }
        
        //四、五级地址
        if($four_name && $five_name){
            $region_area = array_filter(array($first_name, $second_name, $third_name, $four_name, $five_name));
        }elseif($four_name){
            $region_area = array_filter(array($first_name, $second_name, $third_name, $four_name));
        }else{
            $region_area = array_filter(array($first_name,$second_name,$third_name));
        }
        
        $region_area = implode("/", $region_area);
        
        if ($region_area || $region_id){
            $area = $package.":".$region_area.":".$region_id;
            $return = true;
        }
        
        //去除多余分隔符"/"
        if ($return==false){
            $area = implode("/", array_filter($tmp_area));
        }
    }

    /**
     * 前端店铺三级地区本地临时转换
     * @param $area
     */
    public function area_format($area){
        $area_format = array(
            '内蒙古自治' => '内蒙古',
            '广西壮族自治' => '广西',
            '西藏自治' => '西藏',
            '宁夏回族自治' => '宁夏',
            '新疆维吾尔自治' => '新疆',
            '香港特别行政' => '香港',
            '澳门特别行政' => '澳门',
        );
        if ($area_format[$area]){
            return $area_format[$area];
        }else{
            return false;
        }
    }

    /**
     * 拆分标准格式为：省市县
     * @param string $area
     * @return array 下标从0开始，依次代表：省、市、县
     */
    public function split_area(&$area){
       preg_match("/:(.*):/", $area,$tmp_area);
       if($tmp_area[1]){
           $tmp_area = explode('/', $tmp_area[1]);
           $area = $tmp_area;
       }
    }
    
    /**
     * 数组转换字符串
     * 支持多维数组
     * @access public
     * @param array $data
     * @return string
     */
    static function array2string($data){
        if (!is_array($data)) return null;
        ksort($data, SORT_REGULAR);
        $string = '';
        if ($data)
        foreach ((array)$data as $k=>$v){
            $string .= $k . (is_array($v) ? self::array2string($v) : $v);
        }
        return $string;
    }

    /**
     * 日期型转换时间戳
     * @access public
     * @param string $date_time 日期字符串或时间戳
     * @return int 时间戳
     */
    public function date2time($date_time){
        if (strstr($date_time,'-')){
            return strtotime($date_time);
        }else{
            return $date_time;
        }
    }

    /**
     * 输出订单备注与留言
     * @param string $memo 备注与留言内容：序列化数组
     * serail(array(0=>array('op_name'=>'2','op_time'=>'12342'),1=>array);
     * @return array 标准可直接读取的数组
     */
    public function format_memo($memo){
        if (empty($memo)) return NULL;
        $mark = array();
        if ( !is_array($memo) ){
            $mark = unserialize($memo);
        }
        foreach ((array)$mark as $k=>$v){
            if (!strstr($v['op_time'], "-")){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $mark[$k]['op_time'] = $v['op_time'];
            }
        }
        return $mark ? $mark : $memo;
    }

    /**
     * 追加订单备注与留言
     * $new_memo待追加的订单备注类型可为数组或字符串:
     * 数组：array('op_name'=>'操作员姓名','op_content'=>'备注内容','op_time'=>'操作时间')
     * 字符串：则op_content为字符串内容,op_name为当前登录用户的姓名,op_time为当前系统操作时间
     * @access public
     * @param mixed $new_memo 待追加的订单备注(类型可为数组或字符串)
     * @param mixed $old_memo 订单备注/留言原始数据
     * @return Serialize 数组
     */
    public function append_memo($new_memo,$old_memo=''){
        if ( empty($new_memo) ) return NULL;
        $append_memo = array();
        $memo = array();
        $op_name = kernel::single('desktop_user')->get_name();
        $time = time();
        //待追加的内容
        if ( is_array($new_memo) ){
            $op_name = $new_memo['op_name'] ? $new_memo['op_name'] : $op_name;
            $op_content = $new_memo['op_content'];
            $op_time = $this->date2time($new_memo['op_time']);
        }else{
            $op_content = $new_memo;
            $op_time = $time;
        }
        $append_memo = array(
            'op_name' => $op_name,
            'op_time' => $op_time,
            'op_content' => $op_content,
        );
        //订单备注/留言原始数据
        if ($old_memo){
            if ( !is_array($old_memo) ){
                $old_memo = unserialize($old_memo);
            }
            foreach($old_memo as $k=>$v){
                $memo[] = $v;
            }
        }
        $memo[] = $append_memo;
        return $memo;
    }

    /**
     * 计算两个时间的差值转换到日期
     *
     * @param $time1
     * @param $time2
     * @return array
     */
    public function toTimeDiff($time1,$time2){
        $arr_time_diff = array('d'=>0,'h'=>0,'m'=>0,'i'=>0);
        $time_diff = $time1 - $time2;
        $k = 86400;
        $arr_time_diff['d'] = intval($time_diff / $k);
        $time_diff = $time_diff % $k;
        $k = $k/24;
        $arr_time_diff['h'] = intval($time_diff/$k);
        $time_diff = $time_diff % $k;
        $k = $k/60;
        $arr_time_diff['m'] = intval($time_diff/$k);
        $arr_time_diff['i'] = intval($time_diff%$k);

        return $arr_time_diff;
    }

    /**
     * 得到后台登录的管理员信息
     *
     * return array
     */
    public function getDesktopUser(){
        $opInfo['op_id'] = kernel::single('desktop_user')->get_id();
        $opInfo['op_name'] = kernel::single('desktop_user')->get_name();

        if(empty($opInfo['op_id'])){
            $opInfo = $this->get_system();
        }
        return $opInfo;
    }
    
    /**
     * 获取system账号信息，写死。
     */
    public function get_system(){
        $opInfo = array(
            'op_id' => 16777215,
            'op_name' => 'system'
        );
        return $opInfo;
    }

    /**
     * 去除字符BOM头
     * @param array or string $data 字符或数组
     * @return array or string 非BOM头字符串
     */
    static public function strip_bom($data=NULL){
        if (empty($data)) return NULL;
        if(is_array($data)){
            foreach($data as $k=>$v){
                $charset[1] = substr($v, 0, 1);
                $charset[2] = substr($v, 1, 1);
                $charset[3] = substr($v, 2, 1);
                if (ord($charset[1]) == 239 && ord($charset[2]) == 187 && ord($charset[3]) == 191) {
                    $data[$k] = substr($v, 3);
                }
            }
        }else{
            $charset[1] = substr($data, 0, 1);
            $charset[2] = substr($data, 1, 1);
            $charset[3] = substr($data, 2, 1);
            if (ord($charset[1]) == 239 && ord($charset[2]) == 187 && ord($charset[3]) == 191) {
                $data = substr($data, 3);
            }
        }
        return $data;
    }

    /**
     * 修正菜单用
     *
     */
    public function disable_menu($type=''){
        if(empty($type)){
            $type = 'all';
        }

        switch($type){
            case 'ectools':
                $this->_disabe_menu_ectools();
                break;
            case 'image':
                $this->_disable_menu_image();
                break;
            case 'desktop':
                $this->_disable_menu_desktop();
                break;
            case 'all':
                $this->_disabe_menu_ectools();
                $this->_disable_menu_image();
                $this->_disable_menu_desktop();
                break;
        }
    }

    private function _disabe_menu_ectools(){
        kernel::database()->exec("UPDATE sdb_desktop_menus SET disabled='true',display='false' WHERE workground='ectools.wrokground.order' AND app_id='ectools'");
        kernel::database()->exec("UPDATE sdb_desktop_menus SET disabled='true',display='false' WHERE menu_type='permission' AND app_id='ectools' AND permission<>'regions'");
        kernel::database()->exec("UPDATE sdb_desktop_menus SET disabled='true',display='false' WHERE menu_type='panelgroup' AND menu_title='支付与货币'");
        kernel::database()->exec("UPDATE sdb_desktop_menus SET disabled='true',display='false' WHERE menu_type='adminpanel' AND menu_path IN ('app=ectools&ctl=currency&act=index','app=ectools&ctl=payment_cfgs&act=index','app=ectools&ctl=setting&act=index','app=ectools&ctl=admin_payment_notice&act=index')");
    }

    private function _disable_menu_image(){
        kernel::database()->exec("UPDATE sdb_desktop_menus SET disabled='true',display='false' WHERE menu_type='workground' AND app_id='image'");
        kernel::database()->exec("UPDATE sdb_desktop_menus SET disabled='true',display='false' WHERE menu_type='permission' AND app_id='image'");
        kernel::database()->exec("UPDATE sdb_desktop_menus SET disabled='true',display='false' WHERE menu_type='panelgroup' AND menu_title='图片管理'");
        kernel::database()->exec("UPDATE sdb_desktop_menus SET disabled='true',display='false' WHERE menu_type='adminpanel' AND menu_path IN ('app=image&ctl=admin_manage&act=index','app=image&ctl=admin_manage&act=imageset')");
    }

    private function _disable_menu_desktop(){
        kernel::database()->exec("UPDATE sdb_desktop_menus SET disabled='false',display='true' WHERE menu_type='permission' AND app_id='desktop' AND permission='performance'");
        kernel::database()->exec("UPDATE sdb_desktop_menus SET disabled='false',display='true' WHERE menu_type='permission' AND app_id='desktop' AND permission='setting'");
    }
    //---------------修正菜单结束---------------//

    /**
     * 获取insert sql语句
     * @access static public
     * @param Object $model model对象
     * @param Array $data 需插入的关联(字段)数组数据,支持多维
     * @return String insert sql语句
     */
    static public function get_insert_sql($model,$data){
        if (empty($model) || empty($data)) return NULL;

        $cols = $model->_columns();
        $strValue = $insert_data = $column_type = array();
        $strFields = '';

        $rs = $model->db->exec('select * from `'.$model->table_name(1).'` where 0=1');
        $col_count = mysql_num_fields($rs['rs']);

        $tmp_data = $data;
        if (!is_array(array_pop($tmp_data))){
            $insert_data[] = $data;
        }else{
            $insert_data = $data;
        }
        unset($tmp_data);

        foreach ($insert_data as $key=>$value){
            $insertValues = array();
            if (!empty($strFields)){
                $col_count = count($strFields);
            }
            for($i=0;$i<$col_count;$i++) {
                if (empty($strFields)){
                    $column = mysql_fetch_field($rs['rs'],$i);
                    $k = $column->name;
                    $column_type[$k] = $column->type;
                    if( !isset($value[$k]) ){
                        continue;
                    }
                }else{
                    $k = $strFields[$i];
                }
                $p = $cols[$k];

                if(!isset($p['default']) && $p['required'] && $p['extra']!='auto_increment'){
                    if(!isset($value[$k])){
                        trigger_error(($p['label']?$p['label']:$k).app::get('base')->_('不能为空！'),E_USER_ERROR);
                    }
                }

                if( $value[$k] !== false ){
                    if( $p['type'] == 'last_modify' ){
                        $insertValues[$k] = time();
                    }elseif( $p['depend_col'] ){
                        $dependColVal = explode(':',$p['depend_col']);
                        if( $value[$dependColVal[0]] == $dependColVal[1] ){
                            switch( $dependColVal[2] ){
                                case 'now':
                                    $insertValues[$k] = time();
                                    break;
                            }
                        }
                    }
                }

                if( $p['type']=='serialize' ){
                    $value[$k] = serialize($value[$k]);
                }
                if( !isset($value[$k]) && $p['required'] && isset($p['default']) ){
                    $value[$k] = $p['default'];
                }
                $insertValues[$k] = base_db_tools::quotevalue($model->db,$value[$k],$column_type[$k]);
            }
            if (empty($strFields)){
                $strFields = array_keys($insertValues);
            }
            $strValue[] = "(".implode(',',$insertValues).")";
        }

        $strFields = implode('`,`', $strFields);
        $strValue = implode(',', $strValue);
        $sql = 'INSERT INTO `'.$model->table_name(true).'` ( `'.$strFields.'` ) VALUES '.$strValue;

        return $sql;
    }
    static public function get_replace_sql($model,$data){
        if (empty($model) || empty($data)) return NULL;
        
        $cols = $model->_columns();
        $strValue = $insert_data = $column_type = array();
        $strFields = '';
        
        $rs = $model->db->exec('select * from `'.$model->table_name(1).'` where 0=1');
        $col_count = mysql_num_fields($rs['rs']);
        
        $tmp_data = $data;
        if (!is_array(array_pop($tmp_data))){
            $insert_data[] = $data;
        }else{
            $insert_data = $data;
        }
        unset($tmp_data);
        
        foreach ($insert_data as $key=>$value){
            $insertValues = array();
            if (!empty($strFields)){
                $col_count = count($strFields);
            }
            for($i=0;$i<$col_count;$i++) {
                if (empty($strFields)){
                    $column = mysql_fetch_field($rs['rs'],$i);
                    $k = $column->name;
                    $column_type[$k] = $column->type;
                    if( !isset($value[$k]) ){
                        //continue;   
                    }
                }else{
                    $k = $strFields[$i];
                }
                $p = $cols[$k];
                
                if(!isset($p['default']) && $p['required'] && $p['extra']!='auto_increment'){
                    if(!isset($value[$k])){
                        trigger_error(($p['label']?$p['label']:$k).app::get('base')->_('不能为空！'),E_USER_ERROR);
                    }
                }
                
                if( $value[$k] !== false ){
                    if( $p['type'] == 'last_modify' ){
                        $insertValues[$k] = time();
                    }elseif( $p['depend_col'] ){
                        $dependColVal = explode(':',$p['depend_col']);
                        if( $value[$dependColVal[0]] == $dependColVal[1] ){
                            switch( $dependColVal[2] ){
                                case 'now':
                                    $insertValues[$k] = time();
                                    break;
                            }
                        }
                    }
                }
                
                if( $p['type']=='serialize' ){
                    $value[$k] = serialize($value[$k]);
                }
                if( !isset($value[$k]) && $p['required'] && isset($p['default']) ){
                    $value[$k] = $p['default'];
                }
                $insertValues[$k] = base_db_tools::quotevalue($model->db,$value[$k],$column_type[$k]);
            }
            if (empty($strFields)){
                $strFields = array_keys($insertValues);
            }
            $strValue[] = "(".implode(',',$insertValues).")";
        }
        
        $strFields = implode('`,`', $strFields);
        $strValue = implode(',', $strValue);
        $sql = 'REPLACE INTO `'.$model->table_name(true).'` ( `'.$strFields.'` ) VALUES '.$strValue;
        
        return $sql;
    }
    public function getApiResponse($data){
        $return = array(
                'rsp'=>'succ',
                'data'=>$data
        );

        return $return;
    }

     public function getErrorApiResponse($data){
        $return = array(
                'rsp'=>'fail',
                'res'=>$data
        );

        return $return;
     }

    static function class_exists($class_name)
    {
        $p = strpos($class_name,'_');

        if($p){
            $owner = substr($class_name,0,$p);
            $class_name = substr($class_name,$p+1);
            $tick = substr($class_name,0,4);
            if(defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR.'/'.$owner.'/lib/'.str_replace('_','/',$class_name).'.php')){
                $path = CUSTOM_CORE_DIR.'/'.$owner.'/lib/'.str_replace('_','/',$class_name).'.php';
            }else{
                $path = APP_DIR.'/'.$owner.'/lib/'.str_replace('_','/',$class_name).'.php';
            }
            if(file_exists($path)){
                return true;
            }else{
                return false;
            }
        }
    }
    
    /**
     * 判断是否已到达设定的时间点
     * @param clock int 设置的时间点
     * @param msg int 错误信息
     * @return boolean
     **/
    function isRunTime($clock,&$msg = ''){
       $server_time = date('H:i');
       if($server_time == $clock){
          return true;
       }else{
          $msg = 'time is passed';
          return false;
       }
    }
    
    /**
     * @param params array 需要运算的数据，数组、数值等
     * @param operator string 操作类型 + , - , * , /
     * @param digit int 数值精度
     * @return float
     **/
    static function number_math($params = array(),$operator = '',$digit = 2){
       $mathObj = kernel::single('eccommon_math');
       $mathObj->goodsShowDecimals = $digit;
       $mathObj->operationDecimals = $digit;

       switch($operator){
           case '+':
               $action = 'number_plus';
           break;
           case '-':
               $action = 'number_minus';
           break;
           case '*':
               $action = 'number_multiple';
           break;
           case '/':
               $action = 'number_div';
           break;
           default:
               $action = false;
           break;
       }

       if($action === false){
          return false;
       }else{
          return $mathObj->$action($params);
       }
    }

    //csv导出数据的特殊编码过滤
    public function csv_filter($str, $utfToGbk = true){
        $str = str_replace('&nbsp;', '', $str);
        $str = str_replace(array("\r\n","\r","\n"), '', $str);
        $str = str_replace(array("\"","'"), '“', $str);
        $str = str_replace(',', '', $str);
        $str = strip_tags(html_entity_decode($str, ENT_COMPAT | ENT_QUOTES, 'UTF-8'));
        $str = trim($str);
        if($utfToGbk) $str = mb_convert_encoding($str, 'GBK', 'UTF-8');
        return $str;
    }
    
    /**
     * 手机号验证
     * 
     * @param number $mobile
     * @return bool
     */
    function isMobile($mobile)
    {
        if (!is_numeric($mobile) || strlen($mobile) != 11){
            return false;
        }
        
        //严格：|^14[5,7]{1}\d{8}$、|^17[0,6,7,8]{1}\d{8}$
        return preg_match('#^13[\d]{9}$|^14[\d]{9}$|^15[\d]{9}$|^16[\d]{9}$|^17[\d]{9}$|^18[\d]{9}$|^19[\d]{9}$#', $mobile) ? true : false;
    }

    /**
     * 座机验证
     * 
     * @param string $tel
     * @return bool
     */
    public function isTel($tel)
    {
        if (!$tel) {
            return false;
        }
        $pattern  = "/^400\d{7}$/";
        $pattern1 = "/^\d{1,4}-\d{7,8}(-\d{1,6})?$/i";
        $_rs = preg_match($pattern, $tel);
        $_rs1 = preg_match($pattern1, $tel);
        if ((!$_rs) && (!$_rs1)) {
            return false;
        }
        return true;
    }

    function check_install_invoice(){
        if(!app::get('invoice')->is_installed()){
        return false;
        }
        return true;
    } 

    public function setUser($user_id) {
        $user = app::get('desktop')->model('users')->dump($user_id,'user_id,super,name');
        if (!kernel::single('desktop_user')->user_data) {
            $account = app::get('pam')->model('account')->dump($user_id,'login_name');
            kernel::single('desktop_user')->user_data['name'] = $user['name'];
            kernel::single('desktop_user')->user_data['account']['login_name'] = $account['login_name'];
            kernel::single('desktop_user')->user_data['super'] = $user['super'];
            kernel::single('desktop_user')->user_id = $user_id;
        }
        return $user;
    }
    
    public function judgeFun($v) {
        if(preg_match("/(\w+)\s*\((.*)\)/", trim($v), $m) && $m[1] && function_exists($m[1])) {
            return true;
        }
        if(strpos($m[2], '(')) {
            return $this->judgeFun($m[2]);
        }
        return false;
    }

    public function getEncryptText($string, $isExport = false) {
        $bHelper = kernel::single('base_view_helper');
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($string);

        if ($is_encrypt) {
            if($index = strpos($string, '>>')) {
                return substr($string, 0, $index);
            }
            if($isExport) {
                return $string;
            }
            return $bHelper->modifier_cut($string,'-1',strlen($string) > 11 ?'****':'*',false,true);
        }

        return $string;
    }
    
    /**
     * 一维数组重组为二维数组 以index为key的结果
     * @param $array
     * @param $index
     * @param $extend
     * @param string $delete_field bn,name,num
     * @return mixed
     */
    static public function filter_by_value($array, $index, $extend = '', $delete_field = '')
    {
        if (is_array($array) && count($array) > 0) {
            $deleteFieldList = array();
            if ($delete_field) {
                $deleteFieldList = explode(',', $delete_field);
            }
            foreach (array_keys($array) as $key) {
                $val = $array[$key];
                //删除不需要字段
                if ($deleteFieldList) {
                    foreach ($deleteFieldList as $field) {
                        unset($val[$field]);
                    }
                }
                //相同字段放入一个数组中
                $temp[$key][$index] = $array[$key][$index];
                if ($temp[$key][$index] == $array[$key][$index]) {
                    $newarray[$array[$key][$index]][] = $val;
                }
                //同一组数据，扩展字段作为key
                if ($extend) {
                    $temps[$key][$extend] = $array[$key][$extend];
                    if ($temps[$key][$extend] == $array[$key][$extend]) {
                        $newExtendArray[$array[$key][$extend]][] = $val;
                    }
                }
            }
            if ($newExtendArray) {
                return [$index => $newarray, $extend => $newExtendArray];
            }
        }
        return $newarray;
    }
    
    /**
     * 把返回的数据集转换成Tree
     * @param array $list 要转换的数据集
     * @param string $pid parent标记字段
     * @param string $level level标记字段
     * @return array
     */
    static public function listToTree($list, $pk = 'id', $pid = 'pid', $child = 'children', $root = 0)
    {
        // 创建Tree
        $tree = array();
        if (is_array($list)) {
            // 创建基于主键的数组引用
            $refer = array();
            foreach ($list as $key => $data) {
                $refer[$data[$pk]] =& $list[$key];
            }
            
            foreach ($list as $key => $data) {
                // 判断是否存在parent
                $parentId = $data[$pid];
                if ($root == $parentId) {
                    $tree[] =& $list[$key];
                } else {
                    if (isset($refer[$parentId])) {
                        $parent           =& $refer[$parentId];
                        $parent[$child][] =& $list[$key];
                    }
                }
            }
        }
        return $tree;
    }

    static function cast_index_to_key($arr, $index, $separator='-'){
        $new_arr = array();
        foreach ($arr as $_row) {
            if(is_array($index) && !empty($index)){
                $arr_index = '';
                foreach($index as $val){
                    $arr_index .= $_row[$val].$separator;
                }
                $arr_index = (!empty($arr_index) ? substr($arr_index, 0, -1) : $arr_index);
            }else{
                $arr_index = $_row[$index];
            }
            $new_arr[$arr_index] = $_row;
        }
        return $new_arr;
    }
    
    /**
     * 导出主结构的所有数据(包含finder定义字段、模型导出扩展字段、模型修改字段内容等)
     * @Author: xueding
     * @Vsersion: 2022/5/25 下午6:17
     * @param $full_object_name
     * @param $params
     * @return array|null
     */
    public function exportDataMain($full_object_name,$params)
    {
        //初始化当前对象属性
        $data = $this->initExportData($full_object_name);
    
        //当前导出要求的导出字段及过滤条件
        $data['columns']     = explode(',', $params['fields']);
        $data['filter']      = $params['filter'];
        $data['has_detail']  = $params['has_detail'] == 1 ? true : false;
        $data['first_sheet'] = $params['curr_sheet'] == 1 ? true : false;
        $data['op_id']       = $params['op_id'];
    
        return $this->mainExportData($data);
    }
    
    public function initExportData($full_object_name)
    {
        if ($p = strpos($full_object_name, '_mdl_')) {
            $object_app  = substr($full_object_name, 0, $p);
            $object_name = substr($full_object_name, $p + 5);
        } else {
            trigger_error('finder only accept full model name: ' . $full_object_name, E_USER_ERROR);
        }
    
        $service_list = array();
        foreach (kernel::servicelist('desktop_finder.' . $full_object_name) as $name => $object) {
            $service_list[$name] = $object;
        }
        $service_object = array();
        $addon_columns = array();
        foreach ($service_list as $name => $object) {
            $tmpobj = $object;
            foreach (get_class_methods($tmpobj) as $method) {
                switch (substr($method, 0, 7)) {
                    case 'column_':
                        $addon_columns[] = array(&$tmpobj, $method);
                        break;
                }
            }
    
            $service_object[] = &$tmpobj;
            unset($tmpobj);
        }
    
        $object   = app::get($object_app)->model($object_name);
        $object->is_export_data = true;
        $dbschema = $object->schema;
    
        if (method_exists($object, 'extra_cols')) {
            $extra_cols = $object->extra_cols();
        }
    
        //增加导出时候要额外扩展导出的字段
        if (method_exists($object, 'export_extra_cols')) {
            $export_extra_cols = $object->export_extra_cols();
        }
    
        $short_object_name = substr($object_name, strpos($object_name, '_mdl_') + 5);
    
        $data = [
            'object_name'       => $full_object_name,
            'service_object'    => $service_object,
            'addon_columns'     => $addon_columns,
            'object'            => $object,
            'dbschema'          => $dbschema,
            'extra_cols'        => $extra_cols,
            'export_extra_cols' => $export_extra_cols,
            'short_object_name' => $short_object_name,
        ];
        return $data;
    }
    
    public function mainExportData($data)
    {
        $allCols = $this->all_columns($data);
    
        $modifiers       = array();
        $type_modifier   = array();
        $key_modifier    = array();
        $object_modifier = array();
        $modifier_object = new modifiers;
    
        //根据当前字段格式化查询语句
        foreach ($data['columns'] as $col) {
            if (isset($allCols[$col])) {
                $colArray[$col] = &$allCols[$col];
                if (method_exists($data['object'], 'modifier_' . $col)) {
                    $key_modifier[$col] = 'modifier_' . $col;
                } elseif (is_string($colArray[$col]['type'])) {
                    if (substr($colArray[$col]['type'], 0, 6) == 'table:') {
                        $object_modifier[$colArray[$col]['type']] = array();
                    } elseif (method_exists($modifier_object, $colArray[$col]['type'])) {
                        $type_modifier[$colArray[$col]['type']] = array();
                    }
                }
                if (isset($col_width_set[$col])) {
                    $colArray[$col]['width'] = $col_width_set[$col];
                }
            
                if (isset($allCols[$col]['sql'])) {
                    $sql[] = $allCols[$col]['sql'] . ' as ' . $col;
                } elseif ($col == '_tag_') {
                    $sql[] = $dbschema['idColumn'] . ' as _tag_';
                } elseif (isset($data['extra_cols'][$col])) {
                    $sql[] = '1 as ' . $col;
                } elseif (isset($data['export_extra_cols'][$col])) {
                    $sql[] = '1 as ' . $col;
                } else {
                    $sql[] = '`' . $col . '`';
                }
            }
        }
    
        foreach ((array) $data['service_object'] as $k => $object) {
            if ($object->addon_cols) {
                $object->col_prefix = '_' . $k . '_';
                foreach (explode(',', $object->addon_cols) as $col) {
                    $sql[] = $col . ' as ' . $object->col_prefix . $col;
                }
            }
        }
        $sql = (array) $sql;
        if (!isset($colArray[$data['dbschema']['idColumn']])) {
            array_unshift($sql, $data['dbschema']['idColumn']);
            $colArray[$data['dbschema']['idColumn']]['label'] = 'primary_key';
        }
    
        $list = $data['object']->getlist(implode(',', $sql), $data['filter'], 0, -1);
    
        if (is_array($data['extra_cols']) && count($data['extra_cols']) > 0) {
            foreach ($data['extra_cols'] as $ek => $extra_col) {
                $extra_col_method = '';
                if (method_exists($data['object'], 'extra_' . $extra_col['func_suffix'])) {
                    $extra_col_method = 'extra_' . $extra_col['func_suffix'];
                    $list             = $data['object']->$extra_col_method($list);
                }
            }
        }
    
        //导出时候特定额外要导出的字段
        if (is_array($data['export_extra_cols']) && count($data['export_extra_cols']) > 0) {
            foreach ($data['export_extra_cols'] as $ek => $export_extra_col) {
                $export_extra_col_method = '';
                if (method_exists($data['object'], 'export_extra_' . $export_extra_col['func_suffix'])) {
                    $export_extra_col_method = 'export_extra_' . $export_extra_col['func_suffix'];
                    $list                    = $data['object']->$export_extra_col_method($list);
                }
            }
        }
    
        //导出数据客户敏感信息处理
        $securityLib = kernel::single('ome_security_customer');
        $securityLib->check_sensitive_info($list, $data['object_name'], $data['op_id']);
        //导出主明细数据
        $export_data = $this->exportListBody($list, $colArray, $key_modifier, $object_modifier, $type_modifier, $data);
        
        //返回数据
        return $export_data;
    }
    
    /**
     * finder列表原始数据格式化方法
     *
     * @param array $list 查询出来的原始数据
     * @param array $colArray 导出数据的字段名数组
     * @param array $key_modifier 字段在model中有定义modifier_字段的处理方法的数据
     * @param array $object_modifier 对象字段数据，比如id字段是其它表主键
     * @param array $type_modifier 指定字段类型数据，比如money等
     * @param array $data 请求参数与模型内容数据
     * @return null
     */
    public function &exportListBody(&$list, &$colArray, &$key_modifier, &$object_modifier, &$type_modifier, $data)
    {
        $body     = array();
        $curr_row = 1;
        foreach ($list as $i => $row) {
            foreach ((array) $colArray as $k => $col) {
                //如果是第一分片在第一行之前导出标题
//                if ($data['first_sheet'] && $curr_row == 1) {
                    $body['title'][$col['label']] = $k;
//                }
                
                if ($col['type'] == 'func') {
                    $row['idColumn']   = $data['dbschema']['idColumn'];
                    $row['app_id']     = $row['app_id'] ? $row['app_id'] :  '';
                    $row['tag_type']   = $row['tag_type'] ? $row['tag_type'] : $data['short_object_name'];
                    $body['content'][$curr_row][$k] = $a = $col['ref'][0]->{$col['ref'][1]}($row, $list);
                } elseif (isset($key_modifier[$k])) {
                    $data['object']->pkvalue = $row[$data['dbschema']['idColumn']];
                    $body['content'][$curr_row][$k]     = $data['object']->{$key_modifier[$k]}($row[$k], $list, $row);
                } elseif (is_array($col['type']) && !is_null($row[$k])) {
                    $body['content'][$curr_row][$k] = &$col['type'][$row[$k]];
                } elseif (!is_array($col['type']) && isset($object_modifier[$col['type']])) {
                    $object_modifier[$col['type']][$row[$k]] = $row[$k];
                    $body['content'][$curr_row][$k]                       = &$object_modifier[$col['type']][$row[$k]];
                } elseif (!is_array($col['type']) && isset($type_modifier[$col['type']])) {
                    if (is_float($row[$k])) {
                        $number = md5($row[$k]);
                    } else {
                        $number = $row[$k];
                    }
                    $type_modifier[$col['type']][$number] = $row[$k];
                    $body['content'][$curr_row][$k]                    = &$type_modifier[$col['type']][$number];
                } else {
                    $body['content'][$curr_row][$k] = $row[$k];
                }
            }
            $curr_row++;
        }
        
        if ($type_modifier) {
            $type_modifier_object = new modifiers;
            foreach ($type_modifier as $type => $val) {
                if ($val) {
                    $type_modifier_object->$type($val);
                    
                    if ($type == 'money') {
                        foreach ($val as $i => $money) {
                            $val[$i] = str_replace(',', '', $money);
                        }
                    }
                }
            }
        }
        
        foreach ($object_modifier as $target => $val) {
            if ($val) {
                list(, $obj_name, $fkey) = explode(':', $target);
                if ($p = strpos($obj_name, '@')) {
                    $app_id   = substr($obj_name, $p + 1);
                    $obj_name = substr($obj_name, 0, $p);
                    $o        = app::get($app_id)->model($obj_name);
                } else {
                    $o = $data['object']->app->model($obj_name);
                }
                if (!$fkey) {
                    $fkey = $o->textColumn;
                }
                
                $rows = $o->getList($o->idColumn . ',' . $fkey, array($o->idColumn => $val));
                foreach ($rows as $r) {
                    $object_modifier[$target][$r[$o->idColumn]] = $r[$fkey];
                }
                $app_id = null;
            }
        }
        foreach ($body['content'] as $row => $content) {
            $tmp_arr = array();
            foreach ($content as $key => $value) {
                //过滤html编码转换
                $value     = str_replace('&nbsp;', '', $value);
                $value     = str_replace(array("\r\n", "\r", "\n"), '', $value);
                $value     = str_replace(',', '', $value);
                $value     = strip_tags(html_entity_decode($value, ENT_COMPAT | ENT_QUOTES, 'UTF-8'));
                $value     = trim($value);
                $tmp_arr[$key] = $value;
            }
            //去html代码
            $body['content'][$row] = $tmp_arr;
        }
        unset($body['title']['primary_key']);
        return $body;
    }
    //获取所有字段
    private function &all_columns($data)
    {
        //finder扩展字段
        $func_columns = $this->func_columns($data);
        
        //新方式扩展字段
        $extra_columns = array();
        if (is_array($data['extra_cols']) && count($data['extra_cols']) > 0) {
            $extra_columns = $data['extra_cols'];
        }
        
        //额外导出扩展字段
        $export_extra_columns = array();
        if (is_array($data['export_extra_cols']) && count($data['export_extra_cols']) > 0) {
            $export_extra_columns = $data['export_extra_cols'];
        }
        
        //表结构原声字段
        $columns = array();
        foreach ((array) $data['dbschema']['in_list'] as $key) {
            $columns[$key] = &$data['dbschema']['columns'][$key];
        }
        
        //合并所有字段
        $return = array_merge((array) $func_columns, (array) $extra_columns, (array) $export_extra_columns, (array) $columns);
        foreach ($return as $k => $r) {
            if (!$r['order']) {
                $return[$k]['order'] = 100;
                
            }
            $orders[] = $return[$k]['order'];
        }
        array_multisort($orders, SORT_ASC, $return);
        return $return;
    }
    
    //取finder里定义的扩展字段
    private function &func_columns($data)
    {
        if (!isset($func_list)) {
            $default_with    = app::get('desktop')->getConf('finder.thead.default.width');
            $return          = array();
            $func_list = &$return;
            
            foreach ($data['addon_columns'] as $k => $function) {
                $func['type']  = 'func';
                $func['width'] = $function[0]->{$function[1] . '_width'} ? $function[0]->{$function[1] . '_width'} : $default_with;
                $func['label'] = $function[0]->{$function[1]};
                $func['order'] = $function[0]->{$function[1] . '_order'};
                
                $func['ref']         = $function;
                $func['sql']         = '1';
                $func['order_field'] = '';
                if ($function[0]->{$function[1] . '_order_field'}) {
                    $func['order_field'] = $function[0]->{$function[1] . '_order_field'};
                }
                $func['alias_name'] = $function[1];
                if ($func['label']) {
                    //只有有名称，才能被显示
                    $return[$function[1]] = $func;
                }
            }
        }
        return $func_list;
    }
    
    //获取临时目录
    public function getTmpDir(): string
    {
        $tmp = ini_get('upload_tmp_dir');

        if ($tmp !== False && file_exists($tmp)) {
            return realpath($tmp);
        }

        return realpath(sys_get_temp_dir());
    }

    /**
     * 获取全球唯一标识
     * @return string
     */
    public static function uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * 按字节长度截取字符串，确保中文字符完整
     * 规则：
     * - 使用 strlen($str) 检查字节长度
     * - 截取后的字符串，strlen() 结果不能大于255
     * - 如果都是英文字符，可以截取255个字符（1个英文字符=1个字节）
     * - 如果有中文字符，UTF-8编码下一个汉字=3个字节，需要确保最后一个汉字完整
     *
     * @param string $str 要截取的字符串
     * @param int $maxLength 最大字节长度（默认255）
     * @return string 截取后的字符串（strlen() 结果 <= 255）
     */
    public function substrStringSafe($str, $maxLength = 255)
    {
        // 使用 strlen() 获取字符串的字节长度（UTF-8编码：1个英文字符=1字节，1个汉字=3字节）
        $byteLength = strlen($str);
        
        // 如果字符串字节长度不超过最大长度，直接返回
        if ($byteLength <= $maxLength) {
            return $str;
        }
        
        // 从maxLength位置向前查找，找到最后一个安全的截取位置
        // 如果maxLength位置是多字节字符的中间字节，需要向前找到字符的开始位置
        $cutPos = $maxLength;
        
        // 向前查找，确保不会截断中文字符
        // UTF-8多字节字符的后续字节以 0x80-0xBF 开头（即最高两位是10）
        while ($cutPos > 0 && (ord($str[$cutPos]) & 0xC0) == 0x80) {
            $cutPos--;
        }
        
        // 使用 mb_strcut 截取，确保中文字符完整
        $result = mb_strcut($str, 0, $cutPos, 'UTF-8');
        
        // 最终验证：确保 strlen() 结果不超过255
        $finalLength = strlen($result);
        while ($finalLength > $maxLength && $cutPos > 0)
        {
            // 如果还是超过255，继续向前查找
            $cutPos--;
            while ($cutPos > 0 && (ord($str[$cutPos]) & 0xC0) == 0x80) {
                $cutPos--;
            }
            $result = mb_strcut($str, 0, $cutPos, 'UTF-8');
            $finalLength = strlen($result);
        }
        
        return $result;
    }
}