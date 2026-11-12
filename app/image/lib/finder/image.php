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


class image_finder_image{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    var $detail_basic = '图片详细信息';
    var $column_img = '图片';
    function __construct($app){
        $this->app = $app;
    }

    function detail_basic($image_id){
        $app = app::get('image');

        $render = $app->render();

        $image = $this->app->model('image');
        $image_info = $image->dump($image_id);
        $allsize = app::get('image')->getConf('image.default.set');

        $render->pagedata['allsize'] = $allsize;
        $render->pagedata['image'] = $image_info;
     
    
        return $render->fetch('finder/image.html');
    }
    function column_img($row){
 
        $obj = app::get('image')->model('image');
        $row = $obj->dump($row['image_id']);
        $limitwidth = 50;
        
        $maxsize = max($row['width'],$row['height']);
        
        if($maxsize>$limitwidth){
            $size ='width=';
            $size.=$row['width']-$row['width']*(($maxsize-50)/$maxsize);
            $size.=' height=';
            $size.=$row['height']-$row['height']*(($maxsize-50)/$maxsize);
        }else{
            $size ='width='.$row['width'].' height='.$row['height'];
        }
        
        
       
        if($row['storage']=='network'){
         return '<a href="'.$row['ident'].'" target="_blank"><div title="'.$row['ident'].'" style="line-height:41px;width:50px;text-align:center;background:#efefef;">网络图片</div></a><input type="text" value="'.$row['ident'].'" style="font-size:9px;font-family:verdana;border:none;width:50px;padding:0;margin:0;display:block;background:#333;color:#fff"/>';
        }
        return '<div  style="width:50px;height:50px;display:block;font-family:Arail;vertical-align: middle;display:table-cell;font-size:42.5px;padding:1px;background:#fff;"><a href="'.(base_storager::image_path($row['image_id'])).'" target="_blank" style="display:block;">
<img src="'.(base_storager::image_path($row['image_id'],'s')).'" '.$size.' /></a></div>';
    }
}
