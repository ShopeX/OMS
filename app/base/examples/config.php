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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
/**
 * 网店配置模板
 *
 * 版本 $Id: config.sample.php 37482 2009-12-08 10:54:56Z ever $
 * 配置参数讨论专贴 http://www.shopex.cn/bbs/thread-61957-1-1.html
 */


// ** 数据库配置 ** //
define('DB_USER', getenv('ONEX_DB_USER') ?: 'usernamehere');  # 数据库用户名
define('DB_PASSWORD', getenv('ONEX_DB_PASSWORD') ?: 'yourpasswordhere'); # 数据库密码
define('DB_NAME', getenv('ONEX_DB_NAME') ?: 'putyourdbnamehere');    # 数据库名

# 数据库服务器 -- 99% 的情况下您不需要修改此参数
define('DB_HOST', getenv('ONEX_DB_HOST') ?: 'localhost');
//define('DB_PCONNECT',1); #是否启用数据库持续连接？

define('WITH_REWRITE',false);

define('STORE_KEY', ''); #密钥
define('DB_PREFIX', 'sdb_');
#define('LANG', '');
define('DEFAULT_TIMEZONE', '8');
define('WITHOUT_CACHE',false);
define('WITHOUT_KVSTORE_PERSISTENT', false);
#启用触发器日志: home/logs/trigger.php
//define ('TRIGGER_LOG',true);
//define ('DISABLE_TRIGGER',true); #禁用触发器

/* 以下为调优参数 */
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
define('DEBUG_JS',false);
define('ROOT_DIR', realpath(dirname(__FILE__).'/../'));

//安全模式启用后将禁用插件
//define('SAFE_MODE',false);

#您可以更改这个目录的位置来获得更高的安全性
define('DATA_DIR', ROOT_DIR.'/data'); 
define('THEME_DIR', ROOT_DIR.'/themes');
define('PUBLIC_DIR', ROOT_DIR.'/public');  #同一主机共享文件
define('MEDIA_DIR', PUBLIC_DIR.'/images');
define('SECACHE_SIZE','1024M'); #缓存大小,最大不能超过1G
//define('TEMPLATE_MODE','database');
define("MAIL_LOG",false);
define('DEFAULT_INDEX','');
define('SERVER_TIMEZONE',8); #服务器时区
//define('APP_ROOT_PHP','index.php'); #iis 5
//define('HTTP_PROXY','127.0.0.1:8888');
@ini_set('memory_limit','32M');
define('WITHOUT_GZIP',false);
define('WITHOUT_STRIP_HTML', true);
define('WITH_HTTPS_SUPPORT', 'yourhttpssupport'); #是否开启HTTPS支持

# Session 配置
# define('SESS_NAME', 's');   #used as cookie name
# define('SESS_CACHE_EXPIRE', 60);  #expires after n minutes

#前台禁ip
//define('BLACKLIST','10.0.0.0/24 192.168.0.1/24');

#数据库集群.
//define('DB_SLAVE_NAME',DB_NAME);
//define('DB_SLAVE_USER',DB_USER);
//define('DB_SLAVE_PASSWORD',DB_PASSWORD);
//define('DB_SLAVE_HOST',DB_HOST);

#支持泛解的时候才可以用这个, 仅支持fs_storager
/*
 * define('HOST_MIRRORS',
 * 'http://img0.example.com,
 * http://img2.example.com,
 * http://img2.example.com');
 */

#使用ftp存放图片文件
//define('WITH_STORAGER','ftp_storager');

#确定服务器支持htaccess文件时，可以打开下面两个参数获得加速。
//define ('GZIP_CSS',true);
//define ('GZIP_JS',true);

/* 日志 */
//define('LOG_LEVEL',E_ERROR);

/* 日志保存类型 0=>使用系统日志， 3=>保存文件 */
#define('LOG_TYPE', 0);
#define('LOG_TYPE', 3);

#按日期分目录，每个ip一个日志文件。扩展名是php防止下载。
define('LOG_FILE', DATA_DIR.'/logs/{date}/{ip}.php');

#log文件头部放上exit()保证无法下载。
define('LOG_HEAD_TEXT', '<'.'?php exit()?'.">\n");  
//define('LOG_FORMAT',"{gmt}\t{request}\t{code}");

#建议kvstore配置，使用 REDIS
# define('KVSTORE_STORAGE', 'base_kvstore_redis');
# define('KVSTORE_REDIS_CONFIG', '127.0.0.1:6379');
# TLS（需 phpredis 5.3+，connect 支持 stream context）
# define('KVSTORE_REDIS_TLS', true);
# define('KVSTORE_REDIS_CONNECT_TIMEOUT', 2.0);
# define('KVSTORE_REDIS_READ_TIMEOUT', 2.0);
# define('KVSTORE_REDIS_STREAM_CONTEXT', array('stream' => array('verify_peer' => true, 'verify_peer_name' => true, 'cafile' => '/path/to/ca.pem')));

# ome 独享库存 Redis（STORE_REDIS_STORAGE=true 时；TLS 常量 STORE_REDIS_*）
# define('STORE_REDIS_CONFIG', '127.0.0.1:6379');
# define('STORE_REDIS_TLS', true);
# define('STORE_REDIS_CONNECT_TIMEOUT', 2.0);
# define('STORE_REDIS_READ_TIMEOUT', 2.0);
# define('STORE_REDIS_STREAM_CONTEXT', array('stream' => array('verify_peer' => true, 'verify_peer_name' => true, 'cafile' => '/path/to/ca.pem')));

# taskmgr 缓存 Redis（app/taskmgr/lib/cache/redis.php）
# define('__REDIS_CONFIG', '127.0.0.1:6379');
# define('__REDIS_TLS', true);
# define('__REDIS_CONNECT_TIMEOUT', 2.0);
# define('__REDIS_READ_TIMEOUT', 2.0);
# define('__REDIS_STREAM_CONTEXT', array('stream' => array('verify_peer' => true, 'verify_peer_name' => true, 'cafile' => '/path/to/ca.pem')));
# taskmgr 队列 connecter（app/taskmgr/lib/connecter/redis.php）配置数组可选：TLS|USE_TLS, CONNECT_TIMEOUT, READ_TIMEOUT, STREAM_CONTEXT

#建议Cache配置，使用Memcache或Redis
# Memcache
# define('CACHE_STORAGE', 'base_cache_memcache');
# define('CACHE_MEMCACHE_CONFIG', '127.0.0.1:11211,127.0.0.1:11212');

# Redis
# define('CACHE_STORAGE', 'base_cache_redis');
# define('CACHE_REDIS_CONFIG', '127.0.0.1:6379');

#禁止运行安装
//define('DISABLE_SYS_CALL',1);

#使用数据库存放改动过的模板
//define('THEME_STORAGE','db');

# kvstroe后台存储类
define('KVSTORE_STORAGE', 'base_kvstore_filesystem');
# define('KVSTORE_STORAGE', 'base_kvstore_mysql');
# define('KVSTORE_STORAGE', 'base_kvstore_memcache');
# define('KVSTORE_STORAGE', 'base_kvstore_dba');
# define('KVSTORE_STORAGE', 'base_kvstore_tokyotyrant');
# define('KVSTORE_STORAGE', 'base_kvstore_mongodb');
# define('KVSTORE_STORAGE', 'base_kvstore_redis');

# cache后端存储类
# define('CACHE_STORAGE', 'base_cache_nocache');
define('CACHE_STORAGE', 'base_cache_secache');
# define('CACHE_STORAGE', 'base_cache_memcache');
# define('CACHE_STORAGE', 'base_cache_memcached');
# define('CACHE_STORAGE', 'base_cache_redis');

# KV_PREFIX KV引擎前缀
# define('KV_PREFIX', 'default');

# file_storage
#define('FILE_STORAGER','filesystem');
#define('STORAGE_MEMCACHED','192.168.0.230:11211,192.168.0.231:11211');
#define('HOST_MIRRORS','http://img.demo.cn,http://img1.demo.cn');

# app statics host
#define('APP_STATICS_HOST', 'http://img.demo.cn;http://img1.demo.cn');

# Monolog日志配置
// define('MONOLOG_OPTIONS', [
//     'api_log' => [
//         'handlers' => [

//         ],
//     ],
//     'web_log' => [
//         'handlers' => [
//             '\Monolog\Handler\StreamHandler' => [
//                 DATA_DIR.'/logs/web.log'
//             ],
//             'base_logger_handler_apidb' => [],
//         ]
//     ]
// ]);


/**************** compat functions begin ****************/


/**************** compat functions end ****************/
