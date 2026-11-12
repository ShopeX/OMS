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


class desktop_finder_builder_to_export extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }



    function main(){
        $oIo = kernel::servicelist('desktop_io');
        foreach( $oIo as $aIo ){
            if( $aIo->io_type_name == ($_REQUEST['_io_type']?$_REQUEST['_io_type']:'csv') ){
                $oImportType = $aIo;
                break;
            }
        }
        unset($oIo);
		//$this->object_name	omeanalysts_mdl_ome_income
		//$oName				ome_income
		//$this->app->app_id	omeanalysts
        $oName = substr($this->object_name,strlen($this->app->app_id.'_mdl_'));
        $model = app::get($this->app->app_id)->model( $oName );
		      $model->filter_use_like = true;
        $oImportType->init($model);
        $offset = 0;
        $data = array('name'=> $oName );
        if (method_exists($model,'exportName')) {
            $model->exportName($data, []);
        }
        if(isset($_REQUEST['view'])){
            $_view = $this->get_views();
            if(count($this->get_views())){
                $view_filter = (array)$_view[$_POST['view']]['filter'];
                $_POST = array_merge($_POST,$view_filter);
            }
        }
		/** 合并base filter **/
		$base_filter = (array)$this->base_filter;
		$_POST = array_merge($_POST,$base_filter);

		/** 操作日志记录 **/
		$this->doLog($model, $_POST);
		
		//后台导出service
     	$obj_services = kernel::servicelist('desktop_background_export');
        if($obj_services){
        	foreach($obj_services as $service){
        		if(method_exists($service, 'doBackgroundExport')){
        			if($service->doBackgroundExport($this->app->app_id,$oName,$_POST)){
        				return true;
        			}
        		}
        	}
        }
		
		/** end **/
      
        if( method_exists($model,'fgetlist_csv') ){
			/** 到处头部 **/
			$oImportType->export_header( $data,$model,$_POST['_export_type'] );
            while( $listFlag = $model->fgetlist_csv($data,$_POST,$offset,$_POST['_export_type']) ){
                $offset++;
                $oImportType->export( $data,$offset,$model,$_POST['_export_type'] );

                $data = [];
            }

            if ($data) {
                $oImportType->export( $data,$offset,$model,$_POST['_export_type'] );
            }

        }else{
			/** 到处头部 **/
			$oImportType->export_header( $data,$model,$_POST['_export_type'] );
            while( $listFlag = $oImportType->fgetlist($data,$model,$_POST,$offset,$_POST['_export_type']) ){
                $offset++;
				$oImportType->export( $data,$offset,$model,$_POST['_export_type'] );

                $data = [];
            }
            //var_dump($model);
        }
    }
    /**
     * 操作日志
     * @param Obj $model 数据模型
     * @param Array $params 参数
     */
    public function doLog($model, $params) {
        $logParams = array(
            'app' => trim($_GET['app']),
            'ctl' => trim($_GET['ctl']),
            'act' => trim($_GET['act']),
            'modelFullName' => get_class($model),
            'type' => 'export',
            'params' => $params
        );
//        print_r($logParams);exit;
        //是否记录日志
        if ($model->isDoLog()) {
            $type = $model->getLogType($logParams);
            //ome应用是否已经安装
            if (app::get('ome')->is_installed()) {
                ome_operation_log::insert($type, $logParams);
            }
        }
    }


}
