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
 * @Author:
 * @Vsersion:
 * @Describe: 对比ome_branch_product和basic_material_stock_freeze是否有差异
 */
class monitor_autotask_timer_checkfreezestore
{

    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '1024M');
        
        // ---------------------------init time---------------------------
        // ---------------------------init time---------------------------
        // $init_time = strtotime('2026-03-27 09:00:00');
        // base_kvstore::instance('monitor/checkfreezestore')->store('sync-lastexectime-exec', $init_time);
        // base_kvstore::instance('monitor/checkfreezestore')->store('sync-lastexectime', $init_time);
        // ---------------------------init time---------------------------
        // ---------------------------init time---------------------------

        $now = time();

        // 数据检查开始时间
        base_kvstore::instance('monitor/checkfreezestore')->fetch('sync-lastexectime', $lastExecTime);
        !$lastExecTime && $lastExecTime = strtotime('-10 minutes');

        // 最后执行时间
        base_kvstore::instance('monitor/checkfreezestore')->fetch('sync-lastexectime-exec', $lastExecTimeDo);
        !$lastExecTimeDo && $lastExecTimeDo = $lastExecTime;

        $showNum = 3; // 异常明细展示条数（防止企业微信消息字数超限）
        if ($params['show'] || $params['script']) {
            $showNum = 66;
        }
        $eventType = 'store_freeze_abnormal';
        if (!$params || (!$params['show'] && !$params['script'])) {

            // 如果通知关掉，就不再统计
            $eventTemplateMdl = app::get('monitor')->model('event_template');
            $templateList     = $eventTemplateMdl->getList('*',[
                'event_type' => $eventType,
                'status'     => '1',
                'disabled'   => 'false',
            ]);
            if (!$templateList) {
                $error_msg = $eventType . ' notice is close';
                return true;
            }

            // 定义时间段
            $frequency = 60 * 60; // 小时执行一次
            // $currentTime    = date('H:i');
            // $startTime      = '09:00';
            // $endTime        = '22:00';
            // if ($currentTime >= $startTime && $currentTime <= $endTime) {
            //     $frequency = 10 * 60; // 默认一分钟一次
            // } else {
            //     $frequency = 30 * 60; // 下班时间一小时执行一次
            // }
            // // 周末一小时执行一次
            // if (in_array(date('N'), [6, 7])) {
            //     $frequency = 60 * 60; // 下班时间一小时执行一次
            // }

            if ($now - $lastExecTimeDo < $frequency) {
                $error_msg = 'its not time yet';
                return true; // 时间未到
            }

            // 更新最后执行时间
            base_kvstore::instance('monitor/checkfreezestore')->store('sync-lastexectime-exec', $now);
        }

        $db   = kernel::database();
        $timeEnd = date('m/d H:i:s', $now);

        // 获取所有仓
        $branchList = app::get('ome')->model('branch')->getList('branch_id, branch_bn');
        $branchList = array_column($branchList, null, 'branch_id');

        // 查询redis临时流水,有异常，一并获取异常的bm_id，与下面活动中的商品合并一起检测数量是否异常
        $flowBmids = $branchFlow = $materialFlow = [];
        if (!$params['script_bm_id_list']) {
            $howLongAgo     = 60; //查询60秒以前的数据
            $branchFlow     = kernel::single('ome_branch_product')->getRedisFlow($howLongAgo);
            $materialFlow   = kernel::single('material_basic_material_stock')->getRedisFlow($howLongAgo);
        }

        $flowMsg = [];
        $flowMsg[] = "====== ====== ======";
        $flowMsg[] = "Redis仓冻结流水";
        if ($branchFlow['freeze']) {
            $count = 0;

            $flowMsg[] = "对象类型 / 业务类型 / 业务单号 / 加减 / 时间";
            $flowMsg[] = "仓编码 : 基础物料编码 : 数量";

            $fieldList = array_keys($branchFlow['freeze']);
            foreach ($fieldList as $field) {
                list($obj_type, $bill_type, $obj_id, $opt, $time) = explode('#', $field);
                $mainInfo = $this->getMainInfo('freeze', $field);

                if ($count<$showNum) {
                    $flowMsg[] = '<font color="warning">' . sprintf("【%s】%s /【%s】%s /【%s】%s / %s /【%s】%s", $obj_type, $mainInfo['obj_type'], $bill_type, $mainInfo['bill_type'], $obj_id, $mainInfo['obj_id'], $opt, $time, date('H:i:s', $time)) . '</font>';
                }

                $items  = explode(';', $branchFlow['freeze'][$field]);
                $bmList = $this->getMaterialList($items);
                foreach ($items as $item) {
                    list($branch_id, $bm_id, $quantity) = explode(':', $item);
                    $flowBmids[$bm_id] = 1;
                    $branch_bn = $branchList[$branch_id] ? $branchList[$branch_id]['branch_bn'] : '';
                    $bm_bn = isset($bmList[$bm_id]) ? $bmList[$bm_id]['material_bn'] : '';

                    $count++;
                    if ($count>$showNum) {
                        continue;
                    }
                    $flowMsg[] = '<font color="warning">' . sprintf("—【%s】%s :【%s】%s : %s", $branch_id, $branch_bn, $bm_id, $bm_bn, $quantity) . '</font>';
                }
            }
            if ($count>$showNum) {
                $flowMsg[] = '<font color="warning">-【共计' . $count . '条详情明细】</font>';
            }
        } else {
            $flowMsg[] = '<font color="info">' . "— 无异常" . '</font>';
        }

        $flowMsg[] = "------ ------ ------";
        $flowMsg[] = "Redis商品冻结流水";
        if ($materialFlow['freeze']) {
            $count = 0;

            $flowMsg[] = "对象类型 / 业务类型 / 业务单号 / 加减 / 时间";
            $flowMsg[] = "仓编码 : 基础物料编码 : 数量";

            $fieldList = array_keys($materialFlow['freeze']);
            foreach ($fieldList as $field) {
                list($obj_type, $bill_type, $obj_id, $opt, $time) = explode('#', $field);
                $mainInfo = $this->getMainInfo('freeze', $field);

                if ($count<$showNum) {
                    $flowMsg[] = '<font color="warning">' . sprintf("【%s】%s /【%s】%s /【%s】%s / %s /【%s】%s", $obj_type, $mainInfo['obj_type'], $bill_type, $mainInfo['bill_type'], $obj_id, $mainInfo['obj_id'], $opt, $time, date('H:i:s', $time)) . '</font>';
                }

                $items  = explode(';', $materialFlow['freeze'][$field]);
                $bmList = $this->getMaterialList($items);
                foreach ($items as $item) {
                    list($branch_id, $bm_id, $quantity) = explode(':', $item);
                    $flowBmids[$bm_id] = 1;
                    $branch_bn = $branchList[$branch_id] ? $branchList[$branch_id]['branch_bn'] : '';
                    $bm_bn = isset($bmList[$bm_id]) ? $bmList[$bm_id]['material_bn'] : '';

                    $count++;
                    if ($count>$showNum) {
                        continue;
                    }
                    $flowMsg[] = '<font color="warning">' . sprintf("—【%s】%s :【%s】%s : %s", $branch_id, $branch_bn, $bm_id, $bm_bn, $quantity) . '</font>';
                }
            }
            if ($count>$showNum) {
                $flowMsg[] = '<font color="warning">-【共计' . $count . '条详情明细】</font>';
            }
        } else {
            $flowMsg[] = '<font color="info">' . "— 无异常" . '</font>';
        }

        $flowMsg[] = "------ ------ ------";
        $flowMsg[] = "Redis仓库存流水";
        if ($branchFlow['store']) {
            $count = 0;

            $flowMsg[] = "出入库单号 / 加减 / 时间";
            $flowMsg[] = "仓编码 : 基础物料编码 : 数量";

            $fieldList = array_keys($branchFlow['store']);
            foreach ($fieldList as $field) {
                list($iostock_bn, $opt, $time) = explode('#', $field);

                if ($count<$showNum) {
                    $flowMsg[] = '<font color="warning">' . sprintf("%s / %s /【%s】%s", $iostock_bn, $opt, $time, date('H:i:s', $time)) . '</font>';
                }

                $items  = explode(';', $branchFlow['store'][$field]);
                $bmList = $this->getMaterialList($items);
                foreach ($items as $item) {
                    list($branch_id, $bm_id, $quantity) = explode(':', $item);
                    $flowBmids[$bm_id] = 1;
                    $branch_bn = $branchList[$branch_id] ? $branchList[$branch_id]['branch_bn'] : '';
                    $bm_bn = isset($bmList[$bm_id]) ? $bmList[$bm_id]['material_bn'] : '';

                    $count++;
                    if ($count>$showNum) {
                        continue;
                    }
                    $flowMsg[] = '<font color="warning">' . sprintf("—【%s】%s :【%s】%s : %s", $branch_id, $branch_bn, $bm_id, $bm_bn, $quantity) . '</font>';
                }
            }
            if ($count>$showNum) {
                $flowMsg[] = '<font color="warning">-【共计' . $count . '条详情明细】</font>';
            }
        } else {
            $flowMsg[] = '<font color="info">' . "— 无异常" . '</font>';
        }

        $flowMsg[] = "------ ------ ------";
        $flowMsg[] = "Redis商品库存流水";
        if ($materialFlow['store']) {
            $count = 0;

            $flowMsg[] = "出入库单号 / 加减 / 时间";
            $flowMsg[] = "仓编码 : 基础物料编码 : 数量";

            $fieldList = array_keys($materialFlow['store']);
            foreach ($fieldList as $field) {
                list($iostock_bn, $opt, $time) = explode('#', $field);

                if ($count<$showNum) {
                    $flowMsg[] = '<font color="warning">' . sprintf("%s / %s /【%s】%s", $iostock_bn, $opt, $time, date('H:i:s', $time)) . '</font>';
                }

                $items  = explode(';', $materialFlow['store'][$field]);
                $bmList = $this->getMaterialList($items);
                foreach ($items as $item) {
                    list($branch_id, $bm_id, $quantity) = explode(':', $item);
                    $flowBmids[$bm_id] = 1;
                    $branch_bn = $branchList[$branch_id] ? $branchList[$branch_id]['branch_bn'] : '';
                    $bm_bn = isset($bmList[$bm_id]) ? $bmList[$bm_id]['material_bn'] : '';

                    $count++;
                    if ($count>$showNum) {
                        continue;
                    }
                    $flowMsg[] = '<font color="warning">' . sprintf("—【%s】%s :【%s】%s : %s", $branch_id, $branch_bn, $bm_id, $bm_bn, $quantity) . '</font>';
                }
            }
            if ($count>$showNum) {
                $flowMsg[] = '<font color="warning">-【共计' . $count . '条详情明细】</font>';
            }
        } else {
            $flowMsg[] = '<font color="info">' . "— 无异常" . '</font>';
        }

        // 获取所有需要检测的商品
        $last_modified = $lastExecTime - 60*10;
        $earliest = $now; // 比较出有差异的商品中最早的last_modified

        $bmTimeList = [];
        if ($params['script_bm_id_list']) {
            $params['uptime'] = false;
            $script_bm_id_list = $params['script_bm_id_list'];
            $bm_time_where = 'bm_id in ("'.implode('","', $script_bm_id_list).'")';
            $bmSql  = "SELECT bm_id, material_bn as bm_bn FROM sdb_material_basic_material WHERE " . $bm_time_where;
        } else {
            // 获取需要检测的商品以及对应的last_modified
            $bm_time_sql = "SELECT bm_id,last_modified FROM sdb_material_basic_material_stock WHERE last_modified >= {$last_modified} AND last_modified < {$now}";
            $bmTimeList = $db->select($bm_time_sql);
            $bmTimeList = array_column($bmTimeList, 'last_modified', 'bm_id');

            $bmSql  = "SELECT bm_id, material_bn as bm_bn FROM sdb_material_basic_material WHERE bm_id IN (SELECT bm_id FROM sdb_material_basic_material_stock WHERE last_modified >= {$last_modified} AND last_modified < {$now})";
        }
        $bmList = $db->select($bmSql);

        if ($flowBmids) {
            $flowBmids = array_keys($flowBmids);
            $flowBmSql = "SELECT bm_id, material_bn as bm_bn FROM sdb_material_basic_material WHERE bm_id IN ('" . implode("','", $flowBmids) . "') ";
            $flowBmList = $db->select($flowBmSql);
            $flowBmList = array_column($flowBmList, null, 'bm_id');

            $appendBmIds = [];
            $bmList = array_column($bmList, null, 'bm_id');
            foreach ($flowBmList as $_bm_id => $_v) {
                if (!isset($bmList[$_bm_id])) {
                    $appendBmIds[] = $_bm_id;
                    $bmList[$_bm_id] = $_v;
                }
                unset($flowBmList[$_bm_id]);
            }

            if ($appendBmIds) {
                $appendBmTimeList = $db->select("SELECT bm_id,last_modified FROM sdb_material_basic_material_stock WHERE  bm_id IN ('" . implode("','", $appendBmIds) . "') ");
                $appendBmTimeList = array_column($appendBmTimeList, 'last_modified', 'bm_id');
                foreach ($appendBmTimeList as $_bm_id => $_last_modified) {
                    if (!isset($bmTimeList[$_bm_id])) {
                        $bmTimeList[$_bm_id] = $_last_modified;
                    }
                    unset($appendBmTimeList[$_bm_id]);
                }
            }
        }

        $bmList = array_chunk($bmList, 500);
        $failInfo = [];
        foreach ($bmList as $bmIdArr) {
            $bmbnArr = array_column($bmIdArr, null, 'bm_id');
            $bmIdArr = array_column($bmIdArr, 'bm_id');

            // 获取material_basic_material_stock《商品库存表》的库存和冻结
            $bmStoreSql  = "SELECT bm_id, store, store_freeze FROM sdb_material_basic_material_stock WHERE bm_id in ('" . implode("','", $bmIdArr) . "')";
            $bmStoreList = $db->select($bmStoreSql);
            $bmStoreList = array_column($bmStoreList, null, 'bm_id');

            $bmRedisList = $bmSerialList = [];
            foreach ($bmIdArr as $bmId) {
                // 获取redis《物料》的库存和预占数据
                $param = [
                    'bm_id' => $bmId,
                ];
                $redisInfo = material_basic_material_stock::storeFromRedis($param);
                if ($redisInfo[0]) {
                    $bmRedisList[$bmId] = $redisInfo[2];
                }
            }

            // 获取basic_material_stock_freeze《预占流水表》的总预占数据（仓预占+订单预占）
            $serialSql = "SELECT bm_id, sum(num) as store_freeze
                FROM sdb_material_basic_material_stock_freeze
                WHERE bm_id in ('" . implode("','", $bmIdArr) . "')
                GROUP BY bm_id";
            $bmSerialList = $db->select($serialSql);
            $bmSerialList && $bmSerialList = array_column($bmSerialList, null, 'bm_id');

            $branchIdArr = array_column($branchList, 'branch_id');

            // 获取sdb_ome_branch_product《仓库商品表》的数据
            $branchProductListAll = [];
            $branchProductSql = "SELECT product_id as bm_id, store, store_freeze, branch_id
                FROM sdb_ome_branch_product
                WHERE branch_id IN ('" . implode("','", $branchIdArr) . "') AND product_id in ('" . implode("','", $bmIdArr) . "')";
            $_branchProductList = $db->select($branchProductSql);
            foreach ($_branchProductList as $bk => $bv) {
                $branchProductListAll[$bv['branch_id']][] = $bv;
            }
            unset($_branchProductList);

            // 获取basic_material_stock_freeze《预占流水表》的数据-1
            $stockFreezeListAll = [];
            $stockFreezeSql = "SELECT bm_id, sum(num) as num, branch_id
                FROM sdb_material_basic_material_stock_freeze
                WHERE obj_type = " . material_basic_material_stock_freeze::__BRANCH . " AND branch_id IN ('" . implode("','", $branchIdArr) . "') AND bm_id in ('" . implode("','", $bmIdArr) . "')
                GROUP BY branch_id, bm_id";
            $_stockFreezeList = $db->select($stockFreezeSql);
            foreach ($_stockFreezeList as $sk => $sv) {
                $stockFreezeListAll[$sv['branch_id']][] = $sv;
            }
            unset($_stockFreezeList);

            // 获取basic_material_stock_freeze《预占流水表》的数据-2
            $stockFreezeOrderListAll = [];
            $stockFreezeSql = "SELECT bm_id, sum(num) as num, branch_id
                FROM sdb_material_basic_material_stock_freeze
                WHERE (obj_type=" . material_basic_material_stock_freeze::__ORDER . " and bill_type=" . material_basic_material_stock_freeze::__ORDER_YOU . ") AND branch_id IN ('" . implode("','", $branchIdArr) . "') AND bm_id in ('" . implode("','", $bmIdArr) . "')
                GROUP BY branch_id, bm_id";
            $_stockFreezeOrderList = $db->select($stockFreezeSql);
            foreach ($_stockFreezeOrderList as $sfk => $sfv) {
                $stockFreezeOrderListAll[$sfv['branch_id']][] = $sfv;
            }
            unset($_stockFreezeOrderList);

            foreach ($branchList as $branchInfo) {
                $branchProductList = $stockFreezeList = $stockFreezeOrderList = [];

                $branchId = $branchInfo['branch_id'];
                $branchBn = $branchInfo['branch_bn'];

                if ($branchProductList = $branchProductListAll[$branchId]) {
                    $branchProductList = array_column($branchProductList, null, 'bm_id');
                    unset($branchProductListAll[$branchId]);
                }

                if ($stockFreezeList = $stockFreezeListAll[$branchId]) {
                    $stockFreezeList = array_column($stockFreezeList, null, 'bm_id');
                    unset($stockFreezeListAll[$branchId]);
                }

                if ($stockFreezeOrderList = $stockFreezeOrderListAll[$branchId]) {
                    $stockFreezeOrderList = array_column($stockFreezeOrderList, null, 'bm_id');
                    unset($stockFreezeOrderListAll[$branchId]);
                }

                foreach ($stockFreezeOrderList as $_bm_id => $_stockfr) {
                    if (isset($stockFreezeList[$_bm_id])) {
                        $stockFreezeList[$_bm_id]['num'] += $_stockfr['num'];
                    } else {
                        $stockFreezeList[$_bm_id] = $_stockfr;
                    }
                }

                // 获取redis《仓》的库存和预占数据
                $redisList = [];
                foreach ($bmIdArr as $bmId) {
                    $param = [
                        'branch_id'  => $branchId,
                        'product_id' => $bmId,
                    ];
                    $redisInfo = ome_branch_product::storeFromRedis($param);
                    if ($redisInfo[0]) {
                        $redisList[$bmId] = $redisInfo[2];
                    }
                }

                if (!$branchProductList && !$stockFreezeList && !$redisList) {
                    continue;
                }

                // 开始对比
                foreach ($bmIdArr as $bmId) {

                    $bpFreeze = $bpStore = 0;
                    if ($branchProductList[$bmId]) {
                        $bpFreeze = $branchProductList[$bmId]['store_freeze'];
                        $bpStore  = $branchProductList[$bmId]['store'];
                    }

                    $sfInfo = $stockFreezeList[$bmId] ? $stockFreezeList[$bmId]['num'] : 0;

                    $redisFreeze = $redisStore = 0;
                    if ($redisList[$bmId]) {
                        $redisFreeze = $redisList[$bmId]['store_freeze'];
                        $redisStore  = $redisList[$bmId]['store'];
                    }

                    // freeze: ome_branch_product vs material_basic_material_stock_freeze vs redis
                    if ($bpFreeze != $sfInfo || $bpFreeze != $redisFreeze) {
                        // $branchMsg = 'Bn:' . $branchBn . ' Id:' . $branchId;
                        $branchMsg = $branchBn;

                        $failInfo['vsFreeze'][$branchMsg][] = [
                            'bmBn'          => $bmbnArr[$bmId]['bm_bn'],
                            'bmId'          => $bmId,
                            'stockFreeze'   => $sfInfo,
                            'brchPrdct'     => $bpFreeze,
                            'Redis'         => $redisFreeze,
                            'branchId'      => $branchId,
                        ];
                        if ($bmTimeList[$bmId]) {
                            if ($bmTimeList[$bmId] < $earliest) {
                                $earliest = $bmTimeList[$bmId];
                            }
                        } else {
                            $earliest = $lastExecTime;
                        }
                    }

                    // store: ome_branch_product vs redis
                    if ($bpStore != $redisStore) {
                        // $branchMsg = 'Bn:' . $branchBn . ' Id:' . $branchId;
                        $branchMsg = $branchBn;

                        $failInfo['vsStore'][$branchMsg][] = [
                            'bmBn'      => $bmbnArr[$bmId]['bm_bn'],
                            'bmId'      => $bmId,
                            'brchPrdct' => $bpStore,
                            'Redis'     => $redisStore,
                            'branchId'  => $branchId,
                        ];
                        if ($bmTimeList[$bmId]) {
                            if ($bmTimeList[$bmId] < $earliest) {
                                $earliest = $bmTimeList[$bmId];
                            }
                        } else {
                            $earliest = $lastExecTime;
                        }
                    }

                }
            }

            // 对比商品表库存 vs 仓库存 vs redis
            foreach ($bmIdArr as $bmId) {
                $bmStore       = $bmFreeze       = 0; // db商品库存
                $sumFreeze     = 0; // 汇总预占流水
                $bmRedisFreeze = $bmRedisStore = 0; // redis商品库存
                if ($bmStoreList[$bmId]) {
                    $bmFreeze = $bmStoreList[$bmId]['store_freeze'];
                    $bmStore  = $bmStoreList[$bmId]['store'];
                }
                if ($bmSerialList[$bmId]) {
                    $sumFreeze = $bmSerialList[$bmId]['store_freeze'];
                }
                if ($bmRedisList[$bmId]) {
                    $bmRedisFreeze = $bmRedisList[$bmId]['store_freeze'];
                    $bmRedisStore  = $bmRedisList[$bmId]['store'];
                }

                if ($bmFreeze != $sumFreeze or $bmFreeze != $bmRedisFreeze) {
                    $failInfo['vsBmFreeze'][$bmId] = [
                        'bmBn'          => $bmbnArr[$bmId]['bm_bn'],
                        'sumFreeze'     => $sumFreeze,
                        'bmFreeze'      => $bmFreeze,
                        'bmRedisFreeze' => $bmRedisFreeze,
                    ];
                    if ($bmTimeList[$bmId]) {
                        if ($bmTimeList[$bmId] < $earliest) {
                            $earliest = $bmTimeList[$bmId];
                        }
                    } else {
                        $earliest = $lastExecTime;
                    }
                }

                if ($bmStore != $bmRedisStore) {
                    $failInfo['vsBmStore'][$bmId] = [
                        'bmBn'         => $bmbnArr[$bmId]['bm_bn'],
                        'bmStore'      => $bmStore,
                        'bmRedisStore' => $bmRedisStore,
                    ];
                    if ($bmTimeList[$bmId]) {
                        if ($bmTimeList[$bmId] < $earliest) {
                            $earliest = $bmTimeList[$bmId];
                        }
                    } else {
                        $earliest = $lastExecTime;
                    }
                }
            }

        }

        // 定时场景下做二次复检，过滤瞬时抖动导致的误报
        $skipDoubleCheck = false;
        if (!$params['show'] && !$params['script'] && !$params['skip_double_check'] && $failInfo) {
            $candidateBmIds = $this->collectFailBmIds($failInfo);
            if ($candidateBmIds) {
                $candidateCount = count($candidateBmIds);
                if ($candidateCount > 600) {
                    $skipDoubleCheck = true;
                } else {
                    sleep(6);
                    $scriptParams = [
                        'script'             => true,
                        'uptime'             => false,
                        'script_bm_id_list'  => $candidateBmIds,
                        'skip_double_check'  => true,
                    ];
                    $tmpErr = '';
                    $doubleCheckFailInfo = $this->process($scriptParams, $tmpErr);
                    if (is_array($doubleCheckFailInfo)) {
                        $failInfo = $doubleCheckFailInfo;
                    }
                }
            }
        }

        $msgHead = [
            '时间:' . ($last_modified ? date('m/d H:i:s', $last_modified) : 'NULL') . '——' . $timeEnd . ($skipDoubleCheck ? ' (未复检)' : ''),
            // '域名:' . kernel::base_url(1),
            "====== ====== ======",
        ];

        // 获取冻结流水中，num<0的数据
        $stock_freeze_msg = [];
        $stock_freeze_sql = "SELECT * FROM sdb_material_basic_material_stock_freeze WHERE last_modified >= {$last_modified} AND last_modified < {$now} AND num<0";
        $stock_freeze_list = $db->select($stock_freeze_sql);
        if ($stock_freeze_list) {
            $stock_freeze_msg[] = '冻结流水中num小于0，共计：'.count($stock_freeze_list).'条';
            foreach ($stock_freeze_list as $sfk => $sfv) {
                if ($sfv['last_modified']<$earliest) {
                    $earliest = $sfv['last_modified'];
                }
                if ($sfk<$showNum) {
                    $stock_freeze_msg = array_merge($msgHead, [json_encode($sfv, JSON_UNESCAPED_UNICODE)]);
                }
            }
        }

        $msg = $msgHead;
        if ($failInfo) {
            $msg[] = "流水冻结 VS 仓冻结 VS Redis冻结";
            if ($failInfo['vsFreeze']) {
                $count = 0;
                foreach ($failInfo['vsFreeze'] as $branchMsg => $list) {
                    // $msg[] = "— Branch >> " . $branchMsg;
                    foreach ($list as $info) {
                        // $msg[] = "— det. >> " . implode(" ", $info);
                        $count++;
                        if ($count>$showNum) {
                            continue;
                        }
                        $msg[] = '<font color="warning">' . "—【仓-" . $info['branchId'] . "】" . $branchMsg . "【货-" . $info['bmId'] . "】" . $info['bmBn'] . " >> " . $info['stockFreeze'] . " vs " . $info['brchPrdct'] . " vs " . $info['Redis'] . '</font>';
                    }
                }
                if ($count>$showNum) {
                    $msg[] = '<font color="warning">-【共计' . $count . '条】</font>';
                }
            } else {
                $msg[] = '<font color="info">' . "— 无差异" . '</font>';
            }
            $msg[] = "------ ------ ------";

            $msg[] = "仓库存 VS Redis库存";
            if ($failInfo['vsStore']) {
                $count = 0;
                foreach ($failInfo['vsStore'] as $branchMsg => $list) {
                    // $msg[] = "—Branch >> " . $branchMsg;
                    foreach ($list as $info) {
                        // $msg[] = "—det. >> " . implode(" ", $info);
                        $count++;
                        if ($count>$showNum) {
                            continue;
                        }
                        $msg[] = '<font color="warning">' . "—【仓-" . $info['branchId'] . "】" . $branchMsg . "【货-" . $info['bmId'] . "】" . $info['bmBn'] . " >> " . $info['brchPrdct'] . " vs " . $info['Redis'] . '</font>';
                    }
                }
                if ($count>$showNum) {
                    $msg[] = '<font color="warning">-【共计' . $count . '条】</font>';
                }
            } else {
                $msg[] = '<font color="info">' . "— 无差异" . '</font>';
            }
            $msg[] = "------ ------ ------";

            $msg[] = "汇总流水冻结 VS 商品冻结 VS Redis商品冻结";
            if ($failInfo['vsBmFreeze']) {
                $count = 0;
                foreach ($failInfo['vsBmFreeze'] as $bmId => $info) {
                    $count++;
                    if ($count>$showNum) {
                        continue;
                    }
                    $msg[] = '<font color="warning">' . "—【货-" . $bmId . "】" . $info['bmBn'] . " >> " . $info['sumFreeze'] . " vs " . $info['bmFreeze'] . " vs " . $info['bmRedisFreeze'] . '</font>';
                }
                if ($count>$showNum) {
                    $msg[] = '<font color="warning">-【共计' . $count . '条】</font>';
                }
            } else {
                $msg[] = '<font color="info">' . "— 无差异" . '</font>';
            }
            $msg[] = "------ ------ ------";

            $msg[] = "商品库存 VS Redis商品库存";
            if ($failInfo['vsBmStore']) {
                $count = 0;
                foreach ($failInfo['vsBmStore'] as $bmId => $info) {
                    $count++;
                    if ($count>$showNum) {
                        continue;
                    }
                    $msg[] = '<font color="warning">' . "—【货-" . $bmId . "】" . $info['bmBn'] . " >> " . $info['bmStore'] . " vs " . $info['bmRedisStore'] . '</font>';
                }
                if ($count>$showNum) {
                    $msg[] = '<font color="warning">-【共计' . $count . '条】</font>';
                }
            } else {
                $msg[] = '<font color="info">' . "— 无差异" . '</font>';
            }

        } else {
            $msg[] = "流水冻结 VS 仓冻结 VS Redis冻结";
            $msg[] = '<font color="info">' . "— 无差异" . '</font>';
            $msg[] = "------ ------ ------";
            $msg[] = "仓库存 VS Redis库存";
            $msg[] = '<font color="info">' . "— 无差异" . '</font>';
            $msg[] = "------ ------ ------";
            $msg[] = "汇总流水冻结 VS 商品冻结 VS Redis商品冻结";
            $msg[] = '<font color="info">' . "— 无差异" . '</font>';
            $msg[] = "------ ------ ------";
            $msg[] = "商品库存 VS Redis商品库存";
            $msg[] = '<font color="info">' . "— 无差异" . '</font>';

        }

        if (!$params || (!$params['show'] && !$params['script']) || $params['uptime']) {
            // 没有差异才会更新最后执行时间，否则会出现差异没有及时处理，历史差异就检测不到的情况
            base_kvstore::instance('monitor/checkfreezestore')->store('sync-lastexectime', $earliest);
        }

        $msg = array_merge($msg, $flowMsg);
        $elapsedTime = time()-$now;
        array_unshift($msg, "执行耗时:" . $elapsedTime . "秒");

        $notify = [
            'errmsg' => implode("\n", $msg),
        ];

        $notify2 = [];
        if ($stock_freeze_msg) {
            $notify2 = [
                'errmsg' => implode("\n", array_merge($msgHead, $stock_freeze_msg)),
            ];
        }

        $is_sync = $params['is_sync'] ? false : true;

        if ($params['show']) {

            echo "<pre>";
            print_R($notify);
            print_R($notify2);
            exit();
        } elseif ($params['script']) {

            return $failInfo;
        } else {
            kernel::single('monitor_event_notify')->addNotify($eventType, $notify, $is_sync);
            if ($notify2) {
                kernel::single('monitor_event_notify')->addNotify($eventType, $notify2, $is_sync);
            }
        }
        return true;
    }

    /**
     * 汇总各维度异常中的 bm_id，做二次复检用
     */
    private function collectFailBmIds($failInfo)
    {
        $bmIds = [];
        if (!is_array($failInfo)) {
            return [];
        }

        if (!empty($failInfo['vsFreeze'])) {
            foreach ($failInfo['vsFreeze'] as $list) {
                foreach ((array)$list as $info) {
                    if (isset($info['bmId'])) {
                        $bmIds[(int)$info['bmId']] = 1;
                    }
                }
            }
        }

        if (!empty($failInfo['vsStore'])) {
            foreach ($failInfo['vsStore'] as $list) {
                foreach ((array)$list as $info) {
                    if (isset($info['bmId'])) {
                        $bmIds[(int)$info['bmId']] = 1;
                    }
                }
            }
        }

        if (!empty($failInfo['vsBmFreeze'])) {
            foreach ((array)$failInfo['vsBmFreeze'] as $bmId => $info) {
                $bmIds[(int)$bmId] = 1;
            }
        }

        if (!empty($failInfo['vsBmStore'])) {
            foreach ((array)$failInfo['vsBmStore'] as $bmId => $info) {
                $bmIds[(int)$bmId] = 1;
            }
        }

        return array_keys($bmIds);
    }

    private function getMainInfo($type = 'freeze', $info = '')
    {
        $freezeLib = kernel::single('material_basic_material_stock_freeze');

        list($obj_type, $bill_type, $obj_id, $opt, $time) = explode('#', $info);

        $detail = [];
        switch ($type) {
            case 'freeze':
                // obj_type # bill_type # obj_id # $opt # time;

                // init
                $detail = [
                    'obj_type'  =>  '',
                    'bill_type' =>  '',
                    'obj_id'    =>  '',
                    'opt'       =>  '',
                ];

                $obj_type == '1' && $detail['obj_type'] = '订单';
                $obj_type == '2' && $detail['obj_type'] = '仓库';

                switch ($bill_type) {
                    case '0':
                        $detail['bill_type'] = '订单';
                        $mdl = app::get('ome')->model('orders');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['order_id'=>$obj_id], 'order_bn');
                            $tmp && $detail['obj_id'] = $tmp['order_bn'];
                        }
                        break;
                    case '1':
                        $detail['bill_type'] = '发货';
                        $mdl = app::get('ome')->model('delivery');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['delivery_id'=>$obj_id], 'delivery_bn');
                            $tmp && $detail['obj_id'] = $tmp['delivery_bn'];
                        }
                        break;
                    case '2':
                        $detail['bill_type'] = '售后';
                        $mdl = app::get('ome')->model('reship');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['reship_id'=>$obj_id], 'reship_bn');
                            $tmp && $detail['obj_id'] = $tmp['reship_bn'];
                        }
                        break;
                    case '3':
                        $detail['bill_type'] = '采购退货';
                        $mdl = app::get('purchase')->model('returned_purchase');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['rp_id'=>$obj_id], 'rp_bn');
                            $tmp && $detail['obj_id'] = $tmp['rp_bn'];
                        }
                        break;
                    case '4':
                        $detail['bill_type'] = '调拨出库';
                        $mdl = app::get('taoguaniostockorder')->model("iso");
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['iso_id'=>$obj_id], 'iso_bn');
                            $tmp && $detail['obj_id'] = $tmp['iso_bn'];
                        }
                        break;
                    case '5':
                        $detail['bill_type'] = '库内转储';
                        $mdl = app::get('console')->model('stockdump');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['stockdump_id'=>$obj_id], 'stockdump_bn');
                            $tmp && $detail['obj_id'] = $tmp['stockdump_bn'];
                        }
                        break;
                    case '6':
                        $detail['bill_type'] = '唯品会出库';
                        $mdl = app::get('purchase')->model('pick_stockout_bills');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['stockout_id'=>$obj_id], 'stockout_no');
                            $tmp && $detail['obj_id'] = $tmp['stockout_no'];
                        }
                        break;
                    case '7':
                        $detail['bill_type'] = '人工库存预占';
                        $mdl = app::get('material')->model('basic_material_stock_artificial_freeze');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['bmsaf_id'=>$obj_id], 'original_bn');
                            $tmp && $detail['obj_id'] = $tmp['original_bn'];
                        }
                        break;
                    case '8':
                        $detail['bill_type'] = '库存调整单出库';
                        $mdl = app::get('console')->model('adjust');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['id'=>$obj_id], 'adjust_bn');
                            $tmp && $detail['obj_id'] = $tmp['adjust_bn'];
                        }
                        break;
                    case '9':
                        $detail['bill_type'] = '差异单出库';
                        $mdl = app::get('console')->model('difference');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['id'=>$obj_id], 'diff_bn');
                            $tmp && $detail['obj_id'] = $tmp['diff_bn'];
                        }
                        break;
                    case '10':
                        $detail['bill_type'] = '加工单出库';
                        $mdl = app::get('console')->model('material_package');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['id'=>$obj_id], 'mp_bn');
                            $tmp && $detail['obj_id'] = $tmp['mp_bn'];
                        }
                        break;
                    case '11':
                        $detail['bill_type'] = '唯品会拣货单';
                        $mdl = app::get('purchase')->model('pick_bills');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['bill_id'=>$obj_id], 'pick_no');
                            $tmp && $detail['obj_id'] = $tmp['pick_no'];
                        }
                        break;
                    case '12':
                        $detail['bill_type'] = '售后申请单';
                        $mdl = app::get('ome')->model('return_product');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['return_id'=>$obj_id], 'return_bn');
                            $tmp && $detail['obj_id'] = $tmp['return_bn'];
                        }
                        break;
                    case '13':
                        $detail['bill_type'] = '订单缺货';
                        $mdl = app::get('ome')->model('orders');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['order_id'=>$obj_id], 'order_bn');
                            $tmp && $detail['obj_id'] = $tmp['order_bn'];
                        }
                        break;
                    case '14':
                        $detail['bill_type'] = '订单仓库预占';
                        $mdl = app::get('ome')->model('orders');
                        if ($obj_id) {
                            $tmp = $mdl->db_dump(['order_id'=>$obj_id], 'order_bn');
                            $tmp && $detail['obj_id'] = $tmp['order_bn'];
                        }
                        break;
                    default:
                        break;
                }

                $opt == '+' && $detail['opt'] = '增加';
                $opt == '-' && $detail['opt'] = '扣减';

                $time && $detail['time'] = date('m/d H:i:s', $time);

                break;
            case 'store':
                // iostock_bn # $opt # time;
                break;
            default:
                break;
        }
        return $detail;
    }

    private function getMaterialList($items)
    {
        $materialMdl = app::get('material')->model('basic_material');
        
        $bmIdArr = $bmList = [];
        foreach ($items as $_v) {
            list(, $bm_id, ) = explode(':', $_v);
            $bmIdArr[] = $bm_id;
        }
        $bmIdArr = array_chunk(array_unique($bmIdArr), 500);
        foreach ($bmIdArr as $bmIds) {
            $bmList = array_merge($bmList, $materialMdl->getList('bm_id, material_bn', ['bm_id|in'=>$bmIds]));
        }

        return array_column($bmList, null, 'bm_id');
    }
}
