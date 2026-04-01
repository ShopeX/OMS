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


class omecsv_io_io{

    var $charset = null;
    var $limitRow = 20000;

    public function __construct(){
        if(!setlocale(LC_ALL, 'zh_CN.gbk')){
            setlocale(LC_ALL, "chs");
        }
        $this->charset = kernel::single('base_charset');

    }

    public function getTitle(&$cols){
        $title = array();
        foreach( $cols as $col => $val ){
            if( !$val['deny_export'] )
                $title[$col] = $val['label'].'('.$col.')';
        }
        return $title;
    }

    public function init( &$model ){
        $model->charset = $this->charset;
        $model->io = $this;
        $this->model->$model;
    }

    /**
     * @param String $inputFileName 导入文件名
     * @param Array $contents 数量
     * @return Void
     **/
    public function fgethandle($inputFileName,&$contents){
        $path = pathinfo($inputFileName);
        $excel = new \Vtiful\Kernel\Excel(['path' => $path['dirname']]);
        $excel->openFile($path['basename'])
            ->openSheet();
        $row = $excel->nextRow();
        if(!is_array($row)) {
            throw new exception('Don\'t find import title');
        }
        $contents = $excel->setType(array_pad([], count($row), \Vtiful\Kernel\Excel::TYPE_STRING))
                            ->getSheetData();
        $contents = array_merge([0 => $row], $contents);
    }

    public function prepared_import( $appId,$mdl ){
        $this->model = app::get($appId)->model($mdl);
        $this->model->ioObj = $this;
        if( method_exists( $this->model,'prepared_import_csv' ) ){
            $this->model->prepared_import_csv();
        }
        return;
    }

    public function finish_import(){
        if( method_exists( $this->model,'finish_import_csv' ) ){
            return $this->model->finish_import_csv();
        }
    }

}
