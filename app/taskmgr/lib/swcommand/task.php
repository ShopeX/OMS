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
        // 'worker:reload'  => 'reload',
        // 'worker:status'  => 'status',

    ];

    protected static $description = [
        'worker:start'   => '启动 携带参数-d 后台启动',
        'worker:stop'    => '停止',
        'worker:restart' => '重启 携带参数-d 后台启动',
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
     * 启动
     */
    public function start()
    {
        global $argv;

        if ($this->manage->getPid()) {
            taskmgr_swconsole_output::warning('已启动不可重复启动' . PHP_EOL);

            exit();
        }

        if (isset($argv[2]) && $argv[2] == '-d') {
            taskmgr_swconsole_output::warning('以守护进程方式启动,请去日志中查询详细信息,日志目录地址:./logs/' . PHP_EOL);
            Process::daemon();

            taskmgr_swprocess_conf::setDaemon(true);
        }
        taskmgr_log::info('正在启动中,请稍后....', [], 'system');
        
        $this->manage->run();

        // $this->wait();
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
