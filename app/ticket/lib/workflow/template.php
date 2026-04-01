<?php
/**
 * 审批流模板业务逻辑类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_workflow_template extends ticket_abstract {
    /**
     * 验证数据
     *
     * @param array $params
     * @return array
     */
    public function checkParams(&$params)
    {
        $mdl = app::get('ticket')->model('workflow_template');
        
        // check template_bn
        if(empty($params['template_bn'])){
            $error_msg = '模板编号必须填写';
            return $this->error($error_msg);
        }
        
        // check template_name
        if(empty($params['template_name'])){
            $error_msg = '模板名称必须填写';
            return $this->error($error_msg);
        }

        // check scene_type
        if(empty($params['scene_type'])){
            $error_msg = '请选择审批场景类型';
            return $this->error($error_msg);
        }
        
        // check description
        if(empty($params['description'])){
            $error_msg = '流程描述必须填写';
            return $this->error($error_msg);
        }
        
        // is_enabled
        if($params['is_enabled'] == 'false'){
            $params['is_enabled'] = 'false';
        }else{
            $params['is_enabled'] = 'true';
        }
        
        // check exist
        if($params['id']){
            $rowInfo = $mdl->dump(array('id'=>$params['id']), '*');
            if(empty($rowInfo)){
                $error_msg = '编辑的记录不存在,请检查！';
                return $this->error($error_msg);
            }
        }else{
            //判断物料编码只能是由数字英文下划线组成
            $reg_bn_code = "/^[0-9a-zA-Z\_\#\-\/]*$/";
            if(!preg_match($reg_bn_code, $params['template_bn'])){
                $error_msg = "模板编号只支持(数字、英文、_下划线、-横线、#井号、/斜杠)组成";
                return $this->error($error_msg);
            }
            
            //编码首字母只支持数字、英文、_下划线
            $reg_rule_2 = "/^[0-9a-zA-Z\_]*$/";
            $first_letter = substr($params['template_bn'], 0, 1);
            if(!preg_match($reg_rule_2, $first_letter)){
                $error_msg = "模板编号首字母只支持(数字、英文、_下划线)组成";
                return $this->error($error_msg);
            }
        }
        
        return $this->succ('数据验证成功');
    }

    /**
     * 保存数据
     *
     * @param array $data
     * @param string $error_msg
     * @return bool
     */
    public function saveData(&$data, &$error_msg=null)
    {
        $mdl = app::get('ticket')->model('workflow_template');
        
        //data
        $saveData = [
            'template_bn' => $data['template_bn'],
            'template_name' => $data['template_name'],
            'scene_type' => $data['scene_type'],
            'description' => $data['description'],
            'is_enabled' => $data['is_enabled'],
            //'version' => 1,
        ];
        
        //check
        if(empty($saveData['template_bn'])){
            $error_msg = '提交的模板编号无效';
            return $this->error($error_msg);
        }
        
        if(empty($saveData['template_name'])){
            $error_msg = '提交的模板名称无效';
            return $this->error($error_msg);
        }
        
        if(empty($saveData['scene_type'])){
            $error_msg = '提交的模板类型无效';
            return $this->error($error_msg);
        }
        
        // insert or update
        if($data['id']){
            //update
            $rs = $mdl->update($saveData, array('id'=>$data['id']));
            if(is_bool($rs)) {
                $error_msg = '更新数据失败或者数据无变化';
                return $this->error($error_msg);
            }
        }else{
            //insert
            $rs = $mdl->insert($saveData);
            if(!$rs){
                $error_msg = '插入数据失败';
                return $this->error($error_msg);
            }
        }
        
        return $this->succ('数据保存成功');
    }
    
    /**
     * 获取指定的审批场景类型名称
     *
     * @param $type_bn
     * @return string
     */
    public function getSceneTypeName($type_bn)
    {
        $mdl = app::get('ticket')->model('workflow_template');
        
        $typeList = $mdl->getSceneTypes();
        
        if(isset($typeList[$type_bn])){
            return $typeList[$type_bn];
        }else{
            return '';
        }
    }
    
    /**
     * 获取所有审批模拟
     *
     * @return array
     */
    public function getTemplateList($template_id=0)
    {
        $mdl = app::get('ticket')->model('workflow_template');
        
        // filter
        $filter = ['is_enabled'=>'true'];
        
        // 指定模板ID
        if($template_id){
            $filter['template_id'] = $template_id;
        }
        
        // list
        $templateList = $mdl->getList('*', $filter, 0, -1);
        
        return $templateList;
    }
}