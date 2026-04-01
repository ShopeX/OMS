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
 * 订单按分组类型的组合
 */
class omeauto_auto_group {

    /**
     * 该分类下的所有订单组
     *
     * @var array
     */
    private $items = array();

    /**
     * 对应的订单分组规则对像
     *
     * @var Object
     */
    private $filter = array();

    /**
     * 配置信息
     *
     * @var array
     */
    private $config = null;

    /**
     * 审单规则配置
     *
     * @var Array
     */
    private $confirmRoles = null;

    /**
     * 审单插件对像
     *
     * @var Array
     */
    static $_plugObjects = array();

    /**
     * 是否缺省订单组
     *
     * @var boolean
     */
    private $isDefault = false;

    /**
     * 设置过滤规则
     *
     * @param array $config
     * @return void
     */
    function setConfig($config) {

        $this->isDefault = false;
        //想办法加上键值判断
        $this->config = $config;
        //删除过渡器
        $this->clearFilters();
        $roles = unserialize($config['config']);
        //创建Filters对像
        foreach ($roles as $role) {
            $role = json_decode($role, true);
            if (is_array($role)) {
                $className = sprintf('omeauto_auto_type_%s', $role['role']);
                $filter = new $className();
                $filter->setRole($role['content']);
                $this->filter[] = $filter;
            }
        }
    }

    function getConfig() {
        if($this->config){
            return $this->config;
        }
        return array();
    }

    /**
     * 设置缺省规则
     *
     * @param void
     * @return void
     */
    function setDefault() {

        $this->isDefault = true;
        $this->config = $this->getDefaultRoles();
        $this->clearFilters();
    }

    /**
     * 返回规则是否默认
     * @return void
     */
    public function getDefault()
    {
        return $this->isDefault;
    }

    /**
     * 增加订单组
     *
     * @param omeauto_auto_group_item $item
     * @return void
     */
    function addItem($item) {

        //想办法加上键值判断
        $this->items[] = $item;
    }

    /**
     * 检查是否是该类型的订单
     *
     * @param Object $item
     * @return boolean
     */
    function vaild($item) {
        if (!empty($this->filter)) {
            foreach ($this->filter as $filter) {
                if (!$filter->vaild($item)) {
                    return false;
                }
            }
            return true;
        } else {

            if ($this->isDefault) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * 执行该规则对应的审单规则
     *
     * @param void
     * @return mixed
     */
    public function process($inletClass) {

        // 如果没有订单项，直接返回空结果
        if (empty($this->items)) {
            return array('total' => 0, 'succ' => 0, 'fail' => 0);
        }

        $confirmRoles = $this->getRoles();
        $confirmRoles['inlet_class'] = $inletClass;
        $plugins = $this->getPluginNames($inletClass);

        // 获取第一个订单号用于记录
        $originalBn = '';
        if (!empty($this->items)) {
            $orders = $this->items[0]->getOrders();
            $firstOrder = current($orders);
            $originalBn = $firstOrder['order_bn'] ?? '';
        }

        // 开始性能监控
        ome_api_log::record_start('订单审核', $originalBn, 'omeauto_auto_group');

        $pluginTimings = array();

        foreach ($plugins as $plugName) {

            $plugObj = $this->initPlugin($plugName);
            if (is_object($plugObj)) {
                foreach ((array) $this->items as $key => $item) {
                    // 记录单个插件执行开始时间
                    $pluginStartTime = microtime(true);
                    
                    $pluginResult = $plugObj->process($this->items[$key], $confirmRoles);
                    
                    // 如果插件返回了性能数据，记录到性能日志
                    if (is_array($pluginResult) && isset($pluginResult['execution_time'])) {
                        ome_api_log::add_message('plugin', $plugName, $pluginResult['execution_time'], $pluginResult);
                        $pluginExecutionTime = $pluginResult['execution_time'];
                    } else {
                        // 否则使用外层计算的时间
                        $pluginExecutionTime = microtime(true) - $pluginStartTime;
                        ome_api_log::add_message('plugin', $plugName, $pluginExecutionTime, array('total_orders' => count($this->items)));
                    }
                    
                    // 累加插件执行时间
                    if (!isset($pluginTimings[$plugName])) {
                        $pluginTimings[$plugName] = 0;
                    }
                    $pluginTimings[$plugName] += $pluginExecutionTime;
                }
            }
        }


        $result = array('total' => 0, 'succ' => 0, 'fail' => 0);
        
        // 记录订单组处理开始时间
        $groupProcessStart = microtime(true);
        
        foreach ((array) $this->items as $key => $group) {
            $result['total'] += $group->orderNums;
            
            $groupResult = $group->process($confirmRoles);
            
            if ($groupResult) {
                $result['succ'] += $group->orderNums;
            } else {
                $result['fail'] += $group->orderNums;
            }
        }
        
        // 计算订单组处理时间
        $groupProcessTime = microtime(true) - $groupProcessStart;

        // 记录订单组性能数据
        ome_api_log::add_message('group', 'order_group', $groupProcessTime, array(
            'total_orders' => $result['total'],
            'success_orders' => $result['succ'],
            'failed_orders' => $result['fail']
        ));

        // 结束性能监控
        ome_api_log::record_end();

        return $result;
    }

    /**
     * 通过插件名获取插件类并返回
     *
     * @param String $plugName 插件名
     * @return Object
     */
    private function & initPlugin($plugName) {

        $fullPluginName = sprintf('omeauto_auto_plugin_%s', $plugName);
        $fix = md5(strtolower($fullPluginName));

        if (!isset(self::$_plugObjects[$fix])) {

            $obj = new $fullPluginName();
            if ($obj instanceof omeauto_auto_plugin_interface) {

                self::$_plugObjects[$fix] = $obj;
            }
        }
        return self::$_plugObjects[$fix];
    }


    /**
     * @param string $inletClass 入口类区分
     * @return Array
     */
    private function getPluginNames($inletClass) {
        $plugins = array(
            'branch', //先选择仓库
            'routernum',//路由次数
            'split', //拆单
            'checksplitgift',//检查赠品是否独自分拆
            'store', //再判断库存
            'logi', //判定物流
            'reclogi',//推荐物流
            'refundstatus',//订单退款状态
            'abnormal', //数据字段异常订单
            'pay',//判断货到付款是否自动审核
            'arrived',//快递到不到
        );
        switch ($inletClass) {
            case 'combine':
                $plugins[] = 'flag'; //备注和留言
                $plugins[] = 'oversold';//超卖订单
                $plugins[] = 'tbgift';//淘宝订单有赠品
                $plugins[] = 'crm';//crm赠品
                $plugins[] = 'tax';//开发票
                break;
            case 'ordertaking' :
                $plugins[] = 'flag'; //备注和留言
                $plugins[] = 'oversold';//超卖订单
                $plugins[] = 'tbgift';//淘宝订单有赠品
                $plugins[] = 'crm';//crm赠品
                $plugins[] = 'tax';//开发票
                //如果不合单不检查这几个插件
                $combine_select = app::get('ome')->getConf('ome.combine.select');
                if ($combine_select != '1') {
                    $plugins[] = 'member'; //用户多地址
                    $plugins[] = 'ordermulti'; //是否多单合
                    $plugins[] = 'shopcombine'; //同店铺可合并订单
                }
                break;
            default: break;
        }

        return $plugins;
    }

    /**
     * 获取当前组的审单配置信息
     *
     * @param void
     * @return Array
     */
    private function getRoles() {

        //检查定单组配置信息
        if (!empty($this->config) && $this->config['oid'] > 0) {
            //有特定审单规则
            $confirmRoles = app::get('omeauto')->model('autoconfirm')->dump(array('oid' => intval($this->config['oid'])));
            if ($confirmRoles && $confirmRoles['config']) {
                $confirmRoles['config']['confirmName'] = $confirmRoles['name'];
                $confirmRoles['config']['confirmId']   = $confirmRoles['oid'];

                //特定审单规则
                return $confirmRoles['config'];
            } else {
                //缺省规则
                return $this->getDefaultRoles();
            }
        } else {
            //缺省规则
            return $this->getDefaultRoles();
        }
    }

    /**
     * 获取缺省的审单规则
     *
     * @param void
     * @return Array
     */
    public function getDefaultRoles() {

        $config = self::fetchDefaultRoles();

        $isO2oPick = false;
        foreach ((array) $this->items as $group) {
            $isO2oPick = $isO2oPick && $group->isO2oPick();
        }

        if (empty($config) || $isO2oPick) {
            
            //当没有任何审单规则时,默认不自动生成发货单
            $is_autoConfirm = '0';
            
            return array(
                "autoOrders"  => "-1",
                "morder"      => "1",
                "payStatus"   => "1",
                "memo"        => "1",
                "mark"        => "1",
                "autoCod"     => "0",
                "allDlyCrop"  => "1",
                "autoConfirm" => $is_autoConfirm,
                "confirmName" => "系统默认",
                "confirmId"   => "0",
            );
        } else {

            return $config;
        }
    }

    /**
     * 从数据库获取默认审单规则
     *
     * @param void
     * @return Array
     */
    static function fetchDefaultRoles() {

        $configRow = app::get('omeauto')->model('autoconfirm')->dump(array('defaulted' => 'true', 'disabled' => 'false'));

        if ($configRow && $configRow['config']) {
            $configRow['config']['confirmName'] = $configRow['name'];
            $configRow['config']['confirmId']   = $configRow['oid'];
            
            //生效时间范围
            $now_time = time();
            $confirm_config = $configRow['config'];
            if($confirm_config['confirmStartTime'] && $confirm_config['confirmEndTime']){
                if($now_time < $confirm_config['confirmStartTime']){
                    return false; //当前时间小于开始审单时间
                }
                
                if($now_time > $confirm_config['confirmEndTime']){
                    return false; //当前时间大于结束审单时间
                }
            }
            
            //排除时间范围
            if($confirm_config['excludeStartTime'] && $confirm_config['excludeEndTime']){
                if($confirm_config['excludeStartTime']<$now_time && $confirm_config['excludeEndTime']>$now_time){
                    return false; //当前时间在排除审单时间范围内
                }
            }
        }

        return $configRow['config'];
    }

    /**
     * 清除对像
     *
     * @param void
     * @return void
     */
    private function clearFilters() {

        foreach ($this->filter as $key => $value) {

            unset($this->filter[$key]);
        }

        $this->filter = array();
    }


}