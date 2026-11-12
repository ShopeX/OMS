<?php
class ome_system_rpcnotify
{
    public function saveRpcNotify($sdf)
    {
        $rpcNotifyMdl = app::get('base')->model('rpcnotify');
        
        // notifytime
        if(isset($sdf['notifytime']) && $sdf['notifytime'] > 0){
            $notifytime = time() + $sdf['notifytime'];
        }elseif(isset($sdf['date']) && $sdf['date']){
            $notifytime = strtotime($sdf['date']);
        }else{
            $notifytime = 0;
        }
        
        // content
        if(is_array($sdf['content'])){
            $content = json_encode($sdf['content'], JSON_UNESCAPED_UNICODE);
        }else{
            $content = $sdf['content'];
        }
        
        // data
        $saveData = [
            'callback' => ($sdf['callback'] ? $sdf['callback'] : ''),
            'rsp' => ($sdf['rsp'] ? $sdf['rsp'] : 'succ'),
            'status' => 'false',
            'msg' => $content,
            'event_type' => $sdf['event_type'], // 模板类型
            'msg_type' => $sdf['msg_type'], // 消息类型
            'shop_type' => ($sdf['shop_type'] ? $sdf['shop_type'] : ''), // 店铺类型
            'shop_bn' => ($sdf['shop_bn'] ? $sdf['shop_bn'] : ''), // 店铺编码
            'org_id' => ($sdf['org_id'] ? $sdf['org_id'] : 0),
            'notifytime' => $notifytime, // 通知时间
        ];
        
        // save
        $save_action = isset($sdf['save_action']) ? $sdf['save_action'] : 'insert';
        if ($save_action === 'update') {
            $rpcnotify_id = isset($sdf['rpcnotify_id']) ? intval($sdf['rpcnotify_id']) : 0;
            if ($rpcnotify_id <= 0) {
                return false;
            }
            
            $updateData = array(
                'rsp' => $saveData['rsp'],
                'status' => 'false',
                'msg' => $saveData['msg'],
                'notifytime' => $saveData['notifytime'],
            );
            $updated = $rpcNotifyMdl->update($updateData, array('id' => $rpcnotify_id));
            
            return $updated ? $rpcnotify_id : false;
        }
        
        // insert
        $insert_id = $rpcNotifyMdl->insert($saveData);
        
        return $insert_id;
    }
    
    /**
     * 添加店铺事件通知
     *
     * @param array $rpcnotifySdf 系统通知消息
     * @param string $error_msg 错误信息
     * @return bool
     */
    public function addShopEventNotify($rpcnotifySdf, &$error_msg='')
    {
        $eventTemplateMdl = app::get('monitor')->model('event_template');
        $operLogObj = app::get('ome')->model('operation_log');
        
        // check
        if(!isset($rpcnotifySdf['rpcnotify_id']) || empty($rpcnotifySdf['rpcnotify_id'])){
            $error_msg = 'rpcnotify id is empty';
            return false;
        }
        
        $rpcnotify_id = intval($rpcnotifySdf['rpcnotify_id']);
        
        // event_type
        $event_type = isset($rpcnotifySdf['event_type']) ? $rpcnotifySdf['event_type'] : '';
        if (empty($event_type)) {
            $error_msg = '未发送消息提醒：event_type为空';
            $operLogObj->write_log('base_rpc_notify@wms', $rpcnotify_id, $error_msg);
            
            return false;
        }
        
        // shop_type
        $shop_type = isset($rpcnotifySdf['shop_type']) ? (string) $rpcnotifySdf['shop_type'] : '';
        if (empty($shop_type)) {
            $error_msg = '未发送消息提醒：shop_type为空';
            $operLogObj->write_log('base_rpc_notify@wms', $rpcnotify_id, $error_msg);
            
            return false;
        }
        
        // content
        $content = $this->normalizeRpcnotifyContent($rpcnotifySdf);
        $rpcnotifySdf['content'] = $content;
        
        // 获取匹配的模板
        $templateList = $eventTemplateMdl->getList('*', array(
            'event_type' => $event_type,
            'status'     => '1',
            'disabled'   => 'false',
        ));
        
        if (!$templateList) {
            $error_msg = '未发送消息提醒：无可用监控事件模板';
            $operLogObj->write_log('base_rpc_notify@wms', $rpcnotify_id, $error_msg);
            return true;
        }
        
        // 获取过期时间
        $expireMeta = $this->resolveExpireMeta($content);
        $matchedTemplates = array();
        $skipReasons = array();
        
        // 检查模板
        foreach ($templateList as $templateInfo)
        {
            $matchResult = $this->matchShopEventTemplate($templateInfo, $event_type, $shop_type, $expireMeta);
            if ($matchResult === true) {
                // 匹配成功
                $matchedTemplates[] = $templateInfo;
            } else {
                // 匹配失败
                $skipReasons[] = $matchResult;
            }
        }

        $logParts = array();
        if ($skipReasons) {
            $logParts = array_merge($logParts, $skipReasons);
        }
        
        if ($matchedTemplates) {
            $notifyParams = $this->buildShopEventNotifyParams($rpcnotifySdf, $expireMeta);
            
            kernel::single('monitor_event_notify')->addNotify($event_type, $notifyParams, false, $matchedTemplates);
            
            $matchedLabels = array();
            foreach ($matchedTemplates as $tpl)
            {
                $template_bn = $tpl['template_bn'];
                $template_name = $tpl['template_name'];
                
                $temp_template_name = $template_name .'（'. $template_bn .'）';
                
                $matchedLabels[] = $temp_template_name;
            }
            
            $logParts[] = '已添加消息通知日志，预警模板：' . implode(',', $matchedLabels) . '';
        } elseif (!$skipReasons) {
            $logParts[] = '未添加消息通知日志：无匹配监控事件模板';
        }

        $operLogObj->write_log('base_rpc_notify@wms', $rpcnotify_id, implode('；', $logParts));

        return true;
    }

    /**
     * 规范化 rpcnotify content
     *
     * @param array $rpcnotifySdf
     * @return array
     */
    protected function normalizeRpcnotifyContent($rpcnotifySdf)
    {
        if (isset($rpcnotifySdf['content']) && is_array($rpcnotifySdf['content'])) {
            return $rpcnotifySdf['content'];
        }
        if (isset($rpcnotifySdf['content']) && is_string($rpcnotifySdf['content']) && $rpcnotifySdf['content'] !== '') {
            $decoded = json_decode($rpcnotifySdf['content'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        if (isset($rpcnotifySdf['msg']) && is_string($rpcnotifySdf['msg']) && $rpcnotifySdf['msg'] !== '') {
            $decoded = json_decode($rpcnotifySdf['msg'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return array();
    }

    /**
     * 解析到期时间与剩余天数
     *
     * @param array $content
     * @return array{dead_line:string,days_left:int|null,expire_ts:int|null}
     */
    protected function resolveExpireMeta($content)
    {
        $result = array(
            'dead_line' => '',
            'days_left' => null,
            'expire_ts' => null,
        );

        $candidates = array();
        if (!empty($content['access_token_expire_in'])) {
            $candidates[] = trim((string) $content['access_token_expire_in']);
        }
        if (isset($content['dead_line']) && $content['dead_line'] !== '' && $content['dead_line'] !== 0 && $content['dead_line'] !== '0') {
            $candidates[] = trim((string) $content['dead_line']);
        }
        if (!empty($content['info'])) {
            $parsedFromInfo = $this->parseExpireTimeFromInfo((string) $content['info']);
            if ($parsedFromInfo) {
                $candidates[] = $parsedFromInfo;
            }
        }

        $expireTs = null;
        $deadLine = '';
        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (ctype_digit($candidate) || (is_numeric($candidate) && intval($candidate) > 1000000000)) {
                $ts = intval($candidate);
            } else {
                $ts = strtotime($candidate);
            }
            if ($ts) {
                $expireTs = $ts;
                $deadLine = $candidate;
                break;
            }
        }

        if ($expireTs) {
            $result['expire_ts'] = $expireTs;
            $result['dead_line'] = $deadLine !== '' ? $deadLine : date('Y-m-d H:i:s', $expireTs);
            
            // 按自然日计算剩余天数，过期当天为 0
            $daysLeft = (int) floor(($expireTs - strtotime(date('Y-m-d'))) / 86400);
            
            $result['days_left'] = $daysLeft;
        }

        return $result;
    }

    /**
     * 从消息文案解析到期时间
     *
     * @param string $info
     * @return string
     */
    protected function parseExpireTimeFromInfo($info)
    {
        if ($info === '') {
            return '';
        }
        // 授权将于2026-09-20 / 2026-09-20 23:59:59 / 2026/09/20
        if (preg_match('/(\d{4}[-\/\.]\d{1,2}[-\/\.]\d{1,2}(?:\s+\d{1,2}:\d{1,2}(?::\d{1,2})?)?)/u', $info, $m)) {
            return str_replace(array('/', '.'), '-', $m[1]);
        }
        // 2026年09月20日
        if (preg_match('/(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日/u', $info, $m)) {
            return sprintf('%04d-%02d-%02d', intval($m[1]), intval($m[2]), intval($m[3]));
        }
        return '';
    }

    /**
     * 校验单个模板是否匹配；匹配返回 true，否则返回跳过原因文案
     *
     * @param array $templateInfo
     * @param string $event_type
     * @param string $shop_type
     * @param array $expireMeta
     * @return true|string
     */
    protected function matchShopEventTemplate($templateInfo, $event_type, $shop_type, $expireMeta)
    {
        $template_bn = $templateInfo['template_bn'];
        $template_name = $templateInfo['template_name'];
        $temp_template_name = $template_name .'（'. $template_bn .'）';
        
        $config = isset($templateInfo['template_config']) ? $templateInfo['template_config'] : array();
        if (is_string($config) && $config !== '') {
            $decoded = @unserialize($config);
            $config = is_array($decoded) ? $decoded : array();
        }
        
        if (!is_array($config)) {
            $config = array();
        }
        
        // 支持的平台
        $shopTypes = isset($config['shop_types']) ? (array) $config['shop_types'] : array();
        
        // 京东平台，需要额外添加：jd
        if(in_array('360buy', $shopTypes)){
            $shopTypes[] = 'jd';
        }
        
        // 淘宝平台，需要额外添加：tmall
        if(in_array('taobao', $shopTypes)){
            $shopTypes[] = 'tmall';
        }
        
        // check shop_type
        if (!$shopTypes || !in_array($shop_type, $shopTypes, true)) {
            return '未发送消息提醒，预警模板：' . $temp_template_name . '，适用平台不包含[' . $shop_type . ']';
        }

        if ($event_type === 'system_auth_expire') {
            $remindDays = isset($config['remind_days']) ? intval($config['remind_days']) : 0;
            if ($expireMeta['days_left'] === null) {
                return '未发送消息提醒：无法解析授权到期时间，预警模板：' . $temp_template_name;
            }
            
            $daysLeft = intval($expireMeta['days_left']);
            
            // 过期当天(0)至 remind_days 内提醒；已过期(负数)不提醒
            if ($daysLeft < 0 || $daysLeft > $remindDays) {
                return '未发送消息提醒，预警模板：' . $temp_template_name . '，到期前提醒[' . $remindDays . ']天，当前剩余[' . $daysLeft . ']天';
            }
        }

        return true;
    }

    /**
     * 组装监控通知模板占位符参数
     *
     * @param array $rpcnotifySdf
     * @param array $expireMeta
     * @return array
     */
    protected function buildShopEventNotifyParams($rpcnotifySdf, $expireMeta)
    {
        $content = isset($rpcnotifySdf['content']) && is_array($rpcnotifySdf['content']) ? $rpcnotifySdf['content'] : array();
        $shop_type = isset($rpcnotifySdf['shop_type']) ? (string) $rpcnotifySdf['shop_type'] : '';
        $shop_bn = isset($rpcnotifySdf['shop_bn']) ? (string) $rpcnotifySdf['shop_bn'] : '';

        $shop_name = '';
        if ($shop_bn !== '') {
            $shopRow = app::get('ome')->model('shop')->db_dump(array('shop_bn' => $shop_bn), 'name');
            if (!empty($shopRow['name'])) {
                $shop_name = trim((string) $shopRow['name']);
            }
        }
        if ($shop_name === '' && !empty($content['info']) && preg_match('/【([^】]+)】/u', $content['info'], $m)) {
            $shop_name = $m[1];
        }

        $platform = $shop_type;
        if ($shop_type !== '' && class_exists('ome_shop_type')) {
            $typeLabel = trim((string) ome_shop_type::shop_name($shop_type));
            if ($typeLabel !== '') {
                $platform = $typeLabel;
            }
        }

        $dead_line = $expireMeta['dead_line'];
        if ($dead_line === '' && !empty($content['dead_line']) && $content['dead_line'] !== 0 && $content['dead_line'] !== '0') {
            $dead_line = (string) $content['dead_line'];
        }
        if ($dead_line === '' && !empty($content['access_token_expire_in'])) {
            $dead_line = (string) $content['access_token_expire_in'];
        }

        $days_left = ($expireMeta['days_left'] === null) ? '' : (string) $expireMeta['days_left'];

        return array(
            'rpcnotify_id' => $rpcnotifySdf['rpcnotify_id'],
            'event_type'   => isset($rpcnotifySdf['event_type']) ? $rpcnotifySdf['event_type'] : '',
            'shop_type'    => $shop_type,
            'shop_bn'      => $shop_bn,
            'shop_name'    => $shop_name,
            'platform'     => $platform,
            'dead_line'    => $dead_line,
            'days_left'    => $days_left,
            'errmsg'       => isset($content['info']) ? $content['info'] : '',
        );
    }
}
