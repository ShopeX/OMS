<?php
/**
 * Copyright 2012-2026 ShopeX
 * Licensed under the Apache License, Version 2.0
 *
 * Created by PhpStorm.
 * User: yaokangming
 * Date: 2019/3/19
 * Time: 18:04
 */
class crm_gift_buyernick
{
    public function process($ruleBase, $sdf) {
        $db = kernel::database();
        
        // 收集买家标识：uname(pin) 与 buyer_open_uid(xid)
        $buyerAccounts = $this->getBuyerAccounts($sdf);
        
        // 会员是否已经购买过了
        if(isset($ruleBase['filter_arr']['buyer_nick']) && $ruleBase['filter_arr']['buyer_nick'] == '1' ){
            // 有 buyer_open_uid 时只按 uid 判重，避免脱敏昵称（如会员名是：w**）撞名误拦
            $identityAccounts = array();
            if (!empty($sdf['buyer_open_uid'])) {
                // 优先使用buyer_open_uid
                $identityAccounts[] = trim((string) $sdf['buyer_open_uid']);
            } elseif (!empty($sdf['buyer_nick'])) {
                // 会员昵称（加密后的，会有重复的会员名）
                $identityAccounts[] = trim((string) $sdf['buyer_nick']);
            }
            
            if ($identityAccounts) {
                $quoted = array();
                foreach ($identityAccounts as $account) {
                    $quoted[] = $db->quote($account);
                }
                
                // 查询会员已经送出的订单数
                $sql = "select count(distinct order_bn) as total_orders from sdb_ome_gift_logs ";
                $sql .= " WHERE gift_rule_id =" . intval($ruleBase['rule_id']) . " AND buyer_account in (" . implode(',', $quoted) . ")";
                $_rs = $db->selectRow($sql);
                if($_rs['total_orders'] > 0) {
                    return array(false, '每个ID只有第一次购买赠送!');
                }
            }
            
            // 再按 member_id 检查是否已赠送
            $memberId = isset($sdf['member_id']) ? intval($sdf['member_id']) : 0;
            if ($memberId > 0) {
                $sql = "select count(distinct order_bn) as total_orders from sdb_ome_gift_logs ";
                $sql .= " WHERE gift_rule_id =" . intval($ruleBase['rule_id']) . " AND member_id =" . $memberId;
                $_rs = $db->selectRow($sql);
                if ($_rs['total_orders'] > 0) {
                    return array(false, '会员ID只允许第一次购买赠送!');
                }
            }
        }
        
        // 指定会员才能购买
        if (isset($ruleBase['filter_arr']['member_uname']) && !empty($ruleBase['filter_arr']['member_uname'])) {
            $memberList = is_array($ruleBase['filter_arr']['member_uname']) ? $ruleBase['filter_arr']['member_uname'] : explode(',', $ruleBase['filter_arr']['member_uname']);
            $memberList = array_map('trim', $memberList);
            
            // check
            $matched = false;
            foreach ($buyerAccounts as $account) {
                if (in_array($account, $memberList)) {
                    $matched = true;
                    break;
                }
            }
            
            if (!$matched) {
                return array(false, '不符合指定会员!');
            }
        }
        
        return array(true);
    }

    /**
     * 收集买家标识：uname(pin) 与 buyer_open_uid(xid)
     * @param array $sdf
     * @return array
     */
    private function getBuyerAccounts($sdf)
    {
        $accounts = array();
        
        // 会员账号（用户名）
        if (!empty($sdf['buyer_nick'])) {
            $accounts[] = trim((string) $sdf['buyer_nick']);
        }
        
        // buyer_open_uid
        if (!empty($sdf['buyer_open_uid'])) {
            $accounts[] = trim((string) $sdf['buyer_open_uid']);
        }
        
        return array_values(array_unique(array_filter($accounts)));
    }
}
