<?php
/**
 * 拼多多买家拒用快递（cn_info.express_memos）解析与审单过滤
 *
 * 从平台推送的 express_memos 中解析买家拒用快递关键字，映射为 dly_corp.type，
 * 并在自动/手工审单候选物流上按黑名单过滤；过滤后剩余 ≤1 时不过滤以保证可发货。
 */
class ome_order_platform_pinduoduo_expressmemos
{
    /**
     * 拒用关键字 => OMS 物流公司 type 编码（可多值）
     *
     * @var array
     */
    private $keywordTypeMap = [
        '顺丰' => ['SF'],
        '中通' => ['ZTO'],
        '申通' => ['STO'],
        '韵达' => ['YUNDA'],
        '德邦' => ['DBL'],
        '极兔' => ['JITU', 'jitu', 'JTSD'],
        '邮政' => ['POST', 'POSTB'],
        'EMS'  => ['EMS'],
    ];

    /**
     * 从 cn_info 解析买家拒用快递编码与命中 tag
     *
     * @param mixed $cnInfo
     * @return array{types: string[], tags: string[]}
     */
    public function parseFromCnInfo($cnInfo)
    {
        $result = ['types' => [], 'tags' => []];
        if (is_string($cnInfo)) {
            $cnInfo = json_decode($cnInfo, true);
        }
        
        if (!is_array($cnInfo) || empty($cnInfo['express_memos'])) {
            return $result;
        }
        
        $memos = $cnInfo['express_memos'];
        
        // [兼容]平台给的数据是一维数组
        if (isset($memos['scene']) || isset($memos['tag'])) {
            $memos = [$memos];
        }
        
        $types = [];
        $tags = [];
        foreach ($memos as $memo)
        {
            if (!is_array($memo)) {
                continue;
            }
            
            $scene = isset($memo['scene']) ? (string)$memo['scene'] : '';
            $tag = isset($memo['tag']) ? trim((string)$memo['tag']) : '';
            
            // check
            if ($scene !== '1' || $tag === '') {
                continue;
            }
            
            // 在 tag 文本中匹配拒用关键字并返回 type 列表
            $matched = $this->matchKeywordTypes($tag);
            if (!$matched) {
                continue;
            }
            
            $tags[] = $tag;
            foreach ($matched as $type) {
                $types[$type] = $type;
            }
        }
        
        $result['types'] = array_values($types);
        $result['tags'] = array_values(array_unique($tags));
        
        return $result;
    }

    /**
     * 按黑名单过滤候选物流；过滤后剩余 ≤1 则保留原列表
     *
     * @param array $corps 候选物流列表（元素含 type 字段）
     * @param array $blackTypes 黑名单 type 编码
     * @return array
     */
    public function filterCorps(array $corps, array $blackTypes)
    {
        if (!$corps || !$blackTypes) {
            return $corps;
        }

        $blackMap = [];
        foreach ($blackTypes as $type) {
            if ($type === '' || $type === null) {
                continue;
            }
            $blackMap[(string)$type] = true;
        }
        if (!$blackMap) {
            return $corps;
        }

        $filtered = [];
        foreach ($corps as $key => $corp) {
            $type = isset($corp['type']) ? (string)$corp['type'] : '';
            if ($type !== '' && isset($blackMap[$type])) {
                continue;
            }
            $filtered[$key] = $corp;
        }

        if (count($filtered) <= 1) {
            return $corps;
        }

        return array_values($filtered);
    }

    /**
     * 读取订单快递黑名单 type 列表
     *
     * @param int $orderId
     * @return array
     */
    public function loadBlackTypes($orderId)
    {
        if (!$orderId) {
            return [];
        }

        $extend = app::get('ome')->model('order_extend')->db_dump(array('order_id'=>$orderId), 'black_delivery_cps');
        if (empty($extend['black_delivery_cps'])) {
            return [];
        }

        $blackTypes = json_decode($extend['black_delivery_cps'], true);
        if (!is_array($blackTypes)) {
            return [];
        }

        $result = [];
        foreach ($blackTypes as $type)
        {
            if ($type === '' || $type === null) {
                continue;
            }
            
            $type = (string)$type;
            $result[$type] = $type;
        }
        
        return array_values($result);
    }

    /**
     * 合并多个订单的黑名单
     *
     * @param array $orderIds
     * @return array
     */
    public function loadBlackTypesByOrderIds(array $orderIds)
    {
        $result = [];
        foreach ($orderIds as $orderId) {
            foreach ($this->loadBlackTypes($orderId) as $type) {
                $result[$type] = $type;
            }
        }
        return array_values($result);
    }

    /**
     * 在 tag 文本中匹配拒用关键字并返回 type 列表
     *
     * @param string $tag
     * @return array
     */
    private function matchKeywordTypes($tag)
    {
        $types = [];
        foreach ($this->keywordTypeMap as $keyword => $mappedTypes) {
            if (mb_strpos($tag, $keyword) === false) {
                continue;
            }
            
            foreach ($mappedTypes as $type) {
                $types[$type] = $type;
            }
        }
        
        return array_values($types);
    }
}
