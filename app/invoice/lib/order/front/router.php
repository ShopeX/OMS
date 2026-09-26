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

class invoice_order_front_router {
    private $obj;

    public function __construct($source)
    {
        try {
            $objName = 'invoice_order_front_' . $source;
            if (class_exists($objName)) {
                $this->obj = kernel::single($objName);
            }
        } catch (Exception $e) {
            // 默认加载other其它渠道
            try {
                $objName = 'invoice_order_front_other';
                if (class_exists($objName)) {
                    $this->obj = kernel::single($objName);
                }
            } catch (Exception $e) {
                //error
            }
        }
    }

    #获取主表特殊信息
    public function getMain($main) {
        $data = array();
        if ($this->obj) {
            $data = $this->obj->getMain($main);
        }
        return $data;
    }
    #获取明细信息
    public function getItems($main) {
        $data = array();
        if ($this->obj) {
            $data = $this->obj->getItems($main);
        }
        return $data;
    }
    #人工操作
    public function operateTax($arr) {
        $ret = array(true, ['msg'=>'操作完成']);
        if ($this->obj) {
            $ret = $this->obj->operateTax($arr);
        }
        return $ret;
    }
}