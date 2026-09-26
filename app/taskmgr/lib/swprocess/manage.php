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

use Swoole\Process\Manager;
use Swoole\Process\Pool;
use Swoole\Process;
use Swoole\Coroutine;

class taskmgr_swprocess_manage
{
    private static $pid_file = '/var/run/swoole-taskmgr.pid';

    /**
     * 获取已运行队列manage进程pid
     */
    public function getPid()
    {
        if (file_exists(self::$pid_file)) {
            $pid = file_get_contents(self::$pid_file);
            if ($pid && Process::kill($pid, 0)) {
                return $pid;
            }
        }
        return false;
    }

    /**
     * 运行
     * @throws \Exception
     */
    public function run()
    {
        //写入pid
        if ($this->getPid()) {
            taskmgr_log::error('已启动不可重复启动', [], 'system');
            exit();
        }

        file_put_contents(self::$pid_file, getmypid());

        $pm = new Manager();

        // 消费任务
        foreach (taskmgr_whitelist::get_all_task_list() as $taskName => $taskConf) {
            $workerNum = $taskConf['threadNum'] ?: 1;

            $pm->addBatch($workerNum, function (Pool $pool, int $workerId)use($taskName, $taskConf) {
                $process = $pool->getProcess($workerId);
                $process->name('taskmgr-'.$taskName);

                list($result, $msg) = (new taskmgr_swtask_worker())->run($taskName, $taskConf);

                if (!$result) {
                    $errorMsg = sprintf('%s(pid:%s,wid:%s)', $msg?:$taskName.'-任务未返回结果',getmypid(), $workerId);
                    taskmgr_log::error($errorMsg, [], 'system');
                    self::notifyConnecterFail($msg ?: $errorMsg);
                    // 多 worker 同时失败时只由第一个触发 shutdown，避免 kill/waitpid No such process
                    self::shutdownPoolOnce($pool);
                }
            },true);
        }

        // 订阅任务
        foreach (taskmgr_whitelist::init_list() as $taskName => $taskConf) {
            // 检查规则
            if (!taskmgr_swtask_parsecrontab::check($taskConf['rule'])) {
                taskmgr_log::error(sprintf('[%s]CRONTAB规则定义错误', $taskName), [], 'system');

                exit();
            }

            $pm->addBatch(1, function (Pool $pool, int $workerId)use($taskName, $taskConf) {
                $process = $pool->getProcess($workerId);
                $process->name('taskmgr-'.$taskName);


                // 每秒执行一次
                $timerId = Swoole\Timer::tick(1000, function(int $timer_id, $pool, $workerId, $taskName, $taskConf){
                    
                    list($result, $msg) = (new taskmgr_swtask_timer())->run($taskName, $taskConf);

                    if (!$result) {
                        $errorMsg = sprintf('%s(pid:%s,wid:%s)', $msg?:$taskName.'-任务未返回结果',getmypid(), $workerId);
                        taskmgr_log::error($errorMsg, [], 'system');
                        self::notifyConnecterFail($msg ?: $errorMsg);
                        self::shutdownPoolOnce($pool);
                    }
                    
                }, $pool, $workerId, $taskName, $taskConf);

                $process->signal(SIGTERM, function ($sig) use ($timerId) {
                    if ($timerId !== null) {
                        Swoole\Timer::clear($timerId);
                    }
                    // 协程内不能 Process::exit（会 deadlock），退出事件循环即可
                    Swoole\Event::exit();
                });
                
            },true);
        }

        // 日志清理
       $this->cleanupLogs($pm);

        $pm->start();

        // pool 全部退出后清理 pid；Swoole 守护进程仅 exit/Event::exit 可能仍空挂，需强制结束
        self::clearPidFile();
        taskmgr_log::info('taskmgr 主进程已退出', [], 'system');
        @Swoole\Event::exit();
        // 确保进程退出，避免留下 php sw.php worker:start -d 空壳
        Process::kill(getmypid(), SIGKILL);
    }

    /**
     * Redis/队列「服务未启用」「服务中断」时推送企业微信告警
     *
     * @param string $msg
     * @return void
     */
    protected static function notifyConnecterFail($msg)
    {
        if (!$msg) {
            return;
        }
        // worker: xxx-redis服务未启用 / xxx-redis服务中断: ...
        // timer:  xxx-redis服务未启用 / xxx-redis服务中断
        if (false === strpos($msg, '服务未启用') && false === strpos($msg, '服务中断')) {
            return;
        }
        try {
            taskmgr_notify_workwx::alertConnecterFail($msg);
        } catch (\Throwable $e) {
            taskmgr_log::error('企业微信告警调用异常: ' . $e->getMessage(), [], 'system');
        }
    }

    /**
     * 全进程只 shutdown 一次：对 pid file 非阻塞 flock，抢到的只停 pool
     * 不杀主进程、不删 pid（删 pid 会导致可重复 start，叠出多个主进程）
     *
     * @param Pool $pool
     * @return void
     */
    protected static function shutdownPoolOnce(Pool $pool)
    {
        $fp = @fopen(self::$pid_file, 'c+');
        if (!$fp) {
            try {
                $pool->shutdown();
            } catch (\Throwable $e) {
            }
            return;
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return;
        }

        try {
            $pool->shutdown();
        } catch (\Throwable $e) {
            // Swoole 底层 kill/waitpid 失败时忽略
        }

        flock($fp, LOCK_UN);
        fclose($fp);
    }

    protected static function clearPidFile()
    {
        if (file_exists(self::$pid_file)) {
            @unlink(self::$pid_file);
        }
    }

    /**
     * 使用swoole manager协程清理日志，每10秒执行一次
     */
    public function cleanupLogs($pm)
    {
        $pm->addBatch(1, function (Pool $pool, int $workerId) {

            $process = $pool->getProcess($workerId);
            $process->name('taskmgr-cleanupLogs');

            // 使用 Swoole\Timer::tick 替代 process->tick
            $timerId = Swoole\Timer::tick(10000, function (int $timer_id)  {
                try {
                    $threeDaysAgo = date('Ymd', strtotime('-3 days'));
                    $logDir = taskmgr_log::getDirPath($threeDaysAgo);
                    taskmgr_log::info('日志目录: ' . $logDir, [], 'system');
                    if ($logDir && is_dir($logDir)) {
                        // 在这里添加实际的日志清理逻辑
                        $files = glob($logDir . '/*.log');
                        foreach ($files as $file) {
                            if (file_exists($file)) {
                                unlink($file);
                            }
                        }
                        
                        if (is_dir($logDir)) {
                            rmdir($logDir);
                            taskmgr_log::info('日志清理完成: ' . $logDir, [], 'system');
                        }
                    }
                } catch (\Throwable $e) {
                    taskmgr_log::error('日志清理异常: ' . $e->getMessage(), [], 'system');
                }
            });

            // 处理进程终止信号
            $process->signal(SIGTERM, function ($sig) use ($timerId) {
                if ($timerId !== null) {
                    Swoole\Timer::clear($timerId);
                }

                taskmgr_log::info('日志清理进程正在停止...', [], 'system');
                // 协程内不能 Process::exit（会 deadlock），退出事件循环即可
                Swoole\Event::exit();
            });

        }, true);
    }
}
