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
 * 百望电子发票请求业务
 */
class erpapi_invoice_matrix_baiwang_request_invoice extends erpapi_invoice_request_invoice
{
    /**
     * 开票推送是否异步请求
     * @var bool
     */
    protected $_isCreateAsync = false;

    /**
     * 创建
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */

    public function create($sdf)
    {
        $order         = $sdf['order'];
        $requestParams = $sdf['params'];
        $primary_bn = ($order['invoice_apply_bn'] ? $order['invoice_apply_bn'] : $sdf['invoice_apply_bn']);
        
        $title = "百望电子发票开蓝票-" . $order['invoice_apply_bn'];

        $params['data']  = json_encode($requestParams);
        $params['taxNo'] = $order['tax_no'];

        $this->__resultObj->mock_method = STORE_INVOICE_ISSUE;

        $callback = [];
        $rs       = $this->__caller->call(STORE_INVOICE_ISSUE, $params, $callback, $title, 20, $primary_bn);

        return $rs;
    }


    /**
     * 电子发票开票(蓝票或者红票)后获取发票开票结果查询
     * 
     * @param array $sdf 请求参数
     */
    public function create_result($sdf)
    {
        $apiname = $this->get_result_apiname($sdf);

        $params = $this->get_result_params($sdf);

        $result = $this->__caller->call($apiname, $params, null, '百望电子发票获取发票开票结果', 20, $sdf["invoice_apply_bn"]);

        return $result;
    }

    /**
     * 发票取消接口
     * 
     * @param array $sdf
     * @param array
     */
    public function cancel($sdf)
    {
        $order         = $sdf['order'];
        $requestParams = $sdf['params'];

        $params['data']  = json_encode($requestParams);
        $params['taxNo'] = $order['tax_no'];
        $title           = "百望电子发票冲红-" . $order['invoice_apply_bn'];

        $this->__resultObj->is_mock = false;

        $callback = [];

        $rs = $this->__caller->call(STORE_INVOICE_ISSUE, $params, $callback, $title, 10, $order["invoice_apply_bn"]);

        return $rs;
    }


    #版式文件生成（百望生成板式文件的时候，已立即返回了下载地址，所以直接用生成接口查下载地址，代替板式文件查询接口）
    public function get_url($sdf)
    {
        $sdf['gfkhdh'] = '';
        $sdf['gfkhyx'] = '';

        $title = "新版式文件生成（含推送)";
        $rs    = $this->__caller->call(STORE_BW_INVOICE_FILE_CREATE, $sdf, null, $title, 10, $sdf["order_bn"]);
        if ($rs['rsp'] == 'succ') {
            $data       = json_decode($rs['data'], true);
            $rs['data'] = json_encode(array ('url' => $data['msg']['rtndata']));
        }

        return $rs;
    }

    /**
     * 获取_result_apiname
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function get_result_apiname($sdf)
    {
        if ($sdf['billing_type'] == '1') {
            return SHOP_EINVOICE_QUERY;
        }else{
            return INVOICE_RED_APPLY_QUERY;
        }

    }

    /**
     * 获取_result_params
     * @param mixed $sdf sdf
     * @return mixed 返回结果
     */
    public function get_result_params($sdf)
    {
        $data = array (
            'serialNo'    => $sdf['serial_no'],
            'invoiceDate' => date("YmdHis", $sdf['create_time']),
        );

        $params['data'] = json_encode($data);
        if ($sdf['billing_type'] == '1') {
            return $params;
        }else{
            return [
                'taxNo'          => $sdf["payee_register_no"],
                'sellerTaxNo'    => $sdf["payee_register_no"],
                'redConfirmUuid' => $sdf["order_electronic_items"]['red_confirm_uuid'],
            ];
        }


    }

    /**
     * 获取红字申请单请求参数
     * @param $sdf
     * @return void
     */
    protected function _get_reverse_application_create_params($sdf)
    {
        return $sdf['params'];
    }

    /**
     * 获取红字申请单日志业务单号
     * @param $sdf
     * @return void
     */
    protected function _get_reverse_application_create_primary_bn($sdf)
    {
        return $sdf['order']['order_bn'];
    }

    /**
     * 获取红字申请单回调参数
     * @param $sdf
     * @return void
     */
    protected function _get_reverse_application_create_callback_params($sdf)
    {
        return [];
    }

    /**
     * 发票获取
     * @param $sdf
     * @return array
     */
    protected function _get_create_callback_params($sdf)
    {
        $order = $sdf['order'];
        return array (
            "id"        => $order["id"],
            'serial_no' => $order['serial_no'],
            "order_bn"  => $order["order_bn"],
            "shop_id"   => $order["shop_id"],
        );
    }

    /**
     * 创建pdf板式文件
     * @Author: XueDing
     * @Date: 2024/8/12 3:39 PM
     * @param $sdf
     * @return array|null
     */
    public function createPdfFile($sdf)
    {
        $order         = $sdf['order'];
        $requestParams = $sdf['params'];

        $params['data']  = json_encode($requestParams);
        $params['taxNo'] = $order['tax_no'];
        $title           = "百望电子发票红票生成版式文件-" . $order['invoice_apply_bn'];

        $callback = [];

        $rs = $this->__caller->call(STORE_BW_EINVOICE_FORMAT_CREATE, $params, $callback, $title, 10, $order["invoice_apply_bn"]);

        return $rs;
    }
}
