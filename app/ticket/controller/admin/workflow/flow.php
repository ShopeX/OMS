<?php
/**
 * 审批流API控制器
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_ctl_admin_workflow_flow extends desktop_controller {
    var $title = '审批流API';
    var $workground = 'ticket_center';
    
    public function __construct($app)
    {
        parent::__construct($app);
    }

    /**
     * 获取审批流场景类型列表
     */
    public function getSceneTypeList() {
        try {
            $templateMdl = app::get('ticket')->model('workflow_template');
            
            // 定义场景类型（写死）
            $sceneTypes = [
                [
                    'scene_type' => 'add_gift',
                    'scene_name' => '加赠',
                    'scene_memo' => '加赠商品审批流程'
                ]
            ];
            
            $sceneList = [];
            foreach($sceneTypes as $sceneType){
                // 查询该场景类型的模板配置
                $templateInfo = $templateMdl->dump(['scene_type' => $sceneType['scene_type']], 'id,is_enabled');
                
                // 根据查询结果设置status
                if(empty($templateInfo)){
                    // 没有数据，未配置
                    $status = '0';
                } elseif($templateInfo['is_enabled'] == 'true'){
                    // 已配置
                    $status = '1';
                } else {
                    // 已禁用
                    $status = '2';
                }
                
                $sceneList[] = [
                    'scene_type' => $sceneType['scene_type'],
                    'scene_name' => $sceneType['scene_name'],
                    'scene_memo' => $sceneType['scene_memo'],
                    'status' => $status  // 0（未配置），1（已配置），2（已禁用）
                ];
            }
            
            $this->returnJson($sceneList, true, '获取审批流场景类型列表成功');
        } catch (Exception $e) {
            $this->returnJson([], false, '获取审批流场景类型列表失败：' . $e->getMessage());
        }
    }

    /**
     * 根据场景类型获取审批流配置
     */
    public function getWorkflowBySceneType() {
        try {
            // 获取JSON数据
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            // 如果JSON解析失败，尝试从$_POST获取
            if(empty($data)){
                $data = $_POST;
            }
            
            $scene_type = $data['scene_type'] ?? '';
            
            if(empty($scene_type)){
                $this->returnJson([], false, '场景类型不能为空');
            }
            
            // 查询模板信息
            $templateMdl = app::get('ticket')->model('workflow_template');
            $templateInfo = $templateMdl->dump(['scene_type' => $scene_type], '*');
            
            if(empty($templateInfo)){
                $this->returnJson(['nodes'=>[]], true, '获取审批流配置成功');
            }
            
            // 查询节点信息
            $nodeMdl = app::get('ticket')->model('workflow_node');
            $nodeList = $nodeMdl->getList('*', ['template_id' => $templateInfo['id']], 0, -1, 'step_order ASC');
            
            // 格式化节点数据
            $nodes = [];
            foreach($nodeList as $node){
                $nodes[] = [
                    'node_id' => $node['id'],
                    'node_name' => $node['node_name'],
                    'node_type' => $node['node_type'],
                    'step_order' => $node['step_order'],
                    'assignee_type' => $node['assignee_type'],
                    'assignee_id' => $node['assignee_id'],
                    'assignee_name' => $node['assignee_name']
                ];
            }
            
            // 组装返回数据
            $result = [
                'template_id' => $templateInfo['id'],
                'template_bn' => $templateInfo['template_bn'],
                'template_name' => $templateInfo['template_name'],
                'scene_type' => $templateInfo['scene_type'],
                'description' => $templateInfo['description'],
                'version' => '1', // 固定版本号
                'status' => $templateInfo['is_enabled'] == 'true' ? '1' : '2', // 1已配置，2已禁用
                'nodes' => $nodes
            ];
            
            $this->returnJson($result, true, '获取审批流配置成功');
        } catch (Exception $e) {
            $this->returnJson([], false, '获取审批流配置失败：' . $e->getMessage());
        }
    }

    /**
     * 保存审批流（包含模板和节点信息）
     * 支持新建和更新两种场景
     */
    public function saveWorkflow() {
        try {
            // 获取JSON数据
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            // 如果JSON解析失败，尝试从$_POST获取
            if(empty($data)){
                $data = $_POST;
            }
            
            // 验证必要参数
            if(empty($data['template_name'])){
                $this->returnJson([], false, '模板名称不能为空');
            }
            
            if(empty($data['scene_type'])){
                $this->returnJson([], false, '场景类型不能为空');
            }
            
            if(empty($data['nodes']) || !is_array($data['nodes'])){
                $this->returnJson([], false, '节点数据不能为空');
            }
            
            $templateMdl = app::get('ticket')->model('workflow_template');
            $nodeMdl = app::get('ticket')->model('workflow_node');
            
            // 开启事务
            $db = kernel::database();
            $tran = $db->beginTransaction();
            
            // 处理status转is_enabled
            // status: 1启用, 2禁用 -> is_enabled: true启用, false禁用
            $is_enabled = 'true'; // 默认启用
            if(isset($data['status'])){
                $is_enabled = ($data['status'] == '1' || $data['status'] == 1) ? 'true' : 'false';
            } elseif(isset($data['is_enabled'])){
                $is_enabled = $data['is_enabled'];
            }
            
            // 准备模板数据
            $templateData = [
                'template_name' => $data['template_name'],
                'scene_type' => $data['scene_type'],
                'description' => is_array($data['description']) ? implode('', $data['description']) : $data['description'],
                'is_enabled' => $is_enabled
            ];
            
            // 判断是新建还是更新
            $is_edit = !empty($data['template_id']);
            $old_template_data = null; // 用于存储更新前的数据
            
            if(!$is_edit){
                // 新建模板
                // 生成模板编号
                $templateData['template_bn'] = 'TPL_' . strtoupper(uniqid());
                
                $insertResult = $templateMdl->insert($templateData);
                if(!$insertResult){
                    $db->rollBack();
                    $this->returnJson([], false, '创建模板失败');
                }
                
                $template_id = $templateData['id'];
                
                // 新建时直接插入节点数据
                foreach($data['nodes'] as $nodeData){
                    if(empty($nodeData['node_name']) || empty($nodeData['node_type'])){
                        continue; // 跳过无效节点
                    }
                    
                    $insertNodeData = [
                        'template_id' => $template_id,
                        'node_name' => $nodeData['node_name'],
                        'node_type' => $nodeData['node_type'],
                        'step_order' => $nodeData['step_order'] ?? 1,
                        'assignee_type' => $nodeData['assignee_type'] ?? 'user',
                        'assignee_id' => $nodeData['assignee_id'] ?? 0,
                        'assignee_name' => $nodeData['assignee_name'] ?? ''
                    ];
                    
                    $insertResult = $nodeMdl->insert($insertNodeData);
                    if(!$insertResult){
                        $db->rollBack();
                        $this->returnJson([], false, '插入节点数据失败');
                    }
                }
            } else {
                // 更新模板 - 先获取更新前的数据
                $template_id = $data['template_id'];
                $old_template_data = $templateMdl->dump(['id' => $template_id], '*, id as template_id');
                
                // 获取更新前的节点数据
                $old_nodes = $nodeMdl->getList('*, id as node_id', ['template_id' => $template_id], 0, -1, 'step_order ASC');
                $old_template_data['nodes'] = $old_nodes;
                
                $updateResult = $templateMdl->update($templateData, ['id' => $template_id]);
                if($updateResult === false){
                    $db->rollBack();
                    $this->returnJson([], false, '更新模板信息失败');
                }
                
                // 精细处理节点更新
                $this->updateWorkflowNodes($template_id, $data['nodes'], $old_nodes, $db);
            }
            
            // 提交事务
            $db->commit($tran);
            
            // 记录操作日志
            $this->recordWorkflowLog($template_id, $data, $old_template_data, $is_edit);
            
            $this->returnJson([], true, '保存审批流成功');
            
        } catch (Exception $e) {
            $this->returnJson([], false, '保存审批流失败：' . $e->getMessage());
        }
    }

    /**
     * 获取审批人列表
     */
    public function getAssigneeList() {
        try {
            // 获取JSON数据
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            // 如果JSON解析失败，尝试从$_POST获取
            if(empty($data)){
                $data = $_POST;
            }
            
            $assignee_type = $data['assignee_type'] ?? 'user';
            
            // 根据审批人类型返回不同数据
            if($assignee_type == 'user'){
                // 获取用户列表
                $userMdl = app::get('desktop')->model('users');
                $userList = $userMdl->getList('user_id,name', ['status'=>1], 0, -1);
                
                // 格式化数据
                $assigneeList = [];
                foreach($userList as $user){
                    $assigneeList[] = [
                        'id' => $user['user_id'],
                        'name' => $user['name']
                    ];
                }
                
                $result = [
                    'assignee' => $assigneeList
                ];
            } elseif($assignee_type == 'role'){
                // 角色类型暂时返回空数组
                $result = [
                    'assignee' => []
                ];
            } elseif($assignee_type == 'dept'){
                // 部门类型暂时返回空数组
                $result = [
                    'assignee' => []
                ];
            } else {
                // 其他类型返回空数组
                $result = [
                    'assignee' => []
                ];
            }
            
            $this->returnJson($result, true, '获取审批人列表成功');
        } catch (Exception $e) {
            $this->returnJson([], false, '获取审批人列表失败：' . $e->getMessage());
        }
    }

    /**
     * 精细处理节点更新（增删改）
     */
    private function updateWorkflowNodes($template_id, $new_nodes, $old_nodes, $db)
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        // 将旧节点按node_id建立索引
        $old_nodes_map = [];
        foreach($old_nodes as $old_node){
            $old_nodes_map[$old_node['id']] = $old_node;
        }
        
        // 将新节点按node_id建立索引（如果有node_id的话）
        $new_nodes_map = [];
        foreach($new_nodes as $new_node){
            if(!empty($new_node['node_id'])){
                $new_nodes_map[$new_node['node_id']] = $new_node;
            }
        }
        
        // 1. 处理需要删除的节点
        $nodes_to_delete = [];
        foreach($old_nodes_map as $old_node_id => $old_node){
            if(!isset($new_nodes_map[$old_node_id])){
                $nodes_to_delete[] = $old_node_id;
            }
        }
        
        // 检查删除的节点是否被使用中
        if(!empty($nodes_to_delete)){
            $used_nodes = $caseMdl->getList('distinct current_node_id', [
                'template_id' => $template_id,
                'current_node_id|in' => $nodes_to_delete,
                'status|in' => ['pending', 'processing', 'approved']
            ]);
            
            if(!empty($used_nodes)){
                $db->rollBack();
                $used_node_ids = array_column($used_nodes, 'current_node_id');
                $used_node_names = [];
                foreach($used_node_ids as $used_node_id){
                    $used_node_names[] = $old_nodes_map[$used_node_id]['node_name'] ?? '未知节点';
                }
                $this->returnJson([], false, "节点「" . implode('、', $used_node_names) . "」正在被审批流程使用中，无法删除");
            }
            
            // 执行删除
            $deleteResult = $nodeMdl->delete(['id' => $nodes_to_delete]);
            if($deleteResult === false){
                $db->rollBack();
                $this->returnJson([], false, '删除节点失败');
            }
        }
        
        // 2. 处理需要更新的节点
        foreach($new_nodes_map as $node_id => $new_node){
            if(isset($old_nodes_map[$node_id])){
                // 更新节点
                $updateNodeData = [
                    'node_name' => $new_node['node_name'],
                    'node_type' => $new_node['node_type'],
                    'step_order' => $new_node['step_order'] ?? 1,
                    'assignee_type' => $new_node['assignee_type'] ?? 'user',
                    'assignee_id' => $new_node['assignee_id'] ?? 0,
                    'assignee_name' => $new_node['assignee_name'] ?? ''
                ];
                
                $updateResult = $nodeMdl->update($updateNodeData, ['id' => $node_id]);
                if($updateResult === false){
                    $db->rollBack();
                    $node_name = $new_node['node_name'] ?? '未知节点';
                    $this->returnJson([], false, "更新节点「{$node_name}」失败");
                }
            }
        }
        
        // 3. 处理需要新增的节点
        foreach($new_nodes as $new_node){
            if(empty($new_node['node_id']) || !isset($old_nodes_map[$new_node['node_id']])){
                // 新增节点
                if(empty($new_node['node_name']) || empty($new_node['node_type'])){
                    continue; // 跳过无效节点
                }
                
                $insertNodeData = [
                    'template_id' => $template_id,
                    'node_name' => $new_node['node_name'],
                    'node_type' => $new_node['node_type'],
                    'step_order' => $new_node['step_order'] ?? 1,
                    'assignee_type' => $new_node['assignee_type'] ?? 'user',
                    'assignee_id' => $new_node['assignee_id'] ?? 0,
                    'assignee_name' => $new_node['assignee_name'] ?? ''
                ];
                
                $insertResult = $nodeMdl->insert($insertNodeData);
                if(!$insertResult){
                    $db->rollBack();
                    $this->returnJson([], false, '插入新节点数据失败');
                }
            }
        }
    }

    /**
     * 记录审批流操作日志
     */
    private function recordWorkflowLog($template_id, $data, $old_template_data, $is_edit = false)
    {
        // 获取操作日志模型
        $omeLogMdl = app::get('ome')->model('operation_log');
        
        // 准备日志数据
        $log_key = $is_edit ? 'ticket_workflow_edit@ticket' : 'ticket_workflow_add@ticket';
        $operation_type = $is_edit ? '编辑' : '新建';
        
        // 构建memo信息
        $memo = "审批流{$operation_type}：模板名称={$data['template_name']}，场景类型={$data['scene_type']}，节点数量=" . count($data['nodes']);
        
        // 记录日志
        $log_id = $omeLogMdl->write_log($log_key, $template_id, $memo);
        
        // 生成操作快照
        if ($log_id) {
            $shootMdl = app::get('ome')->model('operation_log_snapshoot');
            
            // snapshoot是更新前的数据，updated是更新后的数据
            $snapshoot = $is_edit && $old_template_data ? json_encode($old_template_data, JSON_UNESCAPED_UNICODE) : '';
            $updated = json_encode($data, JSON_UNESCAPED_UNICODE);
            
            $tmp = [
                'log_id' => $log_id, 
                'snapshoot' => $snapshoot,
                'updated' => $updated
            ];
            $shootMdl->insert($tmp);
        }
    }

}
