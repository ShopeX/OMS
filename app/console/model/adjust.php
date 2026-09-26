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
 * @DateTime: 2022/8/1 15:34:58
 * @describe: model层
 * ============================
 */
class console_mdl_adjust extends dbeav_model {
    public $has_export_cnf = true;
    public $export_name = '库存调整单';

    /**
     * modifier_negative_branch_id
     * @param mixed $c c
     * @param mixed $list list
     * @param mixed $row row
     * @return mixed 返回值
     */

    public function modifier_negative_branch_id($c,$list,$row){
        $bid = json_decode($c, 1);
        $branchList = app::get('ome')->model('branch')->getList('branch_bn', ['branch_id'=>$bid,'check_permission'=>'false']);
        return implode(' | ', array_column($branchList, 'branch_bn'));
    }

    /**
     * _filter
     * @param mixed $filter filter
     * @param mixed $tableAlias tableAlias
     * @param mixed $baseWhere baseWhere
     * @return mixed 返回值
     */
    public function _filter($filter,$tableAlias=null,$baseWhere=null){
        if ($filter['material_bn']){
            $material_bn = $filter['material_bn'];
            unset($filter['material_bn']);
            // 多货号查询
            if(strpos($material_bn, "\n") !== false){
                $material_bn = array_unique(array_map('trim', array_filter(explode("\n", $material_bn))));
            }
            $itemFilter = ['bm_bn'=>$material_bn];
            if($filter) {
                $adjustRows = $this->getList('id', $filter);
                $itemFilter['adjust_id'] = array_column($adjustRows, 'id');
            }
            $itemsObj = app::get('console')->model("adjust_items");
            $rows = $itemsObj->getList('distinct adjust_id', $itemFilter);
            if($rows) {
                $baseWhere[] = 'id IN ('.implode(',', array_column($rows, 'adjust_id')).')';
            } else {
                $baseWhere[] = 'id = 0';
            }
        }
        return parent::_filter($filter,$tableAlias,$baseWhere);
    }

    /**
     * gen_id
     * @param mixed $branch_bn branch_bn
     * @return mixed 返回值
     */
    public function gen_id($branch_bn) {
        $prefix = 'TZ'.date("ymd");
        $sign   = kernel::single('eccommon_guid')->incId('adjust', $prefix, 8);
        return $sign;
    }

    private $templateColumn = array(
        '调整方式' => 'adjust_mode',
        '店仓编码' => 'branch_id',
        // '库位'    => 'storage_code',
        '调整类型' => 'adjust_bill_type',
        '单据备注' => 'memo',
        '财务审核' => 'is_check',
        '出入库方式' => 'iso_status',
        '基础物料编码' => 'bm_bn',
        '基础物料名称' => 'bm_name',
        '数量' => 'number',
    );

    /**
     * 获取TemplateColumn
     * @return mixed 返回结果
     */
    public function getTemplateColumn() {
        return array_keys($this->templateColumn);
    }
    /**
     * prepared_import_csv
     * @return mixed 返回值
     */
    public function prepared_import_csv(){
        $this->import_data =[];
        $this->import_data_bm_bn =[];
        $this->import_main = [];
        $this->ioObj->cacheTime = time();
    }

    /**
     * prepared_import_csv_row
     * @param mixed $row row
     * @param mixed $title title
     * @param mixed $tmpl tmpl
     * @param mixed $mark mark
     * @param mixed $newObjFlag newObjFlag
     * @param mixed $msg msg
     * @return mixed 返回值
     */
    public function prepared_import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
        if(empty($row) || empty(array_filter($row))) return false;
        if( $row[0] == '调整方式' ){
            $this->nums = 1;
            $title = array_flip($row);
            foreach($this->templateColumn as $k => $val) {
                if(!isset($title[$k])) {
                    $msg['error'] = '请使用正确的模板';
                    return false;
                }
            }
            return false;
        }
        $this->nums++;
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        $arrRequired = ['bm_bn'];
        if(!$this->import_data['main']) {
            $arrRequired = ['bm_bn','branch_id'];
        }
        $arrData = array();
        foreach($this->templateColumn as $k => $val) {
            $arrData[$val] = trim($row[$title[$k]]);
            if(in_array($val, $arrRequired) && empty($arrData[$val])) {
                $msg['warning'][] = 'Line '.$this->nums.'：'.$k.'不能都为空！';
                return false;
            }
        }
        if($this->nums > 10000){
            $msg['error'] = "导入的数据量过大，请减少到10000条以下！";
            return false;
        }
        $arrData['number'] = (int) $arrData['number'];
        if(!$this->import_data['main']) {
            $main = [];
            $main['adjust_mode'] = $arrData['adjust_mode'] == '全量' ? 'ql' : 'zl';
            $main['is_check'] = '0';
            $main['iso_status'] = 'confirm';
            if($arrData['adjust_bill_type'] == '库存初始化') {
                if($main['iso_status'] != 'confirm') {
                    $msg['error'] = "库存初始化的出入库方式必须为完成！";
                    return false;
                }
            }
            // 判断是门店还是大仓，并验证权限
            $branch = app::get('ome')->model('branch')->db_dump([
                'branch_bn' => $arrData['branch_id'],
                // 'storage_code' => $arrData['storage_code'],
            ], 'branch_id,b_type');

            if ($branch){
                $main['adjust_channel'] = $branch['b_type']=='2'?'storeadjust':'branchadjust';
            } else {
                $store = app::get('o2o')->model('store')->db_dump([
                    'store_bn' => $arrData['branch_id']
                ]);
                if (!$store) {
                    $msg['error'] = "店仓{$arrData['branch_id']}不存在或您没有权限访问！";
                    return false;
                }

                $branch = app::get('ome')->model('branch')->db_dump([
                    'store_id' => $store['store_id'],
                    // 'storage_code' => $arrData['storage_code'],
                ], 'branch_id');

                if (!$branch) {
                    $msg['error'] = "店仓编码{$arrData['branch_id']}不存在或您没有权限访问！";
                    return false;
                }

                $main['adjust_channel'] = 'store';
            }

            $main['branch_id'] = $branch['branch_id'];
            $main['negative_branch_id'] = [$branch['branch_id']];
            $main['memo'] = $arrData['memo'];
            $main['adjust_bill_type'] = $arrData['adjust_bill_type'];
            $main['bill_status'] = $arrData['bill_status'] == '完成' ? '4' : '1';
            $this->import_data['main'] = $main;
            $this->import_main = $arrData;
        } else {
            $arrMainField = ['adjust_mode','branch_id','is_check','iso_status'];
            foreach ($arrData as $key => $value) {
                if(in_array($key, $arrMainField) && !empty($value)) {
                    if($this->import_main[$key] != $value) {
                        $msg['error'] = 'Line '.$this->nums.'：'.$value.' 不对， 应为空';
                        return false;
                    }
                }
            }
        }
        if(in_array($arrData['bm_bn'], $this->import_data_bm_bn)) {
            $msg['error'] = 'Line '.$this->nums.'：'.$arrData['bm_bn'].' 基础物料重复';
            return false;
        }
        $bm = app::get('material')->model('basic_material')->db_dump(['material_bn'=>$arrData['bm_bn']], 'bm_id');
        if(empty($bm)) {
            $msg['error'] = 'Line '.$this->nums.'：'.$arrData['bm_bn'].' 基础物料不存在';
            return false;
        }
        
        $this->import_data_bm_bn[] = $arrData['bm_bn'];
        $this->import_data['items'][$bm['bm_id']] = $arrData['number'];
       
        $mark = 'contents';
        return true;
    }

    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        return null;
    }


    /**
     * finish_import_csv
     * @return mixed 返回值
     */
    public function finish_import_csv(){
        if(empty($this->import_data)) {
            return null;
        }
        $params = [
            'adjust_mode' => $this->import_data['main']['adjust_mode'],
            'is_check' => $this->import_data['main']['is_check'],
            'iso_status' => $this->import_data['main']['iso_status'],
            'branch_id' => $this->import_data['main']['branch_id'],
            'negative_branch_id' => $this->import_data['main']['negative_branch_id'],
            'memo' => $this->import_data['main']['memo'],
            'source' => '导入',
            'items' => $this->import_data['items'],
          
            'adjust_channel' => $this->import_data['main']['adjust_channel'],
            'adjust_bill_type' => $this->import_data['main']['adjust_bill_type'],
        ];

        list($rs, $rsData) = kernel::single('console_adjust')->dealSave($params);

        if(!$rs) {
            return [false, $rsData];
        }
        return null;
    }

    /**
     * 获取exportdetail
     * @param mixed $field field
     * @param mixed $filter filter
     * @param mixed $offset offset
     * @param mixed $limit limit
     * @param mixed $first_sheet first_sheet
     * @return mixed 返回结果
     */
    public function getexportdetail($field, $filter, $offset=0, $limit=-1, $first_sheet=false) {
        $adjust_arr = $this->getList('id,adjust_bn', array('id' => $filter['id']), 0, -1);
        $adjust_bn = [];
        foreach ($adjust_arr as $adjust) {
            $adjust_bn[$adjust['id']] = $adjust['adjust_bn'];
        }
        $adjustItemsObj = app::get('console')->model('adjust_items');
        $adjust_items_arr = $adjustItemsObj->getList('*', array('adjust_id'=>$filter['id']), 0, -1);
        $data = [];
        if($adjust_items_arr){
            foreach ($adjust_items_arr as $key => $item) {
                $adjustItemRow = [];
                $adjustItemRow[] = $adjust_bn[$item['adjust_id']];
                $adjustItemRow[] = mb_convert_encoding($item['bm_bn'], 'GBK', 'UTF-8');
                $adjustItemRow[] = mb_convert_encoding($item['bm_name'], 'GBK', 'UTF-8');
                $adjustItemRow[] = $item['origin_number'];
                $adjustItemRow[] = $item['number'];
                $adjustItemRow[] = $item['final_number'];
                $data[] = implode(',', $adjustItemRow);
            }
        }
        //明细标题处理
        if($data && $first_sheet){
            $title = array(
                '*:调整单单号',
                '*:基础物料编码',
                '*:基础物料名称',
                '*:调整前数量',
                '*:调整数量',
                '*:调整后数量',
            );

            foreach ((array)$title as $key => $value) {
                $title[$key] = mb_convert_encoding($value, 'GBK', 'UTF-8');
            }

            array_unshift($data,implode(',', $title));
        }
        return $data;
    }

    /**
     * 导出唯一码平铺明细（snlists Sheet）
     */
    public function getexportsnlists($field, $filter, $offset = 0, $limit = -1, $first_sheet = false)
    {
        if (empty($filter['id'])) {
            return array();
        }

        $adjustIds = $filter['id'];
        if (!is_array($adjustIds)) {
            $adjustIds = array($adjustIds);
        }

        $columnDefs = console_adjust_serial::getColumnDefinitions();
        $columnKeys = array_keys($columnDefs);

        $title = array('*:调整单单号');
        foreach ($columnDefs as $def) {
            $title[] = $def['label'];
        }

        $adjustList = $this->getList('id,adjust_bn', array('id' => $adjustIds), 0, -1);
        if (empty($adjustList)) {
            if ($first_sheet) {
                return array($this->buildExportCsvTitleLine($title));
            }
            return array();
        }

        $itemsByAdjust = array();
        $adjustItemsObj = app::get('console')->model('adjust_items');
        $adjustItems = $adjustItemsObj->getList('*', array('adjust_id' => $adjustIds), 0, -1);
        foreach ((array) $adjustItems as $item) {
            $itemsByAdjust[$item['adjust_id']][] = $item;
        }

        $data = array();
        foreach ($adjustList as $adjust) {
            $items = !empty($itemsByAdjust[$adjust['id']]) ? $itemsByAdjust[$adjust['id']] : array();
            if (empty($items)) {
                continue;
            }
            $rows = console_adjust_serial::buildRowsFromAdjustItems($items);
            foreach ($rows as $row) {
                $data[] = $this->buildExportSnListLine($adjust['adjust_bn'], $row, $columnKeys);
            }
        }

        if ($first_sheet) {
            array_unshift($data, $this->buildExportCsvTitleLine($title));
        }

        return $data;
    }

    protected function buildExportCsvTitleLine(array $title)
    {
        $encoded = array();
        foreach ($title as $label) {
            $encoded[] = mb_convert_encoding((string) $label, 'GBK', 'UTF-8');
        }

        return implode(',', $encoded);
    }

    protected function buildExportSnListLine($adjustBn, array $row, array $columnKeys)
    {
        $fields = array($this->encodeExportCsvField($adjustBn));
        foreach ($columnKeys as $key) {
            $fields[] = $this->encodeExportCsvField(isset($row[$key]) ? $row[$key] : '');
        }

        return $this->joinExportCsvFields($fields);
    }

    protected function encodeExportCsvField($value)
    {
        return mb_convert_encoding((string) $value, 'GBK', 'UTF-8');
    }

    protected function joinExportCsvFields(array $fields)
    {
        $out = array();
        foreach ($fields as $field) {
            $str = (string) $field;
            if (strpos($str, ',') !== false || strpos($str, '"') !== false || strpos($str, "\n") !== false) {
                $str = '"' . str_replace('"', '""', $str) . '"';
            }
            $out[] = $str;
        }

        return implode(',', $out);
    }

    function modifier_iso_status($row){
        if($row == 'check'){
            return "是";
        }else{
            return '否';
        }
    }
    
    function modifier_adjust_bill_type($adjust_bill_type,$list,$row){
        $type = [
            '差异处理'  => [
                'less'  => '短发',
                'lost'  => '丢失',
                'wrong' => '错发',
                'more'  => '超发',
            ],
        ];
        if($type[$row['source']]){
            $adjustBillType = explode('_',$adjust_bill_type);
            return $type[$row['source']][$adjustBillType[1]];
        }
        return $adjust_bill_type;
    }
}