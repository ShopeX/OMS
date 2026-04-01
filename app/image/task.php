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
class image_task 
{
    function post_install() 
    {
        kernel::log('Initial image');
        //kernel::single('base_initial', 'image')->init();
        $conf = app::get('image')->getConf('image.default.set');
        app::get('image')->setConf('image.set',$conf);
        $obj_image = app::get('image')->model('image');
        $app_dir = app::get('image')->app_dir;
        foreach($conf as $item){
            $obj_image->store($app_dir.'/initial/default_images/'.$item['default_image'].'.gif',$item['default_image']);
        }
    }//End Function
}//End Class
