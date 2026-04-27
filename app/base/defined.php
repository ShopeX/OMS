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


$constants = array(
    //对外链接地址设置
    'MATRIX_URL'               => 'https://matrix-gw-open.shopex.cn/',
    'MATRIX_RELATION_URL'      => 'https://iframe-open.shopex.cn/',
    'MATRIX_GO_URL'            => 'https://matrix-go.shopex.cn/',
    
    'HTTP_TIME_OUT'        => -3,
    'DATA_DIR'             => ROOT_DIR . '/data',
    'OBJ_PRODUCT'          => 1,
    'OBJ_ARTICLE'          => 2,
    'OBJ_SHOP'             => 0,
    'MIME_HTML'            => 'text/html',
    'P_ENUM'               => 1,
    'P_SHORT'              => 2,
    'P_TEXT'               => 3,
    'HOOK_BREAK_ALL'       => -1,
    'HOOK_FAILED'          => 0,
    'HOOK_SUCCESS'         => 1,
    'SYSTEM_ROLE_ID'       => 0,
    'MSG_OK'               => true,
    'MSG_WARNING'          => E_WARNING,
    'MSG_ERROR'            => E_ERROR,
    'MNU_LINK'             => 0,
    'PAGELIMIT'            => 20,
    'MNU_BROWSER'          => 1,
    'MNU_PRODUCT'          => 2,
    'MNU_ARTICLE'          => 3,
    'MNU_ART_CAT'          => 4,
    'PLUGIN_BASE_URL'      => 'plugins',
    'MNU_TAG'              => 5,
    'TABLE_REGEX'          => '([]0-9a-z_\:\"\`\.\@\[-]*)',
    'PMT_SCHEME_PROMOTION' => 0,
    'PMT_SCHEME_COUPON'    => 1,
    'APP_ROOT_PHP'         => '',
    'SET_T_STR'            => 0,
    'SET_T_INT'            => 1,
    'SET_T_ENUM'           => 2,
    'SET_T_BOOL'           => 3,
    'SAFE_MODE'            => false,
    'SET_T_TXT'            => 4,
    'SET_T_FILE'           => 5,
    'SET_T_DIGITS'         => 6,
    'LC_MESSAGES'          => 6,
    'BASE_LANG'            => 'zh_CN',
    'DEFAULT_LANG'         => 'zh_CN',
    'DEFAULT_INDEX'        => '',
    'ACCESSFILENAME'       => '.htaccess',
    'DEBUG_TEMPLETE'       => false,
    'WITH_REWRITE'         => false,
    'PRINTER_FONTS'        => '',
    'APP_DIR'              => ROOT_DIR . '/app',
    'PHP_SELF'             => (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME']),
    'LOG_TYPE'             => 3,
    'DATABASE_OBJECT'      => 'base_db_connections',
    'KVSTORE_STORAGE'      => 'base_kvstore_filesystem',
    'CACHE_STORAGE'        => 'base_cache_secache',
    'IMAGE_MAX_SIZE'       => 1024 * 1024,
    'KV_PREFIX'            => 'defalut',
    'LANG'                 => 'zh-cn',
    'APP_SOURCE'            => getenv('APP_SOURCE') ?: '',
    'APP_TOKEN'             => getenv('APP_TOKEN') ?: '',
    'MQ_HCHSAFE'           => false, // 御城河风控
    'ENTERPRISE_APPLY_URL' => 'https://open-console.shopex.cn/',

    // IDAAS 中间件（身份认证服务，非解密）
    'OMS_MIDDLEWARE_URL'       => '',

    // 加密密钥（从配置文件或环境变量读取，默认值为硬编码值以保持向后兼容）
    'OPENSSL_CPIHER_IV'  => getenv('OPENSSL_CIPHER_IV') ?: '4Clmcz0OyrXI9AsoRsGZSAnky9F4eftC',
    
    'SF_EXPRESS_PARTNER_ID'    => '', // 顺丰快递合作伙伴ID
    'JD_NPS_APPID' => '', //京东NPS评价appId
    
    // 短信服务密钥（从配置文件或环境变量读取）
    'SMS_ISHOPEX_KEY'    => getenv('SMS_ISHOPEX_KEY') ?: '',
    'SMS_ISHOPEX_SECRET' => getenv('SMS_ISHOPEX_SECRET') ?: '',
    'SMS_SHOPEX_KEY'     => getenv('SMS_SHOPEX_KEY') ?: '',
    'SMS_SHOPEX_SECRET'  => getenv('SMS_SHOPEX_SECRET') ?: '',
    'SMS_OAUTH_CLIENT_ID' => getenv('SMS_OAUTH_CLIENT_ID') ?: '',
    'SMS_OAUTH_SECRET'    => getenv('SMS_OAUTH_SECRET') ?: '',
    
    // 奇门API接口
    'QIMEN_URL' => 'https://qimen.api.taobao.com/top/router/qm',

    // 开源安装统计（上报 gwnextapi；未设置环境变量时用默认密钥；环境变量显式设为空字符串可关闭上报）
    'OPEN_SOURCE_STAT_SECRET_OMS' => getenv('OPEN_SOURCE_STAT_SECRET_OMS') !== false
        ? getenv('OPEN_SOURCE_STAT_SECRET_OMS')
        : 'K9mN2xPq5RwT8yLz4CvB7',
    'OPEN_SOURCE_STAT_PRODUCT' => getenv('OPEN_SOURCE_STAT_PRODUCT') ?: 'oms',
    'OPEN_SOURCE_STAT_REPORT_URL' => getenv('OPEN_SOURCE_STAT_REPORT_URL') ?: 'https://gwnextapi.shopex.cn/usercenter/open_source/stat/report',
    'OPEN_SOURCE_STAT_HTTP_TIMEOUT' => (int) (getenv('OPEN_SOURCE_STAT_HTTP_TIMEOUT') ?: 20),
    'OPEN_SOURCE_STAT_SENTINEL_NAME' => getenv('OPEN_SOURCE_STAT_SENTINEL_NAME') ?: '.opensource_install_stat_reported',
);

$constants_ext = array();
$file          = ROOT_DIR . '/config/defined_ext.php';
if (file_exists($file)) {

    include_once $file;
}

// 加载统一密钥配置文件（优先级：环境变量 > 配置文件 > 默认值）
$secrets_file = ROOT_DIR . '/config/secrets.php';
if (file_exists($secrets_file)) {
    $secrets = include $secrets_file;
    if (is_array($secrets)) {
        foreach ($secrets as $key => $value) {
            // 检查环境变量是否已设置（优先级最高）
            $env_value = getenv($key);
            if ($env_value !== false && $env_value !== '') {
                // 环境变量已设置，跳过配置文件的值（环境变量优先级最高）
                continue;
            }
            // 使用配置文件的值覆盖默认值
            // 如果当前值为空字符串或未设置，使用配置文件的值
            if (!isset($constants[$key]) || $constants[$key] === '') {
                $constants[$key] = $value;
            }
        }
    }
}

$constants = array_merge($constants, $constants_ext);
foreach ($constants as $k => $v) {
    if (!defined($k)) {
        define($k, $v);
    }

}

define('TOP_APP_KEY', '10011902');
