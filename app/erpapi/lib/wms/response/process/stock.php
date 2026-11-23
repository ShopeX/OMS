<?php
/**
 * 库存对账
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_response_process_stock
{
    
    /**
     * 库存对账
     *
     * @param Array $params=array(
     *                  'items'=>array(
     *                      'product_bn'=>@货号@
     *                      'normal_num'=>@良品@
     *                      'defective_num'=>@不良品@
     *                  )
     *              )
     * @return void
     * @author 
     **/
    public function quantity($params)
    {
        $items = $params['items'];
        $scMdl = app::get('console')->model('wms_stock_change');
        $data = [];
        $isFail = false;
        $msg = [];
        foreach ($items as $item) {
            $uniqueBn = sha1($item['order_code'].'-|-'.$item['order_type'].'-|-'.$item['batch_code'].'-|-'.$item['warehouse'].'-|-'.$item['product_bn'].'-|-'.$item['wms_node_id'].'-|-'.$item['normal_num'].'-|-'.$item['defective_num'].'-|-'.$item['out_biz_code']);
            $item['unique_bn'] = $uniqueBn;
            
            if (!$item['change_time']){
                unset($item['change_time']);
            }
            
            $rs = $scMdl->insert($item);
            if($rs) {
                list($rs, $rsData) = kernel::single('console_receipt_stockchange')->doAdjust($item['id']);
                if(!$rs) {
                    $isFail = true;
                    $item['is_succ'] = false;
                    $item['fail_msg'] = $rsData['msg'];
                    kernel::single('monitor_event_notify')->addNotify('wms_stock_change', [
                        'order_code' => $item['order_code'],
                        'order_type' => $item['order_type'],
                        'batch_code' => $item['batch_code'],
                        'warehouse' => $item['warehouse'],
                        'product_bn' => $item['product_bn'],
                        'errmsg'      => $rsData['msg'],
                    ]);
                } else {
                    $item['is_succ'] = true;
                }
            } else {
                $isFail = true;
                $item['is_succ'] = false;
                $item['fail_msg'] = $item['product_bn'].'写入失败：'.$scMdl->db->errorinfo();
            }
            if ($item['fail_msg']) {
                $msg[] = '单号：'.$item['order_code'].'; 批次号:'.$item['batch_code'].'; '.$item['fail_msg']; 
            }
            unset($item['wms_node_id']);
            $data[] = $item;
        }
        return $isFail ? ['rsp'=>'fail', 'msg'=>'异动失败。'.implode(' | ', $msg), 'data'=>json_encode($data, JSON_UNESCAPED_UNICODE)] : ['rsp'=>'succ', 'msg'=>'异动完成', 'data'=>json_encode($data, JSON_UNESCAPED_UNICODE)];
    }

}
