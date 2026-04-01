<?php
class ome_api_log {
    
    private static $messages = array();
    private static $begin_time = 0;
    private static $log_id = '';
    private static $task_name = '';
    private static $original_bn = '';
    private static $worker = '';
    
    /**
     * 性能监控 - 记录开始
     * @param string $task_name 任务名称
     * @param string $original_bn 原始单号
     * @param string $worker 执行者
     */
    public static function record_start($task_name, $original_bn = '', $worker = '')
    {
        // 清空messages数组
        self::$messages = array();
        
        // 初始化开始时间
        self::$begin_time = microtime(true);
        
        // 设置类变量
        self::$task_name = $task_name;
        self::$original_bn = $original_bn;
        self::$worker = $worker;
    }

    /**
     * 性能监控 - 追加日志信息
     * @param string $type 类型（如：plugin、node、service等）
     * @param string $name 名称（如：插件名、节点名等）
     * @param float $spendtime 耗时（秒）
     * @param array $details 详细信息
     */
    public static function add_message($type, $name, $spendtime, $details = array())
    {
        // 构建标准数据结构
        $data = array(
            'type' => $type,
            'name' => $name,
            'spendtime' => round($spendtime, 5),
            'details' => $details
        );
        
        // 添加到messages数组
        self::$messages[] = $data;
        
        return true;
    }

    /**
     * 性能监控 - 记录结束
     */
    public static function record_end()
    {
        $apiLogModel = app::get('ome')->model('api_log');
        
        // 生成log_id
        self::$log_id = $apiLogModel->gen_id();
        
        // 计算总执行时间
        $end_time = microtime(true);
        $total_time = $end_time - self::$begin_time;
        $params['start_time'] = self::$begin_time;
        $params['end_time'] = $end_time;
        $params['total_time'] = round($total_time, 5);
        $params['messages'] = self::$messages;       
        
        // 构建日志数据
        $logsdf = array(
            'log_id' => self::$log_id,
            'task_name' => self::$task_name,
            'status' => 'success',
            'worker' => self::$worker,
            'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
            'msg' => '',
            'log_type' => '',
            'api_type' => 'request',
            'memo' => '性能监控数据',
            'original_bn' => self::$original_bn,
            'createtime' => time(),
            'last_modified' => time(),
            'spendtime' => $params['total_time'],
        );
        
        // 直接插入数据库
        $apiLogModel->insert($logsdf);
        
        return true;
    }

}