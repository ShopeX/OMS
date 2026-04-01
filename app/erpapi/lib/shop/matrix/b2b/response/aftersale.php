<?php
/**
 * @desc
 * @author:
 * @since:
 */
class erpapi_shop_matrix_b2b_response_aftersale extends erpapi_shop_response_aftersale {

    protected function _formatAddParams($params) {

        return $this->_formatdata($params);
    }

    protected function _checkeditAftersale($tgReturn, $refund_version_change)
    {
        if ($tgReturn && $refund_version_change == true) {
            return false;
        } else {
            return $tgReturn;
        }
    }

    protected function _formatLogisticsUpdate($params)
    {
        if (is_string($params['logistics_info'])) {
            $logistics_info = json_decode($params['logistics_info'], true);
            $process_data = array();
            $process_data['shipcompany'] = $logistics_info['logistics_company'];
            $process_data['logino'] = $logistics_info['logistics_no'];
        }
        $sdf = array(
            'order_bn'     => $params['tid'],
            'return_bn'    => $params['aftersale_id'],
            'process_data' => $process_data
        );
        return $sdf;
    }

    function _formatdata($params){

        // 售后物流
        if (is_string($params['logistics_info'])) {
            $logistics_info = json_decode($params['logistics_info'], true);
            if ($logistics_info) {
                $arrProcessData = array(
                    'shipcompany' => $logistics_info['logistics_company'],
                    'logino'      => $logistics_info['logistics_no'],
                );
                $process_data = serialize($arrProcessData);
            }
        }

        $return_product_items = array();
        $aftersale_items = json_decode($params['aftersale_items'], true);
        foreach ($aftersale_items as $key => $val){
            $return_product_items[] = array(
                'bn' => $val['sku_bn'],
                'name' => $val['sku_name'],
                'quantity' => $val['number'],
                'num' => $val['number'],
                'price'    => $val['price'],
            );
        }

        $sdf = array(
            'return_bn'             => $params['aftersale_id'],
            'order_bn'              => $params['tid'],
            'status'                => $params['status'],
            'member_uname'          => $params['buyer_name'],
            'return_product_items'  => $return_product_items,
            'process_data'          => $process_data,
            'attachment'            => $params['attachment'] ? $params['attachment'] : null,
            'title'                 => $params['title'],
            'content'               => $params['content'],
            'comment'               => $params['messager'],
            'memo'                  => $params['memo'],
            'add_time'              => $params['created'] ? kernel::single('ome_func')->date2time($params['created']) : time(),
            'source'                => 'matrix',
            'shop_type'             => $this->__channelObj->channel['shop_type'],
            'org_id'                => $this->__channelObj->channel['org_id'],
        );

        if ($params['status'] == '1' || $params['status'] == '5') {
            $sdf['refund_version_change'] = true;
        }

        return $sdf;
    }
} 