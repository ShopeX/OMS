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
 *
 */

class omeauto_auto_type
{

    /**
     * 所有支持的类型
     */
    static $TYPE_LIST = array('address', 'shop', 'money', 'platform', 'sku', 'cod', 'skunum', 'itemnum', 'weight', 'memo', 'ordertype', 'materialtype', 'orderhost', 'shopmode', 'customer', 'orderlabel', 'materialcat');

    /**
     * 执行插件的指定方法
     *
     * @param String $tagName
     * @param String $method
     * @param Array $params
     * @return Mixed
     */
    public function doMethod($tagName, $method, $params)
    {

        $tagName = strtolower($tagName);
        if (in_array($tagName, self::$TYPE_LIST)) {

            $obj = kernel::single(sprintf('omeauto_auto_type_%s', $tagName));
            if (method_exists($obj, $method)) {

                return $obj->$method($params);
            } else {

                return sprintf("方法 {$method} 不存在。");
            }
        } else {

            return sprintf("未知类型 {$tagName}。");
        }
    }

    /**
     * 执行数据保存操作
     *
     * @param void
     * @return mixed
     */
    public function createRole()
    {
        //参数多使用 POST 来传送
        if (empty($_POST)) {

            return '请使用 POST 方式进行数据提交！';
        }
        $tagName = strtolower(trim($_REQUEST['type_id']));
        $method  = 'createRole';
        return $this->doMethod($tagName, $method, $_POST);
    }

    /**
     * 获取所有用于审单的订单分组
     *
     * @param void
     * @return mixed
     */
    public function getAutoOrderTypes()
    {

        return kernel::database()->select("SELECT ot.*, at.config AS confirm_config FROM sdb_omeauto_order_type as ot LEFT JOIN sdb_omeauto_autoconfirm as at ON ot.oid=at.oid WHERE ot.oid > 0 AND ot.disabled = 'false' AND at.disabled='false' ORDER BY weight DESC, tid ASC");
    }

    /**
     * 获取所有用于仓库选择的订单分组
     *
     * @param void
     * @return mixed
     */
    public function getAutoBranchTypes()
    {
        return kernel::database()->select("SELECT ot.tid,ot.oid,ot.did,ot.config,ot.name,ot.memo,ot.weight,ot.delivery_group,ot.group_type,ot.disabled,bt.bid,bt.is_default FROM sdb_omeauto_order_type as ot LEFT JOIN sdb_omeauto_autobranch as bt ON ot.tid=bt.tid WHERE ot.group_type in ('branch') AND ot.disabled = 'false'   ORDER BY bt.is_default DESC,ot.weight DESC,bt.weight DESC");
    }

    /**
     * 获取所有用于hold单选择的订单分组
     *
     * @param void
     * @return mixed
     */
    public function getAutoHoldTypes()
    {
        return kernel::database()->select("SELECT ot.tid,ot.oid,ot.did,ot.config,ot.name,ot.memo,ot.weight,ot.delivery_group,ot.group_type,ot.disabled,ht.hold,ht.hours FROM sdb_omeauto_order_type as ot LEFT JOIN sdb_omeauto_autohold as ht ON ot.tid=ht.tid WHERE ot.group_type in ('hold') AND ot.disabled = 'false'   ORDER BY ot.weight DESC");
    }

    /**
     * 获取所有用于分派选择的订单分组
     *
     * @param void
     * @return mixed
     */
    public function getAutoDispatchTypes()
    {

        return kernel::database()->select("SELECT ot.* FROM sdb_omeauto_order_type as ot LEFT JOIN sdb_omeauto_autodispatch as bt ON ot.did=bt.oid WHERE ot.did > 0 AND ot.disabled = 'false' AND bt.disabled='false' ORDER BY ot.weight DESC, ot.tid ASC");
    }

    /**
     * 获取所有用于打印的订单分组
     *
     * @param void
     * @return mixed
     */
    public function getDeliveryGroupTypes()
    {

        return kernel::database()->select("SELECT * FROM sdb_omeauto_order_type WHERE disabled = 'false' AND delivery_group='true' ORDER BY weight DESC, tid ASC");
    }

    /**
     * 获取所有用于短信发送的订单分组
     *
     * @param void
     * @return mixed
     */
    public function getAutoSendSmsTypes()
    {

        return kernel::database()->select("SELECT * FROM sdb_omeauto_order_type WHERE disabled = 'false' AND group_type='sms' ORDER BY weight DESC, tid ASC");

    }
}
