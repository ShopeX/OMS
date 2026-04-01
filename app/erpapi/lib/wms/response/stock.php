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
* 库存对账
*
* @copyright shopex.cn 2013.04.08
* @author dongqiujing<123517746@qq.com>
*/
class erpapi_wms_response_stock extends erpapi_wms_response_abstract
{    
    /**
     * wms.stock.quantity
     *
     **/
    public function quantity($params){
        $this->__apilog['title']       = $this->__channelObj->wms['channel_name'] . '库存异动';   

        $open = app::get('ome')->getConf('wms.stock.quantity.open');
        if($open == 'false') {
            $this->__apilog['result']['msg'] = '未开启';
            return false;
        }
        $data = array();
        $stock_items = array();
        $items = $params['item'] ? json_decode($params['item'], true) : array();

        //接口字段映射配置处理
        //例如：$mapper_config['content'] = '{"warehouse":"{{warehouse}}-{{product_code}}"}';
        $channel_id   = $this->__channelObj->wms['channel_id'];
        $channel_type = $this->__channelObj->wms['channel_type'];
        // 查询接口字段映射配置
        $mapper_model  = app::get('desktop')->model('interfacefield_mapper');
        $mapper_config = $mapper_model->getMapperByChannel($channel_id, $channel_type, 'stockchange_report');
    
        // 先处理items中的字段映射
        if ($mapper_config && $mapper_config['content']) {
            $content = json_decode($mapper_config['content'], true);
            if ($content && $items) {
                foreach ($items as $key => $item) {
                    // 保存原始数据
                    $original_item = $item;
                    
                    // 先处理常规字段映射
                    foreach ($content as $field_name => $template) {
                        if (isset($item[$field_name])) {
                            $processed_value   = $this->processTemplateVariables($template, $item);
                            $item[$field_name] = $processed_value;
                        }
                    }
                    
                    // 特殊处理：残次商品重置warehouse值（使用原始数据）
                    if (isset($item['inventoryType']) && $item['inventoryType'] != 'ZP' && isset($content['warehouse_inventoryType'])) {
                        $processed_value = $this->processTemplateVariables($content['warehouse_inventoryType'], $original_item);
                        $item['warehouse'] = $processed_value;
                    }
                    
                    // 更新items数组
                    $items[$key] = $item;
                }
            }
        }

        if($items){
            $data['order_code'] = $items[0]['order_code'];
            foreach($items as $key=>$val)  {
                
                $stock_items[] = array(
                    'order_code'    => $val['order_code'],
                    'order_type'    => $val['orderType'],
                    'batch_code'    => $val['batch_code'],
                    'warehouse'     => $val['warehouse'],
                    'product_bn'    => $val['product_bn'],
                    'normal_num'    => $val['normal_num'],
                    'defective_num' => $val['defective_num'],
                    'wms_item_id'   => $val['item_id'],
                    'product_date'  => $val['productDate'],
                    'produce_code'  => $val['produceCode'],
                    'expire_date'   => $val['expireDate'],
                    'change_time'   => $val['changeTime'],
                    'out_biz_code'  => $val['outBizCode'],
                    'sn_list'       => $val['sn_list'] ? json_encode($val['sn_list'], JSON_UNESCAPED_UNICODE) : '',
                    'wms_node_id'   => $this->__channelObj->wms['node_id'],
                    
                );

            }
        }

        $this->__apilog['original_bn'] = $data['order_code'];
        
        $data['items'] = $stock_items;
        return $data;        
    }
    
    /**
     * 处理模板变量
     * 
     * @param string $template 模板字符串
     * @param array $data 数据数组
     * @return string 处理后的字符串
     */
    private function processTemplateVariables($template, $data)
    {
        // 使用正则表达式匹配所有 {{variable}} 格式的变量
        $pattern = '/\{\{([^}]+)\}\}/';
        
        return preg_replace_callback($pattern, function($matches) use ($data) {
            $variable_name = $matches[1];
            
            // 直接使用变量名作为字段名，保持与$val中的key一致
            // 如果数据中存在该字段，返回其值，否则返回原始变量名
            return isset($data[$variable_name]) ? $data[$variable_name] : $matches[0];
        }, $template);
    }
}
