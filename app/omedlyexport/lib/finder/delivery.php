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

class omedlyexport_finder_delivery{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $actions = array(
  
    );
    
    function __construct(){
        
       $user = kernel::single('desktop_user');

//	   if($user->has_permission('process_receipts_print_export')){
//	   		$this->actions[] =  array(
//            'label'=>'导出',
//            'submit'=>'index.php?app=omedlyexport&ctl=ome_delivery&act=index&action=export',
//            'target'=>'dialog::{width:400,height:170,title:\'导出\'}'
//        	);
//	   }
    }
}