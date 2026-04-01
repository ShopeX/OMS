<?php

class material_seller_import {

    const IMPORT_TITLE = [
        'seller_code' => '销售人员编码',
        'seller_name' => '销售人员名称',
    ];

    
    public function getExcelTitle()
    {
        return ['销售人员导入模板.xlsx',[
            self::IMPORT_TITLE,
        ]];
    }

    public function processExcelRow($import_file, $post)
    {
        $format = [];
        // 读取文件
        return kernel::single('omecsv_phpoffice')->import($import_file, function ($line, $buffer, $post, $highestRow) {
            static $title;

            if ($line == 1) {
                $title = $buffer;
                // 验证模板是否正确
                if (array_filter($title) != array_values(self::IMPORT_TITLE)) {
                    return [false, '导入模板不正确'];
                }
                return [true];
            }
            $buffer = array_combine(array_keys(self::IMPORT_TITLE), array_slice($buffer, 0, count(self::IMPORT_TITLE)));
            $require = ['seller_code', 'seller_name'];
            foreach($require as $v) {
                if(empty($buffer[$v])) {
                    return [true, self::IMPORT_TITLE[$v] . '不能为空', 'warnning'];
                }
            }
            $data = [
                'seller_code'       => trim($buffer['seller_code']),
                'seller_name'       => trim($buffer['seller_name']),
            ];
            $seller = app::get('material')->model('seller');
            if($seller->db_dump(['seller_code'=>$data['seller_code']], 'id')) {
                return [true, '销售人员已经存在', 'warnning'];
            }
            $seller->insert($data);
            if($data['id']) {
                app::get('ome')->model('operation_log')->write_log('seller@material', $data['id'], '导入销售人员信息：'.json_encode($data, JSON_UNESCAPED_UNICODE));
            }
            return [true, '导入成功'];
        }, $post, $format);
    }
}