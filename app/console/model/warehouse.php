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
 * 唯品会仓库mdl类
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 */
class console_mdl_warehouse extends dbeav_model
{

    public function table_name($real = false)
    {
        if ($real) {
            $table_name = 'sdb_purchase_warehouse';
        } else {
            $table_name = 'warehouse';
        }

        return $table_name;
    }

    public function get_schema()
    {
        return app::get('purchase')->model('warehouse')->get_schema();
    }

    public function saveWarehouses($data = [])
    {
        $warehouseList = array_column($data, 'warehouse_code');
        $omsWarehouse  = $this->getList('branch_id,branch_bn', ['branch_bn|in' => $warehouseList]);
        $omsWarehouse  = array_column($omsWarehouse, 'branch_id', 'branch_bn');

        $regionLib = kernel::single('eccommon_regions');
        foreach ($data as $k => $v) {

            //收货人/发货人地区转换
            $area = $v['province_ame'] . "/" . $v['city_name'] . "/" . $v['region_name'] . "/" . $v['town_name'];
            $regionLib->region_validate($area);

            $param = [
                'branch_bn'      => $v['warehouse_code'],
                'branch_name'    => $v['warehouse_name'],
                // 'uname'          => '',
                // 'mobile'         => '',
                // 'phone'          => '',
                // 'email'          => '',
                // 'zip'            => '',
                'area'           => $area,
                'address'        => $v['warehouse_address'],
                'warehouse_type' => $v['warehouse_type'],
                'status'         => $v['is_active'],
                // 'wcontent'       => json_encode($v),
            ];
            if ($omsWarehouse[$param['branch_bn']]) {
                $param['branch_id'] = $omsWarehouse[$param['branch_bn']];
            }
            $this->save($param);
        }
    }

    public function saveCooperation($data = [])
    {
        foreach ($data as $k => $v) {
            // JITX子类型，0-仅门店；1-仅省仓；
            if ($v['jitx_subtype'] == '1') { 
                $param = [
                    'cooperation_no' => $v['cooperation_no'],
                    'ccontent'       => json_encode($v),
                ];
                $this->update($param, ['warehouse_type' => '2']);
            }
        }
    }
}
