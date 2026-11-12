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

class financebase_finder_bill_verification_error {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }




    var $column_edit = "操作";
    var $column_edit_width = "150";

    function column_edit($row) {
        // Vue Finder 契约：结构化行操作（不下发 legacy HTML 片段）
        if ($this->vue_mode) {
            return array(
                array('key' => 'edit', 'label' => '编辑', 'action' => 'edit'),
            );
        }

        $finder_id = $_GET['_finder']['finder_id'];

        $ret = '<a href="index.php?app=financebase&ctl=admin_shop_settlement_verification&act=setVerificationError&p[0]='.$row['id'].'&_finder[finder_id]=' . $finder_id . '&finder_id=' . $finder_id . '" target="dialog::{width:550,height:400,resizeable:false,title:\'核销规则编辑\'}">编辑</a>';

        return $ret;
    }



    var $detail_oplog = "操作日志";
    function detail_oplog($id){
        $mdlOpLog = app::get('financebase')->model("bill_verification_error");


        $info= $mdlOpLog->getRow('memo',array('id'=>$id));

        $list = @unserialize($info['memo']);
        $list = $list ?: [];

        // Vue Finder 契约：结构化下发，不下发 legacy HTML
        if ($this->vue_mode) {
            $rows = array();
            $logIndex = 0;
            foreach (array_reverse($list) as $item) {
                $logIndex++;
                $time = isset($item['time']) ? $item['time'] : '';
                if (is_numeric($time)) {
                    $time = date('Y-m-d H:i:s', (int) $time);
                }
                // 快照（编辑类条目才有）：同族页结构 {字段: {old_value, new_value}}
                $snapshot = isset($item['snapshot']) && is_array($item['snapshot']) ? $item['snapshot'] : array();

                $rows[] = array(
                    // 该页日志无框架 log_id（存在行 memo 里）⇒ 用列表序号充当稳定标识
                    'log_id'       => $logIndex,
                    'operate_time' => $time,
                    'op_name'      => (string) ($item['op_name'] ?? ''),
                    // 与同族页一致：operation=行为（新增/编辑）、memo=操作内容；旧数据无该键时回空串
                    'operation'    => (string) ($item['operation'] ?? ($item['action'] ?? '')),
                    'memo'         => (string) ($item['memo'] ?? ''),
                    'can_snapshot' => !empty($snapshot),
                    'snapshot'     => $snapshot,
                );
            }

            return array('logs' => $rows);
        }

        $render = app::get('financebase')->render();
 		$render->pagedata['list'] = array_reverse($list);

        return $render->fetch("admin/verification/op_logs.html");
    }





}

