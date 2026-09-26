<?php
/**
 * 唯一码扫码预占（发货出库）
 * Key: mc_sn_hold_{sn}，TTL 默认 300 秒（5 分钟）
 */
class machinecenter_sn_hold
{
    const DEFAULT_TTL = 300;
    const HOLD_PREFIX = 'mc_sn_hold_';
    const BILL_PREFIX = 'mc_sn_hold_bill_';

    public function getTtl()
    {
        $ttl = app::get('machinecenter')->getConf('sn_hold_ttl');
        return $ttl > 0 ? intval($ttl) : self::DEFAULT_TTL;
    }

    /**
     * 预占 SN
     *
     * @param string $sn
     * @param array  $holder type, bill_id, bill_bn, branch_id, operator_id
     * @param string $err_msg
     * @return bool
     */
    public function acquire($sn, array $holder, &$err_msg = '')
    {
        $sn = trim($sn);
        if ($sn === '') {
            $err_msg = '唯一码不能为空';
            return false;
        }
        if (empty($holder['type']) || empty($holder['bill_id'])) {
            $err_msg = '预占参数不完整';
            return false;
        }

        $holder['hold_at'] = time();
        $key               = $this->_holdKey($sn);
        $encoded           = json_encode($holder);
        $ttl               = $this->getTtl();
        $existing          = cachecore::fetch($key);

        if ($existing !== false) {
            $existHolder = json_decode($existing, true);
            if ($this->_isSameHolder($existHolder, $holder)) {
                cachecore::store($key, $encoded, $ttl);
                $this->_addSnToBillIndex($holder, $sn);
                return true;
            }
            $err_msg = '唯一码已被其他单据占用，请稍后再试';
            return false;
        }

        cachecore::store($key, $encoded, $ttl);
        $check = cachecore::fetch($key);
        if ($check === false) {
            $err_msg = '唯一码预占失败，请重试';
            return false;
        }
        $checkHolder = json_decode($check, true);
        if (!$this->_isSameHolder($checkHolder, $holder)) {
            $err_msg = '唯一码已被其他单据占用，请稍后再试';
            return false;
        }

        $this->_addSnToBillIndex($holder, $sn);
        return true;
    }

    /**
     * 检查是否被其他单据占用
     */
    public function isHeldByOther($sn, array $holder)
    {
        $existing = cachecore::fetch($this->_holdKey($sn));
        if ($existing === false) {
            return false;
        }
        $existHolder = json_decode($existing, true);
        return !$this->_isSameHolder($existHolder, $holder);
    }

    /**
     * 释放单个 SN 预占
     */
    public function release($sn, array $holder = null)
    {
        $sn  = trim($sn);
        $key = $this->_holdKey($sn);
        if ($holder !== null) {
            $existing = cachecore::fetch($key);
            if ($existing !== false) {
                $existHolder = json_decode($existing, true);
                if (!$this->_isSameHolder($existHolder, $holder)) {
                    return false;
                }
            }
        }
        cachecore::delete($key);
        if ($holder !== null) {
            $this->_removeSnFromBillIndex($holder, $sn);
        }
        return true;
    }

    /**
     * 按单据释放全部预占
     */
    public function releaseByBill($type, $billId)
    {
        $billKey = $this->_billKey($type, $billId);
        $sns     = cachecore::fetch($billKey);
        if ($sns !== false) {
            $snList = json_decode($sns, true);
            if (is_array($snList)) {
                foreach ($snList as $sn) {
                    cachecore::delete($this->_holdKey($sn));
                }
            }
        }
        cachecore::delete($billKey);
        return true;
    }

    private function _holdKey($sn)
    {
        return self::HOLD_PREFIX . $sn;
    }

    private function _billKey($type, $billId)
    {
        return self::BILL_PREFIX . $type . '_' . intval($billId);
    }

    private function _isSameHolder($a, $b)
    {
        if (!is_array($a) || !is_array($b)) {
            return false;
        }
        return strval($a['type'] ?? '') === strval($b['type'] ?? '')
            && strval($a['bill_id'] ?? '') === strval($b['bill_id'] ?? '');
    }

    private function _addSnToBillIndex(array $holder, $sn)
    {
        $billKey = $this->_billKey($holder['type'], $holder['bill_id']);
        $sns     = cachecore::fetch($billKey);
        $snList  = $sns !== false ? json_decode($sns, true) : [];
        if (!is_array($snList)) {
            $snList = [];
        }
        if (!in_array($sn, $snList, true)) {
            $snList[] = $sn;
        }
        cachecore::store($billKey, json_encode($snList), $this->getTtl());
    }

    private function _removeSnFromBillIndex(array $holder, $sn)
    {
        $billKey = $this->_billKey($holder['type'], $holder['bill_id']);
        $sns     = cachecore::fetch($billKey);
        if ($sns === false) {
            return;
        }
        $snList = json_decode($sns, true);
        if (!is_array($snList)) {
            return;
        }
        $snList = array_values(array_filter($snList, function ($item) use ($sn) {
            return $item !== $sn;
        }));
        if (empty($snList)) {
            cachecore::delete($billKey);
        } else {
            cachecore::store($billKey, json_encode($snList), $this->getTtl());
        }
    }
}
