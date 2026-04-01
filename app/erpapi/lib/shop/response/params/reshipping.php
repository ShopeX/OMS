<?php
/**
 * 补寄申请参数验证
 *
 * @category
 * @package
 * @author
 * @version $Id: Z
 */
class erpapi_shop_response_params_reshipping extends erpapi_shop_response_params_abstract
{
    /**
     * 补寄申请参数验证规则
     *
     * @return array
     */
    protected function add()
    {
        $arr = array(
            'dispute_id' => array(
                'required' => 'true',
                'errmsg' => '补寄申请单号不能为空'
            ),
            'status' => array(
                'required' => 'true',
                'errmsg' => '补寄状态不能为空'
            ),
        );
        return $arr;
    }
}

