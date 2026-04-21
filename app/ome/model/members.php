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

class ome_mdl_members extends dbeav_model{
    
    /**
     * 须加密字段
     * 
     * @var string
     * */
    private $__encrypt_cols = array(
        'uname'  => 'search',
        'mobile' => 'phone',
        'tel'    => 'simple',
        'name'   => 'simple',
        'email'  => 'simple',
        'addr'   => 'simple',
        'zip'    => 'simple',
    );

    /**
     * 快速查询主表信息
     * @access public
     * @param mixed $filter 过滤条件
     * @param String $cols 字段名
     * @return Array 会员信息
     */
    function getRow($filter,$cols='*'){
        if (empty($filter)) return array();
        return $this->db_dump($filter, $cols);
    }

    function member_detail($member_id){
        $member_detail = $this->dump($member_id);
        return $member_detail;
    }

    /**
     * _filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回值
     */
    public function _filter($filter,$tableAlias=null,$baseWhere=null)
    {
        $tPre      = ($tableAlias ? $tableAlias : '`' . $this->table_name(true) . '`') . '.';
        $tmpBaseWhere = kernel::single('ome_filter_encrypt')->encrypt($filter, $this->__encrypt_cols, $tPre, 'members');
        $baseWhere = $baseWhere ? array_merge((array)$baseWhere, (array)$tmpBaseWhere) : (array)$tmpBaseWhere;

        return parent::_filter($filter,$tableAlias,$baseWhere);
    }

    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        $data = parent::getList($cols,$filter,$offset,$limit,$orderType);

        foreach ((array) $data as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field],$type);
                }
            }
        }

        return $data;
    }

    /**
     * insert
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function insert(&$data)
    {
        $security = kernel::single('ome_security_factory');
        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                if (!$security->isLocalEncryptData($data[$field], $type)) {
                    $data[$field] = (string) $security->encryptPublic($data[$field],$type);
                }
            }
        }

        return parent::insert($data);
    }

    public function update($data,$filter=array(),$mustUpdate = null)
    {
        $security = kernel::single('ome_security_factory');
        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                if (!$security->isLocalEncryptData($data[$field], $type)) {
                    $data[$field] = (string) $security->encryptPublic($data[$field],$type);
                }
            }
        }

        return parent::update($data,$filter,$mustUpdate);
    }

    
    function get_member($data,$col='uname'){
        $uname = $data['uname'];
        $mobile = $data['mobile'];
        $shop_id = $data['shop_id'];
        $fields = 'member_id,uname,area,mobile,email,sex';
        $filter = array();
        if ($col == 'mobile'){
            $filter['mobile|head'] = $mobile;
        }else {
            $filter['uname|head'] = $uname;
        }
        if ($shop_id){
            $filter['shop_id'] = $shop_id;
        }
        $rows = $this->getList($fields, $filter);
        return $rows;
    }
    
    function exportTemplate($filter){
        foreach ($this->io_title() as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }

    function io_title( ){

        $title = array(
            '*:客户账户' => 'uname',
            '*:客户名称'=>'name',
            '*:来源店铺' => 'shop_name',
            '*:所属平台' => 'shop_type',
            '*:地区' => 'area',
            '*:详细地址' => 'addr',
            '*:固定电话' => 'tel',
            '*:手机' => 'mobile',
            '*:Email' => 'email',
            '*:邮编' => 'zip',

        );
        $title = array_keys($title);
        return $title;
    }

     function prepared_import_csv(){
        $this->ioObj->cacheTime = time();
    }

    function finish_import_csv(){  
        header("Content-type: text/html; charset=utf-8");
        $data = $this->import_data;
        unset($this->import_data);
        
        $oQueue = app::get('base')->model('queue');

        $count = 0;
        $limit = 100;
        $page = 0;
        $membersdf = array();
        foreach( $data as $members ){
            if($count < $limit){
                $count ++;
            }else{
                $count = 0;
                $page ++;
            }

            $membersdf[$page][] = $members;
        }
        foreach($membersdf as $v){
            $queueData = array(
                'queue_title'=>'会员信息导入',
                'start_time'=>time(),
                'params'=>array(
                    'sdfdata'=>$v,
                    'app' => 'ome',
                    'mdl' => 'members'
                ),
                'worker'=>'ome_member_import.run',
            );
            $oQueue->save($queueData);

        }
        app::get('base')->model('queue')->flush();

    }     

    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg){
        $shopex_shop_type = ome_shop_type::shopex_shop_type();
        $shop_type_list = ome_shop_type::get_shop_type();
        $shop_type_list = array_flip($shop_type_list);
       
        $fileData = $this->import_data;
        $shopObj = app::get('ome')->model('shop');
        if( !$fileData )
            $fileData = array();

        if( substr($row[0],0,1) == '*' ){
            $titleRs =  array_flip($row);

            $mark = 'title';

            return $titleRs;
        }else{
            if($row){
                $uname = trim($row[0]);
                $name = trim($row[1]);
                $shop_name = trim($row[2]);
                $shop_type = trim($row[3]);
                $area = trim($row[4]);
                $addr = trim($row[5]);
                $tel = trim($row[6]);
                $mobile = trim($row[7]);
                $email = trim($row[8]);
                $zip = trim($row[9]);
                if($uname==''){
                    $msg['error'] = "客户账户不能为空";
                    return false;
                }
                if($shop_type==''){
                    $msg['error'] = "店铺类型不能为空";
                    return false;
                }else{
                    
                    if(in_array($shop_type,array_keys($shop_type_list))){
                        
                        if(in_array($shop_type_list[$shop_type],$shopex_shop_type)){
                            if($shop_name==''){
                                $msg['error'] = "店铺不能为空";
                                return false;
                            }else{
                                $shop_detail = $shopObj->dump(array('name'=>trim($shop_name),'shop_type'=>$shop_type_list[$shop_type]),'shop_id');
                                
                                if(!$shop_detail){
                                    $msg['error'] = "店铺不存在!";
                                    return false;
                                }
                            }
                        }
                    }else{
                        $msg['error'] = $shop_type."店铺类型不存在!";
                        return false;
                    }
                    
                }
                
                if ($area==''){
                    $msg['error'] = "地区不能为空!";
                    return false;
                }
                if($tel=='' && $mobile==''){
                    $msg['error'] = "固定电话或手机至少一个不为空!";
                    return false;
                }
                $fileData[] = array('uname'=>$uname,'name'=>$name,'shop_id'=>$shop_detail['shop_id'],'shop_type'=>$shop_type_list[$shop_type],'area'=>$area,'addr'=>$addr,'tel'=>$tel,'mobile'=>$mobile,'email'=>$email,'zip'=>$zip);
                $this->import_data = $fileData;
            }
            
        }
        return null;
    }

    function modifier_shop_type($row){
       $tmp = ome_shop_type::get_shop_type();
       return isset($tmp[$row]) ? $tmp[$row] : ($row == 'other' ? '其他平台' : '-');
    }

    function modifier_shop_id($row){
        if(empty($row)){
            return '-';
        }else{
            $shopObj = app::get('ome')->model('shop');
            $shop_detail = $shopObj->dump($row,'name');
            return $shop_detail['name'];
        }
       
    }
    
    /**
     * modifier_uname
     * @param mixed $uname uname
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_uname($uname,$list,$row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($uname);
        if (!$is_encrypt) return $uname;
        
        $id = $row['_0_member_id'];
        $encrypt = kernel::single('ome_view_helper2')->modifier_ciphertext($uname,'member','uname');

        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_customer&act=showSensitiveData&p[0]={$id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="uname">{$encrypt}</span></span>
HTML;
        return $uname?$return:$uname;
    }
    
    /**
     * modifier_mobile
     * @param mixed $mobile mobile
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_mobile($mobile,$list,$row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($mobile);
        if (!$is_encrypt) return $mobile;
        
        $id = $row['_0_member_id'];
        $encrypt = kernel::single('ome_view_helper2')->modifier_ciphertext($mobile,'member','mobile');
        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_customer&act=showSensitiveData&p[0]={$id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="mobile">{$encrypt}</span></span>
HTML;
        return $mobile?$return:$mobile;
    }
    
    /**
     * modifier_tel
     * @param mixed $tel tel
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_tel($tel,$list,$row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($tel);
        if (!$is_encrypt) return $tel;
        
        $id = $row['_0_member_id'];
        $encrypt = kernel::single('ome_view_helper2')->modifier_ciphertext($tel,'member','tel');
        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_customer&act=showSensitiveData&p[0]={$id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="tel">{$encrypt}</span></span>
HTML;
        return $tel?$return:$tel;
    }
    
    /**
     * modifier_name
     * @param mixed $name name
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_name($name,$list,$row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($name);
        if (!$is_encrypt) return $name;
        
        $id = $row['_0_member_id'];
        $encrypt = kernel::single('ome_view_helper2')->modifier_ciphertext($name,'member','name');
        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_customer&act=showSensitiveData&p[0]={$id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="name">{$encrypt}</span></span>
HTML;
        return $name?$return:$name;
    }
    
    /**
     * modifier_email
     * @param mixed $email email
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function modifier_email($email,$list,$row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($email);
        if (!$is_encrypt) return $email;
        
        $id = $row['_0_member_id'];
        $encrypt = kernel::single('ome_view_helper2')->modifier_ciphertext($email,'member','email');
        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=ome&ctl=admin_customer&act=showSensitiveData&p[0]={$id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="email">{$encrypt}</span></span>
HTML;
        return $email?$return:$email;
    }

    /**
     * 获取MemberIdByUname
     * @param mixed $uname uname
     * @return mixed 返回结果
     */
    public function getMemberIdByUname($uname){
        $rows = $this->getList('member_id',array('uname'=>$uname));
        $memberId = [0];
        foreach($rows as $row){
            $memberId[] = $row['member_id'];
        }
        $buyer_open_uid = kernel::single('ome_filter_encrypt')->getBuyerOpenUid($uname);
        if($buyer_open_uid) {
            $list = $this->getList('member_id', ['buyer_open_uid'=>$buyer_open_uid]);
            if($list) {
                $memberId = array_merge($memberId, array_column($list, 'member_id'));
            }
        }
        return $memberId;
    }
}
?>