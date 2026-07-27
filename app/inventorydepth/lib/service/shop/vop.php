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
 * 唯品会平台商品处理
 */
class inventorydepth_service_shop_vop extends inventorydepth_service_shop_common
{
    //定义每页拉取数据(平台限制每页最多200条)
    public $customLimit = 50;

    public function __construct(&$app)
    {
        $this->app = $app;
    }

    /**
     * 下载全部商品(包括普通现货、专供现货)
     *
     * @param array $filter
     * @param string $shop_id
     * @param int $offset
     * @param int $limit
     * @param string $limit
     * @return array
     */
    public function downloadList($filter, $shop_id, $offset = 0, $limit = 30, &$errormsg = null)
    {
        $shopService = kernel::single('inventorydepth_rpc_request_shop_items');

        //开始执行
        $result = $shopService->items_all_get($filter, $shop_id, $offset, $limit);
        if ($result === false) {
            $errormsg = $shopService->get_err_msg();
            return false;
        }

        //矩阵数据为空
        if (empty($result['list'])) {
            $this->totalResults = 0;
            return array();
        }

        // 因为接口返回没有总数，所以有下一页就继续获取
        if ($result['has_next']) {
            $this->totalResults = ($offset + 1) * $limit;
        } else {
            $this->totalResults = ($offset - 1) * $limit + count($result['list']); //平台商品总数
        }

        // 把唯品会barcode去当做oms的条码去查询物料类型为普通的销售物料，如果查得到，获取销售物料编码复制给skus的outer_id，这样下载商品就可以正常关联到oms商品 ---barcode to sm_bn start
        $barcodeList = array_column($result['list'], 'barcode');
        $barcodeVsSmbn = $this->barcodeToSmbn($barcodeList);
        if ($barcodeVsSmbn) {
            foreach ($result['list'] as $k => $v) {
                if ($barcodeVsSmbn[$v['barcode']]) {
                    $result['list'][$k]['oms_sm_bn'] = $barcodeVsSmbn[$v['barcode']];
                }
            }
        }
        // ---barcode to sm_bn end

        //格式化
        $data = $spuList = [];
        foreach ($result['list'] as $k => $v) {

            if (!$spuList[$v['sn']]) {
                $spuList[$v['sn']] = [
                    'outer_id'       => $v['sn'], //spu商品编号
                    'title'          => $v['product_name'],
                    'approve_status' => $v['selling_status'] == '1' ? 'onsale' : 'instock', //上下架
                ];
            }
            
            // 获取sku信息
            $spuList[$v['sn']]['skus'][$v['barcode']] = [
                'sku_id'   => $v['barcode'],
                'outer_id' => $v['barcode'], //sku货号（使用平台barcode条形码，作为：销售物料编码）
                'sku_wid'  => $v['barcode'],
                'title'    => $v['product_name'], //货品名称
            ];
        }

        foreach ($spuList as $spuBn => $spuInfo) {
            
            $iid = $spuBn;
            
            $skuList = [];
            foreach ($spuInfo['skus'] as $key => $val) {
                $skuList['sku'][] = $val;
            }
            
            // data
            $data[] = array(
                'outer_id'       => $spuBn, //spu商品编号
                'iid'            => $iid,
                'title'          => $spuInfo['title'],
                'approve_status' => $spuInfo['approve_status'],
                'simple'         => 'false',
                'skus'           => $skuList,
            );
        }
        
        //销毁
        unset($result, $skuList);

        return $data;
    }

    /**
     * 通过IID批量下载商品
     *
     * @param array $iids
     * @param string $shop_id
     * @param string $errormsg
     * @return array
     */
    public function downloadByIIds($iids, $shop_id, &$errormsg = null)
    {
        $errormsg = '不支持通过IID批量下载';

        return false;
    }

    /**
     * 通过IID下载单个商品
     *
     * @param array $iid
     * @param string $shop_id
     * @param string $errormsg
     * @return array
     */
    public function downloadByIId($iid, $shop_id, &$errormsg = null)
    {
        $errormsg = '不支持通过IID下载 单个';

        return false;
    }
    
    /**
     * 根据barcode获取普通商品的sm_bn
     *
     * @param $barcodeList
     * @return array
     */
    public function barcodeToSmbn($barcodeList)
    {
        $codeList = $bmIds = $smIds = $smList = [];
        $basicMaterialBarcode = kernel::single('material_basic_material_barcode');
        $bmIds = $basicMaterialBarcode->getBmidListByFilter(['code|in'=>$barcodeList], $codeList);

        // 获取销售物料ID
        if ($bmIds) {
            $smIds = app::get('material')->model('sales_basic_material')->getList("bm_id,sm_id",array("bm_id|in"=>$bmIds), 0, -1);
        }

        if ($smIds) {
            $salesMaterialMdl = app::get('material')->model('sales_material');
            $saleFilter = [
                'sm_id' =>  array_column($smIds, 'sm_id'),
                'sales_material_type'   =>  '1',
            ];
            $smList = $salesMaterialMdl->getList('sm_id,sales_material_bn', $saleFilter);
            $smList = array_column($smList, 'sales_material_bn', 'sm_id');
        }

        $bmidVsSmbn = [];
        if ($smList) {
            foreach ($smIds as $_k => $_v) {
                if (!$smList[$_v['sm_id']]) {
                    continue;
                }
                if ($bmidVsSmbn[$_v['bm_id']]) {
                    // 有可能一个基础物料对应多个销售物料，只取其中一个销售物料
                    continue;
                }
                $bmidVsSmbn[$_v['bm_id']] = $smList[$_v['sm_id']];
            }
        }

        $barcodeVsSmbn = [];
        if ($bmidVsSmbn) {
            foreach ($codeList as $_bm_id => $_code) {
                if ($bmidVsSmbn[$_bm_id]) {
                    $barcodeVsSmbn[$_code] = $bmidVsSmbn[$_bm_id];
                }
            }
        }
        
        return $barcodeVsSmbn;
    }
}
