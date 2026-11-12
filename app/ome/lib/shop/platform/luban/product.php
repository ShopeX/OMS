<?php
/**
 * 抖音平台相关业务Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2025.07.23
 */
class ome_shop_platform_luban_product extends ome_abstract
{
    /**
     * 建议类型文案
     *
     * @var array
     */
    protected $suggestionLabels = array(
        'force_offline'   => '必须下架',
        'suggest_offline' => '建议下架',
        'optimize'        => '牵引优化',
    );

    /**
     * 查询诊断商品列表及其处理建议（只限抖音类型的店铺）
     * @todo：只限定已经绑定的抖音类型的店铺,并且循环查询三种（商品处理建议：force_offline强制下架, suggest_offline建议下架, optimize牵引优化）；
     *
     * @return array
     */
    public function getProductSuggestionBusiness()
    {
        $shopMdl = app::get('ome')->model('shop');

        //1、获取抖音店铺列表（已经绑定的店铺：node_id 不等于空）；
        $filter = array(
            'shop_type' => 'luban',
            'node_id|noequal' => ''
        );
        $shopList = $shopMdl->getList('shop_id,shop_bn,name,node_id,node_type,org_id', $filter);
        if (empty($shopList)) {
            return $this->succ('没有找到抖音类型的店铺!');
        }

        //2、循环 $suggestionTypes 获取商品处理建议；
        $suggestionTypes = array(
            'force_offline' => 'force_offline', // 必须下架（超宽限期）；副文案语境 / 后续批量下架
            'suggest_offline' => 'suggest_offline', // 建议下架；首页主数字 + 详情建议下架卡片
            'optimize' => 'optimize', // 牵引优化（高潜力新品）
        );

        $successCount = 0;
        $failCount = 0;
        $saveCount = 0;
        
        // 按照店铺纬度获取商品建议
        foreach ($shopList as $shopInfo)
        {
            // 每个店铺合并所有建议类型后，保存一条
            $suggestionData = array();

            foreach ($suggestionTypes as $suggestion)
            {
                try {
                    //3、请求抖音平台 API，只取 total建议数量
                    $requestParams = array(
                        'suggestion' => $suggestion,
                    );
                    $result = kernel::single('erpapi_router_request')->set('shop', $shopInfo['shop_id'])->product_getProductSuggestionList($requestParams);
                    if (empty($result) || $result['rsp'] != 'succ') {
                        $failCount++;
                        continue;
                    }

                    // 解析平台返回的商品建议总数（total=0 也要保留）
                    $total = $this->parseSuggestionTotal($result['data']);
                    $successCount++;

                    $suggestionData[$suggestion] = array(
                        'suggestion' => $suggestion,
                        'label'      => isset($this->suggestionLabels[$suggestion]) ? $this->suggestionLabels[$suggestion] : $suggestion,
                        'total'      => $total,
                    );
                } catch (Exception $e) {
                    $failCount++;
                    continue;
                }
            }

            //4、每个店铺 + 全部建议类型合并保存一条（含 total=0）
            if (empty($suggestionData)) {
                continue;
            }
            
            // save
            $insertRs = $this->saveSuggestionNotify($shopInfo, $suggestionData);
            if ($insertRs) {
                $saveCount++;
            }
        }

        // 滚动清理 7 天前快照
        $this->cleanupExpiredSuggestionNotify(7);

        return $this->succ(sprintf('执行成功! 请求成功:%d, 失败:%d, 落库:%d', $successCount, $failCount, $saveCount));
    }

    /**
     * 是否存在已绑定的抖音店铺
     *
     * @return bool
     */
    public function hasBoundLubanShop()
    {
        $shopMdl = app::get('ome')->model('shop');
        $count = $shopMdl->count(array(
            'shop_type'       => 'luban',
            'node_id|noequal' => '',
        ));
        return $count > 0;
    }

    /**
     * 首页「平台通知」：取今日 suggest_offline 数量最大的一条
     *
     * @return array|null
     */
    public function getHomepageNotify()
    {
        if (!$this->hasBoundLubanShop()) {
            return null;
        }

        $list = $this->getSuggestionNotifyList($this->getTodayRange());
        if (empty($list)) {
            return null;
        }

        $maxItem = null;
        $maxTotal = -1;
        foreach ($list as $row) {
            $parsed = $this->parseNotifyRow($row);
            $total = $parsed['suggest_offline'];
            if ($total > $maxTotal) {
                $maxTotal = $total;
                $maxItem = $parsed;
            }
        }

        if ($maxItem === null) {
            return null;
        }

        return array(
            'shop_bn'   => $maxItem['shop_bn'],
            'shop_name' => $maxItem['shop_name'],
            'total'     => (int) $maxItem['suggest_offline'],
            'title'     => sprintf('【抖店】您有 %d 件长期低效商品建议下架', (int) $maxItem['suggest_offline']),
            'subtitle'  => '下架后可释放流量给优质商品；强制下架商品已自动清理。',
            'detail_url'=> 'index.php?app=ome&ctl=admin_platform_notify&act=index',
            'notifytime'=> $maxItem['notifytime'],
        );
    }

    /**
     * 获取已绑定的抖音店铺列表
     *
     * @return array
     */
    public function getBoundLubanShops()
    {
        $shopMdl = app::get('ome')->model('shop');
        return $shopMdl->getList('shop_id,shop_bn,name,node_id,node_type', array(
            'shop_type'       => 'luban',
            'node_id|noequal' => '',
        ), 0, -1, 'name ASC');
    }

    /**
     * 详情页数据：按店铺返回今日快照 + 近 7 天历史
     *
     * @return array
     */
    public function getDetailData()
    {
        $shopList = $this->getBoundLubanShops();
        $hasShop = !empty($shopList);

        $todayList = $this->getSuggestionNotifyList($this->getTodayRange());
        $todayByShop = array();
        foreach ($todayList as $row) {
            $parsed = $this->parseNotifyRow($row);
            if (empty($parsed['shop_bn'])) {
                continue;
            }
            // 同日同店铺去重后应只有一条；若有多条取最新
            $bn = $parsed['shop_bn'];
            if (!isset($todayByShop[$bn]) || $parsed['notifytime'] > $todayByShop[$bn]['notifytime']) {
                $todayByShop[$bn] = $parsed;
            }
        }

        $tabs = array();
        $shops = array();
        $allSuggestOffline = 0;
        $dataDate = date('Y-m-d');

        foreach ($shopList as $shop) {
            $shopBn = $shop['shop_bn'];
            $parsed = isset($todayByShop[$shopBn]) ? $todayByShop[$shopBn] : array(
                'shop_bn'         => $shopBn,
                'shop_name'       => $shop['name'],
                'suggest_offline' => 0,
                'force_offline'   => 0,
                'optimize'        => 0,
                'notifytime'      => 0,
            );

            $shopName = !empty($parsed['shop_name']) ? $parsed['shop_name'] : $shop['name'];
            $suggestOffline = (int) $parsed['suggest_offline'];
            $forceOffline = (int) $parsed['force_offline'];
            $optimize = (int) $parsed['optimize'];
            $allSuggestOffline += $suggestOffline;

            if (!empty($parsed['notifytime'])) {
                $dataDate = date('Y-m-d', $parsed['notifytime']);
            }

            $tabs[] = array(
                'shop_bn' => $shopBn,
                'label'   => $shopName,
                'count'   => $suggestOffline,
            );

            $shops[] = array(
                'shop_bn'   => $shopBn,
                'shop_name' => $shopName,
                'snapshot'  => array(
                    'title'           => sprintf('【%s】发品流量效率优化', $shopName),
                    'source'          => 'getProductSuggestionList',
                    'data_date'       => !empty($parsed['notifytime']) ? date('Y-m-d', $parsed['notifytime']) : date('Y-m-d'),
                    'is_today'        => true,
                    'suggest_offline' => $suggestOffline,
                    'force_offline'   => $forceOffline,
                    'optimize'        => $optimize,
                    'ai_hosting'      => 0,
                ),
                'history' => $this->buildHistoryList(7, $shopBn, $shopName),
            );
        }

        return array(
            'has_shop' => $hasShop,
            'tab' => array(
                'label' => '抖店',
                'count' => $allSuggestOffline,
            ),
            'tabs'  => $tabs,
            'shops' => $shops,
            // 兼容旧模板：汇总快照（多店时为各店合计）
            'snapshot' => array(
                'title'           => '【抖店】发品流量效率优化',
                'source'          => 'getProductSuggestionList',
                'data_date'       => $dataDate,
                'is_today'        => true,
                'suggest_offline' => $allSuggestOffline,
                'force_offline'   => 0,
                'optimize'        => 0,
                'ai_hosting'      => 0,
            ),
            'history' => array(),
        );
    }

    /**
     * 清理超过保留天数的平台商品建议通知
     *
     * @param int $days
     * @return void
     */
    public function cleanupExpiredSuggestionNotify($days = 7)
    {
        $noticeMdl = app::get('base')->model('rpcnotify');
        
        // day
        $days = (int) $days;
        if ($days <= 0) {
            $days = 7;
        }
        
        // 过期时间
        $expireBefore = strtotime(date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' day')));
        
//        // 这里查询时间会查询所有日期，不能使用（'notifytime|lthan'）
//        $list = $noticeMdl->getList('id', array(
//            'msg_type'         => 'platform',
//            'shop_type'        => 'luban',
//            'event_type'       => 'product_suggestion',
//            'notifytime|lthan' => $expireBefore,
//        ));
        
        // 查询需要清理的数据
        $select_sql = "SELECT id FROM `sdb_base_rpcnotify` WHERE msg_type='platform' AND shop_type='luban' AND event_type='product_suggestion'";
        $select_sql .= "  AND notifytime<". $expireBefore;
        $list = $noticeMdl->db->select($select_sql);
        
        // check
        if (empty($list)) {
            return false;
        }
        
        // delete
        $deleteIds = array_column($list, 'id');
        if ($deleteIds) {
            $noticeMdl->delete(array('id' => $deleteIds));
        }
        
        return true;
    }

    /**
     * 按时间范围读取平台商品建议通知
     *
     * @param array $range [start, end]
     * @return array
     */
    protected function getSuggestionNotifyList($range)
    {
        $noticeMdl = app::get('base')->model('rpcnotify');
        return $noticeMdl->getList('*', array(
            'msg_type'           => 'platform',
            'shop_type'          => 'luban',
            'event_type'         => 'product_suggestion',
            'notifytime|between' => $range,
        ), 0, -1, 'notifytime DESC');
    }

    /**
     * 今日时间范围
     *
     * @return array
     */
    protected function getTodayRange()
    {
        return array(
            strtotime(date('Y-m-d 00:00:00')),
            strtotime(date('Y-m-d 23:59:59')),
        );
    }

    /**
     * 解析 rpcnotify 行
     *
     * @param array $row
     * @return array
     */
    protected function parseNotifyRow($row)
    {
        $msg = array();
        if (!empty($row['msg'])) {
            $decoded = json_decode($row['msg'], true);
            if (is_array($decoded)) {
                $msg = $decoded;
            }
        }

        $suggestions = isset($msg['suggestions']) && is_array($msg['suggestions']) ? $msg['suggestions'] : array();
        $suggestOffline = 0;
        $forceOffline = 0;
        $optimize = 0;
        if (isset($suggestions['suggest_offline']['total'])) {
            $suggestOffline = (int) $suggestions['suggest_offline']['total'];
        }
        if (isset($suggestions['force_offline']['total'])) {
            $forceOffline = (int) $suggestions['force_offline']['total'];
        }
        if (isset($suggestions['optimize']['total'])) {
            $optimize = (int) $suggestions['optimize']['total'];
        }

        $shopName = '';
        if (!empty($msg['info']) && preg_match('/^【(.+?)】/', $msg['info'], $m)) {
            $shopName = $m[1];
        }

        return array(
            'id'              => isset($row['id']) ? $row['id'] : 0,
            'shop_bn'         => isset($row['shop_bn']) ? $row['shop_bn'] : '',
            'shop_name'       => $shopName,
            'suggest_offline' => $suggestOffline,
            'force_offline'   => $forceOffline,
            'optimize'        => $optimize,
            'notifytime'      => isset($row['notifytime']) ? (int) $row['notifytime'] : 0,
            'info'            => isset($msg['info']) ? $msg['info'] : '',
        );
    }

    /**
     * 近 N 天历史（按日期聚合，含今天；可按店铺过滤）
     *
     * @param int $days
     * @param string $shopBn
     * @param string $shopName
     * @return array
     */
    protected function buildHistoryList($days = 7, $shopBn = '', $shopName = '')
    {
        $days = (int) $days;
        if ($days <= 0) {
            $days = 7;
        }

        // 含今天：保留窗口近 N 天（含今日）
        $end = strtotime(date('Y-m-d 23:59:59'));
        $rangeStart = strtotime(date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' day')));
        $list = $this->getSuggestionNotifyList(array($rangeStart, $end));

        $byDate = array();
        foreach ($list as $row) {
            $parsed = $this->parseNotifyRow($row);
            if (empty($parsed['notifytime'])) {
                continue;
            }
            if ($shopBn !== '' && $parsed['shop_bn'] !== $shopBn) {
                continue;
            }
            $dateKey = date('Y-m-d', $parsed['notifytime']);
            if (!isset($byDate[$dateKey])) {
                $byDate[$dateKey] = array(
                    'date'            => $dateKey,
                    'suggest_offline' => 0,
                    'optimize'        => 0,
                    'notifytime'      => $parsed['notifytime'],
                );
            }
            $byDate[$dateKey]['suggest_offline'] += $parsed['suggest_offline'];
            $byDate[$dateKey]['optimize'] += $parsed['optimize'];
            if ($parsed['notifytime'] > $byDate[$dateKey]['notifytime']) {
                $byDate[$dateKey]['notifytime'] = $parsed['notifytime'];
            }
        }

        krsort($byDate);

        $titlePrefix = $shopName !== '' ? sprintf('【%s】', $shopName) : '【抖店】';
        $history = array();
        foreach ($byDate as $item) {
            $history[] = array(
                'date'       => $item['date'],
                'title'      => sprintf('%s发品流量效率优化日报 · %s', $titlePrefix, $item['date']),
                'tag'        => ($item['date'] === date('Y-m-d')) ? '今日' : '日报',
                'notifytime' => date('Y-m-d H:i', $item['notifytime']),
                'summary'    => sprintf(
                    '建议下架 %d 件，高潜力待优化 %d 件，AI 托管未开启 0 家店铺',
                    (int) $item['suggest_offline'],
                    (int) $item['optimize']
                ),
                'suggest_offline' => (int) $item['suggest_offline'],
                'optimize'        => (int) $item['optimize'],
            );
        }

        return $history;
    }

    /**
     * 解析平台返回的商品建议总数
     * 平台成功返回示例：
     * {
     *   "data": {
     *     "total_exposure_lower_cnt": 0,
     *     "total": 0,
     *     "product_list": [],
     *     "total_exposure_upper_cnt": 1000
     *   },
     *   "err_msg": "",
     *   "msg_id": "...",
     *   "res": "",
     *   "rsp": "succ"
     * }
     * 业务侧传入的是 $result['data']，只取 total，忽略 product_list。
     *
     * @param mixed $data
     * @return int
     */
    protected function parseSuggestionTotal($data)
    {
        // 矩阵偶发把 data 以 JSON 字符串返回
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        if (!is_array($data)) {
            return 0;
        }

        // 标准结构：$result['data']['total']
        if (isset($data['total'])) {
            return (int) $data['total'];
        }

        // 容错：整包再包一层 data
        if (isset($data['data']) && is_array($data['data']) && isset($data['data']['total'])) {
            return (int) $data['data']['total'];
        }

        return 0;
    }

    /**
     * 保存商品建议通知（每个店铺合并所有建议类型为一条；当日同店铺去重）
     *
     * @param array $shopInfo
     * @param array $suggestionData 各建议类型汇总，如 force_offline/suggest_offline => [suggestion,label,total]
     * @return bool
     */
    protected function saveSuggestionNotify($shopInfo, $suggestionData)
    {
        $noticeMdl = app::get('base')->model('rpcnotify');

        $infoParts = array();
        $suggestions = array();
        foreach ($suggestionData as $suggestion => $item)
        {
            $label = isset($item['label']) ? $item['label'] : $suggestion;
            $total = isset($item['total']) ? (int) $item['total'] : 0;
            
            $infoParts[] = sprintf('%s商品共%d个', $label, $total);
            
            $suggestions[$suggestion] = array(
                'suggestion' => $suggestion,
                'label'      => $label,
                'total'      => $total,
            );
        }

        $content = array(
            'info'        => sprintf('【%s】%s', $shopInfo['name'], implode('；', $infoParts)),
            'node_id'     => $shopInfo['node_id'],
            'suggestions' => $suggestions,
        );

        // 当日去重：同一 shop_bn + msg_type=platform，先删当日记录再 insert
        $this->removeTodaySuggestionNotify($shopInfo['shop_bn']);
        
        // data
        $data = array(
            'callback'   => '',
            'rsp'        => 'succ',
            'msg'        => json_encode($content, JSON_UNESCAPED_UNICODE),
            'msg_type'   => 'platform',
            'shop_type'  => 'luban',
            'shop_bn'    => $shopInfo['shop_bn'],
            'notifytime' => time(),
            'status'     => 'false',
            'event_type' => 'product_suggestion',
            'org_id' => ($shopInfo['org_id'] ? intval($shopInfo['org_id']) : 0),
        );
        
        return (bool) $noticeMdl->insert($data);
    }

    /**
     * 删除当日同店铺的平台商品建议通知
     *
     * @param string $shopBn
     * @return void
     */
    protected function removeTodaySuggestionNotify($shopBn)
    {
        $noticeMdl = app::get('base')->model('rpcnotify');
        $dayStart = strtotime(date('Y-m-d 00:00:00'));
        $dayEnd = strtotime(date('Y-m-d 23:59:59'));

        $list = $noticeMdl->getList('id', array(
            'msg_type'           => 'platform',
            'shop_type'          => 'luban',
            'shop_bn'            => $shopBn,
            'event_type'         => 'product_suggestion',
            'notifytime|between' => array($dayStart, $dayEnd),
        ));

        if (empty($list)) {
            return;
        }

        $deleteIds = array_column($list, 'id');
        if ($deleteIds) {
            $noticeMdl->delete(array('id' => $deleteIds));
        }
    }

    /**
     * 记录拉取失败日志
     *
     * @param array $shopInfo
     * @param string $suggestion
     * @param string $errMsg
     * @return void
     */
    protected function logSuggestionError($shopInfo, $suggestion, $errMsg)
    {
        $shopBn = isset($shopInfo['shop_bn']) ? $shopInfo['shop_bn'] : '';
        $shopName = isset($shopInfo['name']) ? $shopInfo['name'] : '';
        error_log(sprintf(
            '[product_suggestion] shop_bn=%s name=%s suggestion=%s error=%s',
            $shopBn,
            $shopName,
            $suggestion,
            $errMsg
        ));
    }
}
