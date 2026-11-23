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

class console_material_package_import {
    const IMPORT_TITLE = [
        '加工单名称' => 'mp_name',
        '加工或拆包' => 'service_type',
        '仓库编码' => 'branch_bn',
        '单据备注' => 'memo',
        '礼盒物料编码' => 'bm_bn',
        '数量' => 'number',
    ];

    public function getExcelTitle()
    {
        return ['加工单导入模板.xlsx',[
            array_keys(self::IMPORT_TITLE)
        ]];
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function processExcelRow($import_file, $post)
    {
        $format = [];
        // 读取文件
        return kernel::single('omecsv_phpoffice')->import($import_file, function ($line, $buffer, $post, $highestRow) {
            static $title;

            if ($line == 1) {
                $title = $buffer;
                // 验证模板是否正确
                if (array_filter($title) != array_keys(self::IMPORT_TITLE)) {
                    return [false, '导入模板不正确'];
                }
                return [true];
            }
            $buffer = array_combine(self::IMPORT_TITLE, array_slice($buffer, 0, count(self::IMPORT_TITLE)));
            $branch_id = app::get('ome')->model('branch')->db_dump(['branch_bn'=>$buffer['branch_bn']], 'branch_id')['branch_id'];
            if(empty($branch_id)) {
                return [false, $buffer['branch_bn'].':仓库不存在或没有权限'];
            }
            $service_type = '1';
            if(trim($buffer['service_type']) == '拆包') {
                $service_type = '2';
            }
            $data = [
                'mp_name'       => trim($buffer['mp_name']),
                'branch_id'     => $branch_id,
                'service_type'  => $service_type,
                'memo'          => trim($buffer['memo']),
                'business_type' => trim($post['business_type']),
            ];
            if(app::get('console')->model('material_package')->db_dump(['mp_name'=>$data['mp_name'],'status'=>['1','2'],'branch_id' => $data['branch_id']], 'id')) {
                return [false, $data['mp_name'].'加工单已经存在'];
            }
            $items  = [];
            $number = $buffer['number'];
            foreach (app::get('material')->model('basic_material')->getList('*', ['material_bn' => $buffer['bm_bn'], 'type'=>'4']) as $v) {
                $items[$v['bm_id']] = [
                    'bm_id'   => $v['bm_id'],
                    'bm_bn'   => $v['material_bn'],
                    'bm_name' => $v['material_name'],
                    'number'  => $number,
                ];
            }
            if(empty($items)) {
                return [false, $buffer['bm_bn'].':不存在该礼盒物料'];
            }
            $mpObj = app::get('console')->model('material_package');
            list($rs, $rsData) = $mpObj->insertDataItems($data, $items, '导入');
            return [$rs, $rsData['msg']];
        }, $post, $format);
    }
}