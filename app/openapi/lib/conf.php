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

class openapi_conf
{

    public static function getMethods()
    {
        $return = array(
            'sales'             => array(
                'label'   => '销售单',
                'methods' => array(
                    'getList'         => '销售单列表',
                    'getSalesAmount'  => '销售金额',
                    'getDeliveryList' => '销售发货明细',
                    'getGxList' => '供销销售单',
                ),
            ),
            'aftersales'        => array(
                'label'   => '售后单',
                'methods' => array(
                    'getList' => '售后单列表',
                    'getGxList' => '供销售后单',
                ),
            ),
            'iostock'           => array(
                'label'   => '出入库明细',
                'methods' => array(
                    'getList' => '出入库明细列表',
                ),
            ),
            'workorder'           => array(
                'label'   => '加工单',
                'methods' => array(
                    'getList' => '加工单列表',
                ),
            ),
            'po'                => array(
                'label'   => '采购单',
                'methods' => array(
                    'add'     => '新建采购单',
                    'getList' => '采购单信息',
                    'cancel'  => '取消采购单',
                ),
            ),
            'transfer'          => array(
                'label'   => '出入库单',
                'methods' => array(
                    'add'           => '新建出入库单',
                    'getList'       => '出入库单明细列表',
                    'getIsoList'    => '出入库单列表',
                ),
            ),
            'shop' => array(
                'label'   => '店铺',
                'methods' => array(
                    'getList' => '店铺列表',
                    'add'     => '新建店铺',
                ),
            ),
            'supplier' => array(
                'label'   => '供应商',
                'methods' => array(
                    'add'     => '新建供应商',
                    'edit'    => '编辑供应商',
                ),
            ),
            'member' => array(
                'label'   => '会员',
                'methods' => array(
                    'add'     => '新建会员',
                    'edit'    => '编辑会员',
                ),
            ),
            'stock'             => array(
                'label'   => '库存查询',
                'methods' => array(
                    'getAll'          => '商品库存',
                    'getDetailList'   => '仓库库存',
                    'getBarcodeStock' => '条形码的库存信息',
                    'getBnStock'      => '货品的库存信息',
                ),
            ),
            'invoice'           => array(
                'label'   => '发票操作',
                'methods' => array(
                    'getList'       => '发票列表',
                    'update'        => '更新纸质发票的打印信息',
                    'getResultList' => '获取开票结果列表',
                ),
            ),
            'basicmaterial'     => array(
                'label'   => '基础物料',
                'methods' => array(
                    'getList' => '基础物料列表',
                    'add'     => '新建基础物料',
                    'edit'    => '编辑基础物料',
                ),
            ),
            'salesmaterial'     => array(
                'label'   => '销售物料',
                'methods' => array(
                    'getList' => '销售物料列表',
                    'add'     => '新建销售物料',
                    'edit'    => '编辑销售物料',
                ),
            ),
            'delivery'          => array(
                'label'   => '发货单',
                'methods' => array(
                    'getList' => '发货单列表',
                ),
            ),
            'stockdump'          => array(
                'label'   => '转储单',
                'methods' => array(
                    'getList' => '转储单列表',
                ),
            ),
            'purchasereturn'    => array(
                'label'   => '采购退货单',
                'methods' => array(
                    'getList' => '采购退货单列表',
                    'add'     => '新建采购退货单',
                    'cancel'  => '取消采购退货单',
                ),
            ),
            'appropriation'     => array(
                'label'   => '调拨单',
                'methods' => array(
                    'getList' => '调拨单列表',
                    'add'     => '新建调拨单',
                ),
            ),
            'orders'            => array(
                'label'   => '订单',
                'methods' => array(
                    'getList' => '订单列表',
                    // 'decrypt' => '敏感数据解密',
                    'getCouponList' => '订单优惠明细',
                    'getPmtList' => '查询订单整单优惠信息',
                ),
            ),
            'branch'            => array(
                'label'   => '仓库',
                'methods' => array(
                    'getList' => '仓库列表',
                    // 'decrypt' => '敏感数据解密',
                ),
            ),
            'brand'            => array(
                'label'   => '仓库',
                'methods' => array(
                    'getList' => '品牌列表',
                ),
            ),
            'finance'           => array(
                'label'   => '财务',
                'methods' => array(
                    'getList' => '支付账单列表',
                    'getJZT' => '精准通账单列表',
                    'getJDbill' => '京东钱包流水列表',
                    'getReportItems' => '账期明细列表',
                    'getExpensesSplitList' => '获取拆分结果明细',
                ),
            ),
            'pda_user'          => array(
                'label'   => '用户信息',
                'methods' => array(
                    'confirm' => '用户认证',
                    'cancel'  => '用户退出',
                ),
                'group'   => 'pda',
            ),
            'pda_delivery'      => array(
                'label'   => '发货单',
                'methods' => array(
                    'getList'      => '发货单列表',
                    'updateStatus' => '更新发货单状态',
                    'printCPCL'    => '发货单打印',
                    'check'        => '发货单校验',
                    'batchCheck'   => '批量发货校验',
                    'consign'      => '发货单发货',
                ),
                'group'   => 'pda',
            ),
            'pda_pick'          => array(
                'label'   => '拣货',
                'methods' => array(
                    'getDelivery'   => '领取发货单通知接口',
                    'bindbox'       => '发货单篮子号绑定',
                    'getPickinList' => '获取备货单',
                    'getStockList'  => '获取备货单列表',

                ),
                'group'   => 'pda',
            ),
            'pda_iostock'       => array(
                'label'   => '入库',
                'methods' => array(
                    'getList' => '出入库单查询',
                    'confirm' => '出入库单确认',
                ),
                'group'   => 'pda',
            ),
            'pda_inventory'     => array(
                'label'   => '盘点',
                'methods' => array(
                    'create'          => '新建盘点任务',
                    'update'          => '盘点更新',
                    'getList'         => '获取盘点列表',
                    'getExpireBnInfo' => '获取保质期批次',
                    'getStorageLife'  => '获取关联的保质期列表',
                ),
                'group'   => 'pda',
            ),
            'pda_product'       => array(
                'label'   => '货品',
                'methods' => array(
                    'position' => '货位整理',
                    'getList'  => '商品查询接口',
                ),
                'group'   => 'pda',
            ),
            'pda_branch'        => array(
                'label'   => '仓库',
                'methods' => array(
                    'getList' => '仓库查询',
                ),
                'group'   => 'pda',
            ),
            'pda_stock'         => array(
                'label'   => '库存查询',
                'methods' => array(
                    'getAll'        => '商品库存查询',
                    'getDetailList' => '货位库存查询',
                ),
                'group'   => 'pda',
            ),
            'pda_abnormalcause' => array(
                'label'   => '异常原因',
                'methods' => array(
                    'getList' => '异常原因列表',
                ),
                'group'   => 'pda',
            ),
            'pda_reship'        => array(
                'label'   => '退换货单',
                'methods' => array(
                    'getList'        => '退换货列表',
                    'getDetailList'  => '退换货明细',
                    'normalReturn'   => '正常退货接口',
                    'abnormalReturn' => '异常退货明细',
                    'printReturn'    => '售后信息打印',
                    'forward'        => '退货单转寄',
                ),
                'group'   => 'pda',
            ),
            'pda_setting'       => array(
                'label'   => '配置',
                'methods' => array(
                    'check' => '连接测试',
                ),
                'group'   => 'pda',
            ),
            'middleware_export' => array(
                'label'   => '导出任务',
                'methods' => array(
                    'add'     => '任务添加/更新',
                    'getList' => '获取导出列表',
                ),
                'group'   => 'middleware',
            ),
            'inventory'             => array(
                'label'   => '盘点单',
                'methods' => array(
                    'getList'         => '盘点单列表带明细',
                    'getApplyDetail'       => '单个盘点单申请明细',
                    'getApplyList'  => '盘点单申请列表',
                    'getShopSkuList' => '店铺货品列表',
                    'getShopStockList' => '店铺回写库存列表',
                ),
            ),
            'warehouse' => array(
                'label'   => '转仓单',
                'methods' => array(
                    'add'     => '新建转仓单',
                    'getList' => '转仓单列表',
                ),
            ),
            'refunds' => array(
                'label'   => '退款单',
                'methods' => array(
                    'getList' => '退款单列表',
                    'getDetailList' => '退款单明细',
                ),
            ),
            'ar' => array(
                'label'   => '应收应退',
                'methods' => array(
                    'getList' => '应收应退单列表',
                ),
            ),
        );
        foreach(kernel::servicelist('openapi.conf') as $item) {
            if(method_exists($item, 'getMethods')) {
                $methods = $item->getMethods();
                $return = array_merge($return, $methods);
            }
        }
        return $return;
    }
}
