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
 * 销售物料数据验证Lib类
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: check.php 2016-08-03 15:00
 */
class dealer_sales_check
{
    /**
     * 数据检验有效性
     *
     * @param  Array   $params
     * @param  String  $err_msg
     * @return Boolean
     */
    public function checkParams(&$params, &$err_msg)
    {
        $salesMaterialObj = app::get('dealer')->model('sales_material');

        //新增标记
        $is_new_add = $params['edit'] ? false : true;
        unset($params['edit']);

        if (empty($params['sales_material_name'])) {
            $err_msg = "销售物料名称不能为空";
            return false;
        }

        if (empty($params['sales_material_bn'])) {
            $err_msg = "销售物料编码不能为空";
            return false;
        }

        if (empty($params['shop_id'])) {
            $err_msg = "所属经销店铺不能为空";
            return false;
        }

        if (empty($params['at']) && empty($params['bm_id'])) {
            $err_msg = "关联基础商品不能为空";
            return false;
        }

        //检查有效性
        if ($is_new_add) {
            //判断物料编码只能是由数字英文下划线组成
            $reg_bn_code = "/^[0-9a-zA-Z\_\-]*$/";
            if (!preg_match($reg_bn_code, $params["sales_material_bn"])) {
                $err_msg = "物料编码只能是数字英文下划线组成";
                return false;
            }

            $salesMaterialInfo = $salesMaterialObj->getList('sales_material_bn', array('sales_material_bn' => $params['sales_material_bn'], 'shop_id' => $params['shop_id']));
            if ($salesMaterialInfo) {
                $err_msg = "当前新增的物料编码已被使用，不能重复";
                return false;
            }

            $params['sales_material_bn_crc32'] = sprintf('%u', crc32($params['sales_material_bn']));
        } else {
            if (empty($params['sm_id'])) {
                $err_msg = "销售物料sm_id不能为空";
                return false;
            }

            $salesMaterialExistInfo = $salesMaterialObj->getList('sm_id', array('sales_material_bn' => $params['sales_material_bn'], 'shop_id' => $params['shop_id']));
            if ($salesMaterialExistInfo && $salesMaterialExistInfo[0]['sm_id'] != $params['sm_id']) {
                $err_msg = "当前编辑的物料编码已被使用，不能重复";
                return false;
            }

            $salesMaterialInfo = $salesMaterialObj->dump($params['sm_id']);
            if (!$salesMaterialInfo) {
                $err_msg = "当前物料不存在";
                return false;
            }
        }

        if ($params['sales_material_type'] == 2) {
            if (!isset($params['at'])) {
                $err_msg = "组合物料请至少设置一个物料明细内容";
                return false;
            }

            foreach ($params['at'] as $val) {
                if (count($params['at']) == 1) {
                    if ($val < 2) {
                        $err_msg = "只有一种物料时，数量必须大于1";
                        return false;
                    }
                } else {
                    if ($val < 1) {
                        $err_msg = "数量必须大于0";
                        return false;
                    }
                }
            }

            $tmp_rate = 0;
            foreach ($params['pr'] as $val) {
                $tmp_rate += $val;
            }

            if ($tmp_rate > 100) {
                $err_msg = "分摊销售价合计百分比:" . $tmp_rate . ",已超100%";
                return false;
            } elseif ($tmp_rate < 100) {
                $err_msg = "分摊销售价合计百分比:" . $tmp_rate . ",不足100%";
                return false;
            }
        }

        // 赠品补充校验
        if ($params['sales_material_type'] == 3) {
            if (!isset($params['at'])) {
                $err_msg = "赠品物料请至少设置一个物料明细内容";
                return false;
            }

            foreach ($params['at'] as $val) {
                if ($val < 1) {
                    $err_msg = "数量必须大于0";
                    return false;
                }
            }
        }

        return true;
    }

}
