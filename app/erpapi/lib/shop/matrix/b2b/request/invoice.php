<?php
/**
 * @author
 * @describe 发票 相关请求接口类
 */

class erpapi_shop_matrix_b2b_request_invoice extends erpapi_shop_request_invoice
{
    /**
     * 发票上传组织数据
     *
     * @return void
     * @author
     */
    protected function getUploadParams($sdf)

    {
        $invoice = $sdf['invoice'];
        $electronic = $sdf['electronic'];

        $params = array(
            'order_bn' => $invoice['order_bn'], # 订单编号                    必须
            'invoice_type' => $electronic['billing_type'], # 发票类型 1-蓝票 2-红票                 必须
            'payee_register_no' => $invoice['tax_no'], # 销货方识别号（税号）        必须
            'payee_name' => $invoice['payee_name'],  # 销货方公司名称              必须
            'payee_address' => $invoice['address'],   # 销货方公司地址
            'payee_phone' => $invoice['telephone'], # 销货方电话
            'payee_bankname' => $invoice['bank'],  # 销货方公司开户行
            'payee_bankaccount' => $invoice['bank_no'],   # 销货方公司银行账户
            'payee_operator' => $invoice['payee_operator'],  # 开票人
            'payee_receiver' => $invoice['payee_receiver'],  # 收款人
            'taxfree_amount' => round($invoice['amount'], 2),  # 开票金额 两位小数w
            'invoice_title' => $invoice['title'],   # 发票抬头                    必须
            'invoice_time' => date('Y-m-d', $electronic['create_time']),    # 开票时间 yyyy-MM-dd         必须
            'ivc_content_type' => '',    # 开票内容编号
            'ivc_content_name' => $invoice['content'],    # 开票内容名称
            'invoice_code' => $electronic['invoice_code'],    # 发票代码                    必须
            'invoice_no' => $electronic['invoice_no'],  # 发票号码                    必须
            'invoice_memo' => $invoice['remarks'],    # 发票备注
            'blue_invoice_code' => (string)$electronic['normal_invoice_code'],    # 原蓝票发票代码                开红票的时候必须传
            'blue_invoice_no' => (string)$electronic['normal_invoice_no'],  # 原蓝票发票号码                开红票的时候必须传
            'pdf_info' => $electronic['url'], # 发票PDF文件二进制流base64   必须
            'logi_name' => $electronic['logi_name'],
            'logi_no' => $electronic['logi_no'],
        );

        if ($sdf['items']) {
            $items = array();
            foreach ($sdf['items'] as $value) {
                $items[] = array(
                    'item_no' => '', # 货号
                    'item_name' => $value['spmc'], # SKU商品名称
                    'num' => $value['spsl'], # 数量
                    'price' => round($value['spdj'], 2), # 单价
                    'spec' => '', # 规格
                    'unit' => $value['dw'], # 单位
                    'tax_rate' => $value['sl'], # 税率 两位小数
                    'tax_categroy_code' => $value['spbm'], # 税收分类编码
                    'is_tax_discount' => $value['yhzcbs'], # 优惠政策标识 0-不使用 1-使用
                    'tax_discount_content' => $value['zzstsgl'], # 增值税特殊管理 当优惠政策标识为1时填写
                    'zero_tax' => $value['lslbs'], # 零税率标识 空-非零税率 0-出口退税 1-免税 2-不征收 3-普通零税率
                    'deductions' => '', # 扣除额 两位小数
                    'imei' => '', # 商品IMEI码
                    'discount' => 0, # 折扣
                    'freight' => 0, # 运费
                );
            }
            $params['invoice_items'] = json_encode($items);
        }

        return $params;
    }

    /**
     * 电子发票回传平台,对应第三方B2B接口文档, b2b.invoice.send 订单开票上传 接口
     * 对应D1M文档, oms/pushInvoiceLinkV2 推送发票开具成功消息 接口
     * @param $sdf
     * @param false $sync
     */
    public function upload($sdf, $sync = false)
    {
     
        $params = $this->getUploadParams($sdf);
        
        // 直连请求暂不支持异步回调
        $callback = array();

        $rs = $this->__caller->call(EINVOICE_DETAIL_UPLOAD, $params, $callback, '电子发票回传', 10, $sdf['invoice']['order_bn']);

        $this->uploadCallback($rs, array('electronic_item_id' => $sdf['electronic']['item_id']));

        return $rs;
    }

} 