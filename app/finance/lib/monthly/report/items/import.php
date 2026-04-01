<?php

class finance_monthly_report_items_import implements omecsv_data_split_interface
{
    /**
     * 导入模板标题定义
     */
    const IMPORT_TITLE = [
        ['label' => '*:账单名称', 'col' => 'monthly_date'],
        ['label' => '*:订单号', 'col' => 'order_bn'],
        ['label' => '*:差异类型', 'col' => 'gap_type'],
    ];
    
    public $column_num = 3; // 表头列数
    public $current_key = null; // 切片标识
    private $oSchema = []; // 表头定义
    private $ioTitle = []; // 输入输出标题
    
    // 静态缓存变量，减少数据库查询
    private static $gapNames = []; // 有效的差异类型名称缓存
    private static $monthlyReportCache = []; // 账单信息缓存
    
    /**
     * 初始化缓存数据
     */
    private function initCache()
    {
        // 如果缓存为空，则初始化
        if (empty(self::$gapNames)) {
            $mdlGap = app::get('financebase')->model('gap');
            $gapList = $mdlGap->getList('gap_name', ['status' => '1']);
            self::$gapNames = array_column($gapList, 'gap_name');
        }
    }
    
    /**
     * 获取账单信息（带缓存）
     */
    private function getMonthlyReportInfo($monthly_date, $monthly_id = '')
    {
        // 如果缓存中没有，则查询数据库
        $cacheKey = $monthly_date . '_' . $monthly_id;
        if (!isset(self::$monthlyReportCache[$cacheKey])) {
            $mdlMonthlyReport = app::get('finance')->model('monthly_report');
            // 使用monthly_id和monthly_date一起查询，确保唯一性
            $monthlyInfo = $mdlMonthlyReport->db_dump([
                'monthly_id' => $monthly_id,
                'monthly_date' => $monthly_date
            ], 'monthly_id');
            self::$monthlyReportCache[$cacheKey] = $monthlyInfo;
        }
        
        return self::$monthlyReportCache[$cacheKey];
    }
    
    /**
     * 获取表头定义（接口要求的方法）
     */
    public function getTitle($filter = null, $ioType = 'csv')
    {
        return array_column(self::IMPORT_TITLE, 'label');
    }

    /**
     * 文件格式检查
     */
    public function checkFile($file_name, $file_type, $queue_data)
    {
        // 获取monthly_id参数
        $monthly_id = $queue_data['monthly_id'] ?? '';
        if (!$monthly_id) {
            return [false, '缺少monthly_id参数'];
        }
        
        $ioType = kernel::single('omecsv_io_split_' . $file_type);
        $rows = $ioType->getData($file_name, 0, -1);
        $title = $this->getTitle();
        
        // 检查表头
        $plateTitle = $rows[0];
        foreach ($title as $v) {
            if (array_search($v, $plateTitle) === false) {
                return [false, '文件模板错误：列【' . $v . '】未包含在' . implode('、', $plateTitle)];
            }
        }
        
        // 3. 获取数据行并预处理（跳过第一行标题）
        $dataRows = array_slice($rows, 1);
        if (empty($dataRows)) {
            return [false, '没有数据行'];
        }
        
        // 预处理：过滤空行
        $dataRows = $this->filterEmptyRows($dataRows);
        
        if (empty($dataRows)) {
            return [false, '没有有效的数据行'];
        }
        
        // 4. 验证数据行
        $oSchema = array_column(self::IMPORT_TITLE, 'label', 'col');
        $sdf = [];
        $errmsg = [];
        
        foreach ($dataRows as $rowIndex => $row) {
            $rowNum = $rowIndex + 2; // 行号从2开始（第1行是标题）
            
            // 将行数据与标题对应
            $titleKey = array();
            $_title = $plateTitle;
            foreach ($plateTitle as $k => $t) {
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
                return [false, "第{$rowNum}行：标题列数(" . count($plateTitle) . ")与数据列数(" . count($row) . ")不匹配"];
            }
            
            // 验证必填字段
            foreach ($title as $v) {
                $field = array_search($v, $oSchema);
                if ($field && empty(trim($rowData[$field]))) {
                    return [false, "第{$rowNum}行：{$v}不能为空"];
                }
            }
            
            // 格式化数据
            $this->_formatData($rowData);
            $sdf[] = $rowData;
        }
        
        if ($errmsg) {
            return [false, '文件内容错误信息：' . implode('；<br/>', $errmsg)];
        }
        
        // 业务验证
        list($dataList, $errMsg) = $this->validateData($sdf, true, $monthly_id);
        if ($errMsg) {
            return [false, '文件内容错误信息：' . implode('；<br/>', $errMsg)];
        }
        
        return [true, '文件模板匹配', $rows[0]];
    }

    /**
     * 数据处理主方法
     */
    public function process($cursor_id, $params, &$errmsg)
    {
        set_time_limit(0);
        @ini_set('memory_limit', '128M');
        
        $oFunc = kernel::single('omecsv_func');
        $queueMdl = app::get('omecsv')->model('queue');
        
        // 获取monthly_id参数
        $monthly_id = $params['queue_data']['monthly_id'] ?? '';
        if (!$monthly_id) {
            $errmsg[] = '缺少monthly_id参数';
            return [false];
        }
        
        $oFunc->writelog('处理任务-开始', 'import', $params);
        
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
        $oFunc->writelog('预处理结果', 'import', [
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
                    // 格式化数据
                    $this->_formatData($rowData);
                    $sdf[] = $rowData;
                    $splitCount++;
                } else {
                    array_push($errmsg, "第{$offset}行：数据格式错误");
                }
                $offset++;
            }
        }
        unset($data);
        
        // 数据验证
        if ($sdf) {
            list($validData, $validateErrMsg) = $this->validateData($sdf, true, $monthly_id);
            if ($validateErrMsg) {
                $errmsg = array_merge($errmsg, $validateErrMsg);
            }
            
            // 保存数据
            if ($validData) {
                list($result, $msgList) = $this->saveData($validData);
                if ($msgList) {
                    $errmsg = array_merge($errmsg, $msgList);
                }
                $queueMdl->update(['split_count' => count($validData)], ['queue_id' => $cursor_id]);
            }
        }
        
        $oFunc->writelog('处理任务-完成', 'import', 'Done');
        return [true];
    }
    
    
    
    /**
     * 数据格式转换
     */
    public function getSdf($row, $offset, $title)
    {
        $row = array_map('trim', $row);
        $oSchema = array_column(self::IMPORT_TITLE, 'label', 'col');
        
        $titleKey = [];
        foreach ($title as $k => $t) {
            $titleKey[$k] = array_search($t, $oSchema);
            if ($titleKey[$k] === false) {
                return ['status' => false, 'msg' => '未定义字段`' . $t . '`'];
            }
        }
        
        $res = ['status' => true, 'data' => [], 'msg' => ''];
        
        if ($this->column_num <= count($row) && $row[0] != '*:表头标识') {
            $tmp = array_combine($titleKey, $row);
            
            // 必填字段验证
            foreach ($tmp as $k => $v) {
                if (strpos($k, '*:') === 0 && !$v) {
                    $res['status'] = false;
                    $res['msg'] = sprintf("LINE %d : %s 不能为空！", $offset, $oSchema[$k]);
                    return $res;
                }
            }
            
            $res['data'] = $tmp;
        }
        
        return $res;
    }
    
    /**
     * 数据格式化
     */
    public function _formatData(&$data)
    {
        foreach ($data as $k => $str) {
            $data[$k] = str_replace(["\r\n", "\r", "\n", "\t"], "", $str);
        }
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
     * 数据验证（合并了业务逻辑验证）
     */
    public function validateData($data, $is_check = true, $monthly_id = '')
    {
        // 初始化缓存
        $this->initCache();
        
        $errMsg = [];
        $validData = [];
        $mdlMonthlyReportItems = app::get('finance')->model('monthly_report_items');
        
        foreach ($data as $key => $item) {
            // 先验证差异类型是否存在且有效（使用缓存，最快验证）
            if (!in_array($item['gap_type'], self::$gapNames)) {
                $errMsg[] = sprintf('数据验证失败，行号：%d - 差异类型【%s】不存在或已失效', $key + 1, $item['gap_type']);
                continue;
            }
            
            // 验证账单名称是否存在（使用monthly_id和monthly_date一起查询，确保唯一性）
            $monthlyInfo = $this->getMonthlyReportInfo($item['monthly_date'], $monthly_id);
            if (!$monthlyInfo) {
                $errMsg[] = sprintf('数据验证失败，行号：%d - 账单名称【%s】与当前账单不匹配', $key + 1, $item['monthly_date']);
                continue;
            }
            
            // 验证订单号是否存在
            $itemInfo = $mdlMonthlyReportItems->db_dump([
                'monthly_id' => $monthly_id,
                'order_bn' => $item['order_bn']
            ], 'id');
            
            if (!$itemInfo) {
                $errMsg[] = sprintf('数据验证失败，行号：%d - 订单号【%s】在指定账单中不存在', $key + 1, $item['order_bn']);
                continue;
            }
            
            // 保存验证通过的数据用于后续处理
            $item['monthly_id'] = $monthly_id;
            $item['item_id'] = $itemInfo['id'];
            $validData[] = $item;
        }
        
        return [$validData, $errMsg];
    }
    
    
    /**
     * 保存数据
     */
    public function saveData($contents)
    {
        $errMsg = [];
        
        foreach ($contents as $data) {
            // 开启事务
            kernel::database()->beginTransaction();
            
            try {
                // 更新三个表的差异类型
                $mdlBill = app::get('finance')->model('bill');
                $mdlAr = app::get('finance')->model('ar');
                $mdlItem = app::get('finance')->model('monthly_report_items');
                
                // 更新 bill 表
                $mdlBill->update(
                    ['gap_type' => $data['gap_type']], 
                    ['order_bn' => $data['order_bn'], 'monthly_id' => $data['monthly_id']]
                );
                
                // 更新 ar 表
                $mdlAr->update(
                    ['gap_type' => $data['gap_type']], 
                    ['order_bn' => $data['order_bn'], 'monthly_id' => $data['monthly_id']]
                );
                
                // 更新 monthly_report_items 表
                $mdlItem->update(
                    ['gap_type' => $data['gap_type']], 
                    ['id' => $data['item_id']]
                );
                
                // 提交事务
                kernel::database()->commit();
                
            } catch (Exception $e) {
                // 回滚事务
                kernel::database()->rollBack();
                $errMsg[] = $e->getMessage();
            }
        }
        
        return [true, $errMsg];
    }
    
    /**
     * 切片检测
     */
    public function is_split($row)
    {
        $is_split = false;
        if ($row['0'] !== $this->current_key) {
            if ($this->current_key !== null) {
                $is_split = true;
            }
            $this->current_key = $row['0'];
        }
        return $is_split;
    }
    
    /**
     * 配置信息（可选方法）
     * 如果不实现此方法，系统将默认走队列处理
     * 实现此方法并设置 max_direct_count > 0 时，数据量小于等于此值将直接处理
     */
    public function getConfig($key = null)
    {
        $config = [
            'page_size' => 100, // 每页处理数量
            'max_direct_count' => 50, // 50条以内直接处理，超过则走队列
        ];
        return $key ? $config[$key] : $config;
    }
}
