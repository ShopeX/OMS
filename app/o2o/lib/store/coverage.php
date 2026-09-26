<?php
/**
 * 门店覆盖区域解析与保存
 */
class o2o_store_coverage
{
    /**
     * 解析覆盖区域文本
     *
     * @param string $coverageArea 如：中国 / 安徽-合肥市 / 上海;安徽-合肥市
     * @param string $errmsg
     * @return array|false 成功返回路径数组，全国为 ['CN']
     */
    public function parseCoverageAreaText($coverageArea, &$errmsg = '')
    {
        $coverageArea = trim((string) $coverageArea);
        if ($coverageArea === '') {
            $errmsg = '覆盖区域不能为空';
            return false;
        }

        if ($coverageArea === '中国') {
            return array('CN');
        }

        $regions = explode(';', $coverageArea);
        $result  = array();

        foreach ($regions as $region) {
            $region = trim($region);
            if ($region === '') {
                continue;
            }

            $regionIds = $this->parseRegionPath($region, $errmsg);
            if ($regionIds === false) {
                return false;
            }
            $result[] = implode(',', $regionIds);
        }

        if (empty($result)) {
            $errmsg = '覆盖区域不能为空';
            return false;
        }

        return $result;
    }

    /**
     * 查找门店覆盖区域记录（与列表展示查询键一致，优先 store_bn）
     *
     * @param array $store store_id, store_bn, branch_id
     * @return array
     */
    public function findWarehouseForStore(array $store)
    {
        $warehouseMdl = app::get('logisticsmanager')->model('warehouse');
        $storeBn      = trim((string) ($store['store_bn'] ?? ''));
        if ($storeBn === '') {
            return array();
        }

        $lookupFilters = array(
            array('branch_bn' => $storeBn, 'b_type' => 2),
            array('warehouse_name' => 'STORE_' . $storeBn, 'b_type' => 2),
        );
        if (!empty($store['branch_id'])) {
            $lookupFilters[] = array('branch_id' => $store['branch_id'], 'b_type' => 2);
        }

        foreach ($lookupFilters as $filter) {
            $row = $warehouseMdl->dump($filter, 'id,branch_bn');
            if (!empty($row['id'])) {
                return $row;
            }
        }

        return array();
    }

    /**
     * 按门店编码覆盖更新覆盖区域
     *
     * @param string $store_bn
     * @param array  $coveragePaths parseCoverageAreaText 返回值
     * @param string $errmsg
     * @return bool
     */
    public function saveByStoreBn($store_bn, array $coveragePaths, &$errmsg = '')
    {
        $store_bn = trim((string) $store_bn);
        if ($store_bn === '') {
            $errmsg = '门店编码不能为空';
            return false;
        }
        if (empty($coveragePaths)) {
            $errmsg = '覆盖区域不能为空';
            return false;
        }

        try {
            $storeMdl = app::get('o2o')->model('store');
            $store    = $storeMdl->dump(array('store_bn' => $store_bn), 'store_id,store_bn,branch_id,name');
            if (empty($store['store_id'])) {
                $errmsg = "门店[{$store_bn}]不存在";
                return false;
            }

            if (empty($store['branch_id'])) {
                $errmsg = "门店[{$store_bn}]关联仓库不存在";
                return false;
            }

            $warehouseMdl      = app::get('logisticsmanager')->model('warehouse');
            $existingWarehouse = $this->findWarehouseForStore($store);

            $regionIds   = array();
            $regionNames = array();
            $isNationwide = in_array('CN', $coveragePaths, true);

            if ($isNationwide) {
                $regionsMdl = app::get('eccommon')->model('regions');
                $provinces  = $regionsMdl->getList('region_id', array('region_grade' => 1, 'source' => 'systems'));
                foreach ($provinces as $prov) {
                    $regionIds[] = (string) $prov['region_id'];
                }
                $regionNames = array('中国');
            } else {
                $regionsMdl = app::get('eccommon')->model('regions');
                foreach ($coveragePaths as $regionPath) {
                    $pathParts    = explode(',', $regionPath);
                    $lastRegionId = end($pathParts);
                    $region       = $regionsMdl->dump(array('region_id' => $lastRegionId), 'local_name');
                    if ($region) {
                        $regionNames[] = $region['local_name'];
                    }
                    $regionIds[] = $regionPath;
                }
            }

            $addressIds = explode(',', implode(',', $regionIds));
            $oneLevelLocalAddressName = app::get('eccommon')->model('regions')->getList(
                'local_name,region_grade',
                array('region_id' => $addressIds)
            );
            $oneAddressName = '';
            if ($oneLevelLocalAddressName) {
                foreach ($oneLevelLocalAddressName as $value) {
                    if (intval($value['region_grade']) === 1) {
                        $oneAddressName .= $value['local_name'] . ',';
                    }
                }
                if (substr($oneAddressName, -1) === ',') {
                    $oneAddressName = substr($oneAddressName, 0, -1);
                }
            }

            $operator = kernel::single('desktop_user')->get_name();
            if ($operator === '') {
                $operator = 'system';
            }

            $warehouseData = array(
                'branch_id'              => $store['branch_id'],
                // 与门店列表 column_region_coverage 查询键保持一致
                'branch_bn'              => $store['store_bn'],
                'warehouse_name'         => 'STORE_' . $store['store_bn'],
                'region_ids'             => implode(';', $regionIds),
                'region_names'           => $isNationwide ? '中国' : implode(',', $regionNames),
                'one_level_region_names' => $oneAddressName,
                'warn_num'               => 5,
                'operator'               => $operator,
                'b_type'                 => 2,
            );

            if (!empty($existingWarehouse['id'])) {
                $warehouseMdl->update($warehouseData, array('id' => $existingWarehouse['id']));
            } else {
                $warehouseData['create_time'] = time();
                $warehouseMdl->insert($warehouseData);
            }

            return true;
        } catch (Exception $e) {
            $errmsg = '保存覆盖区域失败：' . $e->getMessage();
            return false;
        }
    }

    /**
     * 批量导入覆盖区域 CSV
     *
     * @param array  $file $_FILES['import_file']
     * @param string $summary
     * @return array [bool success, string message]
     */
    public function doImport(array $file, &$summary = '')
    {
        if (empty($file['tmp_name']) || intval($file['error']) !== 0) {
            $summary = '请上传CSV文件';
            return array(false, $summary);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $summary = '仅支持CSV文件';
            return array(false, $summary);
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            $summary = '无法读取上传文件';
            return array(false, $summary);
        }

        $formatError = $this->detectInvalidImportFormat($content);
        if ($formatError !== '') {
            $summary = $formatError;
            return array(false, $summary);
        }

        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'GB18030');
        }
        $tempFile = tempnam(sys_get_temp_dir(), 'o2o_store_region_');
        file_put_contents($tempFile, $content);
        $handle = fopen($tempFile, 'r');
        if (!$handle) {
            $summary = '无法读取上传文件';
            return array(false, $summary);
        }

        $lineNo  = 0;
        $success = 0;
        $errors  = array();

        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;
            if ($lineNo === 1) {
                continue;
            }

            $storeBn  = trim((string) ($row[0] ?? ''));
            $coverage = trim((string) ($row[1] ?? ''));
            if ($storeBn === '' && $coverage === '') {
                continue;
            }

            if ($storeBn === '' || $coverage === '') {
                $errors[] = "第{$lineNo}行：门店编码或覆盖区域不能为空";
                continue;
            }

            $paths = $this->parseCoverageAreaText($coverage, $errmsg);
            if ($paths === false) {
                $errors[] = "第{$lineNo}行门店[{$storeBn}]：{$errmsg}";
                continue;
            }

            if (!$this->saveByStoreBn($storeBn, $paths, $errmsg)) {
                $errors[] = "第{$lineNo}行门店[{$storeBn}]：{$errmsg}";
                continue;
            }

            $success++;
        }

        fclose($handle);
        @unlink($tempFile);

        $summary = "成功更新 {$success} 条";
        if (!empty($errors)) {
            $summary .= '，失败 ' . count($errors) . ' 条';
            $summary .= '：' . implode('；', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $summary .= '...';
            }
        }

        if ($success > 0) {
            app::get('ome')->model('operation_log')->write_log(
                'store_upsert@o2o',
                0,
                "覆盖区域导入成功 {$success} 条"
            );
        }

        return array($success > 0, $summary);
    }

    /**
     * 检测上传文件是否为 Excel 等非 CSV 格式
     *
     * @param string $content
     * @return string 非空表示错误提示
     */
    protected function detectInvalidImportFormat($content)
    {
        if ($content === '' || $content === false) {
            return '';
        }

        $head = substr($content, 0, 8);
        // xlsx / xlsm / xlsb 等 Office Open XML（ZIP）
        if (substr($head, 0, 2) === 'PK') {
            return '上传的文件实际是 Excel 格式（xlsx），不是 CSV。请在 Excel/WPS 中选择「另存为」→「CSV（逗号分隔）(*.csv)」后再上传';
        }
        // 旧版 xls
        if (substr($head, 0, 4) === "\xD0\xCF\x11\xE0") {
            return '上传的文件实际是 Excel 格式（xls），不是 CSV。请在 Excel/WPS 中选择「另存为」→「CSV（逗号分隔）(*.csv)」后再上传';
        }

        return '';
    }

    /**
     * 解析区域路径（省-市-区）
     *
     * @param string $regionPath
     * @param string $errmsg
     * @return array|false
     */
    protected function parseRegionPath($regionPath, &$errmsg = '')
    {
        $parts = explode('-', $regionPath);
        if (count($parts) < 1 || count($parts) > 3) {
            $errmsg = "区域格式错误，应为：省-市-区，当前：{$regionPath}";
            return false;
        }

        $regionIds = array();
        $parentId  = null;
        $regionObj = app::get('eccommon')->model('regions');

        foreach ($parts as $index => $regionName) {
            $regionName = trim($regionName);
            if ($regionName === '') {
                $errmsg = "区域名称不能为空，路径：{$regionPath}";
                return false;
            }

            $expectedGrade     = $index + 1;
            $queryConditions   = array(
                'local_name'   => $regionName,
                'source'       => 'systems',
                'region_grade' => $expectedGrade,
            );
            if ($expectedGrade > 1 && $parentId !== null) {
                $queryConditions['p_region_id'] = $parentId;
            }

            $region = $regionObj->dump($queryConditions, 'region_id, local_name, p_region_id, region_grade');
            if (empty($region)) {
                if ($expectedGrade > 1 && $parentId !== null) {
                    $errmsg = "区域[{$regionName}]不存在或与上级区域不匹配，路径：{$regionPath}";
                } else {
                    $errmsg = "区域[{$regionName}]不存在，路径：{$regionPath}";
                }
                return false;
            }

            if (intval($region['region_grade']) !== $expectedGrade) {
                $errmsg = "区域[{$regionName}]层级错误，路径：{$regionPath}";
                return false;
            }

            $regionIds[] = $region['region_id'];
            $parentId    = $region['region_id'];
        }

        return $regionIds;
    }
}
