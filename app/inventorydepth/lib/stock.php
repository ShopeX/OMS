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
 * 库存同步处理类
 * 
 * @author chenping<chenping@shopex.cn>
 */

class inventorydepth_stock {
    //const PUBL_LIMIT = 100; //批量发布的最大上限数
    //const SYNC_LIMIT = 50; //批量下载的最大上限数
    protected $type;

    public function __construct($app)
    {
        if(is_array($app)) {
            $this->app = $app[0];
            $this->type = $app[1];
        } else {
            $this->app = $app;
        }
    }

    public function get_benchmark($key=null)
    {
        $return = array(
                'actual_stock'   => '可售库存',
                //'release_stock'  => '发布库存',
                'branch_actual_stock'     => '仓库可售',
                'md_actual_stock'     => '门店可售',
                'shop_freeze'    => '店铺预占',
                'sales_material_shop_freeze' => '销售物料店铺冻结',
                //'globals_freeze' => '全局预占',
                'actual_safe_stock'   => '可售库存-安全库存',
                'branch_actual_safe_stock'     => '仓库可售-安全库存',
                'md_actual_safe_stock'     => '门店可售-安全库存',
            );
        if($this->type == '3') {//代表门店库存回传
            $return = array(
                    'actual_stock'   => '可售库存',
                    'safe_stock' => '安全库存',
                );
        }
        return $key ? $return[$key] : $return;
    }

    public function get_benchobj($key=null)
    {
        $return = array(
                'actual_stock'   => '可售库存',
                //'release_stock'  => $this->app->_('发布库存'),
                //'shop_freeze'    => $this->app->_('店铺预占'),
                //'globals_freeze' => $this->app->_('全局预占'),
            );
        return $key ? $return[$key] : $return;
    }


    public function update_release_stock($merchandise_id, $result)
    {

        $r = $this->app->model('viewProduct_stock')->update(array('release_stock'=>$result,'release_status'=>'sleep'),array('merchandise_id'=>$merchandise_id));

        return $r;
    }

    public function update_check($data, $result, &$msg)
    {
        foreach($data as $d){
            $merchandise_id = $d['merchandise_id'];

            $resultVal = $this->check_and_build($merchandise_id, $result, $msg);
            if ($resultVal === false) {
                if (strpos($msg, '小于零') !== false)
                    $msg = $this->app->_('部分商品调整后的库存已经小于零，请重新填写');

                return false;
            }

            $this->update_release_stock($merchandise_id,$resultVal);
        }
        return true;
    }

    /**
     * 通过公式更新发布库存 弃用
     * @return void
     * @author 
     **/
    public function updateReleaseByformula($filter,$result,&$errormsg)
    {
        return false;
    }

     public function storeNeedUpdateSku($order_id, $shop_id) {
        $prefix = 'inventorydepth_stock-';
        $objs = app::get('ome')->model('order_objects')->getList('bn', ['order_id'=>$order_id]);
        foreach($objs as $v) {
            $index = $prefix.$shop_id.'-'.$v['bn'];
            cachecore::store($index, 1, 600);
        }
    }
    
    /**
     * 运行公式
     *
     * @param Array $sku  商品明细
     * @param String $result 公式
     * @param String $msg 错信息
     * @return void
     * @author
     **/
    public function formulaRun($result,$sku=array(),&$msg,$type='')
    {
        if (is_numeric($result)) {
            if ($result < 0) {
                $msg = $this->app->_('库存已经小于零，请重新填写');
                return false;
            }
            
            return  (int)$result;
        }
        
        # 过滤掉敏感字符
        $dange = array('select', 'update', 'drop', 'delete', 'insert', 'alter');
        $tmp = strtolower($result);
        foreach ($dange as $val){
            if (strpos($tmp, $val) !== false) {
                $msg = $this->app->_('what are you doing? go away!');
                return false;
            }
        }
        
        if (!$sku) {
            foreach ($this->get_benchmark() as $key => $val) {
                $result = str_replace('{'.$val.'}', '0', $result);
            }
            try {
                $result = @kernel::database()->selectrow('select ' . $result . ' as val');
            } catch (Exception $e) {
                $msg = '公式错误';
                return false;
            }
            if ($result !== false) $result = $result['val'];
            
            if (!is_numeric($result)) {
                $msg = $this->app->_('公式错误，请重新填写。');
                
                return false;
            }
            
            return true;
        }
        
        //-------------------变量实际替换校验------------------//
        preg_match_all('/{(.*?)}/',$result,$matches);
        $benchmark = $this->get_benchmark();
        
        # 符合条件的所有货品
        $calLib = kernel::single('inventorydepth_stock_calculation');
        if($this->type == '3') {//代表门店库存回传
            $calLib = kernel::single('inventorydepth_offline_calculation');
        }
        if($matches) {
            
            //按仓库级回写库存
            if($sku['sync_mode'] == 'warehouse'){
                $stock = call_user_func_array(array($calLib,'get_'.$type.'actual_stock'),$sku);
                
                if($stock === false) {
                    $msg = $this->app->_('公式有误!');
                    return false;
                }
                
                return $stock; //以branch_bn仓库编码为下标的多维数组
            }
            $calLib->actual_stock_make = '';
            $msg = 'quantity=' . $result;
            foreach($matches[1] as $match){
                $m = array_search($match,$benchmark);
                
                if(false === $m) {
                    $msg = $this->app->_('公式错误!');
                    return false;
                }
                
                $stock = call_user_func_array(array($calLib,'get_'.$type.$m),$sku);
                $result = str_replace('{'.$match.'}', $stock, $result);
                $msg = str_replace('{'.$match.'}', $match.':'.$stock, $msg);
            }
            if (method_exists($calLib, 'get_'.$type.'actual_stock_make')) {
                $msg .= ' 其中可售库存(' . call_user_func_array(array($calLib,'get_'.$type.'actual_stock_make'),$sku) . ')';
            } elseif ($calLib->actual_stock_make) {
                $msg .= $calLib->actual_stock_make;
            }
            
        }
        
        # 验证 $result
        $result = @kernel::database()->selectrow('select ' . $result . ' as val');
        //@eval("\$result = $result;");
        
        if($result === false) {
            $msg = $this->app->_('公式有误!');
            return false;
        }
        
        $result = $result['val'];
        if (!is_numeric($result)) {
            $msg = $this->app->_('计算结果异常：可能仓库未绑定店铺!');
            return false;
        }else if (floor($result) < 0) {
            //$msg = $sku['shop_product_bn'] . ' ' . $this->app->_('调整后的库存已经小于零，请重新填写');
            return 0;
        }
        
        return (int)$result;
    }
    
    public function getNeedUpdateSku($shop_id, $bn) {
        $prefix = 'inventorydepth_stock-';
        $index = $prefix.$shop_id.'-'.$bn;
        return cachecore::fetch($index);
    }

    /**
     * 公式安全计算
     * 如公式为{可售库存} + {安全库存}，则需要将可售库存、安全库存替换为实际值
     * 
     * @param string $formula 公式
     * @param array $params 参数
     * @return array [bool, string]
     */
    public function cal($formula, $params) 
    {
        $benchmark = $this->get_benchmark();

        // 正则
        $pattern = '/\{('.implode('|', $benchmark).')\}/';

        // 替换变量为数值（使用正则防止变量名被错误替换，比如 "ab" 替换成 a 的值）
        $replacedFormula = preg_replace_callback(
            $pattern,
            function ($matches) use ($params, $benchmark) {
                $key = array_search($matches[1], $benchmark);

                return (int)$params[$key];
            },

            $formula
        );
    
        // 使用简单的白名单验证数学的四则运算表达式是否合法
        if (!preg_match('/^[0-9+\-*\/\.\s\(\)]+$/', $replacedFormula)) {
            throw new \Exception('公式包含非法字符！原始公式: ' . $formula . ', 替换后公式: ' . $replacedFormula);
        }

        // 安全计算表达式（不使用 eval）
        // 可使用安全的 eval 替代函数或第三方库
        $result = eval("return $replacedFormula;");

        return $result;
    }
}


