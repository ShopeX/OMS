<?php

class financebase_expenses_split_import {
    const IMPORT_TITLE = [
        '拆分单号' => 'split_bn',
        '是否已记账' => 'is_accounted',
    ];

    public function getExcelTitle()
    {
        return ['费用拆分记账导入模板.xlsx',[
            array_keys(self::IMPORT_TITLE)
        ]];
    }

    /**
     * 处理Excel导入行数据
     *
     * @param string $import_file 导入文件路径
     * @param array $post 提交参数
     * @return array
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

            // 将Excel行数据转换为关联数组
            $buffer = array_combine(self::IMPORT_TITLE, array_slice($buffer, 0, count(self::IMPORT_TITLE)));
            if (empty($buffer['is_accounted'])) {
                return [false, '“是否已记账”字段不能为空'];
            }
            if (empty($buffer['split_bn'])) {
                return [false, '“拆分单号”字段不能为空'];
            }
            $buffer['is_accounted'] = $buffer['is_accounted'] == '是' ? '1' : '0';
            $expensesSplitModel = app::get('financebase')->model('expenses_split');
            $existRow = $expensesSplitModel->db_dump(['split_bn' => $buffer['split_bn']], 'id');
            if (!$existRow) {
                return [false, '拆分单号不存在：' . $buffer['split_bn']];
            }
            $updateData = [
                'is_accounted' => $buffer['is_accounted'],
            ];
            // 如果已记账，记录记账时间
            if ($buffer['is_accounted'] == '1') {
                $updateData['accounted_time'] = time();
            } else {
                // 如果未记账，将记账时间设置为0
                $updateData['accounted_time'] = 0;
            }
            $expensesSplitModel->update($updateData, ['id' => $existRow['id']]);
            return [true];
        }, $post, $format);
    }

} 