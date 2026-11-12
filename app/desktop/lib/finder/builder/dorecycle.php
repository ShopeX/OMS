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


class desktop_finder_builder_dorecycle extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function main(){

        $this->controller->begin();
        
        $o = $this->app->model($this->object->table_name());
		$o->filter_use_like = true;

        $this->dbschema = $this->object->get_schema();

        $pkey = $this->dbschema['idColumn'];
        
        $pkey_value = $_POST[$pkey];
        $filter = array($pkey=>$pkey_value);
        
        if( $_POST['isSelectedAll']=='_ALL_')  //edit by 矫雷 （点此选择全部) 分开写的应该统一函数处理
            $filter = null;
        if($_GET['view']){
            $views = $this->get_views();
            $filter = array_merge((array)$views[$_GET['view']]['filter'],(array)$filter);
        }

        $recycle = kernel::single('desktop_system_recycle');

        $result = $recycle->dorecycle(get_class($this->object),$filter);

        if($result){
            $this->controller->end(true,app::get('desktop')->_('删除成功'),'javascript:finderGroup["'.$_GET['finder_id'].'"].unselectAll();finderGroup["'.$_GET['finder_id'].'"].refresh();');
        }else{
            $this->controller->end(false,$o->recycle_msg?$o->recycle_msg:app::get('desktop')->_('删除失败！'));
        }
        
    }

}
