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

class o2o_mdl_store extends dbeav_model
{
    //是否有导出配置
    var $has_export_cnf = true;
    public $export_name = '门店列表';
    
    /**
     * 重写_filter方法，支持dealer_bs_bn查找
     * @param array $filter 过滤条件
     * @param string $tableAlias 表别名
     * @param array $baseWhere 基础WHERE条件
     * @return array
     */
    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        if (isset($filter['dealer_bs_bn']) && !empty($filter['dealer_bs_bn'])) {
            $dealer_bs_bn = $filter['dealer_bs_bn'];
            unset($filter['dealer_bs_bn']);
            
            // 使用一条复合SQL查询：通过经销商编号查找门店编号
            // 1. 在sdb_organization_organization表查org_type=3 and org_no="BS_"+dealer_bs_bn的org_id
            // 2. 然后查org_type=2 and parent_id=查到的org_id的org_no，这个org_no就是store_bn
            $dealer_org_no = 'BS_' . $dealer_bs_bn;
            $sql = sprintf("SELECT DISTINCT o2.org_no 
                    FROM sdb_organization_organization o1 
                    INNER JOIN sdb_organization_organization o2 ON o2.parent_id = o1.org_id 
                    WHERE o1.org_type = 3 
                    AND o1.org_no = %s 
                    AND o2.org_type = 2", 
                    $this->db->quote($dealer_org_no));
            
            $store_bns = $this->db->select($sql);
            
            if ($store_bns) {
                $store_bn_list = array_column($store_bns, 'org_no');
                $baseWhere[] = 'store_bn IN (\'' . implode('\',\'', $store_bn_list) . '\')';
            } else {
                // 如果没有找到对应的门店，返回空结果
                $baseWhere[] = 'store_bn = \'\'';
            }
        }
        
        return parent::_filter($filter, $tableAlias, $baseWhere);
    }
    /**
     * 准备导入的参数定义
     *
     * @param Null
     * @return Null
     */
    function prepared_import_csv()
    {
        $this->ioObj->cacheTime = time();
    }
    
    /**
     * 准备导入的数据主体内容部分检查和处理
     *
     * @param Array $data
     * @param Boolean $mark
     * @param String $tmpl
     * @param String $msg
     * @return Null
     */
    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = '')
    {
        return null;
    }
    
    /**
     * 准备导入的数据明细内容部分检查和处理
     *
     * @param Array $row
     * @param String $title
     * @param String $tmpl
     * @param Boolean $mark
     * @param Boolean $newObjFlag
     * @param String $msg
     * @return Null
     */
    function prepared_import_csv_row($row,$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        $serverObj         = app::get('o2o')->model('server');
        $regionObj         = app::get('eccommon')->model('regions');
        $organizationObj   = app::get('organization')->model('organization');
        $checkStoreLib     = kernel::single('o2o_store');
        
        if (empty($row))
        {
            return true;
        }
        
        $mark = false;
        
        if( substr($row[0],0,1) == '*' )
        {
            $titleRs    = array_flip($row);
            $mark       = 'title';
            
            # [防止重复]记录组织编码
            $this->store_bn_list    = array();
            $this->store_name_list  = array();
            $this->basicm_nums      = 1;
            
            return $titleRs;
        }
        else
        {
            $re    = base_kvstore::instance('o2o_store')->fetch('o2oStore-'.$this->ioObj->cacheTime,$fileData);
            
            if( !$re ) $fileData = array();
            
            //判断导入的数量
            if(isset($this->basicm_nums))
            {
                $this->basicm_nums++;
                if($this->basicm_nums > 5000){
                    $msg['error'] = "导入的数量量过大，请减少到5000个以下！";
                    return false;
                }
            }
            
            //导入数据检查
            if(!$row[0]){
                $msg['error'] = "门店编码必须填写,门店名称：". $row[1];
                return false;
            }
            
            if(!$row[1]){
                $msg['error'] = "门店名称必须填写,门店编码：". $row[0];
                return false;
            }
            
            if(!$row[2]){
                $msg['error'] = "所属组织必须填写（例格式：华东地区-上海）,门店编码：". $row[0];
                return false;
            }
            
            //状态值
            if($row[3] == "启用")
            {
                $row[3] = 1;
            }
            elseif($row[3] == "停用")
            {
                $row[3] = 2;
            }
            else
            {
                $msg['error'] = "门店状态填写错误,门店编码：".$row[0].",请填写启用、停用";
                return false;
            }
            
            if(!$row[4]){
                $msg['error'] = "所属地区必须填写,门店编码：". $row[0];
                return false;
            }
            
            if(!$row[5]){
                $msg['error'] = "所属服务端必须填写,门店编码：". $row[0];
                return false;
            }
            
            if(!$row[6]){
                $msg['error'] = "门店地址必须填写,门店编码：". $row[0];
                return false;
            }
            
            if(!$row[7]){
                $msg['error'] = "邮编必须填写,门店编码：". $row[0];
                return false;
            }
            
            if(!$row[8]){
                $msg['error'] = "联系人必须填写,门店编码：". $row[0];
                return false;
            }
            
            if(empty($row[9]) && empty($row[10])){
                $msg['error'] = "固定电话与手机号码必需填写一项,门店编码：". $row[0];
                return false;
            }
            
            // 手机号验证
            if(!empty($row[10])){
                $mobile = str_replace(" ", "", $row[10]);
                if (!kernel::single('ome_func')->isMobile($mobile)) {
                    $msg['error'] = "请输入正确的手机号码,门店编码：". $row[0];
                    return false;
                }
            }
            
            if(!$row[11]){
                $msg['error'] = "经营模式必须填写,门店编码：". $row[0];
                return false;
            }
            
            /*
            if(!$row[12]){
                $msg['error'] = "是否支持自提必须填写,门店编码：". $row[0];
                return false;
            }
            
            if(!$row[13]){
                $msg['error'] = "是否支持配送必须填写,门店编码：". $row[0];
                return false;
            }
            
            if(!$row[14]){
                $msg['error'] = "是否支持门店售后必须填写,门店编码：". $row[0];
                return false;
            }
            
            if(!$row[15]){
                $msg['error'] = "是否需要订单回执确认必须填写,门店编码：". $row[0];
                return false;
            }
            
            if(!$row[16]){
                $msg['error'] = "门店经纬度必须填写,门店编码：". $row[0];
                return false;
            }
            */
            
            # [防止重复]检查门店编码
            if(in_array($row[0], $this->store_bn_list))
            {
                $msg['error'] = 'Line '.$this->basicm_nums.'：门店编码【'. $row[0] .'】重复！';
                return false;
            }
            $this->store_bn_list[]    = $row[0];
            
            # [防止重复]检查门店名称
            if(in_array($row[1], $this->store_name_list))
            {
                $msg['error'] = 'Line '.$this->basicm_nums.'：门店名称【'. $row[0] .'】重复！';
                return false;
            }
            $this->store_name_list[]    = $row[1];
            
            //经营模式store_type
            if($row[11] == "自营")
            {
                $row[11] = 'self';
            }
            elseif($row[11] == "加盟")
            {
                $row[11] = 'join';
            }
            elseif($row[11] == "合营")
            {
                $row[11] = 'cooperation';
            }
            elseif($row[11] == "代理")
            {
                $row[11] = 'proxy';
            }
            elseif($row[11] == "特殊")
            {
                $row[11] = 'special';
            }
            elseif($row[11] == "其他")
            {
                $row[11] = 'other';
            }
            else
            {
                $msg['error'] = "经营模式填写错误,门店编码：".$row[0].",请填写自营、加盟、合营、代理、特殊、其他";
                return false;
            }
            
            /*
            //自提
            if($row[12] == "支持")
            {
                $row[12] = 1;
            }
            elseif($row[12] == "不支持")
            {
                $row[12] = 2;
            }
            else 
            {
                $msg['error'] = "是否支持自提填写错误,门店编码：".$row[0].",请填写支持、不支持";
                return false;
            }
            
            //配送
            if($row[13] == "支持")
            {
                $row[13] = 1;
            }
            elseif($row[13] == "不支持")
            {
                $row[13] = 2;
            }
            else
            {
                $msg['error'] = "是否支持配送填写错误,门店编码：".$row[0].",请填写支持、不支持";
                return false;
            }
            
            //门店售后
            if($row[14] == "支持")
            {
                $row[14] = 1;
            }
            elseif($row[14] == "不支持")
            {
                $row[14] = 2;
            }
            else
            {
                $msg['error'] = "是否支持门店售后填写错误,门店编码：".$row[0].",请填写支持、不支持";
                return false;
            }
            
            //订单回执确认
            if($row[15] == "需要")
            {
                $row[15] = 1;
            }
            elseif($row[15] == "不需要")
            {
                $row[15] = 2;
            }
            else
            {
                $msg['error'] = "是否需要订单回执确认填写错误,门店编码：".$row[0].",请填写支持、不支持";
                return false;
            }
            
            //数据组织
            $map    = explode('|', $row[16]);
            $row['coordinate']    = $map[0] .','. $map[1];//经纬度
            */
            
            $row['org_no']    = $row[0];//组织编码
            $row['org_name']  = $row[1];//组织名称
            $row['zip']       = $row[7];//邮编
            $row['tel']       = $row[9];//固定电话
            $row['mobile']    = $row[10];//手机号
            
            #check数据检查
            $error_msg        = '';
            if(!$checkStoreLib->checkAddParams($row, $error_msg))
            {
                $error_msg    .= ',门店编码：'. $row[0];
                $msg['error'] = $error_msg;
                return false;
            }
            
            //所属组织
            $org_level_num            = 1;//组织层级
            $org_id                   = 0;
            $org_parents_structure    = '';//组织架构结构
            
            $temp_org    = explode('-', $row[2]);
            foreach ($temp_org as $key => $val)
            {
                $org_row    = $organizationObj->dump(array('org_name'=>$val, 'org_type'=>1), 'org_id, org_name, parent_id');
                if(empty($org_row))
                {
                    $msg['error'] = "所属组织名称[". $val ."]不存在,门店编码：". $row[0];
                    return false;
                }
                
                #判断所属组织是否上下级关系
                $org_row['parent_id']    = intval($org_row['parent_id']);
                
                if($org_id != $org_row['parent_id'])
                {
                    $msg['error'] = "所属组织[". $val ."]与[". $temp_org[$key - 1] ."]没有关联,门店编码：". $row[0];
                    return false;
                }
                
                $org_id                   = intval($org_row['org_id']);
                $org_parents_structure    .= ('/' . $org_row['org_name']);
                
                $org_level_num++;
            }
            
            $row['parent_id']                = $org_id;
            $row['org_level_num']            = $org_level_num;
            $row['org_parents_structure']    = 'mainOrganization:'. substr($org_parents_structure, 1) .':'. $org_id;
            
            //所属服务端
            $temp_server    = $serverObj->dump(array('name'=>$row['5']), 'server_id');
            if(empty($temp_server))
            {
                $msg['error'] = "所属服务端[". $row['5'] ."]不存在,门店编码：". $row[0];
                return false;
            }
            $row['server_id']    = $temp_server['server_id'];
            
            //所属地区
            $region_id           = 0;
            $temp_region         = explode('-', $row[4]);
            
            foreach ($temp_region as $key => $val)
            {
                $region_row    = $regionObj->dump(array('local_name'=>$val), 'region_id, local_name, p_region_id, haschild');
                if(empty($region_row))
                {
                    $msg['error'] = "所属地区[". $val ."]不存在,门店编码：". $row[0];
                    return false;
                }
                
                #判断地区是否上下级关系
                $region_row['p_region_id']  = intval($region_row['p_region_id']);
                
                if($region_id != $region_row['p_region_id'])
                {
                    $msg['error'] = "所属地区[". $val ."]与[". $temp_region[$key - 1] ."]没有关联,门店编码：". $row[0];
                    return false;
                }
                
                $region_id           = intval($region_row['region_id']);
            }
            
            #判断地区是否填写到最后一级
            if($region_row['haschild'])
            {
                $msg['error'] = "所属地区[". $region_row['local_name'] ."]没有填写到最后一级,门店编码：". $row[0];
                return false;
            }
            $row['region']    = 'mainland:'. str_replace('-', '/', $row[4]) .':'. $region_id;
            
            // 处理所属经销商
            $dealer_cos_id = '';
            if (!empty($row[12]) && $row[11] == 'join') { // 所属经销商字段，且经营模式为加盟
                $dealer_cos_id = $this->processDealerName($row[12], $msg);
                if ($dealer_cos_id === false) {
                    return false;
                }
            }
            
            // 处理覆盖区域
            $coverage_area = '';
            if (!empty($row[13])) { // 覆盖区域字段
                $coverage_area = $this->processCoverageArea($row[13], $msg);
                if ($coverage_area === false) {
                    return false;
                }
            }
            
            #格式化数据
            $sdf    = array(
                        'org_no' => $row['org_no'],
                        'org_name'=> $row['org_name'],
                        'org_type' => 2,
                        'status' => $row[3],
                        'area' => $row['region'],
                        'org_level_num' => $row['org_level_num'],
                        'org_parents_structure' => $row['org_parents_structure'],
                        'parent_id' => $row['parent_id'],
                        
                        'server_id' => $row['server_id'],
                        'addr' => $row[6],
                        /*
                        'longitude' => $row['longitude'],
                        'latitude' => $row['latitude'],
                        */
                        'zip' => $row['zip'],
                        'contacter' => $row[8],
                        'mobile' => $row['mobile'],
                        'tel' => $row['tel'],
                        'store_type' => $row[11],
                        'dealer_cos_id' => $dealer_cos_id,
                        'coverage_area' => $coverage_area,
                        /*
                        'self_pick' => $row[12],
                        'distribution' => $row[13],
                        'aftersales' => $row[14],
                        'confirm' => $row[15],
                        */
                    );
            
            #销毁
            unset($row, $region_id, $region_row, $temp_server, $temp_org, $org_parents_structure, $org_level_num);
            
            $fileData['basicm']['contents'][]    = $sdf;
            base_kvstore::instance('o2o_store')->store('o2oStore-'.$this->ioObj->cacheTime,$fileData);
        }
        
        return null;
    }
    
    /**
     * 完成导入
     *
     * @param Null
     * @return Null
     */
    function finish_import_csv()
    {
        base_kvstore::instance('o2o_store')->fetch('o2oStore-'.$this->ioObj->cacheTime,$data);
        base_kvstore::instance('o2o_store')->store('o2oStore-'.$this->ioObj->cacheTime,'');
        
        $oQueue = app::get('base')->model('queue');
        $aP = $data;
        $pSdf = array();
        
        $count = 0;
        $limit = 50;
        $page = 0;
        $orderSdfs = array();
        
        foreach ($aP['basicm']['contents'] as $k => $aPi){
            if($count < $limit){
                $count ++;
            }else{
                $count = 0;
                $page ++;
            }
            $pSdf[$page][] = $aPi;
        }
        
        foreach($pSdf as $v){
            $queueData = array(
                    'queue_title' => '门店批量导入',
                    'start_time' => time(),
                    'params' => array(
                                    'sdfdata' => $v,
                                    'app' => 'o2o',
                                    'mdl' => 'store'
                                ),
                    'worker' => 'o2o_store_to_import.run',
            );
            $oQueue->save($queueData);
        }
        $oQueue->flush();
        
        //记录日志
        $operationLogObj    = app::get('ome')->model('operation_log');
        $operationLogObj->write_log('o2o_store_import@wms', 0, "批量导入门店,本次共导入". count($aP['basicm']['contents']) ."条记录!");
        
        return null;
    }
    
    /**
     * 导入门店模板的标题
     *
     * @param Null
     * @return Array
     */
    function exportTemplate()
    {
        $tmpList    = $this->io_title();
        foreach ($tmpList as $v)
        {
            $title[]    = kernel::single('base_charset')->utf2local($v);
        }
        
        return $title;
    }
    
    /**
     * 导入导出的标题
     *
     * @param $ioType  导入文件格式
     * @return Array
     */
    function io_title($ioType='csv')
    {
        $this->oSchema['csv']    = array(
                                        '*:门店编码' => 'store_bn',
                                        '*:门店名称' => 'name',
                                        '*:所属组织' => 'organization_name',
                                        '*:状态' => 'status',
                                        '*:所属地区' => 'area',
                                        '*:所属服务端' => 'server_name',
                                        
                                        '*:门店地址' => 'addr',
                                        '*:邮编' => 'zip',
                                        '*:联系人' => 'contacter',
                                        '*:固定电话' => 'tel',
                                        '*:手机号' => 'mobile',
                                        
                                        '*:经营模式' => 'store_type',
                                        '*:所属经销商' => 'dealer_name',
                                        '*:覆盖区域' => 'coverage_area',
                                        /*
                                        '*:是否支持自提' => 'self_pick',
                                        '*:是否支持配送' => 'distribution',
                                        '*:是否支持门店售后' => 'aftersales',
                                        '*:是否需要订单回执确认' => 'confirm',
                                        '*:门店经纬度(以|竖线分隔)' => 'map',
                                        */
                                    );
        
        $this->ioTitle[$ioType]    = array_keys($this->oSchema[$ioType]);
        
        return $this->ioTitle[$ioType];
    }
    
    function modifier_is_ctrl_store($is_ctrl_store){
        return $is_ctrl_store == '1' ? '是' : '否';
    }
    
    function modifier_is_negative_store($is_negative_store){
        return $is_negative_store == '1' ? '是' : '否';
    }

    function modifier_is_o2o($is_o2o){
        return $is_o2o == '1' ? '是' : '否';
    }

    function modifier_status($status){
        return $status == '1' ? '开店' : '关店';
    }

    /**
     * 处理覆盖区域数据
     * @param string $coverageArea 覆盖区域字符串
     * @param array &$msg 错误信息
     * @return array|false 成功返回区域ID数组，失败返回false
     */
    private function processCoverageArea($coverageArea, &$msg)
    {
        $errmsg = '';
        $result = kernel::single('o2o_store_coverage')->parseCoverageAreaText($coverageArea, $errmsg);
        if ($result === false) {
            $msg['error'] = $errmsg;
            return false;
        }

        return $result;
    }

    /**
     * 处理经销商名称，返回对应的cos_id
     * @param string $dealerName 经销商名称
     * @param array &$msg 错误信息
     * @return string|false 成功返回cos_id，失败返回false
     */
    private function processDealerName($dealerName, &$msg)
    {
        $dealerName = trim($dealerName);
        if (empty($dealerName)) {
            return '';
        }

        // 查询经销商信息
        $cosMdl = app::get('organization')->model('cos');
        $dealer = $cosMdl->dump(array(
            'cos_name' => $dealerName,
            'cos_type' => 'bs' // 只查询经销商类型
        ), 'cos_id, cos_name');

        if (empty($dealer)) {
            $msg['error'] = "经销商[{$dealerName}]不存在或类型不正确";
            return false;
        }

        return $dealer['cos_id'];
    }

}
