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
 * 贸易公司
 */

class dealer_mdl_betc extends dbeav_model
{
    private $import_data      = [];
    private $import_betc_code = [];

    private $templateColumn = array(
        '*:销售团队'   => 'bbu_name',
        '*:贸易公司编码' => 'betc_code',
        '*:贸易公司名称' => 'betc_name',
        '*:联系人姓名'  => 'contact_name',
        '*:联系人手机'  => 'contact_mobile',
        '*:联系人地址'  => 'contact_address',
    );

    // public function searchOptions()
    // {
    //     return array(
    //         'betc_code' => '贸易公司编码',
    //     );
    // }

    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = ' 1 ';
        return parent::_filter($filter, $tableAlias, $baseWhere) . " AND " . $where;
    }

    /*
     * 导出模板
     */
    public function exportTemplate()
    {
        foreach ($this->io_title() as $v) {
            $title[] = kernel::single('base_charset')->utf2local($v);
        }

        return $title;
    }

    public function io_title($ioType = 'csv')
    {
        $this->oSchema['csv'] = $this->templateColumn;

        $this->ioTitle[$ioType] = array_keys($this->oSchema[$ioType]);

        return $this->ioTitle[$ioType];
    }

    /**
     * CSV导入
     */
    public function prepared_import_csv()
    {
        $this->ioObj->cacheTime = time();
    }

    public function prepared_import_csv_obj($data, $mark, $tmpl, &$msg = '')
    {
        return null;
    }

    //CSV导入业务处理
    public function prepared_import_csv_row($row, $title, &$tmpl, &$mark, &$newObjFlag, &$msg)
    {
        if (empty($row)) {
            return true;
        }
        $mark = false;
        if (substr($row[0], 0, 1) == '*') {
            $title = array_flip($row);
            $mark  = 'title';
            foreach ($this->templateColumn as $k => $val) {
                if (!isset($title[$k])) {
                    $msg['error'] = '请使用正确的模板';
                    return false;
                }
            }
            return $title;
        }
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        $arrData = array();
        foreach ($this->templateColumn as $k => $val) {
            $arrData[$val] = trim($row[$title[$k]]);
        }
        if (count($this->import_data) > 10000) {
            $msg['error'] = "导入的数据量过大，请减少到10000条以下！";
            return false;
        }
        if (!$arrData['bbu_name']) {
            $msg['error'] = "销售团队必须填写";
            return false;
        }
        if (!$arrData['betc_code']) {
            $msg['error'] = "贸易公司编码必须填写";
            return false;
        }
        if (!$arrData['betc_name']) {
            $msg['error'] = "贸易公司名称必须填写";
            return false;
        }
        // if (!$arrData['contact_name']) {
        //     $msg['error'] = "联系人姓名必须填写";
        //     return false;
        // }
        // if (!$arrData['contact_mobile']) {
        //     $msg['error'] = "联系人手机必须填写";
        //     return false;
        // }
        // if (!$arrData['contact_address']) {
        //     $msg['error'] = "联系人地址必须填写";
        //     return false;
        // }

        $bbuMdl  = app::get('dealer')->model('bbu');
        $betcMdl = app::get('dealer')->model('betc');
        $cosMdl  = app::get('organization')->model('cos');

        // 检测销售团队
        $bbuInfo = $bbuMdl->db_dump(['bbu_name' => $arrData['bbu_name']]);
        if (!$bbuInfo) {
            $msg['error'] = '销售团队' . $arrData['bbu_name'] . '不存在!';
            return false;
        }

        $bbuCosInfo = $cosMdl->db_dump(['cos_code' => $bbuInfo['bbu_code'], 'cos_type' => 'bbu']);
        if (!$bbuCosInfo) {
            $msg['error'] = '销售团队' . $arrData['bbu_name'] . '组织架构异常!';
            return false;
        }

        if (isset($this->import_betc_code[$arrData['betc_code']])) {
            $msg['error'] = '贸易公司编码' . $arrData['betc_code'] . '重复!';
            return false;
        }
        $this->import_betc_code[$arrData['betc_code']] = $arrData['betc_code'];

        $betcInfo = $betcMdl->db_dump(['betc_code' => $arrData['betc_code']]);
        if ($betcInfo) {
            $msg['error'] = '贸易公司编码' . $arrData['betc_code'] . '已存在!';
            return false;
        }

        $sdfRow = [
            'betc_name'       => $arrData['betc_name'],
            'betc_code'       => $arrData['betc_code'],
            'status'          => 'active',
            // 'op_name'         => $opInfo['op_name'],
            'bbu_id'          => $bbuInfo['bbu_id'],
            'contact_address' => $arrData['contact_address'],
            'contact_mobile'  => $arrData['contact_mobile'],
            'contact_name'    => $arrData['contact_name'],
            'cos_id'          => '',
            'parent_id'       => $bbuCosInfo['cos_id'],
        ];
        //组织数据
        $this->import_data[] = $sdfRow;

        return null;
    }

    public function finish_import_csv()
    {
        $oQueue = app::get('base')->model('queue');

        $aP   = $this->import_data;
        $pSdf = array();

        $count     = 0;
        $limit     = 50;
        $page      = 0;
        $orderSdfs = array();

        foreach ($aP as $k => $aPi) {
            if ($count < $limit) {
                $count++;
            } else {
                $count = 0;
                $page++;
            }
            $pSdf[$page][] = $aPi;
        }

        foreach ($pSdf as $v) {
            // $queueData = array(
            //     'queue_title' => '贸易公司导入',
            //     'start_time'  => time(),
            //     'params'      => array(
            //         'sdfdata' => $v,
            //     ),
            //     'worker'      => 'dealer_mdl_betc.import_run',
            // );
            // $oQueue->save($queueData);
            $this->import_run($cursor_id,$params);
        }
        $oQueue->flush();

        return null;
    }

    public function import_run(&$cursor_id, $params)
    {
        $opInfo  = kernel::single('ome_func')->getDesktopUser();
        $sdfdata = $params['sdfdata'];
        foreach ($sdfdata as $data) {
            $data['op_name'] = $opInfo['op_name'];
            $this->insert($data);

            // 创建/更新 企业组织表,得到返回再去更新贸易公司表
            $cosData = [
                'cos_type'  => 'betc',
                'cos_code'  => $data['betc_code'],
                'cos_name'  => $data['betc_name'],
                'op_name'   => $data['op_name'],
                'parent_id' => $data['parent_id'],
                'is_leaf'   => '1',
            ];
            $cos_id = kernel::single('organization_cos')->saveCos($cosData);
            $cos_id && $this->update(['cos_id' => $cos_id], ['betc_id' => $data['betc_id']]);
        }
        return false;
    }
}
