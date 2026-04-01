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


class desktop_io_type_csv extends desktop_io_io{

    var $io_type_name = 'csv';
    var $charset = null;
    var $model = null;

    function __construct(){
        if(!setlocale(LC_ALL, 'zh_CN.gbk')){
            setlocale(LC_ALL, "chs");
        }
        $this->charset = kernel::single('base_charset');
    }
    function init( $model ){
        $model->charset = $this->charset;
        $model->io = $this;
        $this->model = $model;
    }

    function str2Array( &$content,$char = "\n" ){
        $content = str_replace("\r",'\r',str_replace("\n",'\n',str_replace('"','""',$v)));

        //$content = explode($char,trim($content));
    }

    function fgethandle(&$handle,&$contents){
        $line = 0;
        $contents = array();

		$is_utf8 = true;
        while ($row = fgetcsv($handle) ) {
            $row = implode(',',$row);
            // $row = iconv('GBK','UTF-8',$row);
            $row = explode(',',$row);
            foreach( $row as $num => $col ){
				if ($line==0&&$num==0){
					// 判断下文档的字符集.
					if (!$this->charset->is_utf8($col)){
						$is_utf8 = false;
					}else{
						if ($col_tmp = $this->charset->replace_utf8bom($col)){
							$col = $col_tmp;
						}
					}
				}
				if (!$is_utf8)
					$contents[$line][$num] = $this->charset->local2utf( (string) $col);
				else
					$contents[$line][$num] = (string) $col;

                $encode = mb_detect_encoding($contents[$line][$num], array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));

                if('UTF-8' != $encode) $contents[$line][$num] = mb_convert_encoding($contents[$line][$num], 'UTF-8', $encode);
            }
            $line++;
        }
    }

    // 区别与fgethandle, fgethandle2不会将row先implode(',',$row)再explode(',',$row)，解决导入的时候因为逗号导致数据错误的问题
    function fgethandle2(&$handle,&$contents){
        $line = 0;
        $contents = array();

		$is_utf8 = true;
        while ($row = fgetcsv($handle) ) {
            foreach( $row as $num => $col ){
				if ($line==0&&$num==0){
					// 判断下文档的字符集.
					if (!$this->charset->is_utf8($col)){
						$is_utf8 = false;
					}else{
						if ($col_tmp = $this->charset->replace_utf8bom($col)){
							$col = $col_tmp;
						}
					}
				}
				if (!$is_utf8)
					$contents[$line][$num] = $this->charset->local2utf( (string) $col);
				else
					$contents[$line][$num] = (string) $col;

                $encode = mb_detect_encoding($contents[$line][$num], array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));

                if('UTF-8' != $encode) $contents[$line][$num] = mb_convert_encoding($contents[$line][$num], 'UTF-8', $encode);
            }
            $line++;
        }
    }

    function data2local( $data ){
        $title = array();
        foreach( $data as $aTitle ){
            $title[] = $this->charset->utf2local($aTitle);
        }
        return $title;
    }

    function fgetlist( &$data,&$model,$filter,$offset,$exportType =1,$limit = 100){

        $cols = $model->_columns();
        if ($filter['_export_mb'] == 1) {
            $fields = explode(',',$filter['export_fields']);
        }elseif ($filter['_export_mb'] == 2){
            $exptempObj = app::get('desktop')->model('export_template');
            $tempInfo = $exptempObj->dump(array('et_id'=>$filter['extemp_id']),'et_filter');
            $curr_filter = unserialize($tempInfo['et_filter']);
            $fields = explode(',',$curr_filter['fields']);
        }

        if(!$data['title']){
            $this->title = array();
            foreach( $this->getTitle($cols) as $titlek => $aTitle ){
                if ($fields) {
                    if (!in_array($titlek,$fields)) {
                        continue;
                    }
                }
                $this->title[$titlek] = $aTitle;
            }
            $data['title'] = '"'.implode('","',$this->title).'"';
        }

        if(!$list = $model->getList(implode(',',array_keys($cols)),$filter,$offset*$limit,$limit))return false;

		$data['contents'] = array();
        foreach( $list as $line => $row ){
            $rowVal = array();
            foreach( $row as $col => $val ){

                if( in_array( $cols[$col]['type'],array('time','last_modify') ) && $val ){
                   $val = date('Y-m-d H:i',$val);
                }
                if ($cols[$col]['type'] == 'longtext'){
                    if (strpos($val, "\n") !== false){
                        $val = str_replace("\n", " ", $val);
                    }
                }

                if( strpos( (string)$cols[$col]['type'], 'table:')===0 ){
                    $subobj = explode( '@',substr($cols[$col]['type'],6) );
                    if( !$subobj[1] )
                        $subobj[1] = $model->app->app_id;
                    $subobj = app::get($subobj[1])->model( $subobj[0] );
                    $subVal = $subobj->dump( array( $subobj->schema['idColumn']=> $val ),$subobj->schema['textColumn'] );
                    $val = $subVal[$subobj->schema['textColumn']]?$subVal[$subobj->schema['textColumn']]:$val;
                }

                if( array_key_exists( $col, $this->title ) )
                    $rowVal[] = addslashes(  (is_array($cols[$col]['type'])?$cols[$col]['type'][$val]:$val ) );
            }
            $data['contents'][] = '"'.implode('","',$rowVal).'"';
        }
        return true;

    }

    function turn_to_sdf( $data ){

    }

    function import(&$contents,$app,$mdl ){
        $model = &$this->model;
        if(!is_array($contents))
            $this->str2Array($contents);
        if( !$this->data['title'] )
            $this->data = array('title'=>array(),'contents'=>array());
        $msg = array();

        $oQueue = app::get('base')->model('queue');


        while( true ){
            //
        }
        return array('success', $msg);

        while( true ){
            $row = current($contents);
            if( !is_array($row) )
                $this->str2Array($row,',');
            if( $row ){
                foreach( $row as $num => $col )
                    $row[$num] = trim($col,'"');
            }
            $newObjFlag = false;
            $rowData = $model->prepared_import_csv_row( $row,$this->data['title'],$tmpl,$mark,$newObjFlag,$msg );
            if( $rowData === false ){
                return array('failure',$msg);
            }

            if( !current($contents) || $newObjFlag ){
                if( $mark != 'title' ){

                    $saveData = $model->prepared_import_csv_obj( $this->data,$mark,$tmpl,$msg);
                    if( $saveData === false ){
                        return array('failure',$msg);
                    }

                    if( $saveData ){
                        $queueData = array(
                            'queue_title'=>$mdl.app::get('desktop')->_('导入'),
                            'start_time'=>time(),
                            'params'=>array(
                                'sdfdata'=>$saveData,
                                'app' => $app,
                                'mdl' => $mdl
                            ),
                            'worker'=>'desktop_finder_builder_to_run_import.run',
                        );
                        $oQueue->save($queueData);
                    }
                    if( $mark )
                        eval('$this->data["'.implode('"]["',explode('/',$mark)).'"] = array();');
                }
            }
            next( $contents );
            if( $mark ){
                if( $mark == 'title' )
                    eval('$this->data["'.implode('"]["',explode('/',$mark)).'"] = $rowData;');
                else
                    eval('$this->data["'.implode('"]["',explode('/',$mark)).'"][] = $rowData;');
            }
            if( !$row )break;
        }

        return array('success', $msg);
    }

    function prepared_import( $appId,$mdl ){
        $this->model = app::get($appId)->model($mdl);

        $this->model->ioObj = $this;
        if( method_exists( $this->model,'prepared_import_csv' ) ){
            $this->model->prepared_import_csv();
        }
        return;
    }

    function finish_import(){
        if( method_exists( $this->model,'finish_import_csv' ) ){
            $this->model->finish_import_csv();
        }
    }

    function csv2sdf($data,$title,$csvSchema,$key = null){
        $rs = array();
        $subSdf = array();
        foreach( $csvSchema as $schema => $sdf ){
            $sdf = (array)$sdf;
            if( ( !$key && !$sdf[1] ) || ( $key && $sdf[1] == $key ) ){
                eval('$rs["'.implode('"]["',explode('/',$sdf[0])).'"] = $data[$title[$schema]];');
                unset($data[$title[$schema]]);
            /*}else if( ){
                eval('$rs["'.implode('"]["',explode('/',$sdf[0])).'"] = $data[$title[$schema]];');
                unset($data[$title[$schema]]);*/
            }else{
                $subSdf[$sdf[1]] = $sdf[1];
            }
        }
        if(!$key){
            foreach( $subSdf as $k ){
                foreach( $data[$k] as $v ){
                    $rs[$k][] = $this->csv2sdf($v,$title,$csvSchema,$k);
                }
            }
        }
        foreach( $data as $orderk => $orderv ){
            if( substr($orderk,0,4 ) == 'col:' ){
                $rs[ltrim($orderk,'col:')] = $orderv;
            }
        }
        return $rs;

        }

	function export_header(&$data,&$model,$exportType=1){
		header("Content-Type: text/csv");
        $filename = $data['name'].".csv";
        $encoded_filename = urlencode($filename);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);

        $ua = $_SERVER["HTTP_USER_AGENT"];
        if (preg_match("/MSIE/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        }else if (preg_match("/Firefox\/8/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }else if (preg_match("/Firefox/", $ua)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $filename . '"');
        }else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
	}

    function export(&$data,$page,&$model,$exportType=1){

        if(method_exists($model,'export_csv')){
            $rs = $model->export_csv($data,$exportType);
        }else{
            $rs = '';
            if( is_array( $data ) ){
                $data = (array)$data;
                if( empty( $data['title'] ) && empty( $data['contents'] ) ){
                    $rs = implode( "\n", $data );
                }else{
					if ($page==1)
						$rs = $data['title']."\n".implode("\n",(array)$data['contents']);
					else
						$rs = implode("\n",(array)$data['contents']);
                }
            }else{
                $rs = (string)$data;
            }
        }


        $fp = fopen('php://output', 'a');

		if(function_exists('iconv')){
			//excel 2007 读取utf8乱码bug。
			$string =  iconv('UTF-8', 'GBK//IGNORE', $rs)."\n";
		}else{
			$string =  $this->charset->utf2local( $rs )."\n";
		}

        fwrite($fp, $string);
    }

}
