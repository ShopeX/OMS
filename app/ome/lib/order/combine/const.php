<?php
/**
 * 定义订单审核相关的业务常量
 *
 * @author wangbiao@shopex.cn
 * @version 2025.06.18
 */
class ome_order_combine_const
{
    // 禁止审核订单
    const __LARGE_APPLIANCES = 0x0001;
    
    // 赠品审批
    const __ORDER_GIFT_APPROVAL = 0x0002;
    
    // 等待SAP取消ODN单据
    const __WAIT_SAP_CANCEL_ODN = 0x0004;
    
    // 补发单审批
    const __BUFA_ORDER = 0x0008;
    
    // 调色异常
    const __ORDER_COLOR_ABNORMAL = 0x0010;
    
    // 预选仓库编码为空
    const __NONE_STORECODE = 0x0020;
    
    // 补发退货单
    const __NONE_BUFA_RESHIP = 0x0040;
    
    private $status = array(
        self::__LARGE_APPLIANCES => array('identifier'=>'禁止审核', 'text'=>'禁止审核', 'color'=>'#FF6A6A', 'search'=>'true'),
        self::__ORDER_GIFT_APPROVAL => array('identifier'=>'赠品审批', 'text'=>'赠品审批', 'color'=>'#FF00FF', 'search'=>'true'),
        self::__WAIT_SAP_CANCEL_ODN => array('identifier'=>'等待ODN取消', 'text'=>'等待取消ODN单据', 'color'=>'#FFA500', 'search'=>'true'),
        
        self::__BUFA_ORDER => array('identifier'=>'审批补发单', 'text'=>'审批补发单', 'color'=>'#00E5EE', 'search'=>'true'),
        self::__ORDER_COLOR_ABNORMAL => array('identifier'=>'调色异常', 'text'=>'调色异常', 'color'=>'#FF4500', 'search'=>'true'),
        self::__NONE_STORECODE => array('identifier'=>'预选仓为空', 'text'=>'预选仓库编码为空', 'color'=>'#fecc66', 'search'=>'true'),
        self::__NONE_BUFA_RESHIP => array('identifier'=>'补发退货单', 'text'=>'未创建补发退货单', 'color'=>'#00BFFF', 'search'=>'true'),
    );
    
    /**
     * 获取业务标识名称
     *
     * @param $is_not_combine
     * @return string
     */
    public function getIdentifier($is_not_combine)
    {
        $str = '';
        foreach ($this->status as $key => $val)
        {
            if ($is_not_combine & $key) {
                $str .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#ffffff;'>%s</span>", $val['text'].'：不允许审核', $val['color'], $val['identifier']);
            }
        }
        
        return $str;
    }
}