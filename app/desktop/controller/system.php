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

class desktop_ctl_system extends desktop_controller{

    var $require_super_op = true;

    function __construct($app) {
        parent::__construct($app);
        header("cache-control: no-store, no-cache, must-revalidate");
        $this->app = $app;
    }

    function index(){
        echo $this->app->getConf('shopadminVcode');
    }
    function set_title(){
		if($_POST){
			$this->begin();
			$this->app->setConf('background.title',$_POST['background_title']);
			$this->end(true,app::get('desktop')->_('保存成功'));
		}else{
        echo '<h4 class="head-title" >'.app::get('desktop')->_('标题设置').'</h4>';
            $html = $this->ui()->form_start(array('action'=>'index.php?ctl=system&act=set_title','method'=>'post'));
			$background_title = $this->app->getConf('background.title');
            $html .= $this->ui()->form_input(array('title'=>app::get('desktop')->_('标题：'),'name'=>'background.title','tab'=>'后台设置','value'=>$background_title,'vtype'=>'required'));
            $html.=$this->ui()->form_end();
            echo $html;
		}
	}
    function service()

    {
        echo '<h4 class="head-title" >'.app::get('desktop')->_('系统配置').'</h4>';
       if($_POST){


            $this->app->setConf('shopadminVcode',$_POST['shopamin_vocde']);
        }
        $services = app::get('base')->model('services');
        $filter = array(
                'content_type'=>'service_category',
                'content_path'=>'select',
                'disabled'=>'true',
            );

        $all_category = $services->getList('*', $filter);
        $filter = array(
                'content_type'=>'service',
                'disabled'=>'true',
            );
        $all_services = $services->getList('*', $filter);
        foreach($all_services as $k => $row){
            $vars = get_class_vars($row['content_path']);
            $servicelist[$row['content_name']][$row['content_path']] = $vars['name'];
        }
        $html .= $this->ui()->form_start(array('method'=>'POST'));
        foreach($all_category as $ik => $item){
             if( $item['content_name'] == 'eccommon_regions.eccommon_mdl_regions' ){
                unset( $all_category[$ik] );
                continue;
            }
           $current_set = app::get('base')->getConf('service.'.$item['content_name']);
            if(@array_key_exists($item['content_name'],$_POST['service'])){
                if($current_set!=$_POST['service'][$item['content_name']]){
                    $current_set = $_POST['service'][$item['content_name']];
                    app::get('base')->setConf('service.'.$item['content_name'], $current_set);
                }
            }
            $form_input = array(
                    'title'=>$item['content_title'],
                    'type'=>'select',
                    'required'=>true,
                    'name'=>"service[".$item['content_name']."]",
                    'tab'=>$tab,
                    'value'=> $current_set,
                    'options'=>$servicelist[$item['content_name']],
            );

            $html.=$this->ui()->form_input($form_input);
        }
        $select = $this->app->getConf('shopadminVcode');
        if($select === 'true'){

             $html .="<tr><th><label>".app::get('desktop')->_('后台登陆启用验证码')."</label></th><td>&nbsp;&nbsp;<select name='shopamin_vocde' type='select' ><option value='true' selected='selected'>".app::get('desktop')->_('是')."</option><option value='false' >".app::get('desktop')->_('否')."</option></select></td></tr>";

        }
        else{

             $html .="<tr><th><label>".app::get('desktop')->_('后台登陆启用验证码')."</lable></th><td>&nbsp;&nbsp;<select name='shopamin_vocde' type='select' ><option value='true'>".app::get('desktop')->_('是')."</option><option value='false' selected='selected'>".app::get('desktop')->_('否')."</option></select></td></tr>";

        }
        $html .= $this->ui()->form_end();
        $this->pagedata['_PAGE_CONTENT'] = $html;
        $this->page();
    }

    function licence(){
        $this->sidePanel();
        echo '<iframe width="100%" height="100%" src="'.constant('URL_VIEW_LICENCE').'" ></iframe>';
    }

    /**
     * 站点设置
     *
     * @access public
     * @author chenping<chenping@shopex.cn>
     * @time 2024-06-20 11:35:00
     */
    public function siteSetPage()
    {
        
        $siteInfo = kernel::single('desktop_site')->getInfo();
        
        $this->pagedata['siteInfo'] = $siteInfo;

        $this->page('site/setting.html');
    }
    
    /**
     * 站点信息保存
     *
     * @access public
     * @author chenping<chenping@shopex.cn>
     * @time 2024-06-20 13:38:00
     */
    public function siteSetSave()
    {
        $this->begin();
        
        $file = $_FILES;
       
        $siteInfo = app::get('desktop')->getConf('siteInfo');
        $siteInfo = array_merge((array)$siteInfo, (array)$_POST['siteInfo']);
        
        // 如果使用系统默认
        if ($siteInfo['logoSet'] == '0'){
            
            unset($siteInfo['logoUrl']);
            
            app::get('desktop')->setConf('siteInfo', $siteInfo);
            
            unset($file);
        }
        

        if ($file['logo'] && $file['logo']['tmp_name'] && !$file['logo']['error']){
            
            $extMap = array(1 => '.gif', 2 => '.jpg', 3 => '.png', 6 => '.bmp');
            $imgInfo = @getimagesize($file['logo']['tmp_name']);
            $ext = ($imgInfo && isset($extMap[$imgInfo[2]])) ? $extMap[$imgInfo[2]] : '.png';
            $logoFilename = '/tmp/logo' . $ext;

            move_uploaded_file($file['logo']['tmp_name'], $logoFilename);
            $id = kernel::single('base_storager')->save_upload($logoFilename, 'image', '', $msg, $ext);
            
            if (!$id) {
                $this->end(false, $msg);
            }
            
            $siteInfo['logoUrl'] = $id;

            app::get('desktop')->setConf('siteInfo', $siteInfo);
        }
        
        
        $this->end(true);
    }
}

