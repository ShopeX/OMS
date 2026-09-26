<?php
/**
 * [票易通]电子发票参数处理Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2026.04.02
 */
class invoice_event_trigger_data_piaoyitong extends invoice_event_trigger_data_common
{
    /**
     * 组织参数
     * 
     * @param array $orderInfo
     * @param string $einvoice_type
     * @return array
     */
    public function getEinvoiceRequestParams($orderInfo, $einvoice_type='blue')
    {
        $rsp = array('rsp'=>'fail', 'error_msg'=>'');
        $error_msg = '';
        
//        //绑定关系
//        $result = kernel::single('erpapi_router_request')->set('bind',$orderInfo['channel_type'])->bind_bind();
//        if(!$result){
//            $error_msg = '绑定关系失败!';
//            $rsp['error_msg'] = $error_msg;
//
//            return $rsp;
//        }
        
        $this->__total_je = $this->__total_se = 0;
        $this->tax_rate   = $orderInfo['tax_rate'] / 100;
        
        //发票明细列表
        $items = $this->getEinvoiceInvoiceItems($orderInfo, $einvoice_type, '', $error_msg);
        if(!$items){
            $error_msg = '没有获取到发票明细'. ($error_msg ? '：'. $error_msg : '');
            $rsp['error_msg'] = $error_msg;
            
            return $rsp;
        }
        
        if(bccomp($orderInfo['amount'], ($this->__total_je + $this->__total_se)) != 0){
            $rsp['error_msg'] = '开票金额不正确';
            return $rsp;
        }
        
        $hjje = $this->__total_je;
        $se   = $this->__total_se;
        $jshj = $this->__total_se + $this->__total_je;
        
        if($einvoice_type == 'red'){
            $hjje =- $hjje; //(价税)合计金额  = 价税合计 - 税额,
            $jshj =- $jshj;
            $se =- $se;
        }
        
        $orderInfo['items']         = $items;
        $orderInfo['einvoice_type'] = $einvoice_type;
        $orderInfo['hjjeTotal']     = $hjje; # (价税)合计金额  = 价税合计 - 税额,
        $orderInfo['seTotal']       = $se;
        $orderInfo['jshjTotal']     = $jshj; # 价税合计,价税合计 = 金额(含)
        $orderInfo['tid'] = $orderInfo['order_bn'];
        
        //单号需要唯一
        $orderInfo['order_bn'] .= '#'.$orderInfo['id'];
        
        return $orderInfo;
    }
    
    /**
     * 获取上传数据
     *
     * @return void
     * @author
     */
    public function getUploadParams($invoice, $electronic)
    {
        // 获取PDF发票地址
        if($electronic['file_path']){
            $fileInfo = json_decode($electronic['file_path'], true);
            if($fileInfo['pdf_url']){
                // 原来的url
                $electronic['original_url'] = $fileInfo['url'];
                
                // PDF发票地址
                $electronic['pdf_url'] = $fileInfo['pdf_url'];
                $electronic['url'] = $fileInfo['pdf_url'];
                
                // 其它地址
                $electronic['xml_url'] = $fileInfo['xml_url'];
                $electronic['ofd_url'] = $fileInfo['ofd_url'];
            }
        }
        
        // sdf
        $sdf = array(
            'electronic' => $electronic,
            'invoice'    => $invoice,
        );
        
        // 电子发票开票类型，billing_type：1-蓝票 2-红票
        if ($electronic['billing_type'] == '1') {
            $this->__total_je = $this->__total_se = 0;
            $this->tax_rate   = $invoice['tax_rate']/100;
            
            $items = self::getEinvoiceInvoiceItems($invoice,'blue','upload'); //发票明细列表
            
            if(!$items) return false;
            
            $sdf['items'] = $items;
        } elseif ($electronic['billing_type'] == '2') {
            // 获取蓝票信息
            $invEleItemMdl = app::get('invoice')->model('order_electronic_items');
            
            $blueEle = $invEleItemMdl->db_dump(array('id'=>$invoice['id'],'billing_type'=>'1'));
            
            $sdf['electronic']['normal_invoice_code'] = $blueEle['invoice_code'];
            $sdf['electronic']['normal_invoice_no']   = $blueEle['invoice_no'];
        }
        
        return $sdf;
    }
    
    /**
     * 检查红冲是否需进行确认, 数电专票业务使用
     *
     * @param $sdf
     * @return void
     */
    public function checkCancelConfirm($sdf)
    {
        return false;
    }
}