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
 *
 * 多进程任务执行命令
 *
 * @author chenping@shopex.cn
 * @version Fri May  6 23:15:31 2022
 */
use Swoole\Event;
use Swoole\Process;
use Swoole\Process\Manager;
use Swoole\Process\Pool;
// use Swoole\Timer;

class taskmgr_swcommand_task extends taskmgr_swcommand_base
{

    private $manage;
    private $wait = false;

    protected $cmd;

    protected static $signature = [
        'worker:start'   => 'start',
        'worker:stop'    => 'stop',
        'worker:restart' => 'restart',
        'worker:list'    => 'listTasks',
        // 'worker:reload'  => 'reload',
        // 'worker:status'  => 'status',

    ];

    protected static $description = [
        'worker:start'   => '启动 可选 -d 后台；可选 --type=export,finance；可选 --task=exportsplit,import（默认全部）',
        'worker:stop'    => '停止',
        'worker:restart' => '重启 参数同 worker:start',
        'worker:list'    => '列出可用任务类型与任务名',
        // 'worker:reload'  => '平滑重启',
        // 'worker:status'  => '查看进程运行状态',
    ];

    /**
     * undocumented function
     *
     * @return void
     * @author 
     **/
    public function __construct()
    {
        $this->manage = new taskmgr_swprocess_manage;
    }

    /**
     * 解析 CLI 参数：-d / --type / --task（任意位置）
     *
     * @return array{daemon:bool,type:?string,task:?string}
     */
    protected function parseWorkerArgs()
    {
        global $argv;

        $daemon = false;
        $type = null;
        $task = null;
        $args = array_slice($argv, 2);
        $count = count($args);

        for ($i = 0; $i < $count; $i++) {
            $arg = $args[$i];

            if ($arg === '-d') {
                $daemon = true;
                continue;
            }

            if (strpos($arg, '--type=') === 0) {
                $type = substr($arg, 7);
                continue;
            }
            if ($arg === '--type') {
                $type = isset($args[$i + 1]) ? $args[++$i] : '';
                continue;
            }

            if (strpos($arg, '--task=') === 0) {
                $task = substr($arg, 7);
                continue;
            }
            if ($arg === '--task') {
                $task = isset($args[$i + 1]) ? $args[++$i] : '';
                continue;
            }
        }

        return array(
            'daemon' => $daemon,
            'type'   => $type,
            'task'   => $task,
        );
    }

    /**
     * 启动
     */
    public function start()
    {
        $opts = $this->parseWorkerArgs();

        if ($this->manage->getPid()) {
            taskmgr_swconsole_output::warning('已启动不可重复启动' . PHP_EOL);

            exit();
        }

        try {
            $selection = taskmgr_whitelist::resolve_worker_selection($opts['type'], $opts['task']);
        } catch (InvalidArgumentException $e) {
            taskmgr_swconsole_output::error($e->getMessage() . PHP_EOL);
            exit(1);
        }

        if ($opts['daemon']) {
            taskmgr_swconsole_output::warning('以守护进程方式启动,请去日志中查询详细信息,日志目录地址:./logs/' . PHP_EOL);
            Process::daemon();

            taskmgr_swprocess_conf::setDaemon(true);
        }

        $taskSummary = implode(',', isset($selection['tasks']) ? $selection['tasks'] : array());
        taskmgr_log::info('正在启动中,请稍后.... 选中任务: ' . ($taskSummary ?: '全部'), [], 'system');
        
        $this->manage->run($selection);

        // $this->wait();
    }

    /**
     * 列出可用任务
     */
    public function listTasks()
    {
        $catalog = taskmgr_whitelist::get_tasks_catalog();
        $header = sprintf("%-10s\t%-40s\t%s", 'type', 'task', 'label') . PHP_EOL;
        taskmgr_swconsole_output::normal($header);
        taskmgr_swconsole_output::normal(str_repeat('-', 80) . PHP_EOL);

        foreach ($catalog as $row) {
            $line = sprintf(
                "%-10s\t%-40s\t%s",
                $row['type'],
                $row['name'],
                $row['label']
            ) . PHP_EOL;
            taskmgr_swconsole_output::info($line);
        }

        taskmgr_swconsole_output::normal(PHP_EOL . '类型: ' . implode(',', taskmgr_whitelist::get_task_types()) . '（别名 import=finance）' . PHP_EOL);
        taskmgr_swconsole_output::normal('示例: php app/taskmgr/sw.php worker:start -d --type=export' . PHP_EOL);
        taskmgr_swconsole_output::normal('示例: php app/taskmgr/sw.php worker:start -d --task=assign,import' . PHP_EOL);
    }

    /**
     * 重启
     * @throws \Exception
     */
    public function restart()
    {
        $this->stop();
        $this->start();
    }

    /**
     * 停止
     * @param false $is_start
     * @throws \Exception
     */
    public function stop()
    {
        $pid = $this->manage->getPid();

        if ($pid) {
            taskmgr_swconsole_output::normal('正在停止....' . PHP_EOL);
            while (true) {
                Process::kill($pid, SIGTERM);

                usleep(1000000);
                if (!$this->manage->getPid()) {
                    taskmgr_swconsole_output::normal("已停止...\n");
                    return true;
                }


            }
        }

        taskmgr_swconsole_output::normal('任务未启动无需停止' . PHP_EOL);
        return;
    }
}
