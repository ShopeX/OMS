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
 * @author ykm 2015-12-28
 * @describe 处理打印模板页面所需要的数据
 */

class brush_pagedata {
    private $objPrint;
    private $objElectron;
    private $objExpress;
    
    public function init($objPrint, $objElectron, $objExpress) {
        $this->objPrint = $objPrint;
        $this->objElectron = $objElectron;
        $this->objExpress = $objExpress;
    }
    
    public function getPageData($printData) {
        $pageData = $this->_getDeliveryAttr($this->objPrint->delivery);
        $pageData['dpi'] = 96;
        $pageData['base_dir'] = kernel::base_url();
        $pageData['title'] = '快递单打印';
        $pageData['uniqid'] = uniqid();
        //组织控件打印数据
        $pageData['printTmpl'] = $this->objExpress->printTpl;
        $pageData['jsondata'] = json_encode($printData);
        $pageData['data'] = addslashes(kernel::single('logisticsmanager_print_format')->array2xml2($printData, 'data'));
        $pageData['order_number'] = $pageData['count'] = $pageData['totalPage'] = count($printData);
        //获取有问题的单据号
        $pageData['err'] = count($printData) ? 'false' : 'true';
        $pageData['errBns'] = $this->objPrint->showError['errBns'];
        $pageData['errIds'] = $this->objPrint->showError['errIds'];
        $pageData['errInfo'] = $this->objPrint->showError['errInfo'];
        //打印单基本信息
        $pageData['allItems'] = $pageData['items'];
        $pageData['delivery'] = array_values($pageData['items']);
        $pageData['print_logi_id'] = $this->objPrint->logi_id;
        $pageData['dly_tmpl_id'] = $this->objElectron->corp['prt_tmpl_id'];
        $pageData['ids'] = array_keys($this->objPrint->delivery);
        $pageData['vid'] = implode(',', $pageData['ids']);
        $pageData['hasOnePrint'] = count($this->objPrint->hasPrint);
        $pageData['hasPrintStr'] = implode(',',array_slice($this->objPrint->hasPrint,0,4)) . ($pageData['hasOnePrint'] > 4 ? '......' : '');
        $pageData['logi_name'] = $this->objElectron->corp['name'];
        $pageData['express_company_no'] = strtoupper($this->objElectron->corp['type']);
        $pageData['o_bn'] = $this->_getOrderBn($pageData['ids']);
        //获取打印版本配置信息
        if($printVer = $this->_getPrintVersion($pageData['express_company_no'])) {
            list($pageData['logiVersionFlag'], $pageData['logicfg'], $pageData['print_logi_version']) = $printVer;
        }
        //打印中路由提交的app及控制器
        $pageData['appCtl'] = 'app=brush&ctl=admin_print';
        return $pageData;
    }

    private function _getDeliveryAttr($delivery) {
        $items = $idd = $logid = array();
        foreach($delivery as $k => $val) {
            $idd[] = array(
                'delivery_id' => $val['delivery_id'],
                'delivery_bn' => $val['delivery_bn']
            );
            $logid[$val['delivery_id']] = $val['logi_no'];
            $items[$k] = $val;
            $items[$k]['consignee']['name'] = $val['ship_name'];
            $items[$k]['consignee']['area'] = $val['ship_area'];
            $items[$k]['consignee']['province'] = $val['ship_province'];
            $items[$k]['consignee']['city'] = $val['ship_city'];
            $items[$k]['consignee']['district'] = $val['ship_district'];
            $items[$k]['consignee']['addr'] = $val['ship_addr'];
            $items[$k]['consignee']['zip'] = $val['ship_zip'];
            $items[$k]['consignee']['telephone'] = $val['ship_tel'];
            $items[$k]['consignee']['mobile'] = $val['ship_mobile'];
            $items[$k]['consignee']['email'] = $val['ship_email'];
            $items[$k]['consignee']['r_time'] = $val['ship_time'];
        }
        $ret = array(
            'idd' => $idd,
            'logid' => $logid,
            'items' => $items
        );
        return $ret;
    }

    private function _getOrderBn($deliveryIds) {
        $objDataOrder = kernel::single('logisticsmanager_print_data_order');
        if(empty($objDataOrder->orders)) {
            $sql = 'select o.order_bn from sdb_ome_orders as o left join sdb_brush_delivery_order as d on(o.order_id = d.delivery_id) where delivery_id in (\'' . implode('\',\'', $deliveryIds) . '\')';
            $orderRows = kernel::database()->select($sql);
        } else {
            $orderRows = $objDataOrder->orders;
        }
        $tradeIds = array();
        foreach($orderRows as $row) {
            $tradeIds[] = $row['order_bn'];
        }
        return $tradeIds;
    }

    private function _getPrintVersion($expressCompanyNo) {
        $logicfg = kernel::single('ome_print_logicfg')->getLogiCfg();
        if($logicfg[$expressCompanyNo]){
            $ecnCfg = $logicfg[$expressCompanyNo];
            $print_logi_version = app::get('ome')->getConf('print_logi_version_'.$this->objPrint->logi_id);
            $plv = intval($print_logi_version);
            return array(1, $ecnCfg, $plv);
        } else {
            return false;
        }
    }
}