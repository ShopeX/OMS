<?php
/**
 * [票易通]平台推送数据响应Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2026.04.02
 */
class erpapi_invoice_matrix_piaoyitong_response_order extends erpapi_invoice_response_order
{
    protected function _checkUpdateParams($params)
    {
        if (is_string($params)) {
            $params = json_decode($params, true);
        }

        if (!is_array($params) || !isset($params['response']['success']) || !is_array($params['response']['success'])) {
            throw new exception('票易通回传数据结构不正确!');
        }

        $this->invoiceResponse = $params['response'];

        return $params;
    }
    
    protected function _formatUpdateParams($params)
    {
        $data = [];
        foreach ($this->invoiceResponse['success'] as $key => $invoiceResult) {
            $invOrderMdl = app::get('invoice')->model('order');
            $invOrder = $invOrderMdl->dump(['invoice_apply_bn' => $invoiceResult['invoiceApplyBn']], 'order_bn');

            $this->__apilog['original_bn'] = $invOrder['order_bn'];
            $this->__apilog['title'] = '开票结果更新-' . $invoiceResult['invoiceApplyBn'];

            $data[$key] = array(
                'serial_no'        => $invoiceResult['serialNo'],
                'invoice_apply_bn' => $invoiceResult['invoiceApplyBn'],
                'invoice_code'     => $invoiceResult['invoiceCode'],
                'invoice_no'       => $invoiceResult['invoiceNo'],
                'invoice_date'     => strtotime($invoiceResult['invoiceDate']),
                'invoice_type'     => $invoiceResult['invoiceType'],
                'url'              => $invoiceResult['eInvoiceUrl'],
                'pdf_url'          => $invoiceResult['pdfUrl'],
                'invoice_status'   => $this->invoiceStatusMapping[$invoiceResult['invoiceStatus']],
                'sync'             => $this->syncMapping[$invoiceResult['invoiceStatus']],
                'is_status'        => $this->isStatusMapping[$invoiceResult['invoiceStatus']],
                'xml_url'          => $invoiceResult['xmlUrl'],
                'ofd_url'          => $invoiceResult['ofdUrl'],
            );
        }

        // 可回传列表为空则直接返回
        if (empty($data)) {
            throw new exception('票易通发票回传结果为空!');
        }

        return $data;
    }
}
