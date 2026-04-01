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
 * 订单标签管理Lib类
 *
 * @author wangbiao@shopex.cn
 * @version $Id: Z
 */
class omeauto_order_label
{
    /**
     * 所有支持的类型
     */
    static $TYPE_LIST = array('sku');

    /**
     * 检查标签保存参数
     *
     * @param array $data
     * @param string $error_msg
     * @return boolean
     */
    public function check_label_params(&$data, &$error_msg = null)
    {
        $labelObj = app::get('omeauto')->model('order_labels');

        $label_id = $data['label_id'];
        if ($label_id) {
            $labelInfo = $labelObj->dump(array('label_id' => $label_id), '*');
            if ($labelInfo) {
                $data['label_id'] = $labelInfo['label_id'];

                unset($data['create_time']);
            } else {
                unset($data['label_id']);
            }
        }

        //check
        if (empty($data['label_code']) || empty($data['label_name']) || empty($data['label_color'])) {
            $error_msg = '标签代码、标签名称、标签颜色为必填写项';
            return false;
        }

        if (strlen($data['label_code']) < 3) {
            $error_msg = '标签代码至少三个字符';
            return false;
        }

        if (strlen($data['label_name']) < 3) {
            $error_msg = '标签名称至少三个字符';
            return false;
        }

        $reg_code = "/^[a-zA-Z0-9_]*$/";
        if (!preg_match($reg_code, $data['label_code'])) {
            $error_msg = '标签代码只能为英文字母或数字';
            return false;
        }

        $reg_code = "/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/";
        if (!preg_match($reg_code, $data['label_color'])) {
            $error_msg = '标签颜色值填写错误';
            return false;
        }

        //label_code
        $filter = array('label_code' => $data['label_code']);
        if ($data['label_id']) {
            $filter['label_id|noequal'] = $data['label_id'];
        }

        $checkInfo = $labelObj->getList('label_id', $filter);
        if ($checkInfo) {
            $error_msg = '标签代码已经存在';
            return false;
        }

        //label_name
        $filter = array('label_name' => $data['label_name']);
        if ($data['label_id']) {
            $filter['label_id|noequal'] = $data['label_id'];
        }

        $checkInfo = $labelObj->dump($filter, 'label_id');
        if ($checkInfo) {
            $error_msg = '标签名称已经存在';
            return false;
        }

        return true;
    }

    /**
     * 订单打标签
     *
     * @param int $order_id
     * @param string $error_msg
     * @return boolean
     */
    public function makeOrderLabel($order_id, &$error_msg = null)
    {
        $orderObj = app::get('ome')->model('orders');
        $ruleObj = app::get('omeauto')->model('order_labelrule');
        $ordLabelObj = app::get('ome')->model('bill_label');
        $operLogObj = app::get('ome')->model('operation_log');
        $labelObj = app::get('omeauto')->model('order_labels');
        
        //订单标记规则(最多读取500条有效规则)
        $ruleList = $ruleObj->getList('*', array('disabled'=>'false'), 0, 500, 'weight DESC,id DESC');
        if (empty($ruleList)) {
            //没有配置标记规则,直接返回true,不用记录log日志
            $error_msg = '没有配置订单标记规则';
            return true;
        }
        
        //订单信息
        $orderInfo = $orderObj->dump(array('order_id'=>$order_id), '*', array('order_objects'=>array('*', array('order_items'=>array('*')))));
        if (empty($orderInfo)) {
            $error_msg = '订单不存在';
            return true;
        }
        
        //补发订单
        if ($orderInfo['order_type'] == 'bufa') {
            $error_msg = '补发订单不需要打标签';
            return true;
        }
        
        //操作人
        $opinfo = kernel::single('ome_func')->get_system();
        
        //订单标记规则
        $succList = array();
        $failList = array();
        $succ_i = 0;
        $fail_i = 0;
        foreach ($ruleList as $key => $val)
        {
            if (empty($val['config']) || empty($val['select_label'])) {
                continue;
            }
            
            //标签
            $labelList = json_decode($val['select_label'], true);
            if (empty($labelList)) {
                continue;
            }
            
            //验证归类规则
            $isExec = false;
            $isFailed = false;
            $configList = $val['config'];
            foreach ($configList as $roleKey => $roleItem)
            {
                $role = json_decode($roleItem, true);
                if (empty($role['role']) || empty($role['content'])) {
                    continue;
                }
                
                //执行验证标识
                $isExec = true;
                
                //验证规则
                $className = sprintf('omeauto_order_label_%s', $role['role']);
                $filter = new $className();
                $filter->setRole($role['content']);
                $isVaild = $filter->vaild($orderInfo, $error_msg);
                if (!$isVaild) {
                    //验证失败打标记
                    $isFailed = true;
                    
                    //跳出
                    break;
                }
            }
            
            //check
            if(!$isExec){
                //没有配置有效的归类规则,则跳过
                continue;
            }
            
            //只要有一个[归类规则]验证失败,则未通过
            if($isFailed){
                $fail_i++;
                $failList[] = sprintf('%s、标记规则：%s(%s)，错误信息：%s', $fail_i, $val['name'], $val['id'], $error_msg);
                
                //不满足打标记,则跳过
                continue;
            }
            
            //打标记
            $lableNames = array();
            foreach ($labelList as $laKey => $laVal)
            {
                $label_id = $laVal['label_id'];
                
                //label_name
                $lableNames[] = $laVal['label_name'];
                
                //check
                $isCheck = $ordLabelObj->dump(array('bill_type' => 'order', 'bill_id' => $order_id, 'label_id' => $label_id), 'bill_id');
                if ($isCheck) {
                    continue; //标记已存在,则跳过
                }
                
                // 标记信息
                $labelInfo = $labelObj->dump(array('label_id'=>$label_id), '*');
                
                // sdf
                $saveData = array(
                    'bill_type'   => 'order',
                    'bill_id'     => $order_id,
                    'label_id'    => $label_id,
                    'label_code'  => $labelInfo['label_code'],
                    'label_name'  => $laVal['label_name'],
                    'create_time' => time(),
                );
                $ordLabelObj->insert($saveData);
            }
            
            //succ
            $succ_i++;
            $succList[] = sprintf('%s、标记规则：%s(%s)，打标记：%s', $succ_i, $val['name'], $val['id'], implode('、', $lableNames));
        }
        
        //[logs]所有日志信息统一记录
        if($failList || $succList){
            $logMsg = '';
            
            //succ
            if($succList){
                $logMsg .= '验证通过的规则：<br/>';
                $logMsg .= implode('<br/>', $succList);
                $logMsg .= '<br/>';
            }
            
            //fail
            if($failList){
                $logMsg .= '检验失败的规则：<br/>';
                $logMsg .= implode('<br/>', $failList);
            }
            
            $operLogObj->write_log('order_preprocess@ome', $order_id, $logMsg, time(), $opinfo);
        }
        
        //unset
        unset($ruleList, $orderInfo, $failList, $succList);
        
        return true;
    }

    /**
     * 删除订单赠品打标记
     * @param $order_id
     * @param $error_msg
     * @return bool
     * ============已废弃，改用 markBillLabel 方法============
     */
    public function deleteOrderGiftLable($order_id, &$error_msg = null)
    {
        $labelObj    = app::get('omeauto')->model('order_labels');
        $ordLabelObj = app::get('ome')->model('bill_label');

        //check
        if (empty($order_id)) {
            $error_msg = '无效的请求打标记数据!';
            return false;
        }

        //标记信息
        $label_code = 'deleteordergift';
        $labelInfo  = $labelObj->dump(array('label_code' => $label_code), '*');
        if (empty($labelInfo)) {
            //自动添加标记
            $labelInfo = array(
                'label_code'    => $label_code,
                'label_name'    => '退款删赠品失败',
                'label_color'   => '#ff0000',
                'source'        => 'system',
                'create_time'   => time(),
                'last_modified' => time(),
            );
            $labelObj->insert($labelInfo);
        }

        //check
        if (empty($labelInfo['label_id'])) {
            $error_msg = '删除订单赠品标签不存在';
            return false;
        }

        //检查已经打标,则跳过
        $isCheck = $ordLabelObj->dump(array('bill_type' => 'order', 'bill_id' => $order_id, 'label_id' => $labelInfo['label_id']), 'bill_id');
        if ($isCheck) {
            $error_msg = '订单已经打过删除赠品的标签';
            return false;
        }

        //打标记
        $saveData = array(
            'bill_type'   => 'order',
            'bill_id'     => $order_id,
            'label_id'    => $labelInfo['label_id'],
            'label_name'  => $labelInfo['label_name'],
            'create_time' => time(),
        );
        $ordLabelObj->insert($saveData);

//        //操作人
        //        $opinfo = kernel::single('ome_func')->get_system();
        //
        //        //log
        //        $operLogObj = app::get('ome')->model('operation_log');
        //        $operLogObj->write_log('order_preprocess@ome', $order_id, '订单退款后删除赠品失败', time(), $opinfo);

        return true;
    }

    /**
     * 价保订单打标记
     * @param $order_id 订单ID
     * @param $oidList Oid列表
     * @param $error_msg 错误信息
     * @return false|void
     */
    public function labelPriceProtectOrder($order_id, $oidList, &$error_msg = null)
    {
        /*
        $labelObj    = app::get('omeauto')->model('order_labels');
        $ordLabelObj = app::get('ome')->model('bill_label');

        //check
        if (empty($order_id) || empty($oidList)) {
            $error_msg = '无效的请求打标记数据。';
            return false;
        }

        //标记信息
        $label_code = 'priceprotect';
        $labelInfo  = $labelObj->dump(array('label_code' => $label_code), '*');
        if (empty($labelInfo)) {
            //自动添加标记
            $labelInfo = array(
                'label_code'    => $label_code,
                'label_name'    => '价保订单',
                'label_color'   => '#ff6600',
                'source'        => 'system',
                'create_time'   => time(),
                'last_modified' => time(),
            );
            $labelObj->insert($labelInfo);
        }

        //check
        if (empty($labelInfo['label_id'])) {
            $error_msg = '价保订单标签不存在';
            return false;
        }

        //检查已经打标,则跳过
        $isCheck = $ordLabelObj->dump(array('bill_type' => 'order', 'bill_id' => $order_id, 'label_id' => $labelInfo['label_id']), 'bill_id');
        if ($isCheck) {
            $error_msg = '订单已经打过价保的标签';
            return false;
        }

        //订单打标记
        $saveData = array(
            'bill_type'   => 'order',
            'bill_id'     => $order_id,
            'label_id'    => $labelInfo['label_id'],
            'label_name'  => $labelInfo['label_name'],
            'create_time' => time(),
        );
        $ordLabelObj->insert($saveData);
         */

        kernel::single('ome_bill_label')->markBillLabel($order_id, '', 'priceprotect', 'order', $error_msg);
        $ordLabelObj = app::get('ome')->model('bill_label');
        //订单object明细打标记
        $object_bool_type = ome_order_bool_objecttype::__PRICE_PROTECT_CODE;
        $updateSql        = "UPDATE sdb_ome_order_objects SET object_bool_type = object_bool_type | " . $object_bool_type;
        $updateSql .= " WHERE order_id=" . $order_id . " AND oid IN('" . implode("','", $oidList) . "')";
        $ordLabelObj->db->exec($updateSql);

        //是否价保订单SKU商品
        //kernel::single('ome_order_bool_objecttype')->isPriceProtect($objectVal['object_bool_type'])

        return true;
    }
    
}
