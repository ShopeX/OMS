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
 * ============================
 * @Author:   Assistant
 * @Version:  1.0
 * @DateTime: 2024年
 * @describe: 经销商物料价格管理
 * ============================
 */
class dealer_ctl_admin_goods_price extends desktop_controller 
{
    /**
     * index
     * @return mixed 返回值
     */

    public function index() 
    {
        // 检查导出权限
        if (isset($_GET['action']) && $_GET['action'] == 'to_export') {
            if (!$this->has_permission('dealer_goods_price_export')) {
                $this->splash('error', null, '您没有导出经销商物料价格的权限');
            }
        }
        
        $actions = array();
        
        // 新增按钮权限控制
        if ($this->has_permission('dealer_goods_price_add')) {
            $actions[] = array(
                'label'  => '新增',
                'href'   => 'index.php?app=dealer&ctl=admin_goods_price&act=add',
                'target' => 'dialog::{width:1000,height:600,title:\'新增经销商价格\'}',
            );
        }
        
        // 导入按钮权限控制
        if ($this->has_permission('dealer_goods_price_import')) {
            $actions[] = array(
                'label'  => '导入',
                'href'   => 'index.php?app=dealer&ctl=admin_goods_price&act=displayImportV2&p[0]=goods_price&finder_id={finder_id}',
                'target' => 'dialog::{width:760,height:300,title:\'经销基础物料价格导入\'}',
            );
        }
        
        // 批量更新按钮权限控制（两个更新按钮使用同一个权限）
        if ($this->has_permission('dealer_goods_price_batchupdate')) {
            $actions[] = array(
                'label'  => '批量更新生效时间',
                'submit' => 'index.php?app=dealer&ctl=admin_goods_price&act=batchUpdateStartTime',
                'target' => 'dialog::{width:400,height:300,title:\'批量更新生效时间\'}',
            );
            $actions[] = array(
                'label'  => '批量更新过期时间',
                'submit' => 'index.php?app=dealer&ctl=admin_goods_price&act=batchUpdateEndTime',
                'target' => 'dialog::{width:400,height:300,title:\'批量更新过期时间\'}',
            );
        }
        
        $params = array(
            'title' => '经销基础物料价格管理',
            'use_buildin_set_tag' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => $this->has_permission('dealer_goods_price_export'),
            'use_buildin_import' => false,
            'use_buildin_importxls' => false,
            'use_buildin_recycle' => false,
            'actions' => $actions,
            'orderBy' => 'id desc',
        );
        
        $this->finder('dealer_mdl_goods_price', $params);
    }
    
    /**
     * 新版导出模板方法，参考订单的实现方式
     */
    public function exportTemplateV2()
    {
        $fileName = "经销商物料价格导入模板.xlsx";
        $title = app::get('dealer')->model('goods_price')->exportTemplateV2('dealer_goods_price_importV2');
        kernel::single('omecsv_phpoffice')->export($fileName, [0 => $title]);
    }
    
    public function edit()
    {
        // 检查编辑权限
        if (!$this->has_permission('dealer_goods_price_edit')) {
            $this->splash('error', null, '您没有编辑经销商物料价格的权限');
        }
        
        $id = $_GET['p'][0];
        if (empty($id)) {
            $this->splash('error', null, '缺少记录ID');
        }
        
        $priceObj = app::get('dealer')->model('goods_price');
        $data = $priceObj->dump(array('id' => $id));
        
        if (!$data) {
            $this->splash('error', null, '记录不存在');
        }
        
        // 获取所有经销商 - 简化查询
        $dealerOptions = array();
        try {
            $dealerObj = app::get('dealer')->model('business');
            $dealerList = $dealerObj->getList('bs_id,bs_bn,name');
            
            foreach ($dealerList as $dealer) {
                $dealerOptions[$dealer['bs_id']] = '[' . $dealer['bs_bn'] . ']' . $dealer['name'];
            }
        } catch (Exception $e) {
            // 如果获取经销商失败，设置默认值
            $dealerOptions[$data['bs_id']] = '经销商ID: ' . $data['bs_id'];
        }
        
        // 获取物料信息 - 简化处理
        $material = array(
            'bm_id' => $data['bm_id'],
            'material_bn' => '物料编码: ' . $data['bm_id'],
            'material_name' => '物料名称: ' . $data['bm_id'],
            'specifications' => ''
        );
        
        try {
            $materialObj = app::get('material')->model('basic_material');
            $materialData = $materialObj->dump(array('bm_id' => $data['bm_id']), 'bm_id,material_bn,material_name');
            if ($materialData) {
                $material = array_merge($material, $materialData);
            }
        } catch (Exception $e) {
            // 忽略错误，使用默认值
        }
        
        // 格式化时间数据
        $start_time_formatted = '';
        $end_time_formatted = '';
        
        if (!empty($data['start_time'])) {
            $start_time_formatted = date('Y-m-d', $data['start_time']);
        }
        
        if (!empty($data['end_time'])) {
            $end_time_formatted = date('Y-m-d', $data['end_time']);
        }
        
        $this->pagedata['data'] = $data;
        $this->pagedata['material'] = $material;
        $this->pagedata['dealerOptions'] = $dealerOptions;
        $this->pagedata['start_time_formatted'] = $start_time_formatted;
        $this->pagedata['end_time_formatted'] = $end_time_formatted;
        $this->display('admin/goods/price/edit_simple.html');
    }
    
    
    /**
     * 添加
     * @return mixed 返回值
     */
    public function add()
    {
        // 检查新增权限
        if (!$this->has_permission('dealer_goods_price_add')) {
            $this->splash('error', null, '您没有新增经销商物料价格的权限');
        }
        
        // 获取所有经销商
        $dealerObj = app::get('dealer')->model('business');
        $dealerList = $dealerObj->getList('bs_id,bs_bn,name', array('status' => 'active'));
        
        $dealerOptions = array();
        foreach ($dealerList as $dealer) {
            $dealerOptions[$dealer['bs_id']] = '[' . $dealer['bs_bn'] . ']' . $dealer['name'];
        }
        
        $this->pagedata['dealerOptions'] = $dealerOptions;
        $this->display('admin/goods/price/add.html');
    }
    
    /**
     * 保存
     * @return mixed 返回操作结果
     */
    public function save()
    {
        $this->begin('index.php?app=dealer&ctl=admin_goods_price&act=index'); // 开始事务，返回列表页
        
        // 判断是新增还是编辑
        $is_edit = !empty($_POST['id']);
        
        // 根据操作类型检查对应权限
        if ($is_edit) {
            if (!$this->has_permission('dealer_goods_price_edit')) {
                $this->end(false, '您没有编辑经销商物料价格的权限');
            }
        } else {
            if (!$this->has_permission('dealer_goods_price_add')) {
                $this->end(false, '您没有新增经销商物料价格的权限');
            }
        }
        
        if (empty($_POST['bs_id'])) {
            $this->end(false, '请选择经销商');
        }
        
        // 获取价格模型对象
        $priceObj = app::get('dealer')->model('goods_price');
        
        if ($is_edit) {
            // 编辑模式：单个商品
            if (empty($_POST['bm_id'])) {
                $this->end(false, '基础物料信息缺失');
            }
            
            // 验证必填字段
            if (empty($_POST['start_time'])) {
                $this->end(false, '生效时间不能为空');
            }
            
            if (empty($_POST['end_time'])) {
                $this->end(false, '过期时间不能为空');
            }
            
            // 数据清理和验证（包含价格必填和格式验证）
            list($price_valid, $price, $price_error) = $priceObj->validatePrice($_POST['price']);
            if (!$price_valid) {
                $this->end(false, $price_error);
            }
            
            // 时间验证
            $start_time = strtotime($_POST['start_time'] . ' 00:00:00');
            $end_time = strtotime($_POST['end_time'] . ' 23:59:59');
            
            $validation_result = $priceObj->validateTimeData($start_time, $end_time, $_POST['bs_id'], $_POST['bm_id'], $_POST['id']);
            if ($validation_result !== true) {
                $this->end(false, $validation_result);
            }
            
            $data = array(
                'bs_id' => $_POST['bs_id'],
                'bm_id' => $_POST['bm_id'],
                'price' => $price,
                'price_unit' => !empty($_POST['price_unit']) ? $_POST['price_unit'] : '',
                'start_time' => $start_time,
                'end_time' => $end_time,
            );
            
            try {
                // 确保记录存在，并获取原始数据用于日志
                $existing = $priceObj->dump(array('id' => $_POST['id']));
                if (!$existing) {
                    $this->end(false, '要编辑的记录不存在');
                }
                
                $old_price = $existing['price'];
                $old_price_unit = $existing['price_unit'] ?: '未设置';
                $old_start_time = $existing['start_time'] ? date('Y-m-d', $existing['start_time']) : '未设置';
                $old_end_time = $existing['end_time'] ? date('Y-m-d', $existing['end_time']) : '未设置';
                $new_start_time = $data['start_time'] ? date('Y-m-d', $data['start_time']) : '未设置';
                $new_end_time = $data['end_time'] ? date('Y-m-d', $data['end_time']) : '未设置';
                $new_price_unit = $data['price_unit'] ?: '未设置';
                
                $result = $priceObj->update($data, array('id' => $_POST['id']));
                
                if ($result) {
                    // 记录编辑日志和快照
                    $operInfo = kernel::single('ome_func')->getDesktopUser();
                    $omeLogMdl = app::get('ome')->model('operation_log');
                    $memo = "编辑价格：{$old_price}->{$data['price']}，价格单位：{$old_price_unit}->{$new_price_unit}，生效时间：{$old_start_time}->{$new_start_time}，过期时间：{$old_end_time}->{$new_end_time}";
                    
                    $log_id = $omeLogMdl->write_log('dealer_goods_price_edit@dealer', $_POST['id'], $memo);
                    
                    // 生成操作快照
                    if ($log_id) {
                        $shootMdl = app::get('ome')->model('operation_log_snapshoot');
                        $snapshoot = json_encode($existing, JSON_UNESCAPED_UNICODE);
                        $updated = json_encode($data, JSON_UNESCAPED_UNICODE);
                        $tmp = [
                            'log_id' => $log_id, 
                            'snapshoot' => $snapshoot,
                            'updated' => $updated
                        ];
                        $shootMdl->insert($tmp);
                    }
                    
                    $this->end(true, '价格更新成功');
                } else {
                    $this->end(false, '价格更新失败');
                }
            } catch (Exception $e) {
                $this->end(false, '价格更新异常：' . $e->getMessage());
            }
        } else {
            // 新增模式：多个商品
            if (empty($_POST['items']) || !is_array($_POST['items'])) {
                $this->end(false, '请添加商品价格信息');
            }
            
            $success_count = 0;
            $error_count = 0;
            $error_messages = array();
            
            foreach ($_POST['items'] as $product_id => $item) {
                // 获取物料编码用于错误提示
                $material_bn = isset($item['material_bn']) ? $item['material_bn'] : '未知';
                
                // 验证必填字段
                if (empty($item['start_time'])) {
                    $error_count++;
                    $error_messages[] = "物料编码 {$material_bn}: 生效时间不能为空";
                    continue;
                }
                
                if (empty($item['end_time'])) {
                    $error_count++;
                    $error_messages[] = "物料编码 {$material_bn}: 过期时间不能为空";
                    continue;
                }
                
                // 数据清理和验证（包含价格必填和格式验证）
                list($price_valid, $price, $price_error) = $priceObj->validatePrice($item['price']);
                if (!$price_valid) {
                    $error_count++;
                    $error_messages[] = "物料编码 {$material_bn}: " . $price_error;
                    continue;
                }
                
                // 时间验证
                $start_time = strtotime($item['start_time'] . ' 00:00:00');
                $end_time = strtotime($item['end_time'] . ' 23:59:59');
                
                $validation_result = $priceObj->validateTimeData($start_time, $end_time, $_POST['bs_id'], $product_id);
                if ($validation_result !== true) {
                    $error_count++;
                    $error_messages[] = "物料编码 {$material_bn}: " . $validation_result;
                    continue;
                }
                
                $data = array(
                    'bs_id' => $_POST['bs_id'],
                    'bm_id' => $product_id,
                    'price' => $price,
                    'price_unit' => !empty($item['price_unit']) ? $item['price_unit'] : '',
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                );
                
                try {
                    // 直接创建新记录（时间段重叠已在上面验证过）
                    $result = $priceObj->insert($data);
                    
                    if ($result) {
                        // 记录新增日志
                        $omeLogMdl = app::get('ome')->model('operation_log');
                        $omeLogMdl->write_log('dealer_goods_price_add@dealer', $result, '');
                        $success_count++;
                    } else {
                        $error_count++;
                        $error_messages[] = "保存物料编码 {$material_bn} 失败";
                    }
                } catch (Exception $e) {
                    $error_count++;
                    $error_messages[] = "物料编码 {$material_bn} 保存异常：" . $e->getMessage();
                }
            }
            
            if ($success_count > 0 && $error_count == 0) {
                $this->end(true, "成功保存 {$success_count} 条价格记录");
            } else if ($success_count > 0 && $error_count > 0) {
                $message = "成功保存 {$success_count} 条，失败 {$error_count} 条";
                if (!empty($error_messages)) {
                    $message .= "；错误详情：" . implode('；', array_slice($error_messages, 0, 3));
                }
                $this->end(false, $message);
            } else {
                $message = "保存失败";
                if (!empty($error_messages)) {
                    $message .= "：" . implode('；', array_slice($error_messages, 0, 3));
                }
                $this->end(false, $message);
            }
        }
    }

    /**
     * 查看快照
     */
    public function show_history($log_id)
    {
        $logSnapshootMdl = app::get('ome')->model('operation_log_snapshoot');
        $log = $logSnapshootMdl->db_dump(['log_id' => $log_id]);
        $row = json_decode($log['snapshoot'], 1);
        
        if (!$row) {
            $this->splash('error', null, '快照数据不存在');
        }
        
        // 对比数据差异
        $diff_data = array();
        if (!empty($log['updated'])) {
            $updated_data = json_decode($log['updated'], 1);
            $diff_data = $this->comparePriceData($row, $updated_data);
        }
        
        // 获取经销商信息
        $dealerOptions = array();
        try {
            $dealerObj = app::get('dealer')->model('business');
            $dealerList = $dealerObj->getList('bs_id,bs_bn,name');
            
            foreach ($dealerList as $dealer) {
                $dealerOptions[$dealer['bs_id']] = $dealer['bs_bn'] . ' - ' . $dealer['name'];
            }
        } catch (Exception $e) {
            $dealerOptions[$row['bs_id']] = '经销商ID: ' . $row['bs_id'];
        }
        
        // 获取物料信息
        $material = array(
            'bm_id' => $row['bm_id'],
            'material_bn' => '物料编码: ' . $row['bm_id'],
            'material_name' => '物料名称: ' . $row['bm_id'],
            'specifications' => ''
        );
        
        try {
            $materialObj = app::get('material')->model('basic_material');
            $materialData = $materialObj->dump(array('bm_id' => $row['bm_id']), 'bm_id,material_bn,material_name');
            if ($materialData) {
                $material = array_merge($material, $materialData);
            }
        } catch (Exception $e) {
            // 忽略错误，使用默认值
        }
        
        // 格式化时间数据
        $start_time_formatted = '';
        $end_time_formatted = '';
        
        if (!empty($row['start_time'])) {
            $start_time_formatted = date('Y-m-d', $row['start_time']);
        }
        
        if (!empty($row['end_time'])) {
            $end_time_formatted = date('Y-m-d', $row['end_time']);
        }
        
        $this->pagedata['data'] = $row;
        $this->pagedata['diff_data'] = $diff_data;
        $this->pagedata['material'] = $material;
        $this->pagedata['dealerOptions'] = $dealerOptions;
        $this->pagedata['start_time_formatted'] = $start_time_formatted;
        $this->pagedata['end_time_formatted'] = $end_time_formatted;
        $this->pagedata['history'] = true;
        $this->singlepage('admin/goods/price/edit_simple.html');
    }
    
    /**
     * 批量更新生效时间
     */
    public function batchUpdateStartTime()
    {
        // 检查批量更新权限
        if (!$this->has_permission('dealer_goods_price_batchupdate')) {
            $this->splash('error', null, '您没有批量更新经销商物料价格的权限');
        }
        
        // 获取选中的记录ID
        $selectedIds = $_POST['id'];
        if (empty($selectedIds)) {
            $this->splash('error', null, '请先选择要更新的记录');
        }
        
        $this->pagedata['action_type'] = 'start_time';
        $this->pagedata['action_title'] = '批量更新生效时间';
        $this->pagedata['selectedIds'] = $selectedIds;
        $this->display('admin/goods/price/batch_update_time.html');
    }
    
    /**
     * 批量更新过期时间
     */
    public function batchUpdateEndTime()
    {
        // 检查批量更新权限
        if (!$this->has_permission('dealer_goods_price_batchupdate')) {
            $this->splash('error', null, '您没有批量更新经销商物料价格的权限');
        }
        
        // 获取选中的记录ID
        $selectedIds = $_POST['id'];
        if (empty($selectedIds)) {
            $this->splash('error', null, '请先选择要更新的记录');
        }
        
        $this->pagedata['action_type'] = 'end_time';
        $this->pagedata['action_title'] = '批量更新过期时间';
        $this->pagedata['selectedIds'] = $selectedIds;
        $this->display('admin/goods/price/batch_update_time.html');
    }
    
    /**
     * 执行批量更新时间
     */
    public function doBatchUpdateTime()
    {
        // 检查批量更新权限
        if (!$this->has_permission('dealer_goods_price_batchupdate')) {
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'message' => '您没有批量更新经销商物料价格的权限', 'error_messages' => array()));
            exit;
        }
        
        $action_type = $_POST['action_type'];
        $new_time = $_POST['new_time'];
        $selected_ids = $_POST['selected_ids'];
        
        if (empty($new_time)) {
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'message' => '请选择新的时间', 'error_messages' => array()));
            exit;
        }
        
        if (empty($selected_ids) || !is_array($selected_ids)) {
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'message' => '请选择要更新的记录', 'error_messages' => array()));
            exit;
        }
        
        // 验证时间格式
        $timestamp = strtotime($new_time . ' ' . ($action_type == 'start_time' ? '00:00:00' : '23:59:59'));
        if (!$timestamp) {
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'message' => '时间格式不正确', 'error_messages' => array()));
            exit;
        }
        
        $priceObj = app::get('dealer')->model('goods_price');
        $success_count = 0;
        $error_count = 0;
        $error_messages = array();
        
        // 一次性获取所有记录，提升性能
        $existing_records = $priceObj->getList('*', array('id' => $selected_ids));
        $existing_data = array_column($existing_records, null, 'id');
        
        foreach ($selected_ids as $id) {
            try {
                // 从预获取的数据中获取记录
                $existing = isset($existing_data[$id]) ? $existing_data[$id] : null;
                if (!$existing) {
                    $error_count++;
                    $error_messages[] = "记录ID {$id} 不存在";
                    continue;
                }
                
                // 准备更新数据
                $update_data = array();
                if ($action_type == 'start_time') {
                    $update_data['start_time'] = $timestamp;
                    $old_time = $existing['start_time'] ? date('Y-m-d', $existing['start_time']) : '未设置';
                } else {
                    $update_data['end_time'] = $timestamp;
                    $old_time = $existing['end_time'] ? date('Y-m-d', $existing['end_time']) : '未设置';
                }
                
                // 时间验证 - 参考save方法的验证逻辑
                $start_time = $action_type == 'start_time' ? $timestamp : $existing['start_time'];
                $end_time = $action_type == 'end_time' ? $timestamp : $existing['end_time'];
                
                $validation_result = $priceObj->validateTimeData($start_time, $end_time, $existing['bs_id'], $existing['bm_id'], $id);
                if ($validation_result !== true) {
                    $error_count++;
                    $error_messages[] = "记录ID {$id} 时间验证失败：{$validation_result}";
                    continue;
                }
                
                // 执行更新
                $result = $priceObj->update($update_data, array('id' => $id));
                
                if ($result) {
                    // 记录操作日志
                    $operInfo = kernel::single('ome_func')->getDesktopUser();
                    $omeLogMdl = app::get('ome')->model('operation_log');
                    $field_name = $action_type == 'start_time' ? '生效时间' : '过期时间';
                    $memo = "批量更新{$field_name}：{$old_time}->{$new_time}";
                    
                    // 根据操作类型选择不同的日志key
                    $log_key = $action_type == 'start_time' ? 'dealer_goods_price_batch_update_start_time' : 'dealer_goods_price_batch_update_end_time';
                    $log_id = $omeLogMdl->write_log($log_key . '@dealer', $id, $memo);
                    
                    // 生成操作快照
                    if ($log_id) {
                        $shootMdl = app::get('ome')->model('operation_log_snapshoot');
                        $snapshoot = json_encode($existing, JSON_UNESCAPED_UNICODE);
                        $updated = json_encode($update_data, JSON_UNESCAPED_UNICODE);
                        $tmp = [
                            'log_id' => $log_id, 
                            'snapshoot' => $snapshoot,
                            'updated' => $updated
                        ];
                        $shootMdl->insert($tmp);
                    }
                    
                    $success_count++;
                } else {
                    $error_count++;
                    $error_messages[] = "记录ID {$id} 更新失败";
                }
            } catch (Exception $e) {
                $error_count++;
                $error_messages[] = "记录ID {$id} 更新异常：" . $e->getMessage();
            }
        }
        
        // 准备返回数据
        $field_name = $action_type == 'start_time' ? '生效时间' : '过期时间';
        $response_data = array(
            'success' => $success_count > 0 && $error_count == 0, // 只有全部成功才返回true
            'success_count' => $success_count,
            'error_count' => $error_count,
            'error_messages' => $error_messages
        );
        
        if ($success_count > 0 && $error_count == 0) {
            $response_data['message'] = "成功批量更新 {$success_count} 条记录的{$field_name}";
        } else if ($success_count > 0 && $error_count > 0) {
            $response_data['message'] = "成功更新 {$success_count} 条，失败 {$error_count} 条记录的{$field_name}";
        } else {
            $response_data['message'] = "批量更新失败";
        }
        
        // 返回JSON响应
        header('Content-Type: application/json');
        echo json_encode($response_data);
        exit;
    }

    /**
     * 对比价格数据差异
     */
    private function comparePriceData($old_data, $new_data)
    {
        $diff_data = array();
        
        // 需要对比的字段
        $compare_fields = array('bs_id', 'price', 'price_unit', 'start_time', 'end_time');
        
        foreach ($compare_fields as $field) {
            $old_value = isset($old_data[$field]) ? $old_data[$field] : '';
            
            // 如果$new_data中不存在该字段，视为没差异，跳过对比
            if (!array_key_exists($field, $new_data)) {
                continue;
            }
            
            $new_value = $new_data[$field];
            
            // 特殊处理时间字段
            if (in_array($field, array('start_time', 'end_time'))) {
                $old_value = $old_value ? date('Y-m-d', $old_value) : '';
                $new_value = $new_value ? date('Y-m-d', $new_value) : '';
            }
            
            // 特殊处理经销商字段
            if ($field == 'bs_id') {
                // 这里需要获取经销商名称进行对比
                try {
                    $dealerObj = app::get('dealer')->model('business');
                    $old_dealer = $dealerObj->dump(array('bs_id' => $old_value), 'bs_bn,name');
                    $new_dealer = $dealerObj->dump(array('bs_id' => $new_value), 'bs_bn,name');
                    
                    $old_value = $old_dealer ? $old_dealer['bs_bn'] . ' - ' . $old_dealer['name'] : '未知经销商';
                    $new_value = $new_dealer ? $new_dealer['bs_bn'] . ' - ' . $new_dealer['name'] : '未知经销商';
                } catch (Exception $e) {
                    $old_value = '经销商ID: ' . $old_value;
                    $new_value = '经销商ID: ' . $new_value;
                }
            }
            
            // 只有有变化时才添加到diff_data
            if ($old_value != $new_value) {
                $diff_data[$field] = array(
                    'old_value' => $old_value,
                    'new_value' => $new_value
                );
            }
        }
        
        return $diff_data;
    }

} 