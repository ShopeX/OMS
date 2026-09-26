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
 * @author ykm 2015-12-24
 * @describe 打印数据格式化
 */
class logisticsmanager_print_format {
    private $fieldFormat = array(
        'delivery_order_amount'   => 'moneyFormat',
        'delivery_order_amount_d' => 'financeNum',
        'delivery_receivable'     => 'moneyFormat',
        'delivery_receivable_d'   => 'financeNum',
        'total_product_weight_g'  => 'weightToG',
        'total_product_weight_kg' => 'weightToKG',
        'cp_code'                 => 'strToUpper',
        'order_paytime'           => 'dateFormat',
        'member_uname'            => 'originEncrypt',
    );

    /**
     * formatField
     * @param mixed $printRowData 数据
     * @return mixed 返回值
     */

    public function formatField(&$printRowData) {
        foreach($printRowData as $k => &$val) {
            if ($k == 'wxshipin_product_info') {
                continue;
            }
            if(array_key_exists($k, $this->fieldFormat)) {
                $method = $this->fieldFormat[$k];
                if(method_exists($this, $method)) {
                    $val = $this->$method($val);
                }
            } else { //默认转换成字符串、去掉前后的空格
                $val = $this->printSingleFormat($val);
            }
        }
    }

    /**
     * moneyFormat
     * @param mixed $money money
     * @return mixed 返回值
     */
    public function moneyFormat($money) {
        $num = number_format($money, 2, '.', ' ');
        return isset($num) ? $num : 0.00;
    }

    /**
     * financeNum
     * @param mixed $num num
     * @return mixed 返回值
     */
    public function financeNum($num) {
        $num = number_format($num, 2, '.', '');
        $formatNum = kernel::single('ome_delivery_template')->financeNum($num);
        return isset($formatNum) ? $formatNum : '';
    }

    /**
     * weightToG
     * @param mixed $num num
     * @return mixed 返回值
     */
    public function weightToG($num) {
        return $num . 'g';
    }

    /**
     * weightToKG
     * @param mixed $num num
     * @return mixed 返回值
     */
    public function weightToKG($num) {
        return ($num/1000) . 'kg';
    }

    /**
     * strToUpper
     * @param mixed $str str
     * @return mixed 返回值
     */
    public function strToUpper($str) {
        return strtoupper($str);
    }

    /**
     * dateFormat
     * @param mixed $time time
     * @return mixed 返回值
     */
    public function dateFormat($time)
    {
        return $time?date('Y-m-d H:i:s',$time):'';
    }

    /**
     * originEncrypt
     * @param mixed $value value
     * @return mixed 返回值
     */
    public function originEncrypt($value)
    {
        return kernel::single('ome_security_hash')->getOriginText($value);
    }

    /**
     * printSingleFormat
     * @param mixed $single single
     * @return mixed 返回值
     */
    public function printSingleFormat($single) {
        if($single === null) {
            return '';
        } elseif (is_bool($single)) {
            return $single === false ? 'false' : 'true';
        }
        $str = strval($single);
        $str = trim($str);
        $str = str_replace(array('&#34;','"','&quot;','&quot'),array('“','“','“'), $str);
        $str = str_replace(array('&quot;','&quot'), array('”','”'), $str);
        return $str;
    }

    /**
     * array2xml2
     * @param mixed $data 数据
     * @param mixed $root root
     * @return mixed 返回值
     */
    public function array2xml2($data,$root='root'){
        $xml='<'.$root.'>';
        $this->_array2xml($data,$xml);
        $xml.='</'.$root.'>';
        return $xml;
    }

    private function _array2xml(&$data,&$xml){
        if(is_array($data)){
            foreach($data as $k=>$v){
                if(is_numeric($k)){
                    $xml.='<item>';
                    $xml.=$this->_array2xml($v,$xml);
                    $xml.='</item>';
                }else{
                    $xml.='<'.$k.'>';
                    $xml.=$this->_array2xml($v,$xml);
                    $xml.='</'.$k.'>';
                }
            }
        }elseif(is_numeric($data)){
            $xml.=$data;
        }elseif(is_string($data)){
            $xml.='<![CDATA['.$data.']]>';
        }
    }
}
