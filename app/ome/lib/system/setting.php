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

class ome_system_setting{
    /**
     * 配置项
     * 
     * @var string
     * */
    private $_setting_tab = array(
        array('name' => '订单配置', 'file_name' => 'admin/system/setting/tab_order.html', 'app' => 'ome', 'order' => 1),
        array('name' => '仓储采购', 'file_name' => 'admin/system/setting/tab_storage.html', 'app' => 'ome', 'order' => 10),
//         array('name' => '预处理配置', 'file_name' => 'admin/system/setting/tab_preprocess.html', 'app' => 'ome', 'order' => 20),
        array('name' => '订单复审设置', 'file_name' => 'admin/system/setting/tab_retrial.html', 'app'=>'ome', 'order' => 90),
        array('name' => '自动审单配置', 'file_name' => 'admin/system/setting/tab_consign.html', 'app'=>'ome', 'order' => 95),
        array('name' => '拆单配置', 'file_name' => 'admin/system/setting/tab_ordersplit.html', 'app'=>'ome', 'order' => 98),
        array('name' => '财务配置', 'file_name' => 'admin/system/setting/tab_finance.html', 'app'=>'ome', 'order' => 99),
        array('name' => '其他配置', 'file_name' => 'admin/system/setting/tab_other.html', 'app'=>'ome', 'order' => 100),
    );

        /**
     * 获取_setting_tab
     * @return mixed 返回结果
     */
    public function get_setting_tab()
    {
        return $this->_setting_tab;
    }

    /**
     * view
     * @return mixed 返回值
     */
    public function view(){
        $settings = $this->all_settings();
        foreach($settings as $set){
            $key = str_replace('.','_',$set);
            $setData[$key] = app::get('ome')->getConf($set);
        }

        $render = kernel::single('base_render');

        $render->pagedata['setData'] = $setData;
        $render->pagedata['branchCount'] = $this->getBranchMode();

        $html = $render->fetch('admin/system/setting.html','ome');
        return $html;
    }

    /**
     * all_settings
     * @return mixed 返回值
     */
    public function all_settings(){
        $all_settings =array(
            'ome.branch.mode',
            'ome.order.failtime',
            'ome.api_log.clean_time',
            'ome.order.unconfirmtime',
            'ome.product.serial.merge',
            //'ome.delivery.consign',
            //'ome.delivery.check_type',
            'ome.delivery.check_show_type',
            'ome.batch_print_nums',
            'ome.delivery.check_ident',
            'ome.delivery.weight',
            'ome.delivery.logi',//设置快递单与称重的顺序
            'ome.delivery.check_delivery',//校验后，直接发货
            'ome.delivery.minWeight',
            'ome.delivery.maxWeight',
            'ome.delivery.sellagent',#分销王订单是否打印代销人
            'ome.product.serial.merge',
            'ome.product.serial.separate',
            'ome.checkems',
            'ome.getOrder.intervalTime',
            'ome.payment.confirm',
            'ome.delivery.method',
            'ome.delivery.wuliubao',
            'ome.delivery.hqepay',
            'ome.order.mark',
            'ome.combine.member_id',            // 新增合并购买人
            'ome.combine.shop_id',              // 合并店铺
            'ome.preprocess.tbgift',
            'ome.combine.memberidconf',
            'ome.combine.addressconf',
            'desktop.finder.tab',
            'desktop.finder.tab.count.expire',
            'ome.delivery.checknum.show',
            'ome.delivery.consignnum.show',
            'ome.delivery.back_node',
            'auto.setting',
            'purchase.stock_confirm',
            'purchase.stock_cancel',
            'taoguanallocate.appropriation_type',
            'purchase.po_type',
            'purchase.stock.stockset',
            'ome.orderpause.to.syncmarktext',   // 同步订单备注暂停操作配置
            'ome.product.serial.delivery',
            'ome.combine.select',
            'ome.combine.merge.limit', //自动合单条数限制
            'ome.logi.arrived',//物流配送判断是否开
            'ome.logi.arrived.auto',//自动审单是否拦截
            //'ome.jzorder.delivery',//是否开启家装订单发货
            'ome.order.is_retrial',//是否对修改订单进行复审
            'ome.order.retrial',//复审规则
            'ome.order.clean_day',//复审日志保留天数
            'ome.order.is_monitor',//是否开启价格监控
            'ome.order.monitor.ordergoods',//整单or子单监控
            'ome.order.cost_multiple',//成本价倍数
            'ome.order.sales_multiple',//销售价倍数
            'ome.order.retrial_gift',//复审是否过滤掉订单赠品
            'ome.order.is_auto_combine',//是否开启系统自动审核
            'ome.order.is_auto_ordertaking',//系统获取订单
            'ome.order.pre_sel_branch',//订单提前选仓
            'ome.order.is_merge_order',//是否忽略可合并的订单
            'ome.order.auto_timer',//是否指定时间段自动审单
            'ome.order.auto_exec_timer',//自动审单的指定时间段的时间范围
            'ome.apifail.retry',
            'ome.orderrpc.mq',
            'ome.wmsrpc.mq',
            'ome.callback.mq',
            'ome.kernel.log',
            'ome.order.split',//是否启动拆单
            'ome.order.split.gift',//是否拆分礼品
            'ome.order.split_model',//拆单方式
            'ome.order.split_type',//拆单回写方式
            'ome.reship.auto_finish',
            'ome.reship.diff_refuse',//退货入库有差异不接收
            'tbo2o.shop.setting',//淘宝O2O配置
            'ome.hcsafe.config',//御城河
            'ome.iostock.auto_finish',
            'finance.setting.init_time',//财务对账
            'ome.cn.order.Auto',
            'ome.cn.order.Auto.bindshop',
            'ome.platform.order.consign',//自发货
            'ome.saas.apifail_mq',
            'ome.order.presale',
            'ome.order.presalemoney',
            'ome.order.presaleconfirm',//预售订单付定金能否审单
            'ome.order.presale.combine',//预售订单是否合并
            'ome.order.presale.hold', //预售订单是否开启hold单
            'ome.order.refund.check',//订单是否存在退款
            'desktop.password.reset.cycle',//强制重置密码周期
            'desktop.password.length.limit',//密码长度限制
            'desktop.account.mobile.verify',//强制账号二次验证
            'desktop.account.use.limit',//90天未登录账号 自动禁用
            'desktop.account.error.freeze',//错5次密码冻结10分钟
            'desktop.account.equal.restrict', //相同账号登录限制
            'ome.sensitive.data.encrypt',
            'ome.sensitive.exportdata.encrypt',
            'pam.passport.oidc.enable',
            'pam.passport.oidc.info',
            'pam.passport.idaas.enable',
            'ome.change.order_freeze',//换货单预占冻结设置
            'wms.snapshot.dailystock', //奇门WMS库存快照日盘表
            'wms.stock.quantity.open', //库存异动开关
            'wms.stock.inventory.finish.auto',//盘点自动完成
            'ome.delivery.retry_push', //发货单推送失败
            'ome.aftersales.auto_finish',
            'ome.vopbill.set',
            'taoguaninventory.quantity.mode',//盘点模式
            'ome.refund.refuse.request',//退款单是否请求前端
            'ome.task.logistestimate', //物流对帐设置
            'ome.reship.refund.only.reship',//未签收时售后仅退款转售后退货
            'stockdump.auto.finish', // 库内转储，同wms仓是否自动完成
            'ome.get.all.status.order',//获取全状态订单
            'ome.combine.manual.merge', //是否手工合单
            'ome.order.get.payment', //获取实付
            'ome.call.mobile.group', //报备外呼主叫号码组
        );
        
        // setting
        $setting = [];
        
        // loading
        @include(app::get('ome')->app_dir.'/setting.php');

        $all_settings = array_merge($all_settings, (array) array_keys($setting));

        return $all_settings;
    }

    /**
     * 获取BranchMode
     * @return mixed 返回结果
     */
    public function getBranchMode(){
        $oBranch = app::get('ome')->model('branch');
        $con = count( $oBranch->Get_branchlist());
        return $con;
    }

    /**
     * 保存Conf
     * @param mixed $settings settings
     * @return mixed 返回操作结果
     */
    public function saveConf($settings)
    {
        $all_settings = $this->all_settings();

        foreach ($settings as $set => $value) {
            $old_setting = app::get('ome')->getConf($set);

            if ($old_setting != $value && in_array($set, $all_settings)) {
                app::get('ome')->setConf($set,$value);
            }
        }
    }

    /**
     * 获取_setting_data
     * @return mixed 返回结果
     */
    public function get_setting_data()
    {
        $setData = array();

        $all_settings = $this->all_settings();

        foreach($all_settings as $set){
            $key = str_replace('.','_',$set);
            $setData[$key] = app::get('ome')->getConf($set);
        }

        return $setData;
    }
}
