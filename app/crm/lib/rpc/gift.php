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
class crm_rpc_gift extends crm_rpc_request
{

    //获取crm赠品规则
    /*
    shop_id        店铺ID
    order_bn       订单号
    buyer_nick     买家昵称
    receiver_name  收货人姓名
    mobile         联系电话（手机）
     */
    public function getGiftRule($data)
    {
        $res = array('result' => 'fail', 'data' => array(), 'msg' => '获取失败');

        $api_name = 'store.gift.rule.get';

        $Oshop    = app::get('ome')->model('shop');
        $shopdata = $Oshop->getRow(array('shop_id' => $data['shop_id'], 's_type' => 1), 'node_id,name');

//       $params = array(
        //           'buyer_nick'=>$data['buyer_nick'],
        //           'receiver_name'=>$data['receiver_name'],
        //           'mobile'=>$data['mobile'],
        //           'unique_node'=>$shopdata['node_id'],
        //       );

        $params = array(
            'buyer_nick'    => $data['buyer_nick'],
            'receiver_name' => $data['receiver_name'],
            'mobile'        => $data['mobile'],
            'tel'           => $data['tel'],
            'shop_id'       => $data['shop_id'],
            'unique_node'   => $shopdata['node_id'],
            'shop_name'     => $shopdata['name'],
            'order_bn'      => $data['order_bn'],
            'province'      => $data['province'],
            'city'          => $data['city'],
            'district'      => $data['district'],
            'total_amount'  => $data['total_amount'],
            'payed'         => $data['payed'],
            'createtime'    => $data['createtime'],
            'pay_time'      => $data['pay_time'],
            'is_cod'        => $data['is_cod'],
            'items'         => is_array($data['items']) ? json_encode($data['items']) : $data['items'],
            'addon'         => '',
            'is_send_gift'  => $data['is_send_gift'], #强制重新请求的标示
        );
        $write_log = array(
            'class'    => __CLASS__,
            'method'   => __METHOD__,
            'order_bn' => $data['order_bn'],
        );

        $result = $this->call('store.gift.rule.get', '获取CRM赠品规则', $params, 10, $write_log);

        return $result;
    }

    #发送CRM赠品日志
    public function getGiftRuleLog($data)
    {
        $res      = array('result' => 'fail', 'data' => array(), 'msg' => '发送失败');
        $api_name = 'store.gift.rule.get';
        $Oshop    = app::get('ome')->model('shop');
        $shopdata = $Oshop->getRow(array('shop_id' => $data['shop_id'], 's_type' => 1), 'node_id,name');
        $addon    = array(
            'func'   => 'log',
            'params' => $data['addon'],
        );
        $params = array(
            'buyer_nick'    => $data['buyer_nick'],
            'receiver_name' => $data['receiver_name'],
            'mobile'        => $data['mobile'],
            'tel'           => $data['tel'],
            'shop_id'       => $data['shop_id'],
            'unique_node'   => $shopdata['node_id'],
            'shop_name'     => $shopdata['name'],
            'order_bn'      => $data['order_bn'],
            'province'      => $data['province'],
            'city'          => $data['city'],
            'district'      => $data['district'],
            'total_amount'  => $data['total_amount'],
            'payed'         => $data['payed'],
            'is_cod'        => $data['is_cod'],
            'items'         => '',
            'addon'         => json_encode($addon),
        );
        $write_log = array(
            'class'    => __CLASS__,
            'method'   => __METHOD__,
            'order_bn' => $data['order_bn'],
        );
        $result = $this->call('store.gift.rule.get', '发送CRM赠品日志', $params, 10, $write_log);
        return $result;
    }

    /**
     * 返回数据格式化
     * @param $data
     * @return array
     */
    public function getFormatRst($data)
    {
        $res = array('result' => 'fail', 'msg' => '', 'data' => array());

        $rst = $this->getLocalGiftRule($data);

        if (isset($rst['err'])) {
            $res['msg'] = $rst['msg'];
            return $res;
        } else {
            $res['result'] = 'succ';
            $res['msg']    = $rst['msg'];
            $res['data']   = $rst;
            return $res;
        }
    }

    /**
     * 格式化参数 (废弃)
     */
    private function formatParams($data)
    {

        $Oshop    = app::get('ome')->model('shop');
        $shopdata = $Oshop->getRow(array('shop_id' => $data['shop_id'], 's_type' => 1), 'node_id,name');

        $params = array(
            'buyer_nick'    => $data['buyer_nick'],
            'receiver_name' => $data['receiver_name'],
            'mobile'        => $data['mobile'],
            'tel'           => $data['tel'],
            'shop_id'       => $data['shop_id'],
            'unique_node'   => $shopdata['node_id'],
            'shop_name'     => $shopdata['name'],
            'order_bn'      => $data['order_bn'],
            'province'      => $data['province'],
            'city'          => $data['city'],
            'district'      => $data['district'],
            'total_amount'  => $data['total_amount'],
            'payed'         => $data['payed'],
            'createtime'    => $data['createtime'],
            'pay_time'      => $data['pay_time'],
            'is_cod'        => $data['is_cod'],
            'items'         => is_array($data['items']) ? json_encode($data['items']) : $data['items'],
            'addon'         => '',
            'is_send_gift'  => $data['is_send_gift'], #强制重新请求的标示
        );

        return $params;
    }

    /**
     * 获取erp的赠品规则
     * @param $data
     * @return array
     */
    public function getLocalGiftRule($sdf)
    {
        //获取前端店铺名和node_id
        $Oshop              = app::get('ome')->model('shop');
        $shopdata           = $Oshop->getRow(array('shop_id' => $sdf['shop_id'], 's_type' => 1), 'node_id,name');
        $sdf['unique_node'] = $shopdata['node_id'];
        $sdf['shop_name']   = $shopdata['name'];

        //匹配规则前先判断是否有这三种错误 直接返回

        if (!$shopdata) {
            return array('err' => 1, 'msg' => '门店订单不参与赠品活动');
        }
        /*if (intval($sdf['is_cod']) == 1 || $sdf['pay_status'] != '1') {
            return array('err' => 1, 'msg' => '不处理货到付款和未付款订单');
        }*/
        if (!$sdf['buyer_nick'] && !$sdf['receiver_name']) {
            return array('err' => 1, 'msg' => '买家帐号或收货人不能同时为空');
        }
        //这里is_send_gift都是0 process时$type默认给false
        if (intval($sdf['is_send_gift']) == 0) {
            //查询赠品日志，已经送过的订单号不送第二次
            $rs = app::get('ome')->model('gift_logs')->dump(array('order_bn' => $sdf['order_bn'], 'shop_id' => $sdf['shop_id']), 'id');
            if ($rs) {
                return array('err' => 1, 'msg' => '订单号' . $sdf['order_bn'] . '不能重复赠送');
            }
        }

        $rs = kernel::single('crm_gift')->getGiftRule($sdf);
        return $rs;
        //规则重新开发后续不跑
        //按优先级和主键 查询是否存在有效规则 新的能用的规则rule_type不为空 设置过类型是店铺级或者商品级的
        $sql  = "select * from sdb_ome_gift_rule where status = '1' and rule_type is not null order by priority DESC,id DESC";
        $data = $Oshop->db->select($sql);

        if (!empty($data)) {
            //同时获取店铺级和商品级的数组
            $rule_shop_level = array();
            $rule_good_level = array();
            foreach ($data as $var_data) {
                if ($var_data["rule_type"] == "shopLevel") {
                    $rule_shop_level[] = $var_data;
                }
                if ($var_data["rule_type"] == "goodLevel") {
                    $rule_good_level[] = $var_data;
                }
            }
            $gift_send_log     = array(); //记录赠品发送日志 也用来用rule维度处理赠品数量是否
            $last_exclude_flag = ""; //是否排他 1排他 2不排他
            $rl_gift_id_num    = array();
            if (!empty($rule_shop_level)) {
                //先走店铺级规则 再走商品级规则
                $rs_gift_info = $this->getGiftInfo($rule_shop_level, $sdf, $gift_send_log, $rl_gift_id_num, $last_exclude_flag);
                if ($last_exclude_flag == 1 && count($rs_gift_info["gift_send_log"]) == 1) {
                    //按优先级和主键id来匹配 ：如果第一条店铺规则满足且排他,则忽略后续店铺级和商品级规则。
                } else {
                    //其他情况下开始匹配商品规则（这里可能是没匹配到店铺规则 或者 匹配店铺规则了且此店铺规则是不排他的）
                    if (!empty($rule_good_level)) {
                        $rs_gift_info = $this->getGiftInfo($rule_good_level, $sdf, $rs_gift_info["gift_send_log"], $rs_gift_info["rl_gift_id_num"], $last_exclude_flag);
                    }
                }
            } else {
                //没有店铺级规则 直接走商品级规则
                if (!empty($rule_good_level)) {
                    $rs_gift_info = $this->getGiftInfo($rule_good_level, $sdf, $gift_send_log, $rl_gift_id_num, $last_exclude_flag);
                }
            }

            //赠品获取失败 直接返回
            if (empty($rs_gift_info["gift_send_log"])) {
                $msg = "未匹配到赠品规则，获取赠品失败。";
                if ($rs_gift_info["reason"]) {
                    $msg = $rs_gift_info["reason"];
                }
                return array(
                    "err" => "未匹配到赠品",
                    "msg" => $msg,
                );
            }

            //有效赠品数据合并 $gift_id_num是关系数组 $gift_id_arr所有gift_id数组
            $gift_id_num = array();
            $gift_id_arr = array();
            foreach ($rs_gift_info["gift_send_log"] as $var_g_i) {
                foreach ($var_g_i["gift_ids"] as $k_inner => $v_inner) {
                    $gift_id_num[$v_inner] += intval($var_g_i["gift_num"][$k_inner]);
                    if (!in_array($v_inner, $gift_id_arr)) {
                        $gift_id_arr[] = $v_inner;
                    }
                }
            }

            //符合条件赠品数据处理
            $rs_gift  = app::get('crm')->model('gift')->getList('gift_id,gift_bn,gift_name,gift_num,giftset,product_id', array('gift_id' => $gift_id_arr));
            $gift_bns = array();
            $gifts    = array();
            foreach ($rs_gift as $var_gift) {
                $gift_num = $gift_id_num[$var_gift['gift_id']];
                $sqlstr   = '';
                if ($var_gift['giftset'] == '0') {
                    $sqlstr = ',gift_num=gift_num-' . $gift_num;
                }
                $rs_gifts[$var_gift['gift_id']] = $var_gift;
                $gift_bns[]                     = $var_gift['gift_bn'];
                $gifts[$var_gift['gift_bn']]    = $gift_num;
                //更新已发出数量 按指定数量的需要扣减库存
                $sql_update = "update sdb_crm_gift set send_num=send_num+" . $gift_num . $sqlstr . " where gift_id=" . $var_gift['gift_id'];
                $Oshop->db->exec($sql_update);
            }

            //记录赠品发送日志
            $create_time = time();
            $log_arr     = array();
            foreach ($rs_gift_info["gift_send_log"] as $v) {
                foreach ($v['gift_ids'] as $kk => $vv) {
                    $md5_key   = md5($sdf['order_bn'] . $rs_gifts[$vv]['gift_bn'] . $v['gift_rule_id'] . $create_time);
                    $log_arr[] = array(
                        'order_source'  => $sdf['shop_name'],
                        'order_bn'      => $sdf['order_bn'],
                        'buyer_account' => !empty($sdf['buyer_nick']) ? $sdf['buyer_nick'] : $sdf['buyer_open_uid'],
                        'shop_id'       => $sdf['shop_id'],
                        'paid_amount'   => floatval($sdf['payed']),
                        'gift_num'      => $v['gift_num'][$kk],
                        'gift_rule_id'  => $v['gift_rule_id'],
                        'gift_bn'       => $rs_gifts[$vv]['gift_bn'],
                        'gift_name'     => $rs_gifts[$vv]['gift_name'],
                        'create_time'   => $create_time,
                        'md5_key'       => $md5_key,
                        'status'        => 0,
                    );
                }
            }

            //返回有效的赠品数据
            $return = array(
                'order_bn' => $sdf['order_bn'],
                'gifts'    => $gifts,
                'gift_bn'  => implode(',', $gift_bns),
                'source'   => 'local',
                'log_arr'  => $log_arr,
            );

            return $return;

        } else {
            //没有有效的赠品规则
            return array('msg' => '不存在有效的赠品规则，赠品获取失败');
        }

    }

    /*
     * 检测是否符合赠送条件 店铺级规则和商品级规则共用
     * $rules 当前规则的集合
     * $sdf 用到的其他相关数据
     * $gift_send_log 匹配到的数据集合
     * $rl_gift_id_num 累计赠品与数量的数据集合 用于后续赠品数量库存判断用
     * $last_exclude_flag 上个匹配到的规则是否排他 1排他 2不排他
     */
    private function getGiftInfo($rules, $sdf, $gift_send_log, $rl_gift_id_num, &$last_exclude_flag)
    {
        $Oshop = app::get('ome')->model('shop');
        $db = kernel::database();
        
        //循环匹配规则
        $error_msg_giftset = "";
        foreach ($rules as $rule) {
            if (!empty($last_exclude_flag)) {
                //已有满足的规则
                if ($last_exclude_flag == 1) {
                    //上个满足的规则是排他的 忽略后续任何规则
                    break;
                } else {
                    //上个满足的规则不排他 当前规则排他的话直接跳过
                    if ($rule['is_exclude'] == 1) {
                        continue;
                    }
                }
            }
            //检测时间有效期
            if ($rule['time_type'] == 'createtime') {
                $createtime = intval($sdf['createtime']);
                if ($createtime > $rule['end_time'] or $createtime < $rule['start_time']) {
                    $reason = '不符合选择的时间类型：订单创建时间的起止时间范围';
                    continue;
                }
            } elseif ($rule['time_type'] == 'pay_time') {
                $pay_time = intval($sdf['pay_time']);
                if ($pay_time > $rule['end_time'] or $pay_time < $rule['start_time']) {
                    $reason = '不符合选择的时间类型：订单付款时间的起止时间范围';
                    continue;
                }
            } else {
                $time = time();
                if ($time > $rule['end_time'] or $time < $rule['start_time']) {
                    $reason = '不符合选择的时间类型：订单处理时间的起止时间范围';
                    continue;
                }
            }

            //赠品判断条件
            $rule['filter_arr'] = json_decode($rule['filter_arr'], true);

            //勾选的前端店铺 不勾选就是全选
            if ($rule['shop_ids']) {
                $rule['shop_ids'] = explode(',', $rule['shop_ids']);
            }

            if (!$rule['gift_ids']) {
                $reason = '没有设定赠品';
                continue;
            } elseif ($rule['shop_ids'] && !in_array($sdf['shop_id'], $rule['shop_ids'])) {
                $reason = '不符合指定店铺';
                continue;
            }

            if ($rule['filter_arr']['province']) {
                if (!$sdf['province'] or !in_array($sdf['province'], $rule['filter_arr']['province'])) {
                    $reason = '不符合指定收货区域';
                    continue;
                }
            }

            //订单类型过滤
            if ($rule['filter_arr']['order_type']) {
                $normal_selected  = false;
                $presale_selected = false;
                foreach ($rule['filter_arr']['order_type'] as $order_t) {
                    if ($order_t == "normal") {
                        $normal_selected = true;
                    }
                    if ($order_t == "presale") {
                        $presale_selected = true;
                    }
                }
                //$rule['filter_arr']['order_type']普通订单normal和预售订单presale都选的情况下认为是全部订单 不考虑此条件
                if ($normal_selected && $presale_selected) {
                } else {
                    if (!in_array($sdf['order_type'], $rule['filter_arr']['order_type'])) {
                        $reason = '不符合指定订单类型';
                        continue;
                    }
                }
            }

            if ($rule['filter_arr']['order_amount']['type'] == 1) {
                $payed = floatval($sdf['payed']);
                if ($rule['filter_arr']['order_amount']['sign'] == 'bthan') {
                    if ($payed < $rule['filter_arr']['order_amount']['max_num']) {
                        $reason = '不满足最低付款';
                        continue;
                    }
                } else {
                    if ($payed < $rule['filter_arr']['order_amount']['min_num'] or $payed > $rule['filter_arr']['order_amount']['max_num']) {
                        $reason = '不满足付款区间';
                        continue;
                    }
                }
            }

            //指定会员（兼容 pin/uname 与 xid/buyer_open_uid）
            if (isset($rule['filter_arr']['member_uname']) && $rule['filter_arr']['member_uname']) {
//                // 这是原来的逻辑
//                if (!$sdf['buyer_nick'] or !in_array(trim($sdf['buyer_nick']), $rule['filter_arr']['member_uname'])) {
//                    $reason = '不符合指定会员';
//                    continue;
//                }
                
                // 指定会员列表
                $memberList = is_array($rule['filter_arr']['member_uname']) ? $rule['filter_arr']['member_uname'] : explode(',', $rule['filter_arr']['member_uname']);
                $memberList = array_map('trim', $memberList);
                $buyerAccounts = array();
                
                // 会员账号（用户名）
                if (!empty($sdf['buyer_nick'])) {
                    $buyerAccounts[] = trim($sdf['buyer_nick']);
                }
                
                // buyer_open_uid（京东xid）
                if (!empty($sdf['buyer_open_uid'])) {
                    $buyerAccounts[] = trim($sdf['buyer_open_uid']);
                }
                
                // check
                $matched = false;
                foreach ($buyerAccounts as $account) {
                    if (in_array($account, $memberList)) {
                        $matched = true;
                        break;
                    }
                }
                
                if (!$matched) {
                    $reason = '不符合指定会员';
                    continue;
                }
            }

            //每ID第一次购买赠送（兼容 gift_logs.buyer_account 为 pin 或 xid）
            if (isset($rule['filter_arr']['buyer_nick']) && $rule['filter_arr']['buyer_nick'] == '1') {
//                // 原来的逻辑
//                //查询会员已经送出的订单数,针对当前规则
//                $sql = "select count(distinct order_bn) as total_orders from sdb_ome_gift_logs where gift_rule_id =" . $rule['id'] . " and buyer_account='" . $sdf['buyer_nick'] . "'";
//                $rs  = kernel::database()->selectRow($sql);
//                if ($rs['total_orders'] > 0) {
//                    continue;
//                }
                
                $buyerAccounts = array();
                
                // 会员账号（用户名）
                if (!empty($sdf['buyer_nick'])) {
                    $buyerAccounts[] = trim($sdf['buyer_nick']);
                }
                
                if (!empty($sdf['buyer_open_uid'])) {
                    $buyerAccounts[] = trim($sdf['buyer_open_uid']);
                }
                
                $buyerAccounts = array_values(array_unique(array_filter($buyerAccounts)));
                if ($buyerAccounts) {
                    $quoted = array();
                    foreach ($buyerAccounts as $account) {
                        $quoted[] = $db->quote($account);
                    }
                    
                    // 查询会员已经送出的订单数,针对当前规则
                    $sql = "select count(distinct order_bn) as total_orders from sdb_ome_gift_logs ";
                    $sql .= " WHERE gift_rule_id =". intval($rule['id']) . " AND buyer_account IN (" . implode(',', $quoted) . ")";
                    $rs  = $db->selectRow($sql);
                    if ($rs['total_orders'] > 0) {
                        continue;
                    }
                }
            }

            //限量赠送
            if ($rule['filter_arr']['buy_goods']['limit_type'] == 1) {
                //判断已经送出的订单数
                $sql     = "select count(distinct order_bn) as total_orders from sdb_ome_gift_logs where gift_rule_id=" . $rule['id'] . " ";
                $rs_temp = $Oshop->db->selectRow($sql);
                if ($rs_temp) {
                    if ($rs_temp['total_orders'] >= $rule['filter_arr']['buy_goods']['limit_orders']) {
                        $reason = '超过送出数量限制';
                        continue;
                    }
                }
            }

            $has_buy = false;
            //$item_nums = $this->get_buy_goods_num($rule, $sdf['items'], $has_buy);
            $item_nums = $this->get_buy_goods_num($rule, $sdf['objects'], $has_buy);
            if ($rule['filter_arr']['buy_goods']['count_type'] == 'paid') {
                $item_nums = floatval($sdf['payed']);
            }

            if ($has_buy == false) {
                $reason = '不符合指定商品购买条件';
                continue;
            }

            //计算赠品数量
            if ($item_nums > 0 && $rule['filter_arr']['buy_goods']['num_rule'] == 'auto') {
                $ratio = intval($item_nums / $rule['filter_arr']['buy_goods']['per_num']);
                $suite = $rule['filter_arr']['buy_goods']['send_suite'] * $ratio;
                $suite = min($suite, $rule['filter_arr']['buy_goods']['max_send_suite']);
                if ($suite >= 1) {
                    //数量倍数
                    $temp_arr = explode(',', $rule['gift_num']);
                    foreach ($temp_arr as $k => $v) {
                        $temp_arr[$k] = $v * $suite;
                    }
                    $temp_arr = implode(',', $temp_arr);
                } elseif ($suite == 0) {
                    //数量不符合要求
                    $reason = '不符合商品数量购买条件';
                    continue;
                }
                //赠送方式
                if ($rule["filter_arr"]["handling"]["type"] == 2) {
                    //选择 “选择” 在库存不足的情况下会多次匹配
                    $handling_content_arr = $this->get_handling_content_arr($rule['gift_ids'], $temp_arr, $rule["filter_arr"]["handling"]["content"]);
                    if (empty($handling_content_arr)) {continue;}
                    foreach ($handling_content_arr as $var_hca) {
                        $check_rs = $this->check_giftset_by_rule($var_hca['gift_ids'], $var_hca['gift_num'], $rl_gift_id_num, $error_msg_giftset);
                        if ($check_rs) {
                            //成功送出一组 跳出当前规则 继续匹配下一条规则
                            $last_exclude_flag = $rule["is_exclude"]; //是否排他 1排他 2不排他
                            $gift_send_log[]   = $this->format_gift_send_log($rule['id'], $var_hca['gift_ids'], $var_hca['gift_num']);
                            break;
                        } else {
                            //错误信息覆盖取最新的返回
                            $reason = $error_msg_giftset;
                        }
                    }
                } else {
                    //默认 “全部” 只需匹配一次表格中勾选的赠品
                    $check_rs = $this->check_giftset_by_rule($rule['gift_ids'], $temp_arr, $rl_gift_id_num, $error_msg_giftset);
                    if ($check_rs) {
                        //数量足 可以完成此规则赠送
                        $last_exclude_flag = $rule["is_exclude"]; //是否排他 1排他 2不排他
                        $gift_send_log[]   = $this->format_gift_send_log($rule['id'], $rule['gift_ids'], $temp_arr);
                    } else {
                        //数量不足 无法完成此规则赠送
                        $reason = $error_msg_giftset;
                        continue;
                    }
                }
            } elseif ($item_nums > 0) {
                if ($rule['filter_arr']['buy_goods']['rules_sign'] == 'nequal') {
                    if ($item_nums != $rule['filter_arr']['buy_goods']['min_num']) {
                        $reason = '不等于指定数量';
                        continue;
                    }
                } elseif ($rule['filter_arr']['buy_goods']['rules_sign'] == 'between') {
                    if ($item_nums < $rule['filter_arr']['buy_goods']['min_num'] or $item_nums >= $rule['filter_arr']['buy_goods']['max_num']) {
                        $reason = '不在数量范围内';
                        continue;
                    }
                } else {
                    if ($item_nums < $rule['filter_arr']['buy_goods']['min_num']) {
                        $reason = '小于指定数量';
                        continue;
                    }
                }
                //赠送方式
                if ($rule["filter_arr"]["handling"]["type"] == 2) {
                    //选择 “选择” 在库存不足的情况下会多次匹配
                    $handling_content_arr = $this->get_handling_content_arr($rule['gift_ids'], $rule['gift_num'], $rule["filter_arr"]["handling"]["content"]);
                    if (empty($handling_content_arr)) {continue;}
                    foreach ($handling_content_arr as $var_hca) {
                        $check_rs = $this->check_giftset_by_rule($var_hca['gift_ids'], $var_hca['gift_num'], $rl_gift_id_num, $error_msg_giftset);
                        if ($check_rs) {
                            //成功送出一组 跳出当前规则 继续匹配下一条规则
                            $last_exclude_flag = $rule["is_exclude"]; //是否排他 1排他 2不排他
                            $gift_send_log[]   = $this->format_gift_send_log($rule['id'], $var_hca['gift_ids'], $var_hca['gift_num']);
                            break;
                        } else {
                            //错误信息覆盖取最新的返回
                            $reason = $error_msg_giftset;
                        }
                    }
                } else {
                    //默认 “全部” 只需匹配一次表格中勾选的赠品
                    $check_rs = $this->check_giftset_by_rule($rule['gift_ids'], $rule['gift_num'], $rl_gift_id_num, $error_msg_giftset);
                    if ($check_rs) {
                        //数量足 可以完成此规则赠送
                        $last_exclude_flag = $rule["is_exclude"]; //是否排他 1排他 2不排他
                        $gift_send_log[]   = $this->format_gift_send_log($rule['id'], $rule['gift_ids'], $rule['gift_num']);
                    } else {
                        //数量不足 无法完成此规则赠送
                        $reason = $error_msg_giftset;
                        continue;
                    }
                }
            }
        }

        return array(
            "gift_send_log"  => $gift_send_log,
            "rl_gift_id_num" => $rl_gift_id_num,
            "reason"         => $reason,
        );

    }

    /*
     * 获取赠送方式是“选择”类型的匹配数组
     * $gift_ids 全部表格中勾选的赠品
     * $gift_num 对应$gift_ids的赠送数量
     * $handling_content 输入框中填写的内容  “|”表示“或”；“#”表示“和”
     */
    private function get_handling_content_arr($gift_ids, $gift_num, $handling_content)
    {
        $gift_ids_arr = explode(",", $gift_ids);
        $gift_num_arr = explode(",", $gift_num);
        //赠品id和数量的关系数组
        $rl_gift_id_num = array();
        foreach ($gift_ids_arr as $key_id => $value_id) {
            $rl_gift_id_num[$value_id] = intval($gift_num_arr[$key_id]);
        }
        $mdl_crm_gift = app::get('crm')->model('gift'); //这里统一用gift_id作为数组的key值，用bn的话可能出现大小写没匹配上的情况。
        //获取“选择”的输入框设置内容
        $arr_handling_content_or = explode("|", $handling_content);
        $return_arr              = array();
        foreach ($arr_handling_content_or as $var_hco) {
            if (strpos($handling_content, "#")) {
                //存在“和”关系 有多个gift_bn
                $inner_ids_arr            = array();
                $inner_num_arr            = array();
                $arr_handling_content_and = explode("#", $var_hco);
                $error_status             = false;
                foreach ($arr_handling_content_and as $var_hca) {
                    $rs_one_gift = $mdl_crm_gift->dump(array("gift_bn" => $var_hca), "gift_id");
                    if (isset($rl_gift_id_num[$rs_one_gift["gift_id"]])) {
                        $inner_ids_arr[] = $rs_one_gift["gift_id"];
                        $inner_num_arr[] = $rl_gift_id_num[$rs_one_gift["gift_id"]];
                    } else {
                        $error_status = true;
                        break;
                    }
                }
                if (!$error_status && !empty($inner_ids_arr)) {
                    //正常情况的 并且都能通过gift_bn匹配到gift_id
                    $return_arr[] = array(
                        "gift_ids" => implode(",", $inner_ids_arr),
                        "gift_num" => implode(",", $inner_num_arr),
                    );
                }
            } else {
                //单个gift_bn
                $rs_one_gift = $mdl_crm_gift->dump(array("gift_bn" => $var_hco), "gift_id");
                if (isset($rl_gift_id_num[$rs_one_gift["gift_id"]])) {
                    $return_arr[] = array(
                        "gift_ids" => $rs_one_gift["gift_id"],
                        "gift_num" => $rl_gift_id_num[$rs_one_gift["gift_id"]],
                    );
                }
            }
        }
        return $return_arr;
    }

    /*
     * 格式化gift_send_log
     * $rule_id ome_gift_rule表id
     * $gift_ids_str gift_ids用逗号分割的字符串
     * $gift_num_str gift_num用逗号分割的字符串
     */
    private function format_gift_send_log($rule_id, $gift_ids_str, $gift_num_str)
    {
        return array(
            'gift_rule_id' => $rule_id,
            'gift_ids'     => explode(',', $gift_ids_str),
            'gift_num'     => explode(',', $gift_num_str),
        );
    }

    /*
     * 以一条规则为单位 依据赠品数量设置 判断是否有完成赠送的数量
     * $gift_ids 当前rule的gift_ids
     * $gift_num 当前rule的gift_ids对应的gift_num
     * $rl_gift_id_num 累计gift_id对应的数量
     * $error_msg_giftset 失败信息
     */
    private function check_giftset_by_rule($gift_ids, $gift_num, &$rl_gift_id_num, &$error_msg_giftset)
    {
        if (!$gift_ids || !$gift_num) {
            return false;
        }
        //获取每个赠品id对应的数量
        $gift_id_arr  = explode(',', $gift_ids);
        $gift_num_arr = explode(',', $gift_num);
        //赠品id和数量的关系数组
        $current_rl_gift_id_num = array();
        foreach ($gift_id_arr as $k_i_a => $v_i_a) {
            $current_rl_gift_id_num[$v_i_a] = intval($gift_num_arr[$k_i_a]) + intval($rl_gift_id_num[$v_i_a]);
        }
        $stockLib  = kernel::single('material_sales_material_stock');
        $return_rs = true;
        $rs        = app::get('crm')->model('gift')->getList('gift_id,gift_bn,gift_num,giftset,product_id', array('gift_id' => $gift_id_arr));
        foreach ($rs as $v) {
            $gift_num = $current_rl_gift_id_num[$v['gift_id']];
            if ($v['giftset'] == '0') {
                //指定数量
                $left_num = $v['gift_num'] - $gift_num;
                //当前指定数量的值小于等于0 或者 剩余数量小于0
                if ($v['gift_num'] <= 0 || $left_num < 0) {
                    $error_msg_giftset .= $v['gift_bn'] . '库存不足;';
                    $return_rs = false;
                    break;
                }
            }
            if ($v['giftset'] == '2') {
                //实际库存数量
                $store = $stockLib->getSalesMStockById($v['product_id']);
                if ($store < $gift_num) {
                    $error_msg_giftset .= $v['gift_bn'] . '可用库存不足;';
                    $return_rs = false;
                    break;
                }
            }
        }
        //满足数量条件 更新$rl_gift_id_num
        if ($return_rs) {
            foreach ($current_rl_gift_id_num as $k_c => $v_c) {
                $rl_gift_id_num[$k_c] = $v_c;
            }
        }
        return $return_rs;
    }

    /**
     * @param $rule
     * @param $order_obj
     * @param $has_buy
     * @return int|mixed
     */
    public function get_buy_goods_num($rule, $order_obj, &$has_buy)
    {
        $item_nums     = 0;
        $item_num_arr  = array();
        $item_bns      = array();
        $buy_goods_bns = $rule['filter_arr']['buy_goods']['goods_bn'];
        $count_type    = $rule['filter_arr']['buy_goods']['count_type'];

        if (!is_array($buy_goods_bns)) {
            $buy_goods_bns = array($buy_goods_bns);
        }

        // 清理空数据
        $buy_goods_bns = $this->clear_value($buy_goods_bns);

        // 转大写
        $buy_goods_bns = array_map('strtoupper', $buy_goods_bns);

        foreach ($order_obj as $item) {

            if ($rule['filter_arr']['buy_goods']['type'] == 1) {
                if ($rule['filter_arr']['buy_goods']['buy_type'] == 'all') {
                    //购买了全部指定商品
                    if (!in_array($item['bn'], $item_bns)) {
                        $item_bns[] = $item['bn'];
                    }

                    if (in_array(strtoupper($item['bn']), $buy_goods_bns)) {
                        $item_num_arr[$item['bn']] = intval($item_num_arr[$item['bn']]) + $item['nums'];

                        unset($buy_goods_bns[array_search(strtoupper($item['bn']), $buy_goods_bns)]);
                    }

                } elseif ($rule['filter_arr']['buy_goods']['buy_type'] == 'none') {
                    //排除购买的指定商品
                    if (!in_array(strtoupper($item['bn']), $buy_goods_bns)) {
                        $item_nums += $item['nums'];
                        if (!in_array($item['bn'], $item_bns)) {
                            $item_bns[] = $item['bn'];
                        }

                        $has_buy = true;
                    }
                } else {
                    //购买了任意一个指定商品
                    if (in_array(strtoupper($item['bn']), $buy_goods_bns)) {
                        $item_nums += $item['nums'];
                        if (!in_array($item['bn'], $item_bns)) {
                            $item_bns[] = $item['bn'];
                        }

                        $has_buy = true;
                    }
                }
            } else {
                $item_nums += $item['nums'];
                $item_num_arr[$item['bn']] = intval($item_num_arr[$item['bn']]) + $item['nums'];
                $has_buy                   = true;
            }
        }

        //购买了全部指定商品，数量以最少的为准
        if ($rule['filter_arr']['buy_goods']['type'] == 1) {
            if ($rule['filter_arr']['buy_goods']['buy_type'] == 'all') {
                if (!$buy_goods_bns) {
                    $item_nums = min($item_num_arr);
                    $has_buy   = true;
                }
            }
        }

        if ($count_type == 'sku' && $has_buy === true) {
            $item_nums = count($item_bns);
        }

        return $item_nums;

    }

    //删除数组里的空元素
    public function clear_value($arr)
    {
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = $this->clear_value($v);
            } else {
                //检测邮政编码格式
                if ($k === 'zip' && !preg_match("/^(\d){5,6}$/", $v)) {
                    unset($arr[$k]);
                }

                if (!$v) {
                    unset($arr[$k]);
                }

            }
        }
        return $arr;
    }

    public function saveGiftLog($logs)
    {
        $m_gift_logs = app::get('ome')->model('gift_logs');
        if ($logs) {
            foreach ($logs as $log) {
                $m_gift_logs->save($log);
            }
        }
    }

}
