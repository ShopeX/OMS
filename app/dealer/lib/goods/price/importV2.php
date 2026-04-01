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
 * 经销商品价格导入导出类
 * 参考订单导入导出的实现方式
 */
class dealer_goods_price_importV2 implements omecsv_data_split_interface
{

    /**
     * 导入模板标题定义
     */
    const IMPORT_TITLE = [
        ['label' => '*:经销商编码', 'col' => 'bs_bn'],
        ['label' => '*:经销商名称', 'col' => 'bs_name'],
        ['label' => '*:基础物料编码', 'col' => 'material_bn'],
        ['label' => '*:基础物料名称', 'col' => 'material_name'],
        ['label' => '*:采购价', 'col' => 'price'],
        ['label' => '*:价格单位', 'col' => 'price_unit'],
        ['label' => '*:生效时间', 'col' => 'start_time', 'format' => 'yyyy-mm-dd hh:mm:ss'],
        ['label' => '*:过期时间', 'col' => 'end_time', 'format' => 'yyyy-mm-dd hh:mm:ss'],
    ];

    /**
     * 检查文件是否有效
     * @param $file_name 文件名
     * @param $file_type 文件类型
     * @param $queue_data 请求参数
     * @return array
     */

    public function checkFile($file_name, $file_type, $queue_data)
    {
        // 检查导入权限
        $desktop_user = kernel::single('desktop_user');
        if (!$desktop_user->has_permission('dealer_goods_price_import')) {
            return array(false, '您没有导入经销商物料价格的权限');
        }
        
        // 1. 读取文件数据
        $ioType = kernel::single('omecsv_io_split_' . $file_type);
        $rows = $ioType->getData($file_name, 0, -1);
        
        if (empty($rows) || count($rows) < 2) {
            return array(false, '文件数据为空或格式不正确');
        }

        // 2. 检查表头结构
        $header = $rows[0];
        $requiredFields = ['*:经销商编码', '*:基础物料编码', '*:采购价', '*:生效时间', '*:过期时间'];
        
        foreach ($requiredFields as $requiredField) {
            if (!in_array($requiredField, $header)) {
                return array(false, '缺少必填字段：' . $requiredField);
            }
        }

        // 3. 获取数据行并预处理
        $dataRows = array_slice($rows, 1);
        if (empty($dataRows)) {
            return array(false, '没有数据行');
        }
        
        // 预处理：过滤空行
        $dataRows = $this->filterEmptyRows($dataRows);
        
        if (empty($dataRows)) {
            return array(false, '没有有效的数据行');
        }

        // 4. 验证数据行 - 参考订单导入的数据对齐逻辑
        $oSchema = array_column(self::IMPORT_TITLE, 'label', 'col');
        
        foreach ($dataRows as $rowIndex => $row) {
            $rowNum = $rowIndex + 2; // 行号从2开始（第1行是标题）
            
            // 将行数据与标题对应 - 参考订单导入的逻辑
            $titleKey = array();
            $_title = $header;
            foreach ($header as $k => $t) {
                $titleKey[$k] = array_search($t, $oSchema);
                if ($titleKey[$k] === false) {
                    unset($titleKey[$k]);
                    unset($row[$k]);
                    unset($_title[$k]);
                }
            }
            $_title = array_values($_title);
            $row = array_values($row);
            
            // 如果当前行的数据长于标题，截取标题长度的数据
            if (count($row) > count($titleKey)) {
                $row = array_splice($row, 0, count($titleKey));
            }
            
            $rowData = array_combine($titleKey, $row);
            if (!$rowData) {
                return array(false, "第{$rowNum}行：标题列数(" . count($header) . ")与数据列数(" . count($row) . ")不匹配");
            }
            
            // 验证必填字段
            foreach ($requiredFields as $requiredField) {
                $field = array_search($requiredField, $oSchema);
                if ($field && empty(trim($rowData[$field]))) {
                    return array(false, "第{$rowNum}行：{$requiredField}不能为空");
                }
            }
            
            // 获取价格模型用于验证
            $priceMdl = app::get('dealer')->model('goods_price');
            
            // 验证价格格式（复用model中的验证方法）
            $priceField = array_search('*:采购价', $oSchema);
            if ($priceField && isset($rowData[$priceField])) {
                $price = trim($rowData[$priceField]);
                list($price_valid, $validated_price, $price_error) = $priceMdl->validatePrice($price);
                if (!$price_valid) {
                    return array(false, "第{$rowNum}行：{$price_error}");
                }
            }
            
            // 验证时间格式和逻辑
            $startTimeField = array_search('*:生效时间', $oSchema);
            $endTimeField = array_search('*:过期时间', $oSchema);
            
            $startTime = null;
            $endTime = null;
            
            if ($startTimeField && isset($rowData[$startTimeField]) && !empty(trim($rowData[$startTimeField]))) {
                $startTimeStr = trim($rowData[$startTimeField]);
                $startTimeStr = $this->convertExcelDate($startTimeStr);
                if ($startTimeStr === false) {
                    return array(false, "第{$rowNum}行：生效时间格式不正确");
                }
                $startTime = strtotime($startTimeStr);
            }
            
            if ($endTimeField && isset($rowData[$endTimeField]) && !empty(trim($rowData[$endTimeField]))) {
                $endTimeStr = trim($rowData[$endTimeField]);
                $endTimeStr = $this->convertExcelDate($endTimeStr);
                if ($endTimeStr === false) {
                    return array(false, "第{$rowNum}行：过期时间格式不正确");
                }
                $endTime = strtotime($endTimeStr);
            }
            
            // 验证经销商编码有效性
            $dealerField = array_search('*:经销商编码', $oSchema);
            $dealer = null;
            if ($dealerField && isset($rowData[$dealerField])) {
                $dealerCode = trim($rowData[$dealerField]);
                if (!empty($dealerCode)) {
                    $dealerMdl = app::get('dealer')->model('business');
                    $dealer = $dealerMdl->dump(['bs_bn' => $dealerCode], 'bs_id');
                    if (!$dealer) {
                        return array(false, "第{$rowNum}行：经销商编码 [{$dealerCode}] 不存在");
                    }
                }
            }
            
            // 验证基础物料编码有效性
            $materialField = array_search('*:基础物料编码', $oSchema);
            $material = null;
            if ($materialField && isset($rowData[$materialField])) {
                $materialCode = trim($rowData[$materialField]);
                if (!empty($materialCode)) {
                    $materialMdl = app::get('material')->model('basic_material');
                    $material = $materialMdl->dump(['material_bn' => $materialCode], 'bm_id');
                    if (!$material) {
                        return array(false, "第{$rowNum}行：基础物料编码 [{$materialCode}] 不存在");
                    }
                }
            }
            
            // 验证时间段重叠（复用已获取的经销商和物料信息）
            // 经过上面的验证，如果$dealer和$material存在，说明验证已通过
            if ($startTime && $endTime && $dealer && $material) {
                $validation_result = $priceMdl->validateTimeData($startTime, $endTime, $dealer['bs_id'], $material['bm_id']);
                if ($validation_result !== true) {
                    return array(false, "第{$rowNum}行：{$validation_result}");
                }
            }
        }

        return array(true, '文件检查通过');
    }

    /**
     * 转换Excel日期格式
     * @param $dateValue 日期值（可能是Excel序列号或标准日期格式）
     * @return string|false 转换后的日期字符串或false
     */
    private function convertExcelDate($dateValue)
    {
        // 如果已经是标准日期格式，直接返回
        if (is_string($dateValue) && strtotime($dateValue) !== false) {
            return $dateValue;
        }
        
        // 检查是否是Excel序列号（纯数字且大于1000）
        if (is_numeric($dateValue) && $dateValue > 1000) {
            // Excel的日期序列号是从1900年1月1日开始的天数
            // 但Excel有个bug：1900年被当作闰年，所以1900年3月1日之前的日期需要减去1天
            $excelEpoch = 25569; // 1970年1月1日的Excel序列号
            
            // 处理浮点数精度问题，确保计算准确
            // 使用字符串处理来避免浮点数精度问题
            $dateValueStr = (string)$dateValue;
            $parts = explode('.', $dateValueStr);
            
            $days = intval($parts[0]);
            $fraction = isset($parts[1]) ? '0.' . $parts[1] : '0';
            
            // 计算从1970年开始的天数
            $unixDays = $days - $excelEpoch;
            
            // 计算小数部分对应的秒数，使用字符串计算避免精度问题
            $fractionSeconds = round(floatval($fraction) * 86400);
            
            // 计算最终的Unix时间戳
            $unixTimestamp = ($unixDays * 86400) + $fractionSeconds;
            
            // 验证转换后的时间戳是否合理（1900-2100年之间）
            if ($unixTimestamp > -2208988800 && $unixTimestamp < 4102444800) {
                // 使用UTC时间避免时区问题
                return gmdate('Y-m-d H:i:s', $unixTimestamp);
            }
        }
        
        return false;
    }

    /**
     * 过滤空行
     * @param $rows 数据行数组
     * @return array 过滤后的数据行数组
     */
    private function filterEmptyRows($rows)
    {
        $filteredRows = [];
        
        foreach ($rows as $row) {
            // 检查行是否为空（所有列都是空值）
            $isEmpty = true;
            if (is_array($row)) {
                foreach ($row as $cell) {
                    if (!empty(trim($cell))) {
                        $isEmpty = false;
                        break;
                    }
                }
            }
            
            // 如果遇到第一个空行，就停止处理
            if ($isEmpty) {
                break;
            }
            
            $filteredRows[] = $row;
        }
        
        return $filteredRows;
    }

    /**
     * 获取导入模板标题
     * @return array
     */
    public function getImportTitle()
    {
        return self::IMPORT_TITLE;
    }

    /**
     * 获取导出模板标题
     * @return array
     */
    public function getExportTitle()
    {
        return self::IMPORT_TITLE;
    }

    /**
     * 获取配置信息
     * @param $key 配置键
     * @return array|mixed
     */
    public function getConfig($key = '')
    {
        $config = array(
            'page_size' => 200,
            'max_direct_count' => 200,
        );
        return $key ? $config[$key] : $config;
    }

    /**
     * 实现接口方法：处理数据
     * @param $cursor_id 游标ID
     * @param $params 参数
     * @param $errmsg 错误信息
     * @return array
     */
    public function process($cursor_id, $params, &$errmsg)
    {
        @ini_set('memory_limit', '128M');
        $oFunc = kernel::single('omecsv_func');
        $queueMdl = app::get('omecsv')->model('queue');
        
        $oFunc->writelog('处理任务-开始', 'dealer_goods_price_import', $params);
        
        // 业务逻辑处理
        $data = $params['data'];
        // 使用params中的title，这是实际的CSV标题行
        $title = $params['title'];
        $sdf = [];
        $offset = intval($data['offset']) + 1; // 文件行数 行数默认从1开始
        $splitCount = 0; // 执行行数
        
        // 去掉第一行标题，只保留数据行
        $data = array_slice($data, 1);
        
        // 预处理：过滤空行
        $data = $this->filterEmptyRows($data);
        
        // 记录预处理结果
        $oFunc->writelog('预处理结果', 'dealer_goods_price_import', [
            '原始数据行数' => count($params['data']),
            '过滤后数据行数' => count($data)
        ]);
        
        // 使用过滤后的数据 - 参考订单导入的数据对齐逻辑
        if ($data) {
            $oSchema = array_column(self::IMPORT_TITLE, 'label', 'col');
            
            foreach ($data as $row) {
                // 将行数据与标题对应 - 参考订单导入的逻辑
                $titleKey = array();
                $_title = $title;
                foreach ($title as $k => $t) {
                    $titleKey[$k] = array_search($t, $oSchema);
                    if ($titleKey[$k] === false) {
                        unset($titleKey[$k]);
                        unset($row[$k]);
                        unset($_title[$k]);
                    }
                }
                $_title = array_values($_title);
                $row = array_values($row);
                
                // 如果当前行的数据长于标题，截取标题长度的数据
                if (count($row) > count($titleKey)) {
                    $row = array_splice($row, 0, count($titleKey));
                }
                
                $rowData = array_combine($titleKey, $row);
                if ($rowData) {
                    $res = $this->processRow($rowData, $offset, $title);
                    
                    if ($res['status'] && $res['data']) {
                        $sdf[] = $res['data'];
                        $splitCount++;
                    } elseif (!$res['status']) {
                        array_push($errmsg, $res['msg']);
                    }
                } else {
                    array_push($errmsg, "第{$offset}行：数据格式错误");
                }
                $offset++;
            }
        }
        unset($data);
        
        // 创建经销商物料价格记录
        if ($sdf) {
            list($result, $msgList) = $this->saveGoodsPrices($sdf);
            if ($msgList) {
                $errmsg = array_merge($errmsg, $msgList);
            }
            $queueMdl->update(['original_bn' => 'dealer_goods_price', 'split_count' => $splitCount], ['queue_id' => $cursor_id]);
        }
        
        // 任务数据统计更新等
        $oFunc->writelog('处理任务-完成', 'dealer_goods_price_import', 'Done');
        return [true];
    }

    /**
     * 处理单行数据
     * @param $row 数据行
     * @param $offset 行号
     * @param $title 标题行
     * @return array
     */
    private function processRow($row, $offset, $title)
    {
        try {
            // 数据已在checkFile中验证过，这里直接处理
            // $row已经是array_combine后的关联数组，键是字段名，值是数据
            $processedData = [
                'bs_bn' => trim($row['bs_bn']),
                'bs_name' => isset($row['bs_name']) ? trim($row['bs_name']) : '',
                'material_bn' => trim($row['material_bn']),
                'material_name' => isset($row['material_name']) ? trim($row['material_name']) : '',
                'price' => floatval($row['price']),
                'price_unit' => isset($row['price_unit']) ? trim($row['price_unit']) : '',
                'start_time' => !empty($row['start_time']) ? strtotime($this->convertExcelDate($row['start_time'])) : null,
                'end_time' => !empty($row['end_time']) ? strtotime($this->convertExcelDate($row['end_time'])) : null,
            ];

            return ['status' => true, 'data' => $processedData];

        } catch (Exception $e) {
            return ['status' => false, 'msg' => "第{$offset}行：处理异常 - " . $e->getMessage()];
        }
    }

    /**
     * 保存经销商物料价格数据
     * @param $sdf 处理后的数据
     * @return array
     */
    private function saveGoodsPrices($sdf)
    {
        $priceMdl = app::get('dealer')->model('goods_price');
        $omeLogMdl = app::get('ome')->model('operation_log');
        
        $successCount = 0;
        $errorCount = 0;
        $msgList = [];
        
        // 批量获取经销商和物料信息，避免循环查询
        $bs_bns = array_unique(array_column($sdf, 'bs_bn'));
        $material_bns = array_unique(array_column($sdf, 'material_bn'));
        
        $dealerMdl = app::get('dealer')->model('business');
        $materialMdl = app::get('material')->model('basic_material');
        
        $dealers = $dealerMdl->getList('bs_id,bs_bn,name', ['bs_bn' => $bs_bns]);
        $materials = $materialMdl->getList('bm_id,material_bn', ['material_bn' => $material_bns]);
        
        // 转换为关联数组，提高查找效率
        $dealerMap = array_column($dealers, null, 'bs_bn');
        $materialMap = array_column($materials, 'bm_id', 'material_bn');
        
        foreach ($sdf as $data) {
            try {
                // 获取经销商和物料ID（已在checkFile中验证过，这里直接获取）
                $dealer = $dealerMap[$data['bs_bn']] ?? null;
                $bm_id = $materialMap[$data['material_bn']] ?? null;
                
                if (!$dealer || !$bm_id) {
                    $msgList[] = "数据异常：经销商 {$data['bs_bn']} 或物料 {$data['material_bn']} 信息缺失";
                    $errorCount++;
                    continue;
                }
                
                $bs_id = $dealer['bs_id'];
                
                // 时间段重叠验证已在checkFile中完成，这里直接处理
                
                // 创建新记录（与页面新增逻辑一致）
                $insertData = [
                    'bs_id' => $bs_id,
                    'bm_id' => $bm_id,
                    'price' => $data['price'],
                    'price_unit' => $data['price_unit'],
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'create_time' => time(),
                    'last_modify' => time(),
                ];

                $result = $priceMdl->insert($insertData);
                if ($result) {
                    // 记录新增日志（与页面新增逻辑一致）
                    $omeLogMdl->write_log('dealer_goods_price_add@dealer', $result, '');
                    $successCount++;
                } else {
                    $msgList[] = "保存失败：经销商 {$data['bs_bn']} 和物料 {$data['material_bn']} 的新增失败";
                    $errorCount++;
                }

            } catch (Exception $e) {
                $msgList[] = "处理异常：" . $e->getMessage();
                $errorCount++;
            }
        }

        return [true, $msgList];
    }

    /**
     * 实现接口方法：获取标题
     * @param $filter 过滤器
     * @param $ioType IO类型
     * @return array
     */
    public function getTitle($filter = null, $ioType = 'csv')
    {
        $title = [];
        foreach (self::IMPORT_TITLE as $item) {
            $title[] = $item['label'];
        }
        return $title;
    }
}
