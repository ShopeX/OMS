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
 
class desktop_ctl_tags extends desktop_controller{

    var $workground = 'desktop_ctl_tags';
    var $url = 'index.php?app=desktop&ctl=tags';

    function index(){
        $ex_p = '&wg='.urlencode($_GET['wg']).'&type='.urlencode($_GET['type']).'&app_id='.urlencode($_GET['app_id']);
        $params = array(
            'title'=>app::get('desktop')->_('标签管理'),
            'actions'=>array(
                array('label'=>app::get('desktop')->_('新建普通标签'),'icon'=>'add.gif','href'=>$this->url.'&act=new_mormal_tag'.$ex_p,'target'=>'dialog::{title:\''.app::get('desktop')->_('新建普通标签').'\'}'),
               // array('label'=>'新建条件标签','href'=>$this->url.'&act=new_filter_tag'.$ex_p,'target'=>'dialog::{title:\'新建条件标签\'}'),
            ),
            'base_filter'=>array(
                'tag_type'=>$_GET['type']
            ),'use_buildin_new_dialog'=>false,'use_buildin_set_tag'=>false,'use_buildin_recycle'=>false,'use_buildin_export'=>false);
        $this->finder('desktop_mdl_tag',$params);
    }

    function new_mormal_tag(){
        $ex_p = '&wg='.urlencode($_GET['wg']).'&type='.urlencode($_GET['type']).'&app_id='.urlencode($_GET['app_id']);
       if($_POST){
            $this->begin();
            $tagmgr = $this->app->model('tag');
            $data = array(
                    'tag_name'=>$_POST['tag_name'],
                    'tag_abbr'=>$_POST['tag_abbr'],
                    'tag_type'=>$_GET['type'],
                    'app_id'=>$_GET['app_id'],
                    'tag_mode'=>'normal',
                    'tag_bgcolor'=>$_POST['tag_bgcolor'],
                );
            $tagmgr->save($data);
            $this->end();
        }else{
            $html = $this->ui()->form_start(array(
                'action'=>$this->url.'&act=new_mormal_tag'.$ex_p,
                ));
            $html .= $this->ui()->form_input(array('title'=>app::get('desktop')->_('标签名'),'name'=>'tag_name'));
            $html .= $this->ui()->form_input(array('title'=>app::get('desktop')->_('标签备注'),'maxlength'=>'50','name'=>'tag_abbr'));
            $html .= $this->ui()->form_input(array('title'=>app::get('desktop')->_('标签背景色'),'type'=>'color','name'=>'tag_bgcolor'));
            $html.=$this->ui()->form_end();
            echo $html;
        }
    }

    function new_filter_tag(){
    }

}
