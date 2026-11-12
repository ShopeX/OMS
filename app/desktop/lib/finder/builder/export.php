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


class desktop_finder_builder_export extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function main(){
        $finder_aliasname = $_GET['finder_aliasname']?$_GET['finder_aliasname']:$_POST['finder_aliasname'];
        $render = app::get('desktop')->render();
        $ioType = array();
        foreach( kernel::servicelist('desktop_io') as $aio ){
            $ioType[] = $aio->io_type_name;
        }
        $render->pagedata['ioType'] = $ioType;
        if( $_GET['change_type'] )
            $render->pagedata['change_type'] = $_GET['change_type'];

        //判断当前导出对象是否有配置项目
        if($this->object->has_export_cnf){
            $render->pagedata['has_export_cnf'] = true;
            $render->pagedata['finder_aliasname'] = $finder_aliasname;
            $render->pagedata['export_type'] = $this->object_name;
            
            //默认展示导出字段
            $in_use = array_flip($this->getColumns());
            $all_columns = $this->all_columns();

            //扩展额外导出的字段
            if(method_exists($this->object,'export_extra_cols')){
                $this->export_extra_cols = $this->object->export_extra_cols();
                $all_columns = array_merge($all_columns, $this->export_extra_cols);
            }

            //去除多余没用的导出字段
            unset($in_use['column_confirm'], $in_use['column_control'], $in_use['column_picurl']);
            unset($all_columns['column_confirm'], $all_columns['column_control'], $all_columns['column_picurl']);

            if(method_exists($this->object,'disabled_export_cols')){
                $this->object->disabled_export_cols($in_use);
                $this->object->disabled_export_cols($all_columns);
            }

            $listorder = explode(',',$this->app->getConf('listorder.'.$this->object_name.'.'.$finder_aliasname.'.'.$this->controller->user->user_id));
            if($listorder){
                $ordered_columns = array();
                foreach($listorder as $col){
                    if(isset($all_columns[$col])){
                        $ordered_columns[$col] = $all_columns[$col];
                        unset($all_columns[$col]);
                    }
                }
                $all_columns = array_merge((array)$ordered_columns,(array)$all_columns);
                $ordered_columns = null;
            }
            
            $msg ='';
            foreach($all_columns as $key=>$col){
                if(isset($in_use[$key])){
                    $msg.= $all_columns[$key]['label'].' ,';
                }
            }
            $msg = substr($msg,0,strlen($msg)-2);
            $render->pagedata['export_fields_msg'] = $msg;
            
            $export_fields = implode(',',array_flip($in_use));
            $render->pagedata['export_fields'] = $export_fields;

            $render->pagedata['export_cnf'] = json_encode(array('type'=>$this->object_name,'desc'=>$msg,'content'=>$export_fields));
        }

        //print_r($all_columns);exit;
        
        if( !$render->pagedata['thisUrl'] )
            $render->pagedata['thisUrl'] = $this->url;
        echo $render->fetch('common/export.html',app::get('desktop')->app_id);
    }
}
