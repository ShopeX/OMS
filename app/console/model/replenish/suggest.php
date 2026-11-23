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

class console_mdl_replenish_suggest extends dbeav_model{

    public function modifier_task_bn($task_bn,$list,$row)
    {
        $page = 1;
        if (isset($_GET['page'])) {
            $page = $_GET['page'];
        }
        $finder_vid = $_GET['finder_vid'];
    
        $sug_id = $row['sug_id'];
        $str = "<a href='index.php?app=console&ctl=admin_replenish_suggest&act=detail&sug_id=" .$sug_id ."&finder_vid=" . $finder_vid . "'>".$task_bn."</a>";
        return $str;
    }


    private $templateColumn = array(
        '补货建议单号' => 'task_bn',
        '货号'        => 'material_bn',
        '实际补货数量' => 'reple_nums',
    );


    public function getItems($sug_id){
        $itemsMdl = app::get('console')->model('replenish_suggest_items');
        $items = $itemsMdl->getlist('material_bn,reple_nums',array('sug_id'=>$sug_id));

        return $items;
    }
    public function getTemplateColumn() {
        return array_keys($this->templateColumn);
    }
    public function prepared_import_csv(){
        $this->import_data =[];
        $this->import_data_bm_bn =[];
        $this->ioObj->cacheTime = time();
    }

    public function prepared_import_csv_row($row,&$title,&$tmpl,&$mark,&$newObjFlag,&$msg)
    {
       
        if(empty($row) || empty(array_filter($row))) return false;
        if( $row[0] == '补货建议单号' ){
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
        $arrRequired = ['task_bn','material_bn','reple_nums'];
        
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
        
        if(!$this->import_data['main']) {
            $main = [];
            $main['task_bn'] = $arrData['task_bn'];
            if($main['task_bn']) {
                $sugg_detail = $this->db_dump(['task_bn'=>$main['task_bn']], 'sug_id,sug_status');
                if(!$sugg_detail) {
                    $msg['error'] = $main['task_bn'].':任务不存在';
                    return false;
                }

                if(!in_array($sugg_detail['sug_status'],array('0'))){
                    $msg['error'] = '补货任务单状态非 未确认 不可以导入修改';
                    return false;
                }
            }
            
            $this->import_data['main'] = $main;
        }
        
        if(in_array($arrData['material_bn'], $this->import_data_bm_bn)) {
            $msg['error'] = 'Line '.$this->nums.'：'.$arrData['material_bn'].' 基础物料重复';
            return false;
        }
        $bm = app::get('material')->model('basic_material')->db_dump(['material_bn'=>$arrData['material_bn']], 'bm_id,material_name');
        if(empty($bm)) {
            $msg['error'] = 'Line '.$this->nums.'：'.$arrData['material_bn'].' 物料编码不存在';
            return false;
        }
        $this->import_data_bm_bn[] = $arrData['material_bn'];
        $item = [
            'bm_id'         => $bm['bm_id'],
            'material_bn'   => $arrData['material_bn'],
            'reple_nums'    => $arrData['reple_nums'],

        ];

        $this->import_data['items'][] = $item;
     
        $mark = 'contents';
        return true;
    }

    function prepared_import_csv_obj($data,$mark,$tmpl,&$msg = ''){
        return null;
    }

    public function finish_import_csv(){
        if(empty($this->import_data)) {
            return null;
        }
        $data = $this->import_data['main'];
        $items = $this->import_data['items'];

        $task_bn = $data['task_bn'];
        $suggestMdl = app::get('console')->model('replenish_suggest');
        $suggests = $suggestMdl->dump(array('task_bn'=>$task_bn),'sug_id');
        $data['sug_id'] = $suggests['sug_id'];

        $this->updateDataItems($data, $items, '导入');
    }

    
    public function updateDataItems($data, $items) {
       
        if(empty($data) || empty($items)) {
            return [false, '数据不全'];
        }
        $this->db->beginTransaction();
       
        $itemObj = app::get('console')->model('replenish_suggest_items');
        $oldItems = $itemObj->getList('*', ['sug_id'=>$data['sug_id']]);
    
        $oldBmIds = array_column($oldItems, 'bm_id');
    
        $items = array_column($items,null, 'bm_id');
       
        foreach ($oldItems as $value) {
            if($items[$value['bm_id']]) {
              
                if($items[$value['bm_id']]['reple_nums'] != $value['reple_nums']) {
                    $itemObj->update(['reple_nums'=>$items[$value['bm_id']]['reple_nums']], ['item_id'=>$value['item_id']]);
                    
                }
            } 
        }
        if(empty($items)) {
            $this->db->commit();
            return [true, ['msg'=>'操作成功']];
        }
 
        $this->db->commit();
        app::get('ome')->model('operation_log')->write_log('replenish_suggest@console',$data['sug_id'],"补货单导入更新明细数量");
        return [true, ['msg'=>'操作成功']];
    }

}
?>