<?php
/**
 * @desc
 * @author:
 * @since:
 */
class erpapi_shop_matrix_b2b_response_invoice extends erpapi_shop_response_invoice {

    /**
     * 获取数据
     *
     * @param array $params
     *
     * @return array:
     */
    protected function _returnParams($params)
    {
        $sdf = $this->_formatParams($params);
        
        $sdf['tax_no'] = $params['register_no'];    // 发票号, 待确认是否要传
        $sdf['register_no'] = $params['register_no']; // 税务号 format函数缺失字段
        $sdf['tax_title'] = $params['company_title'];  // 发票抬头 format函数缺失字段
        // 发票抬头 format函数缺失字段 todo 待确认是否调整文档
        // 文档:发票属性。可选值：0（公司），1（个人）
        // dbschema :可选值：0（个人），1（企业）
        $sdf['title_type'] = $params['invoice_attr'] ? '0' : '1';
        
        // todo extend_arg字段后续未使用. order_invoice表没有字段;ome_event_trigger_shop_invoice::create_invoice_order 格式不正确
        return $sdf;
    }


    /**
     * 格式化发票传输数据
     *
     * @param array $params
     *
     * @return array:
     */
    protected function _formatMessagePush($params)
    {
        $sdf = array(
            'tid'          => $params['tid'],
            'tax_title'    => $params['company_title'],
            'register_no'  => $params['register_no'],
            'title_type'   => $params['invoice_attr'], //发票属性。可选值：0（公司），1（个人）
            'invoice_amount'=> $params['invoice_amount'],
            'extend_arg' => $params['extend_arg']
        );

        //发票类型: 接口文档 1=电子发票，2=纸质发票，3=专票 && ERP 0=纸质发票，1=电子发票
        //兼容金税四期,专票调整为电子类型
        $invoiceKindMap = [
            '1' => 1,
            '2' => 2,
            '3' => 1,
        ];

        if(!isset($invoiceKindMap[$params['invoice_kind']])){
            $this->__apilog['result']['msg'] = '不支持的发票类型';
            return false;
        }
        $sdf['invoice_kind'] = $invoiceKindMap[$params['invoice_kind']];
        
        // 专票兼容
        if($params['invoice_kind'] == '3'){
            $sdf['value_added_tax_invoice'] = true;
        }
        
        return $sdf;
    }
   
} 