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
 * 产品线model类
 * @author wangjianjun@shopex.cn
 * @version 2024.04.12
 */
class dealer_mdl_series extends dbeav_model
{
    var $has_export_cnf = false;
    var $export_name    = '产品线';

    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = ' 1 ';
        if ($filter['series_shop']) {
            $seriesEndorseMdl = app::get('dealer')->model('series_endorse');

            $seriesIdArr = $seriesEndorseMdl->getList('*', ['shop_id' => $filter['series_shop']]);
            if ($seriesIdArr) {
                $seriesIdArr = array_unique(array_column($seriesIdArr, 'series_id'));
                $where .= " AND series_id in ('" . implode("','", $seriesIdArr) . "')";
            } else {
                $where .= " AND series_id='0'";

            }
            unset($filter['series_shop']);
        }
        return parent::_filter($filter, $tableAlias, $baseWhere) . " AND " . $where;
    }

    public function exportTemplate($filter = 'series_main')
    {
        foreach ($this->io_title($filter) as $v) {
            $title[] = $v;
        }
        return $title;
    }

    public function io_title($filter = 'series_main', $ioType = 'csv')
    {
        switch ($filter) {
            case 'series_main':
                $this->oSchema['csv'][$filter] = [
                    '*:产品线编码'    => 'series_code',
                    '*:产品线名称'    => 'series_name',
                    '*:所属贸易公司编码' => 'betc_code',
                ];
                break;
        }

        $this->ioTitle[$ioType][$filter] = array_keys($this->oSchema[$ioType][$filter]);
        return $this->ioTitle[$ioType][$filter];
    }

    public function finish_import_csv()
    {
        header("Content-type: text/html; charset=utf-8");
        
        $data = $this->import_data;
        if (!$data['contents']) {
            return true;
        }
        unset($this->import_data);

        $opLogMdl = app::get('ome')->model('operation_log');
        $opInfo   = kernel::single('ome_func')->getDesktopUser();

        kernel::database()->beginTransaction();

        foreach ($data['contents'] as $key => $row) {
            $insert = [
                'series_code'   =>  $row[0],
                'series_name'   =>  $row[1],
                'betc_id'       =>  $row[2], // 导入的是betc_code，在prepared_import_csv_row中处理成了betc_id
                'description'   =>  '',
                'cat_name'      =>  '',
                'status'        =>  'close',
                'sku_nums'      =>  0,
                'cos_id'        =>  $row[3], // 在prepared_import_csv_row中查到的
                'remark'        =>  '手动导入',
                'op_name'       =>  $opInfo['op_name'],
            ];
            if (!$this->insert($insert)) {
                kernel::database()->rollBack();
                return false;
            };
            $opLogMdl->write_log('dealer_series_add@dealer',$insert['series_id'],"导入产品线。");
        }
        kernel::database()->commit();

        return true;
    }

    public function prepared_import_csv_row($row, $title, &$tmpl, &$mark, &$newObjFlag, &$msg)
    {
        $mark = 'contents';

        $fileData = $this->import_data;
        if (!$fileData) {
            $fileData = [];
        }

        if (substr($row[0], 0, 1) == '*') {
            $mark    = 'title';
            $titleRs = array_flip($row);
            return $titleRs;
        } else {
            if ($row) {
                $row[0] = trim($row[0]);
                if (empty($row[0])) {
                    unset($this->import_data);
                    $msg['error'] = "产品线编码不能为空";
                    return false;
                }
                if (empty($row[1])) {
                    unset($this->import_data);
                    $msg['error'] = "产品线名称不能为空";
                    return false;
                }
                if (empty($row[2])) {
                    unset($this->import_data);
                    $msg['error'] = "贸易公司编码不能为空";
                    return false;
                }

                if (!preg_match('/^[a-zA-Z0-9]+$/', $row[0])) {
                    unset($this->import_data);
                    $msg['error'] = "产品线编码只能为英文字母或数字";
                    return false;
                }

                if (isset($this->import_data) && is_array($this->import_data['contents'])) {
                    $tmp_series_code_arr = array_column($this->import_data['contents'], 0);
                    if (in_array($row[0], $tmp_series_code_arr)) {
                        unset($this->import_data, $tmp_series_code_arr);
                        $msg['error'] = "产品线编码【" . $row[0] . "】不能重复";
                        return false;
                    }
                }

                $has = $this->db_dump(['series_code' => $row[0]]);
                if ($has) {
                    unset($this->import_data);
                    $msg['error'] =  "产品线编码【" . $row[0] . "】已存在";
                    return false;
                }

                $betcMdl  = app::get('dealer')->model('betc');
                $betcInfo = $betcMdl->db_dump(['betc_code'=>trim($row[2])]);
                if (!$betcInfo) {
                    unset($this->import_data);
                    $msg['error'] = "贸易公司编码【" . $row[2] . "】不存在";
                    return false;
                } else {
                    $row[2] = $betcInfo['betc_id'];
                    $row[3] = $betcInfo['cos_id'];
                }

                $fileData['contents'][] = $row;
            }
            $this->import_data = $fileData;
        }

        return true;
    }

    public function prepared_import_csv_obj($data, $mark, $tmpl, &$msg = '')
    {
        return true;
    }

}
