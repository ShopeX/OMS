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
 * 订单接口处理
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_matrix_taobao_response_qianniu extends erpapi_shop_response_qianniu
{
    /**
     * ERP订单
     *
     * @var string
     **/
    public $_order_detail= array();

    /**
     * 订单接收格式
     *
     * @var string
     **/
    public $_qnordersdf = array();


    protected function _formatSdf(){

        if (is_string($this->_qnordersdf['modifiedAddress'])) {

            $this->_qnordersdf['modifiedAddress'] = json_decode($this->_qnordersdf['modifiedAddress'],true);

            if (isset($this->_qnordersdf['oaid']) && !empty($this->_qnordersdf['oaid'])) {
                // 加密字段处理
                $hashCode = kernel::single('ome_security_hash')->get_code();
                foreach ($this->_qnordersdf['modifiedAddress'] as $key => $value) {
                    // postCode 是邮编，不属于 OMS 隐私索引字段。
                    if ($key == 'postCode') {
                        continue;
                    }

                    if(strpos($value, '*') !== false) {
                        $this->_qnordersdf['modifiedAddress'][$key] .= '>>' . $this->_qnordersdf['oaid'] . $hashCode;
                    }
                }
            }
        }
        $modifiedAddress =  $this->_qnordersdf['modifiedAddress'];
        //比较地址是否有差异
        $area = $modifiedAddress['province'].'/'.$modifiedAddress['city'].'/'.$modifiedAddress['area'];
        kernel::single('ome_func')->region_validate($area);
        $this->_qnordersdf['consignee'] = array(//area,addr,zip,mobile
                'name'      =>  $modifiedAddress['name'] ? $modifiedAddress['name'] : $this->_order_detail['consignee']['name'],
                'area'      =>  $area,
                'addr'      =>  $modifiedAddress['town']? $modifiedAddress['town'].$modifiedAddress['addressDetail'] : $modifiedAddress['addressDetail'],
                'zip'       =>  $modifiedAddress['postCode'],
                'mobile'    =>  $modifiedAddress['phone'] ? $modifiedAddress['phone'] : $this->_order_detail['consignee']['mobile'],
        );

    }

}
