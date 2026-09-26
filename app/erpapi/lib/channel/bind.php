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
 * @author ykm 2018/3/20
 * @describe 绑定相关
 */
class erpapi_channel_bind extends erpapi_channel_abstract
{
    private $bindConfig = array(
        'crossborder' => array(
            'node_type' => 'kjb2c',
            'to_node'   => '1183376836',
            'shop_name' => '跨境申报',
            'title'     => '电子口岸',
        ),
        'jinshui' => array(
            'node_type' => 'jinshui',
            'to_node'   => '1483186233',
            'shop_name' => '金税',
            'title'     => '金税电子发票',
        ),
        'pinjun' => array(
            'node_type' => 'pinjun',
            'to_node'   => '1826146436',
            'shop_name' => '品骏',
            'title'     => '品骏电子面单',
        ),
        'yto4gj' => array(
            'node_type' => 'yto4gj',
            'to_node'   => '1324146426',
            'shop_name' => '圆通国际',
            'title'     => '圆通国际电子面单',
        ),
        'other' => array(
            'title' => '其他',
        ),
        'chinaums' => array(
            'node_type' => 'chinaums',
            'to_node' => '',
            'shop_name' => '银联金4',
            'title' => '银联金4电子发票',
        ),
        'baiwang' => array(
            'node_type' => 'baiwang_cloud',
            'to_node'   => '',
            'shop_name' => '百望金4',
            'title'     => '百望金4电子发票',
        ),
        'huifu' => array(
            'node_type' => 'huifu',
            'to_node'   => '',
            'shop_name' => '汇付发票',
            'title'     => '汇付电子发票',
        ),
        'piaoyitong' => array(
            'node_type' => 'piaoyitong',
            'to_node'   => '',
            'shop_name' => '票易通',
            'title'     => '票易通电子发票',
        ),
    );
    
    public $channel;

    /**
     * 初始化
     * @param mixed $node_id ID
     * @param mixed $channel_id ID
     * @return mixed 返回值
     */

    public function init($node_id,$channel_id)
    {
        $this->__adapter = '';
        $this->__platform = '';

        $this->channel = $this->bindConfig[$channel_id];
        if (!$this->channel) return false;


        return true;
    }

    /**
     * summary
     * 
     * @return void
     * @author 
     */
    public function getNode($node_type)
    {
        return $this->bindConfig[$node_type];
    }
}
