<?php

/**
 * OMS→SAP HTTP 推送配置
 * 优先级：defaults ← 常量/环境变量 ← 后台 conf
 */
class goldentra_push_setting
{
    const CONF_KEY = 'sap.push.http.config';

    public static function defaults()
    {
        return array(
            'enabled' => '1',
            'base_url' => 'http://172.18.16.17:50000/RESTAdapter/OMS',
            'username' => 'OMS',
            'password' => '0KTEtO72xxoP',
            'timeout' => 60,
            'zly' => '7',
            'destsystem' => 'SAP',
            // 相对 base_url 的路径，或以 http 开头的完整 URL
            'url_sd003' => '/UpdStoreSalesInfo',
            // 确认收货：http://172.18.16.17:50000/RESTAdapter/OMS/ConfirmGoodsReceipt
            'url_sd004' => '/ConfirmGoodsReceipt',
            'url_fi001' => '',
        );
    }

    public function getEffective()
    {
        $cfg = self::defaults();

        $defines = array(
            'GOLDENTRA_SAP_PUSH_ENABLED' => 'enabled',
            'GOLDENTRA_SAP_PUSH_BASE_URL' => 'base_url',
            'GOLDENTRA_SAP_PUSH_USER' => 'username',
            'GOLDENTRA_SAP_PUSH_PASS' => 'password',
            'GOLDENTRA_SAP_PUSH_TIMEOUT' => 'timeout',
            'GOLDENTRA_SAP_PUSH_ZLY' => 'zly',
            'GOLDENTRA_SAP_PUSH_DESTSYSTEM' => 'destsystem',
            'GOLDENTRA_SAP_PUSH_URL_SD003' => 'url_sd003',
            'GOLDENTRA_SAP_PUSH_URL_SD004' => 'url_sd004',
            'GOLDENTRA_SAP_PUSH_URL_FI001' => 'url_fi001',
        );
        foreach ($defines as $const => $key) {
            if (defined($const) && constant($const) !== '') {
                $cfg[$key] = constant($const);
            }
            $env = getenv($const);
            if ($env !== false && $env !== '') {
                $cfg[$key] = $env;
            }
        }

        $saved = app::get('goldentra')->getConf(self::CONF_KEY);
        if (is_array($saved) && $saved) {
            foreach ($saved as $k => $v) {
                if ($v !== '' && $v !== null && array_key_exists($k, $cfg)) {
                    $cfg[$k] = $v;
                }
            }
        }

        $cfg['timeout'] = max(5, intval($cfg['timeout']));
        $cfg['enabled'] = (string) $cfg['enabled'] === '1' || $cfg['enabled'] === 1 || $cfg['enabled'] === true ? '1' : '0';
        return $cfg;
    }

    public function getForForm()
    {
        $cfg = $this->getEffective();
        $cfg['password_display'] = !empty($cfg['password']) ? '******' : '';
        $cfg['has_password'] = !empty($cfg['password']) ? '1' : '0';
        return $cfg;
    }

    public function save(array $post)
    {
        $cfg = self::defaults();
        $saved = app::get('goldentra')->getConf(self::CONF_KEY);
        if (is_array($saved) && $saved) {
            foreach ($saved as $k => $v) {
                if (array_key_exists($k, $cfg) && $v !== null && $v !== '') {
                    $cfg[$k] = $v;
                }
            }
        }
        foreach (array_keys($cfg) as $k) {
            if ($k === 'enabled') {
                continue;
            }
            if (!array_key_exists($k, $post)) {
                continue;
            }
            $v = is_string($post[$k]) ? trim($post[$k]) : $post[$k];
            if ($k === 'password' && ($v === '' || $v === '******')) {
                continue;
            }
            $cfg[$k] = $v;
        }
        $cfg['timeout'] = max(5, intval($cfg['timeout']));
        $cfg['enabled'] = !empty($post['enabled']) ? '1' : '0';
        return app::get('goldentra')->setConf(self::CONF_KEY, $cfg);
    }

    /**
     * @param string $intfid SD003|SD004|FI001
     * @return string
     */
    public function resolveUrl($intfid)
    {
        $cfg = $this->getEffective();
        $map = array(
            'SD003' => 'url_sd003',
            'SD004' => 'url_sd004',
            'FI001' => 'url_fi001',
        );
        $key = isset($map[$intfid]) ? $map[$intfid] : 'url_sd003';
        $path = isset($cfg[$key]) ? trim((string) $cfg[$key]) : '';
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $base = rtrim((string) $cfg['base_url'], '/');
        if ($base === '') {
            return $path;
        }
        return $base . '/' . ltrim($path, '/');
    }
}
