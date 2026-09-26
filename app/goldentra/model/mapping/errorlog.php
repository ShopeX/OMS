<?php

class goldentra_mdl_mapping_errorlog extends dbeav_model
{
    var $has_export_cnf = true;
    var $export_name = 'mappiing接收失败日志';

    public function getExportDataByCustom($fields, $filter, $has_detail, $curr_sheet, $start, $end, $op_id)
     {
         $list = parent::getList('*', $filter, 0, -1);
         $titles = [
             '日志编码' => 'bill_bn',
             '唯一码' => 'unique_code',
             '错误描述和参数' => 'error_msg',
         ];
         if ($curr_sheet == 1) {
             $exptmp_data = array();
             $title_keys = array_keys($titles);
             foreach ($title_keys as $col) {
                 $exptmp_data[] = mb_convert_encoding($col, 'GBK', 'UTF-8');
             }
             $data['content']['main'][] = implode(',', $exptmp_data);
         }
         $newList = [];
         foreach ($list as $k => $row) {
             $newList[] = [
                 'bill_bn' => $row['bill_bn'],
                 'unique_code' =>$row['unique_code'],
                 'error_msg' => $row['error_msg'],
             ];
         }

         foreach ($newList as $key => $row) {
             $exptmp_msg_data = array();
             foreach ($titles as $name => $col) {
                 $exptmp_msg_data[] = mb_convert_encoding($row[$col], 'GBK', 'UTF-8');
             }
             $data['content']['main'][] = implode(',', $exptmp_msg_data);
         }
         return $data;
     }

     public function modifier_error_msg($row) {
        $msg = json_decode($row, true);
        if (is_null($msg)) {
            return $row;
        }
        return json_encode($msg, 320);
     }
}
