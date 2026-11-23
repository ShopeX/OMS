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
 * 赠品规则记录
 *
 * @author wangbiao@shopex.cn
 * @version v0.1
 */
class crm_mdl_gift_rule_logs extends dbeav_model
{
    //导出的文件名
    var $export_name = '赠品规则记录';
    
    var $ioTitle = array();
    var $export_flag = false;
    //是否有导出配置
    var $has_export_cnf = true;
    
    function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        return parent::_filter($filter, $tableAlias, $baseWhere);
    }
    
    /**
     * 导入导出的标题
     *
     * @param Null
     * @return Array
     */
    function io_title($filter, $ioType='csv')
    {
        switch($filter)
        {
            case 'gift_rule':
                $this->oSchema['csv'][$filter] = array(
                    '*:规则编号' => 'rule_bn',
                    '*:规则名称' => 'rule_name',
                    '*:规则状态' => 'rule_status',
                    '*:赠品货号' => 'gift_bn',
                    '*:赠品名称' => 'gift_name',
                    '*:赠品状态' => 'gift_status',
                    '*:是否预警' => 'is_warning',
                    '*:预警数量' => 'warning_num',
                    '*:已赠送数量' => 'send_num',
                    '*:预警手机号' => 'warning_mobile',
                    '*:最后赠送日期' => 'send_time',
                    '*:创建时间' => 'create_time',
                    '*:最后修改日期' => 'update_time',
                );
            break;
            default:
                $this->oSchema['csv'][$filter] = array();
        }
        
        $this->ioTitle[$ioType][$filter] = array_keys($this->oSchema[$ioType][$filter]);
        
        return $this->ioTitle[$ioType][$filter];
    }
    
    /**
     * 导出模板的标题
     *
     * @param Null
     * @return array
     */
    function exportTemplate($filter)
    {
        foreach ($this->io_title($filter) as $v)
        {
            $title[] = $v;
        }
        
        return $title;
    }
    
    /**
     * 整理导出数据
     **/
    function fgetlist_csv(&$data, $filter, $offset, $exportType=1)
    {
        unset($filter['_io_type']);
        
        @ini_set('memory_limit','1024M');
        set_time_limit(0);
        
        $giftObj = app::get('crm')->model('gift');
        $ruleObj = app::get('crm')->model('gift_rule_base');
        
        $this->export_flag = true;
        $limit = 100;
        
        //限制导出的最大页码数(最多一次导出1w条记录)
        $max_offset = 100;
        if ($offset>$max_offset){
            return false;
        }
        
        //标题
        if(empty($data['title'])){
            $title = array();
            foreach( $this->io_title('gift_rule') as $key => $val){
                $title[] = $val;
            }
            $data['title'][] = '"'. implode('","', $title) .'"';
        }
        
        //赠送规则记录
        $tempList = $this->getList('*', $filter, $offset*$limit, $limit);
        if(empty($tempList)){
            return false;
        }
        
        $rule_ids = array();
        $gift_ids = array();
        foreach($tempList as $key => $val)
        {
            $rule_id = $val['rule_id'];
            $gift_id = $val['gift_id'];
            
            $rule_ids[$rule_id] = $rule_id;
            $gift_ids[$gift_id] = $gift_id;
        }
        
        //赠品规则信息
        $ruleList = array();
        $ruleTemp = $ruleObj->getList('id,title', array('rule_id'=>$rule_ids));
        foreach ((array)$ruleTemp as $key => $val)
        {
            $rule_id = $val['id'];
            
            $ruleList[$rule_id] = $val;
        }
        
        //赠送赠品信息
        $giftList = array();
        $giftTemp = $giftObj->getList('gift_id,gift_name,is_del', array('gift_id'=>$gift_ids));
        foreach ((array)$giftTemp as $key => $val)
        {
            $gift_id = $val['gift_id'];
            
            $val['gift_status'] = ($val['is_del']=='1' ? '禁用' : '启用');
            $giftList[$gift_id] = $val;
        }
        
        //赠送规则记录日志
        $ruleLogList = array();
        foreach($tempList as $key => $val)
        {
            $sid = $val['sid'];
            $rule_id = $val['rule_id'];
            $gift_id = $val['gift_id'];
            
            $ruleInfo = $ruleList[$rule_id];
            if($ruleInfo){
                $val['rule_name'] = $ruleInfo['title'];
                $val['rule_status'] = '使用中';
            }else{
                $val['rule_name'] = '已删除';
                $val['rule_status'] = '已删除';
            }
            
            $giftInfo = $giftList[$gift_id];
            if($giftInfo){
                $val['gift_name'] = $giftInfo['gift_name'];
                $val['gift_status'] = $giftInfo['gift_status'];
            }else{
                $val['gift_status'] = '已删除';
            }
            
            //export
            $exportData = array(
                    '*:规则编号' => $val['rule_bn'],
                    '*:规则名称' => $val['rule_name'],
                    '*:规则状态' => $val['rule_status'],
                    '*:赠品货号' => $val['gift_bn'],
                    '*:赠品名称' => $val['gift_name'],
                    '*:赠品状态' => $val['gift_status'],
                    '*:是否预警' => ($val['is_warning']=='true' ? '是' : '否'),
                    '*:预警数量' => $val['warning_num'],
                    '*:已赠送数量' => $val['send_num'],
                    '*:预警手机号' => $val['warning_mobile'],
                    '*:最后赠送日期' => ($val['send_time'] ? date('Y-m-d H:i:s', $val['send_time']) : ''),
                    '*:创建时间' => ($val['create_time'] ? date('Y-m-d H:i:s', $val['create_time']) : ''),
                    '*:最后修改日期' => ($val['update_time'] ? date('Y-m-d H:i:s', $val['update_time']) : ''),
            );
            
            $data['contents'][] = '"'. implode('","', $exportData) .'"';
        }
        
        return true;
    }
    
    function export_csv($data, $exportType=1)
    {
        $output = array();
        foreach($data['title'] as $k => $val){
            $output[] = kernel::single('base_charset')->utf2local($val);
        }
        
        foreach($data['contents'] as $k => $val){
            $output[] = kernel::single('base_charset')->utf2local($val);
        }
        
        echo implode("\n", $output);
    }
}