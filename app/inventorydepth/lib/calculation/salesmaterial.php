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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2022/6/13 14:30:53
 * @describe: 销售物料分配
 * ============================
 */
class inventorydepth_calculation_salesmaterial {
    protected $obj;
    protected $isInit = false;
    protected $releaseStock = [];
    protected $shopFreeze = []; // 缓存店铺冻结数据，key为 sha1(shop_id.'-'.sm_id)

    public function init($salesmaterial) {
        $this->isInit = true;
        $this->releaseStock = [];
        $this->shopFreeze = [];
        $smBn = [];
        foreach ($salesmaterial as $key => $value) {
            $smBn[] = $value['sales_material_bn'];
        }
        $list = app::get('inventorydepth')->model('shop_adjustment')->getList('release_stock,shop_product_bn,shop_bn',array('shop_product_bn'=>$smBn));
        if ($list) {
            foreach ($list as $l) {
                $tmpsha1 = sha1($l['shop_bn'].'-'.$l['shop_product_bn']);
                $this->releaseStock[$tmpsha1] = (int)$l['release_stock'];
            }
        }
        
        // 批量查询冻结数据
        $smIds = [];
        foreach ($salesmaterial as $key => $value) {
            $smIds[] = $value['sm_id'];
        }
        
        if (!empty($smIds)) {
            $smIds = array_unique($smIds);
            
            $orderObjectsFreezeModel = app::get('ome')->model('order_objects_freeze');
            $freezeList = $orderObjectsFreezeModel->getList('sm_id,shop_id,quantity', [
                'sm_id|in' => $smIds
            ]);
            
            // 按 sm_id 和 shop_id 汇总
            foreach ($freezeList as $freeze) {
                $key = sha1($freeze['shop_id'].'-'.$freeze['sm_id']);
                if (!isset($this->shopFreeze[$key])) {
                    $this->shopFreeze[$key] = 0;
                }
                $this->shopFreeze[$key] += (int)$freeze['quantity'];
            }
        }
    }

    public function get_release_stock($sku)
    {
        $shop_product_bn = $sku['shop_product_bn'];
        $shop_bn = $sku['shop_bn'];
        $shop_id = $sku['shop_id'];
        $sha1Str = $shop_bn.'-'.$shop_product_bn;
        $sha1 = sha1($sha1Str);
        if(isset($this->releaseStock[$sha1])) return [$this->releaseStock[$sha1], []];
        if($this->isInit) {
            return [0, ['warning'=>'初始化未查到']];
        }

        $skusLib = kernel::single('inventorydepth_shop_skus');
        $shop_product_bn_crc32 = $skusLib->crc32($shop_product_bn);
        $shop_bn_crc32 = $skusLib->crc32($shop_bn);
        $list = $this->app->model('shop_adjustment')
            ->select()->columns('release_stock,shop_product_bn,shop_bn')
            ->where('shop_product_bn_crc32=?',$shop_product_bn_crc32)
            ->where('shop_bn_crc32=?',$shop_bn_crc32)
            ->instance()->fetch_all();

        $release_stock = 0;
        foreach ($list as $key => $value) {
            if ($value['shop_product_bn'] == $shop_product_bn && $value['shop_bn'] == $shop_bn) {
                $release_stock = $value['release_stock'];
                break;
            }
        }
        $this->releaseStock[$sha1] = (int)$release_stock > 0 ? (int)$release_stock : 0;
        return [$this->releaseStock[$sha1], []];
    }

    /**
     * 获取销售物料店铺冻结数量
     * @param array $sku 包含 shop_product_bn, shop_id, shop_bn
     * @return array [冻结数量, 详细信息]
     */
    public function get_sales_material_shop_freeze($sku)
    {
        $shop_product_bn = $sku['shop_product_bn'];
        $shop_id = $sku['shop_id'];
        
        // 获取销售物料信息
        $basicObj = kernel::single('inventorydepth_calculation_basicmaterial');
        $salesMaterial = $basicObj->getSalesMaterial($shop_product_bn, $shop_id);
        
        if (empty($salesMaterial)) {
            return [0, ['warning' => '未找到销售物料']];
        }
        
        $sm_id = $salesMaterial['sm_id'];
        
        $key = sha1($shop_id.'-'.$sm_id);
        
        if (isset($this->shopFreeze[$key])) {
            return [$this->shopFreeze[$key], ['info' => '销售物料店铺冻结', 'quantity' => $this->shopFreeze[$key]]];
        }
        
        if ($this->isInit) {
            return [0, ['warning' => '初始化未查到']];
        }
        
        // 如果未初始化，则查询数据库
        $orderObjectsFreezeModel = app::get('ome')->model('order_objects_freeze');
        $freezeList = $orderObjectsFreezeModel->getList('quantity', [
            'sm_id' => $sm_id,
            'shop_id' => $shop_id
        ]);
        
        $totalFreeze = 0;
        foreach ($freezeList as $freeze) {
            $totalFreeze += (int)$freeze['quantity'];
        }
        
        $freezeQuantity = (int)$totalFreeze;
        $this->shopFreeze[$key] = $freezeQuantity;
        
        return [$freezeQuantity, ['info' => '销售物料店铺冻结', 'quantity' => $freezeQuantity]];
    }

    /**
     * 运行公式
     *
     * @param array $sku  商品明细
     * @param string $result 公式
     * @param string $msg 错信息
     * @return int|false 计算结果或false
     * @author 
     **/
    public function formulaRun($formula,$sku,&$msg)
    {
      if (is_numeric($formula)) 
      {
           if ($formula < 0) 
           {
               $regulation_code = isset($sku['regulation_code']) ? $sku['regulation_code'] : '';
               $error_msg = '库存已经小于零，请重新填写';
               if ($regulation_code) {
                   $error_msg .= ' (规则编码: ' . $regulation_code . ')';
               }
               throw new \Exception($error_msg);  
           }

           return  (int)$formula;
       }

       //-------------------变量实际替换校验------------------//
       $benchmark = kernel::single('inventorydepth_stock')->get_benchmark();

       $pattern = '/\{('.implode('|', $benchmark).')\}/';
       preg_match_all($pattern,$formula,$matches);

       $params = $detail = [];

       if($matches) 
       {
            foreach($matches[1] as $match){
                $m = array_search($match,$benchmark);

                if(false === $m) {
                    $regulation_code = isset($sku['regulation_code']) ? $sku['regulation_code'] : '';
                    $error_msg = '公式错误:' . $formula;
                    if ($regulation_code) {
                        $error_msg .= ' (规则编码: ' . $regulation_code . ')';
                    }
                    throw new \Exception($error_msg);
                }

                $method = 'get_'.$m;
                list($quantity, $msg) = $this->{$method}($sku);

                if($quantity === false) {
                    $regulation_code = isset($sku['regulation_code']) ? $sku['regulation_code'] : '';
                    $error_msg = $match . '计算错误:' . $msg;
                    if ($regulation_code) {
                        $error_msg .= ' (规则编码: ' . $regulation_code . ', 方法: ' . $method . ')';
                    }
                    throw new \Exception($error_msg);
                }

                $detail[$match]['quantity'] = $quantity;
                $detail[$match]['info'] = $msg;

                $params[$m] = $quantity;
            }

            $msg = $detail;
       }

       $update_stock = kernel::single('inventorydepth_stock')->cal($formula, $params);

       if (!is_numeric($update_stock)) {
           $regulation_code = isset($sku['regulation_code']) ? $sku['regulation_code'] : '';
           $error_msg = '计算结果异常:' . $update_stock;
           if ($regulation_code) {
               $error_msg .= ' (规则编码: ' . $regulation_code . ')';
           }
           throw new \Exception($error_msg);
       }else if (floor($update_stock) < 0) {
           return 0;
       }

       return (int)$update_stock;  
    }

    /**
     * 获取库存回写数
     * @param  string $method 方法，库存回写规则变量值
     * @param  string $args         参数
     * @return array                  [1, [
     *                                        'error'=>'',
     *                                        'info'=>'',
     *                                        'basic'=>[
     *                                            'bn' => [
     *                                                'quantity' => 1,
     *                                                'info' => [
     *                                                    '公式' => '库存-全局预占-仓库预占-指定仓预占',
     *                                                    '库存' => 1,
     *                                                    '全局预占' => 0,
     *                                                    '仓库预占' => 0,
     *                                                    '指定仓预占' => 0,
     *                                                ]
     *                                             ]
     *                                        ],
     *                                    ]]
     */
    public function __call($method, $args) {
        $sku = $args[0];
        if(empty($sku['shop_product_bn']) || empty($sku['shop_bn']) || empty($sku['shop_id']) || empty($method)) {
            return [false, ['error'=>'参数不全'.json_encode($sku, JSON_UNESCAPED_UNICODE).$method]];
        }
        

        $basicObj = kernel::single('inventorydepth_calculation_basicmaterial');

        // 设置专用供货仓信息
        $basicObj->setApplySupplyBranches($sku['supply_branch_bn']);
        
        
        $type = $basicObj->getSalesMaterialType($sku['shop_product_bn'], $sku['shop_id']);
        $sales_material = $basicObj->getSalesMaterial($sku['shop_product_bn'], $sku['shop_id']);
        if(empty($sales_material)) {
            return [false, ['error'=>'未找到销售物料']];
        }
        $basic_materials = $sales_material['products'];
        if(empty($basic_materials)) {
            return [false, ['error'=>'未找到基础物料']];
        }
        
        //按销售物料类型
        if($type == 'pkg' || $type == 'gift') { 
            $rs = [
                'info'=>'最小基础物料的值',
                '基准'=>'',
                'basic'=>[],
            ];
            if(isset($rsNum)) unset($rsNum);
            foreach ($basic_materials as $basic_material) {
                list($oriNum, $msg) = $basicObj->{$method}($basic_material['bm_id'], $sku['shop_bn'], $sku['shop_id']);
                $num = floor($oriNum / $basic_material['number']);
                if(!(isset($rsNum) && $rsNum < $num)) {
                    $rsNum =  $num;
                    $rs['基准'] = $basic_material['material_bn'];
                }
                $rs['basic'][$basic_material['material_bn']] = [
                    'quantity' => $num,
                    '基础数量' => $oriNum,
                    '单位数量' => $basic_material['number'],
                    'info' => $msg
                ];
            }
            
            return [(int)$rsNum, $rs];
        }elseif($type == 'fukubukuro'){
            //福袋组合类型
            $luckyBagLib = kernel::single('ome_order_luckybag');
            
            //获取每个福袋组合最大可售卖的库存数量
            $sales_material['skuInfo'] = $sku;
            $basic_materials = $luckyBagLib->getLuckyBagProductStock($sales_material, $method);
            
            //setting
            $rs = [
                'info' => '福袋组合最小库存',
                '基准' => '',
                'luckybag' => [],
                'basic' => [],
            ];
            
            //按福袋纬度获取最小可回写库存数量
            //@todo：福袋销售物料是三层结构(销售物料-->福袋组合-->基础物料);
            $line_i = 0;
            $min_stock = 0;
            foreach ($basic_materials as $combine_id => $combineInfo)
            {
                $line_i++;
                
                $combine_bn = $combineInfo['combine_bn'];
                
                //福袋组合最大可售卖库存
                $luckybag_stock = intval($combineInfo['luckybag_stock']);
                
                //第一行赋值
                if($line_i == 1){
                    $min_stock = $luckybag_stock;
                    
                    $rs['基准福袋编码'] = $combineInfo['combine_bn'];
                }elseif($min_stock > $luckybag_stock){
                    //获取最小可回写库存数量
                    $min_stock = $luckybag_stock;
                    
                    $rs['基准福袋编码'] = $combine_bn;
                }
                
                //luckybag
                $rs['luckybag'][$combine_bn] = [
                    'quantity' => $luckybag_stock,
                    '福袋最大库存数量' => $luckybag_stock,
                ];
                
                //基础物料列表
                foreach ($combineInfo['items'] as $itemKey => $bmInfo)
                {
                    $material_bn = $bmInfo['material_bn'];
                    $actual_stock = $bmInfo['actual_stock']; //实际可用库存数量
                    $material_stock = $bmInfo['material_stock']; //基础物料总库存数量
                    $mateiral_number = $bmInfo['number']; //购买件数
                    
                    //logs
                    $rs['basic'][$material_bn] = [
                        'quantity' => $actual_stock,
                        '基础数量' => $material_stock,
                        '单位数量' => $mateiral_number,
                        'info' => $bmInfo['stock_msg'],
                    ];
                }
            }
            
            return [(int)$min_stock, $rs];
        }elseif($type == 'pko') {
            //多选一商品
            //@todo：代码从上面搬迁下来,没有修改代码逻辑;
            $rs = [
                'info'=>'基础物料值之和',
                'basic'=>[],
            ];
            
            $rsNum = 0;
            foreach ($basic_materials as $basic_material) {
                list($num, $msg) = $basicObj->{$method}($basic_material['bm_id'], $sku['shop_bn'], $sku['shop_id']);
                $rsNum += $num;
                $rs['basic'][$basic_material['material_bn']] = [
                    'quantity' => $num,
                    'info' => $msg
                ];
            }
            
            return [(int)$rsNum, $rs];
        }
        
        //普通销售物料
        $basic_material = current($basic_materials);
        list($num, $msg) = $basicObj->{$method}($basic_material['bm_id'], $sku['shop_bn'], $sku['shop_id']);
        $rs = [
            'info'=>'基础物料的值',
            'basic'=>[
                $basic_material['material_bn'] => [
                    'quantity' => $num,
                    'info' => $msg
                ]
            ],
        ];
        return [$num, $rs];
    }
}
