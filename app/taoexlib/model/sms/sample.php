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

class taoexlib_mdl_sms_sample extends dbeav_model 
{

    /**
     * 根据模板id获取模板信息
     *
     * @param  $smaple_id 模板id
     * @return array
     * @author 
     **/
    public function getBindBySampleId($sample_id)
    {
    	$res = app::get('taoexlib')->model('sms_bind')->select()->columns()->where('id=?',$sample_id)->instance()->fetch_row();
    	return $res;
    }
    /**
     * 根据模板id获取banding信息
     *
     * @param  $smaple_id 模板id
     * @return array
     * @author 
     **/
    public function getOpenBindBySampleId($sample_id)
    {
        $res = app::get('taoexlib')->model('sms_bind')->select()->columns()->where('id=?',$sample_id)->where('status=?','1')->instance()->fetch_row();
        return $res;
    }


    
    /**
     * 保存短信模板.
     * @param  array $params
     * @return  
     * @access  public
     * @author cyyr24@sina.cn
     */
    function save_sample($param)
    {
        $template_type = 'register';
        $db = kernel::database();
        $transaction = $db->beginTransaction();
        $result = $this->save($param);
        
        if ($param['id']) {
            if($this->isWsdlSmsChannelEnabled()){
                $param['status'] = '1';
                $param['approved'] = '1';
                $param['isapproved'] = 'true';
                $param['sync_status'] = 'true';
                $param['approvedtime'] = time();
                $param['createtime'] = time();
                $param['op_name'] = 'channel_sms';
                $items_result = app::get('taoexlib')->model('sms_sample_items')->save($param);
                $sampleData = array(
                    'id' => $param['id'],
                    'status' => '1',
                    'approved' => '1',
                    'isapproved' => 'true',
                );
                $this->save($sampleData);
                $db->commit($transaction);
                return $items_result;
            }

        	$op_name = kernel::single('desktop_user')->get_name();
        	$param['createtime'] = time();
        	$param['op_name'] = $op_name;
            $items_result = app::get('taoexlib')->model('sms_sample_items')->save($param);
            $api_results = kernel::single('taoexlib_request_sms')->sms_request($template_type,'post',$param);
            if ($api_results['res'] == 'succ') {
                $db->commit($transaction);
                //后续操作
                $tplid = $api_results['data']['tplid'];
                $iid = $param['iid'];
                 if ($tplid) {
                    $db->exec("UPDATE sdb_taoexlib_sms_sample_items SET tplid='".$tplid."' WHERE id=".$param['id']." AND iid=".$iid);
                    $db->exec("UPDATE sdb_taoexlib_sms_sample SET tplid='".$tplid."',approved='0' WHERE id=".$param['id']);
                }
                return true;
            }else{
                 $db->rollBack();
                 return false;
            }
        }
        
    }

    private function isWsdlSmsChannelEnabled()
    {
        $channelMdl = app::get('channel')->model('channel');
        $channels = $channelMdl->getList('channel_id,node_type', array('channel_type' => 'sms', 'disabled' => 'false'), 0, 1, 'channel_id ASC');
        if(!$channels || $channels[0]['node_type'] != 'soap'){
            return false;
        }

        $adapter = app::get('channel')->model('adapter')->db_dump(array('channel_id' => $channels[0]['channel_id']), '*');
        if(!$adapter || $adapter['adapter'] != 'openapi'){
            return false;
        }

        return true;
    }

     /**
     * 模板状态
     * @param   
     * @return  string
     * @access  public
     * @author cyyr24@sina.cn
     */
    function modifier_status($row)
    {
        $reason = sprintf("<div style='background-color:#a7a7a7;;float:left;'><span style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", '关闭');
        if ($row=='1') {
            $reason = sprintf("<div style='background-color:green;float:left;'><span style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>",  '开启');
           
        }
         return $reason;
        
    }
}
