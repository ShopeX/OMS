<?php
/**
 * 生成售后单
 * @package default
 * @author
 **/
class sales_aftersale
{
    public $aftersale_type = array(
        'refund' => 'refund',
        'return' => 'change',
        'change' => 'change',
        'refuse' => 'refuse',
    );
    
    public $return_product_type = array(
        'return' => 'RETURN_STORAGE',
        'change' => 'RE_STORAGE',
        'refund' => 'SALE_REFUND',
    );
    
    public function generate_aftersale($id, $type, $trigger_event = '1')
    {
        if (in_array($type, array_keys($this->aftersale_type))) {
            $obj_aftersale = kernel::single('sales_aftersale_type_' . $this->aftersale_type[$type]);

            if (is_object($obj_aftersale) && method_exists($obj_aftersale, 'generate_aftersale')) {

                $data = $obj_aftersale->generate_aftersale($id);

                if ($data === false) {
                    return true;
                } else {
                    $Oaftersale           = app::get('sales')->model('aftersale');
                    if($data['aftersale_id'] && $data['aftersale_bn'])
                    {
                        $aftersale_items = $data['aftersale_items'];
                        unset($data['aftersale_items']);
                      
                        //不用更新售后单创建时间
                        unset($data['aftersale_time']);

                        //更新售后单信息
                        $result = $Oaftersale->save($data);
                        
                    }else{
                        $data['aftersale_bn'] = $Oaftersale->get_aftersale_bn();
                        $data['trigger_event'] = $trigger_event;
                        $result               = $Oaftersale->save($data);
                        // 退款后自动冲红
                        if (!in_array($type,['refuse','change']) && $data['refundmoney']>0 && $data['order_id']) {
                            kernel::single('invoice_order_front')->updateItemsByOrder($data['order_id'], 'b2c', ($data['refund_shipping_fee'] > 0));
                            //kernel::single('ome_event_trigger_shop_invoice')->process(array('order_id'=>$data['order_id'],'invoice_action_type'=>'2'),'cancel_invoce_order',"order_aftersale");
                        }
                        foreach(kernel::servicelist('sales.service.aftersale.after.generate') as $service) {
                            if(method_exists($service,'after_generate')) {
                                $service->after_generate($data['aftersale_id']);
                            }
                        }
                    }
                    
                    return $result;
                }
            } else {
                trigger_error('Type is not recognized', E_USER_ERROR);
                return false;
            }
        } else {
            trigger_error('Type is not recognized', E_USER_ERROR);
            return false;
        }
    }

    /**
     * 格式化财务对账数据(已经废弃)
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function _format_finance_data($data)
    {
        $obj_aftersale   = kernel::single('sales_aftersale_type_change');
        $Oaftersale      = app::get('sales')->model('aftersale');
        $data['sale_bn'] = $data['aftersale_bn'];
        $type            = $this->return_product_type['change'];

        $sales_items = $data['aftersale_items'];
        if ($sales_items) {
            foreach ($sales_items as $k => $sale) {
                $sales_items[$k]['order_id']     = $data['order_id'];
                $sales_items[$k]['order_bn']     = $data['order_bn'];
                $sales_items[$k]['shop_id']      = $data['shop_id'];
                $sales_items[$k]['shop_name']    = $data['shop_name'];
                $sales_items[$k]['name']         = $sale['product_name'];
                $sales_items[$k]['sales_amount'] = $sale['saleprice'];
                $sales_items[$k]['nums']         = $sale['num'];
            }
            unset($data['aftersale_items']);
            $data['sale_amount']      = $data['refund_apply_money'];
            $saledata[$type]['sales'] = $data;

            $saledata[$type]['sales']['sales_items'] = $sales_items;

            $financeObj = kernel::single('finance_iostocksales');
            $result     = $financeObj->do_sales_data($saledata);
        }
    }
    
    /**
     * 获取退货关联的订单明细
     * 
     * @param int $order_id
     * @return array
     */
    public function getReturnOrderItems($order_id, $is_archive=false)
    {
        if(empty($order_id)) {
            return false;
        }
        $orderMdl = app::get('ome')->model('orders');
        
        if($is_archive){
            $sql = "SELECT a.item_id,a.order_id,a.obj_id,a.bn,a.product_id,a.item_type, b.goods_id,b.bn AS goods_bn,a.shop_goods_id,a.shop_product_id FROM sdb_archive_order_items AS a ";
            $sql .= " LEFT JOIN sdb_archive_order_objects AS b ON a.obj_id=b.obj_id WHERE a.order_id=". $order_id ." AND return_num>0";
        }else{
            $sql = "SELECT a.item_id,a.order_id,a.obj_id,a.bn,a.product_id,a.item_type, b.goods_id,b.bn AS goods_bn,a.shop_goods_id,a.shop_product_id FROM sdb_ome_order_items AS a ";
            $sql .= " LEFT JOIN sdb_ome_order_objects AS b ON a.obj_id=b.obj_id WHERE a.order_id=". $order_id ." AND return_num>0";
        }
        
        //select
        $dataList = $orderMdl->db->select($sql);
        if(empty($dataList)){
            return false;
        }
        
        //退货明细
        $result = array();
        $result['items'] = array_column($dataList, null, 'item_id');
        $result['products'] = array_column($dataList, null, 'product_id');
        
        return $result;
    }
}
