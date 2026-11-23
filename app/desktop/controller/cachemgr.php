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

/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 

class desktop_ctl_cachemgr extends desktop_controller 
{
    
    public function index() 
    {
        $this->pagedata['enable'] =
 		(get_class(cachemgr::instance()) == 'base_cache_nocache') ? 'false' : 'true';
 		if(cachemgr::status($msg)){
		   $this->pagedata['status']  = $msg; 
		}
		
		
        $this->page('cachemgr/index.html');
    }//End Function

    public function status() 
    {  
		
        $this->pagedata['status'] = 'current';
        $this->index();
        if(cachemgr::status($msg)){
            $this->pagedata['status'] = $msg;
            // $this->display('cachemgr/status.html');
        }else{
            //echo '<p class="notice">'.(($msg) ? $msg : app::get('desktop')->_('无法查看状态')).'</p>';
        }
    }//End Function

    public function optimize() 
    {
	
        $this->begin('');
		$this->end(cachemgr::optimize($msg),$msg);
    }//End Function
    
    public function clean() 
    {
	    $this->begin('');
		$this->end(cachemgr::clean($msg),$msg);
    }//End Function
}//End Class
