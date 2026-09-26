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
 * 配置信息
 */

// 读取 .env（同 base/examples/config.php 风格）
if (!function_exists('__load_env_if_exists')) {
    function __load_env_if_exists(array $paths)
    {
        foreach ($paths as $envFile) {
            if (!$envFile || !is_readable($envFile)) {
                continue;
            }
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(ltrim($line), '#') === 0) {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                $k = trim($parts[0]);
                $v = trim($parts[1]);
                if ((substr($v, 0, 1) === '"' && substr($v, -1) === '"') || (substr($v, 0, 1) === "'" && substr($v, -1) === "'")) {
                    $v = substr($v, 1, -1);
                }
                putenv($k.'='.$v);
                $_ENV[$k] = $v;
            }
        }
    }
}
$envCandidates = array(__DIR__.'/.env');
__load_env_if_exists($envCandidates);

if (!function_exists('__env_or_default')) {
    function __env_or_default($key, $default = null, $asBool = false)
    {
        $v = getenv($key);
        if ($v === false) {
            $v = null;
        }
        if ($v === null) {
            $v = $default;
        }
        if ($asBool && $v !== null) {
            $v = in_array(strtolower((string)$v), array('1','true','on','yes'), true);
        }
        return $v;
    }
}

// 简洁的前缀读取助手
if (!function_exists('env_taskmgr')) {
    function env_taskmgr($suffix, $default = null, $asBool = false)
    {
        return __env_or_default('ONEX_OMS_TASKMGR_' . $suffix, $default, $asBool);
    }
}

error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// 数据提供者：redis | rabbitmq
define('__CONNECTER_MODE', env_taskmgr('CONNECTER_MODE', 'redis'));
$rabbitmqInterface = env_taskmgr('RABBITMQ_INTERFACE', 'pecl');
if (__CONNECTER_MODE === 'rabbitmq') {
    define('__RABBITMQ_INTERFACE__', $rabbitmqInterface);
    $GLOBALS['__RABBITMQ_CONFIG'] = array(
        'HOST'         => env_taskmgr('RABBITMQ_HOST', '127.0.0.1'),
        'PORT'         => (int)env_taskmgr('RABBITMQ_PORT', 5672),
        'USER'         => env_taskmgr('RABBITMQ_USER', 'task'),
        'PASSWD'       => env_taskmgr('RABBITMQ_PASSWD', 'task123'),
        'VHOST'        => env_taskmgr('RABBITMQ_VHOST', 'erp_task'),
        'ROUTER'       => env_taskmgr('RABBITMQ_ROUTER', 'erp.task.%s.*'),
        'QUEUE_PREFIX' => env_taskmgr('QUEUE_PREFIX', 'ERP'),
    );
}

if (__CONNECTER_MODE === 'redis') {
    $redisConfig = array(
        'HOST'         => env_taskmgr('REDIS_HOST', '127.0.0.1'),
        'PORT'         => (int)env_taskmgr('REDIS_PORT', 6379),
        'QUEUE_PREFIX' => env_taskmgr('QUEUE_PREFIX', 'ERP'),
        'WAIT_TIME'    => (int)env_taskmgr('REDIS_WAIT_TIME', 1),
    );
    $redisPass = env_taskmgr('REDIS_PASSWD', null);
    if ($redisPass !== null && $redisPass !== '') {
        $redisConfig['PASSWD'] = $redisPass;
    }
    $GLOBALS['__REDIS_CONFIG'] = $redisConfig;
}

// 缓存存储介质提供者
define('__CACHE_MODE', env_taskmgr('CACHE_MODE', 'filesystem'));
if (__CACHE_MODE === 'memcache' || __CACHE_MODE === 'memcached') {
    define(
        '__MEMCACHE_CONFIG',
        env_taskmgr('MEMCACHE_CONFIG', '127.0.0.1:11211,127.0.0.1:11212')
    );
}

// 文件存储介质提供者
define('__STORAGE_MODE', env_taskmgr('STORAGE_MODE', 'local'));
if (__STORAGE_MODE === 'ftp') {
    $GLOBALS['__STORAGE_CONFIG'] = array(
        'HOST'    => env_taskmgr('STORAGE_HOST', '127.0.0.1'),
        'PORT'    => (int)env_taskmgr('STORAGE_PORT', 21),
        'USER'    => env_taskmgr('STORAGE_USER', 'test'),
        'PASSWD'  => env_taskmgr('STORAGE_PASSWD', 'test'),
        'TIMEOUT' => (int)env_taskmgr('STORAGE_TIMEOUT', 30),
        'PASV'    => env_taskmgr('STORAGE_PASV', false, true),
    );
} elseif (__STORAGE_MODE === 'sftp') {
    $GLOBALS['__STORAGE_CONFIG'] = array(
        'HOST'     => env_taskmgr('STORAGE_HOST', '127.0.0.1'),
        'PORT'     => (int)env_taskmgr('STORAGE_PORT', 22),
        'USER'     => env_taskmgr('STORAGE_USER', 'test'),
        'PASSWD'   => env_taskmgr('STORAGE_PASSWD', 'test'),
        'TIMEOUT'  => (int)env_taskmgr('STORAGE_TIMEOUT', 30),
        'rootPath' => env_taskmgr('STORAGE_ROOTPATH', '/'),
    );
} elseif (__STORAGE_MODE === 'aliyunoss') {
    // aliyunoss 使用 base_storage_aliyunosssystem 配置（如需个性化，可在全局 storager 配置处调整）
}

//设置为真实的域名,如果开启ssl可以配置https前缀的域名
define('DOMAIN', env_taskmgr('DOMAIN', 'http://127.0.0.1'));

//定义内部任务请求的token（必须配置）
define('REQ_TOKEN', env_taskmgr('REQ_TOKEN', 'YOUR_TOKEN_HERE'));
