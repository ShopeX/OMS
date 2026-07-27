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
 * 单据标签管理Lib类
 *
 * @author maxiaochen@shopex.cn
 * @version $Id: Z
 */
class ome_bill_label
{
    // 颜色取值参考：https://plantuml.com/zh/color
    // 系统内部的请用SOMS_为开头
    public $orderLabelsPreset = [
        'deleteordergift'   => ['label_name' => '退款删赠品失败', 'label_color' => 'Thistle'],
        'priceprotect'      => ['label_name' => '价保订单', 'label_color' => 'LimeGreen'],
        'sf_free_shipping'  => ['label_name' => '顺丰包邮', 'label_color' => 'SkyBlue'],
        'XGJY'              => ['label_name' => '中国香港集运', 'label_color' => 'OliveDrab'],
        'XJJY'              => ['label_name' => '中国新疆中转', 'label_color' => 'OliveDrab'],
        'HSKSTJY'           => ['label_name' => '哈萨克斯坦集运', 'label_color' => 'OliveDrab'],
        'XZJY'              => ['label_name' => '中国西藏中转', 'label_color' => 'OliveDrab'],
        'RBJY'              => ['label_name' => '日本集运', 'label_color' => 'OliveDrab'],
        'TWJY'              => ['label_name' => '中国台湾集运', 'label_color' => 'OliveDrab'],
        'HGJY'              => ['label_name' => '韩国集运', 'label_color' => 'OliveDrab'],
        'XJPJY'             => ['label_name' => '新加坡集运', 'label_color' => 'OliveDrab'],
        'MLXYJY'            => ['label_name' => '马来西亚集运', 'label_color' => 'OliveDrab'],
        'TGJY'              => ['label_name' => '泰国集运', 'label_color' => 'OliveDrab'],
        'use_before_payed'  => ['label_name' => '先用后付', 'label_color' => 'OrangeRed'],
        'quality_check'     => ['label_name' => '重点检查', 'label_color' => 'Tomato', 'label_thumb'=>'重'],
        'priority_delivery' => ['label_name' => '优先发货', 'label_color' => 'Green'],
        'newcarton_package' => ['label_name' => '全新纸箱发货', 'label_color' => 'SteelBlue'],
        'gift_package'      => ['label_name' => '礼盒包装发货', 'label_color' => 'IndianRed'],
        'system_bufa'       => ['label_name' => '延迟补发赠品', 'label_color' => 'DeepPink'],
        'SOMS_JDECARD'      => ['label_name' => '京东E卡', 'label_color' => 'LightSeaGreen'],
        'SOMS_BUFA_REFUND'  => ['label_name' => '补发转退款', 'label_color' => 'OrangeRed'],
        // 系统内部的请用SOMS_为开头
        'SOMS_WFP'          => ['label_name' => '晚发赔', 'label_color' => 'Green'],
        'SOMS_SHSM'         => ['label_name' => '送货上门', 'label_color' => 'Chocolate', 'label_thumb'=>'送'],
        'SOMS_ZFSHSM'       => ['label_name' => '自费送货上门', 'label_color' => 'Chocolate', 'label_thumb'=>'自费送', 'label_to_delivery' => true, 'extend_info_to_delivery' => true],
        'SOMS_IMEI'         => ['label_name' => '发货必传IMEI码', 'label_color' => 'Gold', 'label_to_delivery' => true],
        'SOMS_SERIALNUMBER' => ['label_name' => '发货必传SN码', 'label_color' => 'Gold', 'label_to_delivery' => true],
        'SOMS_EXPRESS_MUST' => ['label_name' => '自选快递', 'label_color' => 'CornflowerBlue'],
        'SOMS_HIGH_EXPRESS' => ['label_name' => '优质快递', 'label_color' => 'GoldenRod'],
        'SOMS_PAID_EXPRESS' => ['label_name' => '加价快递', 'label_color' => 'LightCoral'],
        'SOMS_HOST'         => ['label_name' => '达人', 'label_color' => 'Darkorange'],
        'SOMS_LOTTERY'      => ['label_name' => '抽奖', 'label_color' => 'GoldenRod'],
        'SOMS_GNJY'         => ['label_name' => '国内集运', 'label_color' => 'OliveDrab'],
        'SOMS_GYJY'         => ['label_name' => '国外集运', 'label_color' => 'OliveDrab'],
        'SOMS_JDZD'         => ['label_name' => '京东子单', 'label_color' => 'darkkhaki'],
        'SAMS_RETURN_GAP'   => ['label_name' => '差异入库', 'label_color' => 'sandybrown'],
        'SOMS_LOCAL_WAREHOUSE' => ['label_name' => '本地仓', 'label_color' => 'CadetBlue'],
        'SOMS_OLDCHANGENEW' => ['label_name' => '返修换新', 'label_color' => '#cca4e3'],
        'ORDER_CUSTOMS'     => ['label_name' => '定制', 'label_color' => '#E066FF'], //定制订单
        'SOMS_PRESENT'      => ['label_name' => '送礼', 'label_color' => '#FF6146'],
        'SOMS_PRESALEPARTPAD'=>['label_name' => '预打包', 'label_color' => '#cca4e3'],
        'SOMS_PREPAYED'=>['label_name' => '预售付定金', 'label_color' => '#cca4e3'],
        'SOMS_MREFUND'=>['label_name' => '秒退', 'label_color' => '#fd6194'],
        'SOMS_QN_DISTR'      => ['label_name' => '清仓', 'label_color' => '#ff6f61'],
        'SOMS_CHANGE_CANCEL'      => ['label_name' => '换货取消', 'label_color' => '#cca4e3'],
        'SOMS_UPDATE_ITEM'  => ['label_name' => '改SKU', 'label_color' => 'PaleVioletRed'],//#DB7093
        'SOMS_MATERIAL_REPLACE' => ['label_name' => '物料替换', 'label_color' => 'MediumSlateBlue', 'label_thumb' => '替换'],
        'SOMS_COMBINE_ORDER'  => ['label_name' => '合单', 'label_color' => 'Salmon', 'label_to_delivery' => true],//#FA8072
        'SOMS_GXD'          => ['label_name' => '工小达', 'label_color' => 'MediumTurquoise','label_to_delivery' => true],
        'SOMS_GB'           => ['label_name' => '国补', 'label_color' => 'Coral', 'label_thumb'=>'国补'],
        'SOMS_FENXIAO'      => ['label_name' => '分销订单', 'label_color' => 'Peru'],
        'SOMS_ASCP'         => ['label_name' => '天猫供应链', 'label_color' => 'DarkOrchid'],
        'SOMS_WEIPAI'      => ['label_name' => '微派服务', 'label_color' => '#cca4e3', 'label_thumb'=>'微','label_desc'=>'该订单已签署顺丰微派服务，请与物流服务商（如顺丰）确认获取现场拆封激活拍照的核验照片'],
        'SOMS_FUKUBUKURO'  => ['label_name' => '福袋', 'label_color' => '#FFD700', 'label_thumb'=>'福袋'],
        'SOMS_FULLPAY_PRESALE'  => ['label_name' => '全款预售', 'label_color' => 'LightCoral', 'label_thumb'=>'全预'],
        'SOMS_GIFT_ORDER_STATUS'  => ['label_name' => '含赠品', 'label_color' => '#cca4e3', 'label_thumb'=>'含赠品'],
        'SOMS_ISDELIVERY'  => ['label_name' => '禁发', 'label_color' => '#DC2626', 'label_thumb'=>'禁发'],
        'SOMS_LOGISTICS'  => ['label_name' => '承诺达', 'label_color' => '#1E40AF', 'label_thumb'=>'物流升级'],
        'SOMS_XSDBC'       => ['label_name' => '小时达', 'label_color' => '#FF4500'],
        'SOMS_GIFT_RELATED_ORDER'  => ['label_name' => '赠品子单', 'label_color' => '#cca4e3', 'label_thumb'=>'赠品子单'],
        'SOMS_YPDS'        => ['label_name' => '百亿补贴', 'label_color' => '#FF6B6B', 'label_thumb'=>'百补'],
        'SOMS_SUPERLINK'   => ['label_name' => '超链', 'label_color' => '#FF6B6B', 'label_thumb'=>'超链'],

        'SOMS_TMYP' => ['label_name' => '天猫优品国补', 'label_color' => 'Coral', 'label_thumb'=>'天猫优品国补'],
        'SOMS_LARGE_APPLIANCE' => ['label_name'=>'大家电', 'label_color'=>'#c25975', 'label_to_delivery'=>true],
        'SOMS_PICKUP'          => ['label_name' => '物流提货', 'label_color' => 'Teal'],
        'SOMS_URGENT_SHIP'     => ['label_name' => '加急发货', 'label_color' => 'OrangeRed', 'label_to_delivery' => true, 'label_thumb' => '加急'],
    ];
    // 小标
    public $labelValuePreset = [
        'quality_check' => [
            0x0001 => ['label_name' => '一换'], // 一换质检
            0x0002 => ['label_name' => '二换'], // 二换质检
            0x0004 => ['label_name' => '退换重拍同品'], // 退换货重拍同商品
            0x0008 => ['label_name' => '品相敏感'], // 商品品相敏感
        ],
        'SOMS_SHSM' => [
            0x0001 => ['label_name' => '大件'],
            0x0002 => ['label_name' => '中小件'],
            0x0004 => ['label_name' => '修改为不支持'],
        ],
        'SOMS_GNJY' => [
            0x0001 => ['label_name' => '新疆'],
            0x0002 => ['label_name' => '西藏'],
            0x0004 => ['label_name' => '甘肃'],
            0x0008 => ['label_name' => '内蒙古'],
            0x0010 => ['label_name' => '宁夏'],
            0x0020 => ['label_name' => '青海'],
            0x0040 => ['label_name' => '香港'],
            0x0080 => ['label_name' => '台湾'],
            0x0100 => ['label_name' => '澳门'],
        ],
        'SOMS_GYJY' => [
            0x0001 => ['label_name' => '哈萨克斯坦'],
            0x0002 => ['label_name' => '日本'],
            0x0004 => ['label_name' => '韩国'],
            0x0008 => ['label_name' => '新加坡'],
            0x0010 => ['label_name' => '马来西亚'],
            0x0020 => ['label_name' => '泰国'],
            0x0040 => ['label_name' => '越南'],
            0x0080 => ['label_name' => '吉尔吉斯斯坦'],
            0x0100 => ['label_name' => '乌兹别克斯坦'],
            0x0200 => ['label_name' => '柬埔寨'],
            0x0400 => ['label_name' => '老挝'],
            0x0800 => ['label_name' => '塔吉克斯坦'],
            0x1000 => ['label_name' => '亚美尼亚'],
            0x2000 => ['label_name' => '格鲁吉亚'],
            0x4000 => ['label_name' => '蒙古'],
            0x8000 => ['label_name' => '加拿大'],
        ],
        'SOMS_GXD'  => [
            0x0001 => ['label_name' => '平台结算'],
            0x0002 => ['label_name' => '自行结算'],
        ],
        'SOMS_GB' => [
            0x0001 => ['label_name' => '支付立减'],
            0x0002 => ['label_name' => '下单立减'],
            0x0004 => ['label_name' => '一品卖多地'],
            0x0008 => ['label_name' => '一店多主体'],
            0x0010 => ['label_name' => '国补供销'],
            0x0020 => ['label_name' => '国补自销'],
            0x0040 => ['label_name' => '需采集SN码'],
            0x0080 => ['label_name' => '需采集IMEI码'],
            0x0100 => ['label_name' => '需校验SN码'],
            0x0200 => ['label_name' => '需校验IMEI码'],
            0x0400 => ['label_name' => '专项补贴'],
            0x0800 => ['label_name' => '天猫优品'],
            0x2000 => ['label_name' => '纯商家出资'],
            0x4000 => ['label_name' => '平台出资'],
            0x8000 => ['label_name' => '平台和商家混合出资'],
            0x10000 => ['label_name' => '政府和商家混合出资'],
            0x20000 => ['label_name' => '政府、商家和平台三方混合出资'],
        ],
        'SOMS_ASCP' => [
            0x0001 => ['label_name' => '猫享商仓鸟配'],
            0x0002 => ['label_name' => '国际商仓鸟配'],
            0x0004 => ['label_name' => '猫享商仓顺丰配'],
            0x0008 => ['label_name' => '喵速达商仓顺丰配'],
        ],
        'SOMS_ISDELIVERY'=>[
            0x0001 => ['label_name' => '赠品订单未创建'],
            0x0002 => ['label_name' => '骗补订单'],

        ],
        'SOMS_LOGISTICS'=>[
            0x0001 => ['label_name' => '次日达'],
            0x0002 => ['label_name' => '隔日达'],
            0x0004 => ['label_name' => '商家承诺送达'],

        ],
        'SOMS_XSDBC' => [
            0x0001 => ['label_name' => '第三方运力'],
            0x0002 => ['label_name' => '商家自配运力'],
            0x0004 => ['label_name' => '平台运力'],
            0x0008 => ['label_name' => '城市仓配'],
        ],
        'SOMS_YPDS' => [
            0x0001 => ['label_name' => '百补超级半托管'],
        ],
        'SOMS_PICKUP' => [
            0x0001 => ['label_name' => '官方'],
        ],
        'SOMS_PAID_EXPRESS' => [
            0x0001 => ['label_name' => '加价选顺丰标准'],
            0x0002 => ['label_name' => '加价选顺丰特快'],
        ],
    ];

    // // 单据类型对应的model，可用于检测bill_id是否有效
    // private $billTypeAll = [
    //     // 订单表
    //     'order'                   => [
    //         'app'   => 'ome',
    //         'model' => 'orders',
    //         'id'    => 'order_id',
    //     ],
    //     // vop拣货单表
    //     'pick_bill'               => [
    //         'app'   => 'purchase',
    //         'model' => 'pick_bills',
    //         'id'    => 'bill_id',
    //     ],
    //     // vop拣货单明细表
    //     'pick_bill_item'          => [
    //         'app'   => 'purchase',
    //         'model' => 'pick_bill_items',
    //         'id'    => 'bill_item_id',
    //     ],
    //     // vop出库单表
    //     'pick_stockout_bill'      => [
    //         'app'   => 'purchase',
    //         'model' => 'pick_stockout_bills',
    //         'id'    => 'stockout_id',
    //     ],
    //     // vop出库单明细表
    //     'pick_stockout_bill_item' => [
    //         'app'   => 'purchase',
    //         'model' => 'pick_stockout_bill_items',
    //         'id'    => 'stockout_item_id',
    //     ],
    // ];

    // 集运编码
    public $consolidateTypeBox = [
        'XGJY', // 中国香港集运
        'XJJY', // 中国新疆中转
        'HSKSTJY', // 哈萨克斯坦集运
        'XZJY', // 中国西藏中转
        'RBJY', // 日本集运
        'TWJY', // 中国台湾集运
        'HGJY', // 韩国集运
        'XJPJY', // 新加坡集运
        'MLXYJY', // 马来西亚集运
        'TGJY', // 泰国集运
        'SOMS_GNJY', // 国内集运
        'SOMS_GYJY', // 国外集运
    ];

    /**
     * 给单据打标签
     * @param $bill_id  单据id
     * @param $label_id   标签id
     * @param $label_code   标签code label_id和label_code二填一,常用label_code
     * @param $bill_type    单据类型
     * @param $error_msg
     * @param $label_value  相同label_code下的二进制小标，比如quality_check
     * @param $extend_info  JSON格式简易的扩展信息，字段类型：varchar(255)
     * @return bool
     */
    public function markBillLabel($bill_id, $label_id = '', $label_code = '', $bill_type = 'order', &$error_msg = null, $label_value = 0, $extend_info='')
    {
        if (!$bill_id) {
            $error_msg = '单据id为空!';
            return false;
        }
        // if (!$bMdl = $this->billTypeAll[$bill_type]) {
        //     $error_msg = '无效的单据类型!';
        //     return false;
        // }
        // $billInfo = app::get($bMdl['app'])->model($bMdl['model'])->db_dump([$bMdl['id'] => $bill_id]);
        // if (!$billInfo) {
        //     $error_msg = '无效的单据id!';
        //     return false;
        // }

        if (!$label_code) {
            if (!$label_id) {
                $error_msg = '标签id为空!';
                return false;
            }

            $labelMdl  = app::get('omeauto')->model('order_labels');
            $labelInfo = $labelMdl->db_dump(['label_id' => $label_id]);
        } else {
            $labelInfoDefault = [
                'label_name'  => $label_code,
                'label_code'  => $label_code,
                'label_color' => '#67757c', // 默认是系统头部的灰色
            ];

            $preset = $this->orderLabelsPreset[$label_code];
            if ($preset) {
                $labelInfo = array_merge($preset, ['label_code' => $label_code]);
            } else {
                $labelInfo = $labelInfoDefault;
            }
            $labelInfo = $this->doLabel($labelInfo);
        }

        if (!$labelInfo) {
            $error_msg = '标签信息为空!';
            return false;
        }

        //打标记
        $saveData = array(
            'bill_type'   => $bill_type,
            'bill_id'     => $bill_id,
            'label_id'    => $labelInfo['label_id'],
            'label_name'  => $labelInfo['label_name'],
            'label_code'  => $labelInfo['label_code'],
            'label_value' => $label_value?:0,
            'extend_info' => $extend_info,
            'create_time' => time(),
            'label_desc'  => $labelInfo['label_desc'],
        );
        $billLabelMdl = app::get('ome')->model('bill_label');
        $isCheck      = $billLabelMdl->db_dump(['bill_type' => $bill_type, 'bill_id' => $bill_id, 'label_id' => $labelInfo['label_id']], 'id,label_value,extend_info');
        if (!$isCheck) {
            $billLabelMdl->insert($saveData);
        } elseif ($isCheck['label_value'] != $label_value || (string)$isCheck['extend_info'] !== (string)$extend_info) {
            $billLabelMdl->update($saveData, ['id' => $isCheck['id']]);
        }

        return true;
    }

    public function doLabel($info)
    {
        if (!$info['label_code']) {
            return false;
        }
        !$info['label_name'] && $info['label_name'] = $info['label_code'];

        $filter = [
            'label_code' => $info['label_code'],
        ];

        $labelInfo = [
            'label_code'    => $info['label_code'],
            'label_name'    => $info['label_name'],
            'label_color'   => $info['label_color'],
            'source'        => 'system',
            'create_time'   => time(),
            'last_modified' => time(),
            'label_desc'    => $info['label_desc'],
        ];

        $labelMdl = app::get('omeauto')->model('order_labels');
        $res      = $labelMdl->db_dump($filter);
        if ($res) {
            /***
            $labelInfo['label_id'] = $res['label_id'];
            unset($labelInfo['create_time']);
            $labelMdl->update($labelInfo, $filter);
            ***/
            
            // unset
            unset($labelInfo['create_time']);
            
            // 按主键更新，且不把 label_id 放入 SET，避免同 label_code 多行时 UPDATE 主键冲突
            // Duplicate entry 'N' for key 'PRIMARY'
            $labelMdl->update($labelInfo, ['label_id' => $res['label_id']]);
            
            // label_id
            $labelInfo['label_id'] = $res['label_id'];
        } else {
            $labelMdl->insert($labelInfo);
        }

        return $labelInfo;
    }

    public function getLabelFromOrder($bill_id = '', $bill_type = 'order')
    {
        if (!$bill_id) {
            return [];
        }
        $billLabelmdl = app::get('ome')->model('bill_label');
        $labelMdl     = app::get('omeauto')->model('order_labels');
        $labelList    = [];

        if (is_array($bill_id)) {
            $filter = [
                'bill_type'  => $bill_type,
                'bill_id|in' => $bill_id,
            ];
            $orderLabelInfo = $billLabelmdl->getList('*', $filter);
            if (!$orderLabelInfo) {
                return [];
            }
            $labelIdArr   = array_unique(array_column($orderLabelInfo, 'label_id'));
            $labelListAll = $labelMdl->getList('*', ['label_id|in' => $labelIdArr]);
            $labelListAll = array_column($labelListAll, null, 'label_id');

            foreach ($orderLabelInfo as $k => $v) {
                // 处理小标
                if ($v['label_value']) {
                    $labelListAll[$v['label_id']]['label_value'] = $v['label_value'];

                    $label_code       = $labelListAll[$v['label_id']]['label_code'];
                    $labelValuePreset = $this->labelValuePreset[$label_code];
                    $label_name       = [];
                    foreach ($labelValuePreset as $pk => $pv) {
                        if ($v['label_value'] & $pk) {
                            // &位运算符
                            $label_name[] = $pv['label_name'];
                        }
                    }
                    if ($label_name) {
                        $labelsPreset = kernel::single('ome_bill_label')->orderLabelsPreset[$label_code];
                        if ($labelsPreset['label_thumb']) {
                            $labelListAll[$v['bill_id']]['label_name'] = $labelsPreset['label_thumb'];
                        }
                        $labelListAll[$v['label_id']]['label_name'] .= '(' . implode('/', $label_name) . ')';
                    }
                }

                $labelList[$v['bill_id']][] = $labelListAll[$v['label_id']];
            }

        } else {
            $filter = [
                'bill_type' => $bill_type,
                'bill_id'   => $bill_id,
            ];
            $orderLabelInfo = $billLabelmdl->getList('*', $filter);
            if (!$orderLabelInfo) {
                return [];
            }
            $labelIdArr = array_column($orderLabelInfo, 'label_id');
            $labelList  = $labelMdl->getList('*', ['label_id|in' => $labelIdArr]);

            // 处理小标
            $labelValueList = array_column($orderLabelInfo, 'label_value', 'label_id');
            if ($labelValueList) {
                foreach ($labelList as $lk => $lv) {
                    if ($label_value = $labelValueList[$lv['label_id']]) {
                        $labelList[$lk]['label_value'] = $label_value;
                        $labelValuePreset = $this->labelValuePreset[$lv['label_code']];
                        $label_name       = [];
                        foreach ($labelValuePreset as $pk => $pv) {
                            if ($label_value & $pk) {
                                // &位运算符
                                $label_name[] = $pv['label_name'];
                            }
                        }
                        if ($label_name) {
                            $labelsPreset = kernel::single('ome_bill_label')->orderLabelsPreset[$lv['label_code']];
                            if ($labelsPreset['label_thumb']) {
                                $labelList[$lk]['label_name'] = $labelsPreset['label_thumb'];
                            }
                            $labelList[$lk]['label_name'] .= '(' . implode('/', $label_name) . ')';
                        }
                    }
                }
            }
        }

        return $labelList;
    }

    /**
     * 根据单据id和标签id删除标签
     * @param $bill_id  单据id
     * @param $label_id   标签id
     * @param $bill_type    单据类型
     * @param $error_msg
     * @return bool
     */
    public function delLabelFromBillId($bill_id, $label_id, $bill_type = 'order', &$error_msg = null)
    {
        if (!$bill_id || !$label_id) {
            $error_msg = '单据id或标签id为空';
            return false;
        }
        if (is_array($bill_id) && count($bill_id) > 1 && is_array($label_id) && count($label_id) > 1) {
            $error_msg = '单据id和标签不允许同时为多个值';
            return false;
        }

        // if (!$bMdl = $this->billTypeAll[$bill_type]) {
        //     $error_msg = '无效的单据类型!';
        //     return false;
        // }

        !is_array($bill_id) && $bill_id   = [$bill_id];
        !is_array($label_id) && $label_id = [$label_id];

        $billLabelmdl = app::get('ome')->model('bill_label');
        if (count($bill_id) >= 1 && count($label_id) == 1) {
            $filter = [
                'bill_id|in' => array_values($bill_id),
                'label_id'   => array_values($label_id)[0],
                'bill_type'  => $bill_type,
            ];
        } elseif (count($label_id) >= 1 && count($bill_id) == 1) {
            $filter = [
                'bill_id'     => array_values($bill_id)[0],
                'label_id|in' => array_values($label_id),
                'bill_type'   => $bill_type,
            ];
        }

        if ($filter) {
            $billLabelmdl->delete($filter);
        }
        return true;
    }

    //订单标识 带到 发货单上
    public function transferLabel($transferType = '', $params = [])
    {
        switch ($transferType) {
            case 'omeorders_to_wmsdelivery':
                if ($params['order_id'] && $params['wms_delivery_id']) {
                    $labelOrder = $this->getLabelFromOrder($params['order_id'], 'order');
                    foreach ($labelOrder as $k => $label) {
                        $this->markBillLabel($params['wms_delivery_id'], '', $label['label_code'], 'wms_delivery', $err, $label['label_value']);
                    }
                }
                break;
            case 'omeorders_to_omedelivery':
                if ($params['order_id'] && $params['ome_delivery_id']) {
                    $labelOrder = $this->getLabelFromOrder($params['order_id'], 'order');
                    foreach ($labelOrder as $k => $label) {
                        $this->markBillLabel($params['ome_delivery_id'], '', $label['label_code'], 'ome_delivery', $err, $label['label_value']);
                    }
                }
                break;
            default:
                break;
        }
        return true;
    }

    public function orderToDeliveryLabel($orderId, $deliveryId, $bill_type = 'ome_delivery', $extend_info='') {
        $labelOrder = $this->getLabelFromOrder($orderId, 'order');
        foreach ($labelOrder as $k => $label) {
            if($this->orderLabelsPreset[$label['label_code']]['label_to_delivery']) {
                $labelExtendInfo = $extend_info;
                if ($this->orderLabelsPreset[$label['label_code']]['extend_info_to_delivery']) {
                    $orderLabelInfo = $this->getBillLabelInfo($orderId, 'order', $label['label_code']);
                    $labelExtendInfo = $orderLabelInfo['extend_info'] ?? '';
                }
                $this->markBillLabel($deliveryId, '', $label['label_code'], $bill_type, $err, $label['label_value'], $labelExtendInfo);
            }
        }
    }

    /**
     * 获取指定标签编码关联的标记信息
     *
     * @param $bill_id 单据ID
     * @param $bill_type 单据类型
     * @param $label_code 标签编码
     * @para $error_msg 错误信息
     * @return array
     */
    public function getBillLabelInfo($bill_id, $bill_type, $label_code, &$error_msg=null)
    {
        $labelMdl = app::get('omeauto')->model('order_labels');
        $billLabelMdl = app::get('ome')->model('bill_label');

        //check
        if(empty($bill_id) || empty($bill_type) || empty($label_code)){
            $error_msg = '无效的传参,请检查';
            return array();
        }

        $lableInfo = $labelMdl->db_dump(array('label_code'=>$label_code), '*');
        if(empty($lableInfo)){
            $error_msg = '标签编码：'. $label_code .'不存在';
            return array();
        }

        $billLableInfo = $billLabelMdl->db_dump(array('bill_type'=>$bill_type, 'bill_id'=>$bill_id, 'label_id'=>$lableInfo['label_id']), '*');
        if(empty($billLableInfo)){
            $error_msg = '标签编码：'. $label_code .'没有单据打标记信息';
            return array();
        }

        //merge
        $billLableInfo = array_merge($billLableInfo, $lableInfo);

        return $billLableInfo;
    }

    public function isExpressMust()
    {
        return 'SOMS_EXPRESS_MUST';
    }

    public function isJDZD($order_id){

        //京东变成可发货
        $ordLabelObj = app::get('ome')->model('bill_label');

        $bills = $ordLabelObj->dump(array('label_code'=>'SOMS_JDZD','bill_type'=>'order','bill_id'=>$order_id),'bill_id');

        $extendMdl = app::get('ome')->model('order_extend');

        if($bills){
            $extends = $extendMdl->dump(array('order_id'=>$order_id),'extend_field');
            $extend_field = json_decode($extends['extend_field'],true);

            if($extend_field['sendpayMap']) {
                foreach($extend_field['sendpayMap'] as $spVal){
                    if(is_string($spVal)) {
                        $spVal = json_decode($spVal, 1);
                    }
                    if(is_array($spVal) && $spVal['987'] == '2') {
                       return true;

                    }
                }
            }

        }

        return false;

    }

    public function isCloudbranch($order_id){

        //京东变成可发货
        $ordLabelObj = app::get('ome')->model('bill_label');

        $bills = $ordLabelObj->dump(array('label_code'=>'SOMS_CLOUDBRANCH','bill_type'=>'order','bill_id'=>$order_id),'bill_id');

        $extendMdl = app::get('ome')->model('order_extend');

        if($bills){
           return true;

        }

        return false;

    }

    //工小达标识
    public function isSomsGxd()
    {
        return 'SOMS_GXD';
    }

    /**
     * 判断是否是小时达订单并返回配送方式信息
     * @param int $order_id 订单ID
     * @return array 返回配送方式信息
     */
    public function isXiaoshiDa($order_id)
    {
        if (!$order_id) {
            return [
                'is_xiaoshi_da' => false,
                'delivery_method' => '',
                'is_platform_delivery' => false,
                'is_self_delivery' => false,
                'is_meituan_delivery' => false,
                'is_meituan_runner' => false
            ];
        }

        $billLabelMdl = app::get('ome')->model('bill_label');

        // 检查是否有小时达标签
        $xiaoshiLabel = $billLabelMdl->dump([
            'label_code' => 'SOMS_XSDBC',
            'bill_type' => 'order',
            'bill_id' => $order_id
        ], '*');

        if (!$xiaoshiLabel) {
            return [
                'is_xiaoshi_da' => false,
                'delivery_method' => '',
                'is_platform_delivery' => false,
                'is_self_delivery' => false,
                'is_meituan_delivery' => false,
                'is_meituan_runner' => false
            ];
        }

        $label_value = intval($xiaoshiLabel['label_value']);

        // 根据二进制值判断配送方式
        $is_third_party = (bool)($label_value & 0x0001);       // 第三方运力
        $is_self_delivery = (bool)($label_value & 0x0002);     // 商家自配运力
        $is_platform_delivery = (bool)($label_value & 0x0004); // 平台运力

        // 确定配送方式描述
        $delivery_methods = [];
        if ($is_third_party) $delivery_methods[] = '第三方运力';
        if ($is_self_delivery) $delivery_methods[] = '商家自配运力';
        if ($is_platform_delivery) $delivery_methods[] = '平台运力';

        $delivery_method = implode('、', $delivery_methods);

        return [
            'is_xiaoshi_da' => true,
            'delivery_method' => $delivery_method,
            'is_third_party' => $is_third_party,
            'is_self_delivery' => $is_self_delivery,
            'is_platform_delivery' => $is_platform_delivery
        ];
    }
    /**
     * 判断指定单据上是否已存在目标标签，兼容订单与发货单。
     * @param $bill_id
     * @param $label_code
     * @param string $bill_type
     * @return bool
     */
    public function existLabel($bill_id, $label_code, $bill_type = 'order')
    {
        $labelInfo = $this->getBillLabelInfo($bill_id, $bill_type, $label_code);
        return !empty($labelInfo);
    }

    /**
     * 获取标签信息
     * @param $order_id
     * @param $label_code
     * @return false|mixed|null
     */
    public function getLabelInfo($order_id, $label_code)
    {
        $labelInfo = $this->getBillLabelInfo($order_id, 'order', $label_code);
        return $labelInfo;
    }
}
