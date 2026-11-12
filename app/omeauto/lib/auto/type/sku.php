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
 * 订单产品 (会有时间概念)
 */
class omeauto_auto_type_sku extends omeauto_auto_type_abstract implements omeauto_auto_type_interface {

    /**
     * 检查输入的参数
     *
     * @param Array $params
     * @returm mixed
     */
    public function checkParams($params) {

        if (empty($params['s_skus'])) {
            return "你没有输入活动商品的SKU\n\n请输入以后再试！！";
        }

        //这里判断物料编码只能是由数字英文下划线及横杠组成
        $sales_material_bns = explode(',', $params['s_skus']);
        if($sales_material_bns){
            $reg_bn_code = "/^[0-9a-zA-Z\_\-\/]*$/";
            $flag = false;
            foreach ($sales_material_bns as $var_bn){
                if(!preg_match($reg_bn_code,$var_bn)){
                    $flag = true;
                    break;
                }
            }
            if($flag){
                return "物料编码是由数字英文字母下划线及横杠组成";
            }
        }

        return true;
    }

    /**
     * 生成规则字串
     *
     * @param Array $params
     * @return String
     */
    public function roleToString($params) {

        if (!empty($params['s_start'])) {
            $h = $params['_DTIME_']['H']['s_start'] ?? '';
            $m = $params['_DTIME_']['M']['s_start'] ?? '';
            $sStart = strtotime(sprintf('%s %s:%s:00', $params['s_start'], $h ?: '00', $m ?: '00'));
        } else {
            $sStart = '';
        }

        if (!empty($params['s_end'])) {
            $h = $params['_DTIME_']['H']['s_end'] ?? '';
            $m = $params['_DTIME_']['M']['s_end'] ?? '';
            $sEnd = strtotime(sprintf('%s %s:%s:00', $params['s_end'], $h ?: '00', $m ?: '00'));
        } else {
            $sEnd = '';
        }
        if($params['sku_range'] == 'false'){
            $sku_range = '包含';
        }else{
            $sku_range = '仅有';
        }
        if (!empty($sStart) && !empty($sEnd)) {
            $caption = sprintf('在 %s 至 %s %s %s 的订单', date('Y-m-d H:i', $sStart), date('Y-m-d H:i', $sEnd), $sku_range, $params['s_skus']);
        } else if (!empty($sStart) && empty($sEnd)) {
            $caption = sprintf('从 %s 开始%s %s 的订单', date('Y-m-d H:i', $sStart), $sku_range, $params['s_skus']);
        } else if (empty($sStart) && !empty($sEnd)) {
            $caption = sprintf('到 %s 为止%s %s 的订单', date('Y-m-d H:i', $sEnd), $sku_range, $params['s_skus']);
        } else {
            $caption = sprintf('%s商品 %s 的订单', $sku_range, $params['s_skus']);
        }

        $role = array('role' => 'sku', 'caption' => $caption, 'content' => array('sku' => $params['s_skus'], 'sku_range' =>  $params['sku_range'], 'start' => $sStart, 'end' => $sEnd));

        return json_encode($role);
    }

    /**
     * 设置已经创建好的配置内容
     *
     */
    public function setRole($params) {

        $this->content = $params;
        if (!empty($this->content['sku'])) {
            $this->content['sku'] = explode(',', $this->content['sku']);
            foreach ($this->content['sku'] as $key => $sku) {
                $this->content['sku'][$key] = strtolower(trim($sku));
            }
        }

    }

    /**
     * 检查订单数据是否符合要求
     *
     * @param omeauto_auto_group_item $item
     * @return boolean
     */
    public function vaild($item) {
        if (!empty($this->content)) {
            //先检查开始结束时间
            $count = count($item->getOrders());
            $matchskus = $nomatchskus=array();

            foreach ($item->getOrders() as $order) {

                //检查订单创建时间
                if (intval($this->content['start']) > 0 && $order['createtime'] < intval($this->content['start'])) {
                    return false;
                }

                if(intval($this->content['end']) > 0 && $order['createtime'] > intval($this->content['end'])){
                    return false;
                }
                //检查订单object
                /*foreach ($order['objects'] as $object) {
                    if($this->content['sku_range']=='false') {
                        if (in_array(strtolower($object['bn']), $this->content['sku'])) {
                            return true;
                        }
                    } else {
                        if (!in_array(strtolower($object['bn']), $this->content['sku'])) {
                            return false;
                        }
                    }
                }
                if($this->content['sku_range']=='false'){
                    return false;
                }*/
                $rs = $this->checkSku($order,$this->content);
                if(!$rs){
                    $nomatchskus[] = $order['order_id'];
                }else{
                    $matchskus[$order['order_id']] = $order;
                }

            }

            if($count!=count($matchskus) && $matchskus){
             
                $item->updateOrderInfo($matchskus);
                $item->setOriginalOrders($matchskus);
                return true;
            }
           

            return $rs;

        } else {

            return false;
        }
    }
    /**
     * 获取输入UI
     *
     * @param mixed $val
     * @return String|Array
     */
    public function getUI($val) {

        $tpl = kernel::single('base_render');
        $role = array_shift($val);
        $salesMaterialObj = app::get('material')->model('sales_material');
        $skus=explode(",",$_REQUEST['sku_str'] ?? '');
        $result=    $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,shop_id', array('sales_material_bn'=>$skus, 'is_bind'=>1));
        $sm_id='';
        foreach ($result as $key=>$value){
            $sm_id .=$value['sm_id'].",";
        }
        $tpl->pagedata['sm_id']=$sm_id;
        $tpl->pagedata['role'] = $role;
        $tpl->pagedata['init'] = json_decode(base64_decode($role), true);
        if (method_exists($this, '_prepareUI')) {
            $this->_prepareUI($tpl, $val);
        }

        if ($this->vue_mode) {
            return $tpl->pagedata;
        }

        return $tpl->fetch($this->getTemplateName(), 'omeauto');
    }

    

    public function checkSku($order,$content){

        foreach ($order['objects'] as $object) {
            if($content['sku_range']=='false') {
                if (in_array(strtolower($object['bn']), $content['sku'])) {
                    return true;
                }
            } else {
                if (!in_array(strtolower($object['bn']), $content['sku'])) {
                    return false;
                }
            }
        }

        if($content['sku_range']=='false'){
            return false;
        }
        return true;
    }
}
