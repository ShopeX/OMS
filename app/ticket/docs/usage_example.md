# 订单赠品审批流程使用示例

## 概述

本文档展示了如何在订单编辑添加赠品后触发审批流程的完整实现。

## 数据库表结构

### 核心表关系

1. **订单相关表**
   - `ome_orders` - 订单主表
   - `ome_order_objects` - 订单子单表（包含赠品）
   - `ome_order_items` - 订单行明细表

2. **审批流程表**
   - `ticket_workflow_template` - 审批流模板表
   - `ticket_workflow_node` - 审批流节点表
   - `ticket_workflow_case` - 审批流实例表
   - `ticket_workflow_record` - 审批记录表

## 使用流程

### 1. 配置审批模板

首先需要创建赠品审批模板：

```php
// 创建审批模板
$templateData = [
    'template_bn' => 'GIFT_APPROVAL_001',
    'template_name' => '赠品审批模板',
    'scene_type' => 'add_gift',
    'description' => '用于订单添加赠品的审批流程',
    'is_enabled' => 'true'
];

$templateMdl = app::get('ticket')->model('workflow_template');
$template_id = $templateMdl->insert($templateData);

// 创建审批节点
$nodeData = [
    'node_name' => '部门经理审批',
    'node_type' => 'approval',
    'template_id' => $template_id,
    'step_order' => 1,
    'assignee_type' => 'user',
    'assignee_id' => 1001, // 部门经理用户ID
    'assignee_name' => '张经理'
];

$nodeMdl = app::get('ticket')->model('workflow_node');
$nodeMdl->insert($nodeData);
```

### 2. 订单编辑时触发审批

在订单编辑页面添加赠品时：

```php
// 订单编辑控制器
class ome_ctl_admin_orders extends desktop_controller {
    
    public function add_gift() {
        $order_id = $_POST['order_id'];
        $gift_items = $_POST['gift_items'];
        
        // 1. 保存赠品到订单
        $this->saveGiftToOrder($order_id, $gift_items);
        
        // 2. 触发审批流程
        $giftApprovalService = kernel::single('ticket_service_order_gift_approval');
        $result = $giftApprovalService->triggerGiftApproval($order_id, $gift_items);
        
        if($result['rsp'] == 'succ') {
            $this->end(true, '赠品添加成功，审批流程已启动');
        } else {
            $this->end(false, $result['error_msg']);
        }
    }
    
    private function saveGiftToOrder($order_id, $gift_items) {
        // 保存赠品到订单明细表
        $orderObjectMdl = app::get('ome')->model('order_objects');
        
        foreach($gift_items as $gift) {
            $objectData = [
                'order_id' => $order_id,
                'obj_type' => 'gift',
                'name' => $gift['name'],
                'price' => $gift['price'],
                'quantity' => $gift['quantity'],
                // ... 其他字段
            ];
            
            $orderObjectMdl->insert($objectData);
        }
    }
}
```

### 3. 审批人处理审批

审批人可以在审批页面查看和处理：

```php
// 审批页面
public function audit($case_id) {
    $caseMdl = app::get('ticket')->model('workflow_case');
    $caseInfo = $caseMdl->dump($case_id);
    
    // 获取订单和赠品信息
    $workflowCaseLib = kernel::single('ticket_workflow_case');
    $orderResult = $workflowCaseLib->getOrderDetails($caseInfo);
    
    $this->pagedata['caseInfo'] = $caseInfo;
    $this->pagedata['orderInfo'] = $orderResult['data'];
    $this->page('admin/workflow/case/audit.html');
}

// 处理审批
public function do_audit() {
    $data = $_POST;
    
    $workflowCaseLib = kernel::single('ticket_workflow_case');
    $result = $workflowCaseLib->processAudit($data, $error_msg);
    
    if($result['rsp'] == 'succ') {
        $this->end(true, '审批处理成功');
    } else {
        $this->end(false, $error_msg);
    }
}
```

### 4. 查看审批状态

订单编辑页面可以显示审批状态：

```php
// 在订单编辑页面显示审批状态
public function edit($order_id) {
    $orderMdl = app::get('ome')->model('orders');
    $orderInfo = $orderMdl->dump($order_id);
    
    // 检查审批状态
    $giftApprovalService = kernel::single('ticket_service_order_gift_approval');
    $approvalResult = $giftApprovalService->getOrderApprovalStatus($order_id);
    
    $this->pagedata['orderInfo'] = $orderInfo;
    $this->pagedata['approvalStatus'] = $approvalResult['data'];
    $this->page('admin/orders/edit.html');
}
```

## 前端页面示例

### 审批页面模板

```html
<!-- admin/workflow/case/audit.html -->
<div class="audit-container">
    <h3>订单赠品审批</h3>
    
    <div class="order-info">
        <p><strong>订单号：</strong>{$orderInfo.order_bn}</p>
        <p><strong>客户：</strong>{$orderInfo.ship_name}</p>
        <p><strong>订单金额：</strong>{$orderInfo.total_amount}</p>
    </div>
    
    <div class="gift-list">
        <h4>申请添加的赠品：</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>赠品名称</th>
                    <th>数量</th>
                    <th>单价</th>
                    <th>小计</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$giftList item=gift}
                <tr>
                    <td>{$gift.name}</td>
                    <td>{$gift.quantity}</td>
                    <td>{$gift.price}</td>
                    <td>{$gift.amount}</td>
                </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
    
    <form method="post" action="index.php?app=ticket&ctl=admin_workflow_case&act=do_audit">
        <input type="hidden" name="case_id" value="{$data.id}">
        
        <div class="audit-action">
            <label>审批意见：</label>
            <textarea name="remark" rows="4" cols="50"></textarea>
        </div>
        
        <div class="audit-buttons">
            <button type="submit" name="action" value="approve" class="btn btn-success">同意</button>
            <button type="submit" name="action" value="reject" class="btn btn-danger">拒绝</button>
            <button type="submit" name="action" value="return" class="btn btn-warning">退回</button>
        </div>
    </form>
</div>
```

### 订单编辑页面集成

```html
<!-- 在订单编辑页面添加审批状态显示 -->
<div class="approval-status">
    {if $approvalStatus}
        <h4>赠品审批状态</h4>
        {foreach from=$approvalStatus item=approval}
        <div class="approval-item">
            <p><strong>审批号：</strong>{$approval.case_bn}</p>
            <p><strong>状态：</strong>{$approval.status}</p>
            <p><strong>提交人：</strong>{$approval.submitter_name}</p>
            <p><strong>当前审批人：</strong>{$approval.assignee_name}</p>
            
            {if $approval.records}
            <div class="approval-records">
                <h5>审批记录：</h5>
                {foreach from=$approval.records item=record}
                <div class="record-item">
                    <span>{$record.assignee_name}</span>
                    <span>{$record.action}</span>
                    <span>{$record.remark}</span>
                    <span>{$record.process_time|date_format:'%Y-%m-%d %H:%M:%S'}</span>
                </div>
                {/foreach}
            </div>
            {/if}
        </div>
        {/foreach}
    {/if}
</div>
```

## API接口示例

### 创建审批实例

```php
// POST /api/ticket/workflow/create_gift_approval
{
    "order_id": 12345,
    "gift_items": [
        {
            "name": "赠品A",
            "quantity": 1,
            "price": 10.00,
            "amount": 10.00
        },
        {
            "name": "赠品B", 
            "quantity": 2,
            "price": 5.00,
            "amount": 10.00
        }
    ]
}

// 响应
{
    "rsp": "succ",
    "msg": "赠品审批流程已启动",
    "data": {
        "case_id": 1001,
        "case_bn": "WF202507100001"
    }
}
```

### 处理审批

```php
// POST /api/ticket/workflow/process_audit
{
    "case_id": 1001,
    "action": "approve",
    "remark": "同意添加赠品"
}

// 响应
{
    "rsp": "succ",
    "msg": "审批处理成功"
}
```

### 查询审批状态

```php
// GET /api/ticket/workflow/approval_status?order_id=12345

// 响应
{
    "rsp": "succ",
    "data": [
        {
            "case_id": 1001,
            "case_bn": "WF202507100001",
            "status": "approved",
            "submitter_name": "张三",
            "assignee_name": "李四",
            "start_time": 1640995200,
            "end_time": 1640998800,
            "records": [
                {
                    "assignee_name": "李四",
                    "action": "approve",
                    "remark": "同意添加赠品",
                    "process_time": 1640998800
                }
            ]
        }
    ]
}
```

## 注意事项

1. **权限控制**：确保只有有权限的用户才能进行审批操作
2. **状态同步**：审批通过后要及时更新订单状态
3. **日志记录**：所有审批操作都要记录详细日志
4. **异常处理**：处理审批流程中的各种异常情况
5. **性能优化**：大量数据时的查询和统计性能优化

## 扩展功能

1. **多级审批**：支持多级审批流程
2. **条件审批**：根据订单金额等条件自动选择审批人
3. **审批委托**：支持审批人委托他人审批
4. **审批提醒**：邮件、短信等提醒功能
5. **审批统计**：详细的审批数据统计和分析
