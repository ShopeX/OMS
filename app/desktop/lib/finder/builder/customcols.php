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


class desktop_finder_builder_customcols extends desktop_finder_builder_prototype{

    function main(){
        $customcolsMdl = app::get('desktop')->model('customcols');

        if($_POST['customcols_save'] || $_POST['cols']){

            $postCols = (isset($_POST['cols']) && is_array($_POST['cols'])) ? $_POST['cols'] : array();

            // 验证入参
            foreach($postCols as $k=>$v){
                // $v['col_key']只能是字母、数字、下划线
                if(!preg_match('/^[a-zA-Z0-9_]+$/', $v['col_key'])){
                    header('Content-Type:text/jcmd; charset=utf-8');
                    echo '{error:"保存错误,原因:字段名称【'.$v['col_key'].'】只能是字母、数字、下划线",_:null}';
                    exit;
                }

                // $v['col_name']只能是中文、英文、数字、下划线
                if(!preg_match('/^[\p{Han}a-zA-Z0-9_\(\)\（\）]+$/u', $v['col_name'])){
                    header('Content-Type:text/jcmd; charset=utf-8');
                    echo '{error:"保存错误,原因:字段描述【'.$v['col_name'].'】只能是中文、英文、数字、下划线",_:null}';
                    exit;
                }

                $colType = isset($v['col_type']) ? $v['col_type'] : 'input';
                if (!in_array($colType, array('input', 'radiobox', 'select'), true)) {
                    header('Content-Type:text/jcmd; charset=utf-8');
                    echo '{error:"保存错误,原因:字段【'.$v['col_key'].'】类型不合法",_:null}';
                    exit;
                }
                if ($colType === 'select') {
                    $options = $this->_parseOptionsFromPost($v);
                    if (empty($options)) {
                        header('Content-Type:text/jcmd; charset=utf-8');
                        echo '{error:"保存错误,原因:下拉字段【'.$v['col_name'].'】请至少配置一项选项",_:null}';
                        exit;
                    }
                }
            }

            $table_name = $this->object->table_name(1);

            $columns  = $this->object->_columns();
            $column_keys = array_keys($columns);

            $col_keys = $postCols ? array_column($postCols,'col_key') : array();

            $intersection = array_intersect($col_keys, $column_keys);
            //通用默认判断
            if($intersection){
                $msg = '自定义列:'.implode(',',$intersection).'已存在';
                header('Content-Type:text/jcmd; charset=utf-8');
                echo '{error:"保存错误,原因:'.$msg.'",_:null}';
                exit;
              
            }
            if ($postCols && method_exists($this->object, 'checkCustomcols')){

                list($rs,$msg) = $this->object->checkCustomcols($postCols);
                if(!$rs){
                    header('Content-Type:text/jcmd; charset=utf-8');
                    echo '{error:"保存错误,原因:'.$msg.'",_:null}';
                    exit;
                }
            }

            $postedIds = array();
            $pOrder = 0;
            foreach($postCols as $k=>$v){

                $v['tbl_name'] = $table_name;
                $v['p_order'] = $pOrder++;
                $colType = isset($v['col_type']) ? $v['col_type'] : 'input';
                if (!in_array($colType, array('input', 'radiobox', 'select'), true)) {
                    $colType = 'input';
                }
                $v['col_type'] = $colType;

                $existFilter = array();
                if (!empty($v['id'])) {
                    $exist = $customcolsMdl->dump(array('id' => $v['id']), 'filter_data');
                    if (!empty($exist['filter_data'])) {
                        $decoded = json_decode($exist['filter_data'], true);
                        if (is_array($decoded)) {
                            $existFilter = $decoded;
                        }
                    }
                }

                if ($colType === 'select') {
                    $existFilter['options'] = $this->_parseOptionsFromPost($v);
                    $v['filter_data'] = json_encode($existFilter, JSON_UNESCAPED_UNICODE);
                } else {
                    if (isset($existFilter['options'])) {
                        unset($existFilter['options']);
                    }
                    $v['filter_data'] = $existFilter ? json_encode($existFilter, JSON_UNESCAPED_UNICODE) : '';
                }
                unset($v['options_text'], $v['options']);

                $customcolsMdl->save($v);
                if (!empty($v['id'])) {
                    $postedIds[] = (int) $v['id'];
                }
            }

            // 删除界面上已移除的字段
            $existing = $customcolsMdl->getList('id', array('tbl_name' => $table_name), 0, -1);
            if (is_array($existing)) {
                foreach ($existing as $row) {
                    $eid = (int) $row['id'];
                    if ($eid && !in_array($eid, $postedIds, true)) {
                        $customcolsMdl->delete(array('id' => $eid, 'tbl_name' => $table_name));
                    }
                }
            }

            header('Content-Type:text/jcmd; charset=utf-8');
            echo '{success:"'.app::get('desktop')->_('设置成功').'"}';    
        }else{

           
            $render = app::get('desktop')->render();

            $cols = $customcolsMdl->getList('*',array('tbl_name'=>$this->object->table_name(1)), 0, -1, 'p_order ASC, id ASC');
            if (is_array($cols)) {
                foreach ($cols as &$col) {
                    $col['options'] = array();
                    if (empty($col['col_type'])) {
                        $col['col_type'] = 'input';
                    }
                    if (!empty($col['filter_data'])) {
                        $decoded = json_decode($col['filter_data'], true);
                        if (is_array($decoded) && !empty($decoded['options']) && is_array($decoded['options'])) {
                            foreach ($decoded['options'] as $opt) {
                                $value = isset($opt['value']) ? (string) $opt['value'] : '';
                                $label = isset($opt['label']) ? (string) $opt['label'] : $value;
                                if ($value === '' && $label === '') {
                                    continue;
                                }
                                if ($label === '') {
                                    $label = $value;
                                }
                                $col['options'][] = array(
                                    'value' => $value,
                                    'label' => $label,
                                );
                            }
                        }
                    }
                }
                unset($col);
            }

            $render->pagedata['cols'] = $cols;

            echo $render->fetch('common/customcols.html');
        }
    }

    /**
     * 从 POST 解析 options（优先结构化数组，兼容旧 options_text）
     *
     * @param array $v
     * @return array
     */
    private function _parseOptionsFromPost($v)
    {
        if (!empty($v['options']) && is_array($v['options'])) {
            $options = array();
            foreach ($v['options'] as $opt) {
                if (!is_array($opt)) {
                    continue;
                }
                $value = isset($opt['value']) ? trim((string) $opt['value']) : '';
                $label = isset($opt['label']) ? trim((string) $opt['label']) : '';
                if ($value === '' && $label === '') {
                    continue;
                }
                if ($value === '') {
                    $value = $label;
                }
                if ($label === '') {
                    $label = $value;
                }
                $options[] = array(
                    'value' => $value,
                    'label' => $label,
                );
            }
            return $options;
        }
        return $this->_parseOptionsText(isset($v['options_text']) ? $v['options_text'] : '');
    }

    /**
     * 解析「值|显示名」文本为 options 数组
     *
     * @param string $text
     * @return array
     */
    private function _parseOptionsText($text)
    {
        $options = array();
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (strpos($line, '|') !== false) {
                $parts = explode('|', $line, 2);
                $value = trim($parts[0]);
                $label = trim($parts[1]);
            } else {
                $value = $line;
                $label = $line;
            }
            if ($value === '') {
                continue;
            }
            if ($label === '') {
                $label = $value;
            }
            $options[] = array(
                'value' => $value,
                'label' => $label,
            );
        }
        return $options;
    }
}
