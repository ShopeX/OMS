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


class desktop_controller extends base_controller
{

    protected $checkCSRF = true;
    public $defaultwg;
    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */
    public function __construct($app)
    {
        if(!isset($this->workground)) {
            $this->workground = get_class($this);
        }
        if($this->checkCSRF && strpos($_SERVER['CONTENT_TYPE'], 'x-www-form-urlencoded') && $_SERVER['HTTP_X_REQUESTED_BY'] != 'shopex-ui') {
            header('Content-Type:text/html; charset=utf-8');
            die('非法请求');
        }
        header("Cache-Control:no-store, no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // 强制查询etag
        header('Progma: no-cache');
        $this->defaultwg = $this->defaultWorkground;
        parent::__construct($app);
        kernel::single('base_session')->start();
        if ($_SESSION['account'][$_SESSION['type']] && kernel::single('desktop_user')->checkUpdatePwd($_SESSION['account'][$_SESSION['type']])) {
            kernel::single('base_session')->destory();
        }

        //验证产品有效性，通行证等信息，不然提示相关信息
        $obj_services = kernel::servicelist('app_pre_auth_use');
        foreach ($obj_services as $obj) {
            if (method_exists($obj, 'pre_auth_uses') && method_exists($obj, 'active_top_html')) {
                $pre_auth_kv = get_class($obj) . '_pre_auth';
                if (!app::get('desktop')->getConf($pre_auth_kv) && !$obj->pre_auth_uses()) {
                    $this->pagedata['desktop_active_url'] = $obj->active_top_html();
                    app::get('desktop')->setConf($pre_auth_kv, false);
                } else {
                    app::get('desktop')->setConf($pre_auth_kv, true);
                }
            }
        }

        #remove xss 防sql注入
        if($_GET['ctl'] != "admin_print_otmpl") {
            $_REQUEST = utils::_filter_input($_REQUEST);
        }

        if ($_COOKIE['autologin'] > 0) {
            kernel::single('base_session')->set_sess_expires($_COOKIE['autologin']);
        } //如果有自动登录，设置session过期时间，单位：分
        $auth    = pam_auth::instance(pam_account::get_account_type('desktop'));
        $account = $auth->account();
        if (get_class($this) != 'desktop_ctl_passport' && !$account->is_valid()) {
            if (get_class($this) != 'desktop_ctl_default') {
                $url = kernel::router()->gen_url($_GET, 1);
            } else {
                $url = kernel::router()->gen_url(array(), 1);
            }

            $url     = base64_encode($url);
            $arr_get = $_GET;
            foreach ($arr_get as &$str_get) {
                if(is_string($str_get)) {
                    $str_get = urldecode($str_get);
                }
            }

            $params = urlencode(json_encode($arr_get));
           // $_GOTO  = 'index.php?ctl=passport&url=' . $url . '&params=' . $params;
           // echo "<script>location.replace ('$_GOTO');console.log(window.parent)</script>";exit;
            $_GOTO = kernel::router()->gen_url(['ctl' => 'passport', 'params'=>$params], 1);
            echo <<<JS
            <script>
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ location: '$_GOTO', data: '' }, '*');
            } else {
                location = '$_GOTO';
            }
            </script>
JS;
            exit;

        }
        $this->user = kernel::single('desktop_user');
        if ($_GET['ctl'] != "passport" && $_GET['ctl'] != "") {
            $this->status = $this->user->get_status();
            if (!$this->status && $this->status == 0) {
                #echo "未启用";exit;
                //echo "<script>alert('管理员未启用')</script>";
                $url = kernel::router()->gen_url(array(), 1);
                $url = base64_encode($url);
                header('Content-Type:text/html; charset=utf-8');
                $this->pagedata['link_url'] = 'index.php?ctl=passport&url=' . $url;
                echo $this->fetch('auth_error.html');exit;
            }
        }
        ###如果不是超级管理员就查询操作权限
        if (!$this->user->is_super()) {
            if (!$this->user->chkground($this->workground)) {
                header('Content-Type:text/html; charset=utf-8');
                echo app::get('desktop')->_("您无权操作");exit;
            }
        }
        $obj_model = app::get('desktop')->model('menus');
        //检查链接是否可用
        $obj_model->permissionId($_GET);
        //end
        $this->_finish_modifier = array();
        foreach (kernel::servicelist(sprintf('desktop_controller_content.%s.%s.%s', $_GET['app'], $_GET['ctl'], $_GET['act'])) as $class_name => $service) {
            if ($service instanceof desktop_interface_controller_content) {
                if (method_exists($service, 'modify')) {
                    $this->_finish_modifier[$class_name] = $service;
                }
                if (method_exists($service, 'boot')) {
                    $service->boot($this);
                }
            }
        }
        //修改tab detail 里的内容
        foreach (kernel::servicelist(sprintf('desktop_controller_content_finderdetail.%s.%s.%s.%s', $_GET['app'], $_GET['ctl'], $_GET['act'], (string) (isset($_GET['finderview']) ? $_GET['finderview'] : '0'))) as $class_name => $service) {
            if ($service instanceof desktop_interface_controller_content) {
                if (method_exists($service, 'modify')) {
                    $this->_finish_modifier[$class_name] = $service;
                }
                if (method_exists($service, 'boot')) {
                    $service->boot($this);
                }
            }
        }
        if ($this->_finish_modifier) {
            ob_start();
            register_shutdown_function(array(&$this, 'finish_modifier'));
        }

        $this->url = 'index.php?app=' . $this->app->app_id . '&ctl=' . $_GET['ctl'];
    }

    /**
     * __destruct
     * @return mixed 返回值
     */
    public function __destruct()
    {
        foreach (kernel::servicelist('desktop_controller_destruct') as $service) {
            if (is_object($service) && method_exists($service, 'destruct')) {
                $service->destruct($this);
            }
        }
    }

    /*
     * 有modifier的处理程序
     */

    public function finish_modifier()
    {
        $content = ob_get_contents();
        ob_end_clean();
        foreach ($this->_finish_modifier as $modifier) {
            $modifier->modify($content, $this);
        }
        echo $content;
    }

    /**
     * redirect
     * @param mixed $url url
     * @return mixed 返回值
     */
    public function redirect($url)
    {
        $arr_url = parse_url($url);
        if ($arr_url['scheme'] && $arr_url['host']) {
            header('Location: ' . $url);
        } else {
            header('Location: ' . kernel::router()->app->base_url(1) . $url);
        }
        //
    }
    /**
     * location_to
     * @return mixed 返回值
     */
    public function location_to()
    {
        if (kernel::single('base_component_request')->is_ajax() != true) {
            header('Location: index.php#' . $_SERVER['QUERY_STRING']);exit;
        }
    }
    public function finder($object_name, $params = array())
    {
        if ($_GET['action'] != 'to_export' && $_GET['action'] != 'to_import' && $_GET['singlepage'] != 'true') {
            $this->location_to();
        }
        header("cache-control: no-store, no-cache, must-revalidate");
        $_GET['action'] = $_GET['action'] ? $_GET['action'] : 'view';
        $finder         = kernel::single('desktop_finder_builder_' . $_GET['action'], $this);

        foreach ($params as $k => $v) {
            $finder->$k = $v;
        }
        $app_id      = substr($object_name, 0, strpos($object_name, '_'));
        $app         = app::get($app_id);
        $finder->app = $app;
        $finder->work($object_name);
    }

    /**
     * singlepage
     * @param mixed $view view
     * @param mixed $app_id ID
     * @return mixed 返回值
     */
    public function singlepage($view, $app_id = '')
    {

        $service = kernel::service(sprintf('desktop_controller_display.%s.%s.%s', $_GET['app'], $_GET['ctl'], $_GET['act']));
        if ($service) {
            if (method_exists($service, 'get_file')) {
                $view = $service->get_file();
            }

            if (method_exists($service, 'get_app_id')) {
                $app_id = $service->get_app_id();
            }

        }
        $page = $this->fetch($view, $app_id);

        $this->pagedata['_PAGE_PAGEDATA_'] = $this->_vars;

        $re              = '/<script([^>]*)>(.*?)<\/script>/is';
        $this->__scripts = '';

        preg_match_all($re, $page, $match);
        if (is_array($match[0])) {

            foreach ($match[0] as $key => $one) {
                if ($match[2][$key] && !strpos($match[1][$key], 'src') && !strpos($match[1][$key], 'hold')) {
                    $this->__scripts .= "\n" . $match[2][$key];

                    $page = str_replace($one, ' ', $page);

                }
            }
        }

        $page = $page . '<script type="text/plain" id="__eval_scripts__" >' . $this->__scripts . '</script>';

        $this->pagedata['statusId']          = $this->app->getConf('b2c.wss.enable');
        $this->pagedata['session_id']        = kernel::single('base_session')->sess_id();
        $this->pagedata['desktop_path']      = app::get('desktop')->res_url;
        $this->pagedata['shopadmin_dir']     = dirname($_SERVER['PHP_SELF']) . '/';
        $this->pagedata['shop_base']         = $this->app->base_url();
        $this->pagedata['desktopresurl']     = app::get('desktop')->res_url;
        $this->pagedata['desktopresfullurl'] = app::get('desktop')->res_full_url;

        $this->pagedata['_PAGE_'] = &$page;
        $this->display('singlepage.html', 'desktop');
    }

    /**
     * _singlepage_prepare
     * @param mixed $match match
     * @return mixed 返回值
     */
    public function _singlepage_prepare($match)
    {
        if ($match[2] && !strpos($match[1], 'src') && !strpos($match[1], 'hold')) {
            $this->__scripts .= "\n" . $match[2];
            return '';
        } else {
            return $match[0];
        }
    }

    /**
     * _outSplitBegin
     * @param mixed $key key
     * @return mixed 返回值
     */
    public function _outSplitBegin($key)
    {
        return "<!-----$key-----";
    }

    /**
     * _outSplitEnd
     * @param mixed $key key
     * @return mixed 返回值
     */
    public function _outSplitEnd($key)
    {
        return "-----$key----->";
    }

    /**
     * url_frame
     * @param mixed $url url
     * @return mixed 返回值
     */
    public function url_frame($url)
    {
        $this->sidePanel();
        echo '<iframe width="100%" scrolling="auto" allowtransparency="true" frameborder="0" height="100%" src="' . $url . '" ></iframe>';
    }

    /**
     * page
     * @param mixed $view view
     * @param mixed $app_id ID
     * @return mixed 返回值
     */
    public function page($view = '', $app_id = '')
    {
        $this->location_to();
        $_SESSION['message'] = '';
        $_GET['finder_id'] || $_GET['finder_id'] = ($_GET['_finder']['finder_id'] ? : ($_GET['find_id'] ? : substr(md5($_SERVER['QUERY_STRING']),5,6)));
        $service = kernel::service(sprintf('desktop_controller_display.%s.%s.%s', $_GET['app'], $_GET['ctl'], $_GET['act']));
        if ($service) {
            if (method_exists($service, 'get_file')) {
                $view = $service->get_file();
            }

            if (method_exists($service, 'get_app_id')) {
                $app_id = $service->get_app_id();
            }

        }

        if (!$view) {
            $view   = 'common/default.html';
            $app_id = 'desktop';
        }

        ob_start();
        parent::display($view, $app_id);
        $output = ob_get_contents();
        ob_end_clean();

        $output = $this->sidePanel() . $output;

        $this->output($output);
    }

    /**
     * sidePanel
     * @return mixed 返回值
     */
    public function sidePanel()
    {
        $menuObj = app::get('desktop')->model('menus');
        $bcdata  = $menuObj->get_allid($_GET);
        $output  = '';
        if (!$this->workground) {
            $this->workground = get_class($this);
        }
        $output .= "<script>window.BREADCRUMBS ='" . ($bcdata['workground_id'] ? $bcdata['workground_id'] : 0)
            . ":"
            . ($bcdata['menu_id'] ? $bcdata['menu_id'] : 0)
            . "';</script>";

        if ('desktop_ctl_dashboard' == $this->workground) {

            $output .= "<script>fixSideLeft('add');</script>";
            return $output;
        } else {

            $output .= "<script>fixSideLeft('remove');</script>";
        }

        if ($_SERVER['HTTP_WORKGROUND'] == $this->workground) {
            return $output;
        }

        $output .= $this->_outSplitBegin('.side-content');
        $output .= $this->get_sidepanel($menuObj);
        $output .= $this->_outSplitEnd('.side-content');

        $output .= '<script>window.currentWorkground=\'' . $this->workground . '\';</script>';

        return $output;
    }

    /**
     * output
     * @param mixed $output output
     * @return mixed 返回值
     */
    public function output(&$output)
    {
        echo $output;
    } //End Function

    public function splash($status = 'success', $url = null, $msg = null, $method = 'redirect', $params = array())
    {
        header("Cache-Control:no-store, no-cache, must-revalidate"); // HTTP/1.1
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // 强制查询etag
        header('Progma: no-cache');
        $default = array(
            $status => $msg ? $msg : app::get('desktop')->_('操作成功'),
            $method => $url,
        );

        $parse = parse_url($url);
        if ($url && $parse && $parse['scheme'] != 'javascript') {
            if ($parse['query']){
                parse_str($parse['query'], $query);

                $query['act'] = $query['act']??'index';

                // 定位二级菜单
                if (!$query['finder_vid']){
                    $query['finder_vid'] = $_GET['finder_vid'] ?: app::get('desktop')->router()->getFinderVid(http_build_query($query));
                }

                // 定准越权权限
                if ($_GET['finder_id']){
                    $query['finder_id'] = $_GET['finder_id'];
                }

                $parse['query'] = http_build_query($query);
            }

            $default[$method] = $parse['path'].'?'.$parse['query'];
        }

        $arr  = array_merge($default, $params, array('splash' => true));
        $json = json_encode($arr, JSON_UNESCAPED_UNICODE);
        $_GET['bodyBytesSent'] = strlen($json);
        if ($_FILES) {
            header('Content-Type: text/html; charset=utf-8');
        } else {
            header('Content-Type:text/jcmd; charset=utf-8');
        }
        echo $json;
        exit;
    }

    /**
     * jump_to
     * 
     * @param string $act
     * @param string $ctl
     * @param array $args
     * @access public
     * @return void
     */
    public function jumpTo($act = 'index', $ctl = null, $args = null)
    {

        $_GET['act'] = $act;
        if ($ctl) {
            $_GET['ctl'] = $ctl;
        }

        if ($args) {
            $_GET['p'] = $args;
        }

        if (!is_null($ctl)) {

            if ($pos = strpos($_GET['ctl'], '/')) {
                $domain = substr($_GET['ctl'], 0, $pos);
            } else {
                $domain = $_GET['ctl'];
            }
            $ctl           = $this->app->single(str_replace('/', '-', $ctl));
            $ctl->message  = $this->message;
            $ctl->pagedata = &$this->pagedata;
            $ctl->ajaxdata = &$this->ajaxdata;
            call_user_func(array(str_replace('/', '_', $ctl), $act), $args);
        } else {
            call_user_func(array(get_class($this), $act), $args);
        }
    }

    /**
     * has_permission
     * @param mixed $perm_id ID
     * @return mixed 返回值
     */
    public function has_permission($perm_id)
    {
        $user = kernel::single('desktop_user');
        return $user->has_permission($perm_id);
    }

    /**
     * 获取_sidepanel
     * @param mixed $menuObj menuObj
     * @return mixed 返回结果
     */
    public function get_sidepanel($menuObj)
    {
        $obj              = $menuObj;
        $workground_menus = ($obj->menu($_GET, $this->defaultwg));
        if ($workground_menus['nogroup']) {
            $nogroup = $workground_menus['nogroup'];
            unset($workground_menus['nogroup']);

        }
        if (!$workground_menus) {
            $dashboard_menu = new desktop_sidepanel_dashboard(app::get('desktop'));
            return $dashboard_menu->get_output();

        }
        $workground = array();
        $render     = app::get('desktop')->render();
        if ($_GET['app'] && $_GET['ctl']) {
            $workground                     = $obj->get_current_workground($_GET);
            $render->pagedata['workground'] = $workground;
        };
        $data_id = $obj->get_allid($_GET);
        //$render->pagedata['dataid'] = $data_id['workground_id'].":".$data_id['menu_id'];
        $render->pagedata['side']       = "leftpanel";
        $render->pagedata['menus_data'] = $workground_menus;
        $render->pagedata['nogroup']    = $nogroup;
        return $render->fetch('sidepanel.html');

    }
    /**
     * tags
     * @return mixed 返回值
     */
    public function tags()
    {
        $ex_p   = '&wg=' . urlencode($_GET['wg']) . '&type=' . urlencode($_GET['type']);
        $params = array(
            'title'       => app::get('desktop')->_('标签管理'),
            'actions'     => array(
                array('label' => app::get('desktop')->_('新建普通标签'), 'icon' => 'add.gif', 'href' => $this->url . '&act=new_mormal_tag' . $ex_p, 'target' => 'dialog::{title:\'' . app::get('desktop')->_('新建普通标签') . '\'}'),
                // array('label'=>'新建条件标签','href'=>$this->url.'&act=new_filter_tag'.$ex_p,'target'=>'dialog::{title:\'新建条件标签\'}'),
            ),
            'base_filter' => array(
                'tag_type' => $_GET['type'],
            ), 'use_buildin_new_dialog' => false, 'use_buildin_set_tag' => false, 'use_buildin_export' => false);
        $this->finder('desktop_mdl_tag', $params);
    }

    /**
     * new_mormal_tag
     * @return mixed 返回值
     */
    public function new_mormal_tag()
    {
        $ex_p = '&wg=' . urlencode($_GET['wg']) . '&type=' . urlencode($_GET['type']);
        if ($_POST) {
            $this->begin();
            $tagmgr = app::get('desktop')->model('tag');
            $data   = array(
                'tag_name'    => $_POST['tag_name'],
                'tag_abbr'    => $_POST['tag_abbr'],
                'tag_type'    => $_REQUEST['type'],
                'app_id'      => $this->app->app_id,
                'tag_mode'    => 'normal',
                'tag_bgcolor' => $_POST['tag_bgcolor'],
                //'tag_fgcolor'=>$_POST['tag_fgcolor'],
            );
            if ($_POST['tag_id']) {
                $data['tag_id'] = $_POST['tag_id'];
            } //print_r($data);exit;
            $tagmgr->save($data);
            $this->end();
        } else {
            $html = $this->ui()->form_start(array(
                'action' => $this->url . '&act=new_mormal_tag' . $ex_p,
                'id'     => 'form_settag',
                'method' => 'post',
            ));
            $html .= $this->ui()->form_input(array('title' => app::get('desktop')->_('标签名'), 'vtype' => 'required', 'name' => 'tag_name'));
            $html .= $this->ui()->form_input(array('title' => app::get('desktop')->_('标签备注'), 'name' => 'tag_abbr'));
            $html .= $this->ui()->form_input(array('title' => app::get('desktop')->_('标签颜色'), 'type' => 'color', 'name' => 'tag_bgcolor'));
            //$html .= $this->ui()->form_input(array('title'=>app::get('desktop')->_('标签字体景色'),'type'=>'color','name'=>'tag_fgcolor'));
            $html .= $this->ui()->form_end();
            $___infomation = app::get('desktop')->_('如果新建的标签已经存在，则此操作变为编辑原标签');

            echo <<<EOF
<div style="margin: 5px;" class="notice">
$___infomation
</div>
{$html}
<script>

   \$('form_settag').store('target',{


        onComplete:function(){

            if(window.finderGroup['{$_GET['finder_id']}'])
            window.finderGroup['{$_GET['finder_id']}'].refresh();

            $('form_settag').getParent('.dialog').retrieve('instance').close();

        }

   });

</script>
EOF;
        }
    }

    /**
     * tag_edit
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function tag_edit($id)
    {
        $this->url = 'index.php?app=' . $_GET['app'] . '&ctl=' . $_GET['ctl'];
        $render    = app::get('desktop')->render();
        //return $render->fetch('admin/tag/detail.html',$this->app->app_id);
        $mdl_tag = app::get('desktop')->model('tag');
        $tag     = $mdl_tag->dump($id, '*');
        $ui      = new base_component_ui(null, app::get('desktop'));
        $html    = $ui->form_start(array(
            'action' => $this->url . '&act=new_mormal_tag',// . $ex_p,
            'id'     => 'tag_form_add',
            'method' => 'post',
        ));
        $html .= $ui->form_input(array('title' => app::get('desktop')->_('标签名'), 'name' => 'tag_name', 'value' => $tag['tag_name']));
        $html .= $ui->form_input(array('title' => app::get('desktop')->_('标签备注'), 'name' => 'tag_abbr', 'value' => $tag['tag_abbr']));
        $html .= $ui->form_input(array('title' => app::get('desktop')->_('标签颜色'), 'type' => 'color', 'name' => 'tag_bgcolor', 'value' => $tag['tag_bgcolor']));
        //$html .= $ui->form_input(array('title'=>app::get('desktop')->_('标签字体色'),'type'=>'color','name'=>'tag_fgcolor','value'=>$tag['tag_fgcolor']));
        $html .= '<input type="hidden" name="tag_id" value="' . $id . '"/>';
        $html .= '<input type="hidden" name="app_id" value="' . $tag['app_id'] . '"/>';
        $html .= '<input type="hidden" name="type" value="' . $tag['tag_type'] . '"/>';
        $html .= $ui->form_end();
        echo $html;
        echo <<<EOF
<script>
window.addEvent('domready', function(){
    $('tag_form_add').store('target',{
        onComplete:function(){

           if(window.finderGroup['{$_GET['finder_id']}'])
            window.finderGroup['{$_GET['finder_id']}'].refresh();

            if($('tag_form_add').getParent('.dialog'))
            $('tag_form_add').getParent('.dialog').retrieve('instance').close();
        }
    });
});
</script>
EOF;
    }

    /**
     * 将废弃，请使用dialog_batch方法
     * @param  [type] $model      [description]
     * @param  [type] $pageData   [description]
     * @param  array  $baseFilter [description]
     * @return [type]             [description]
     */
    protected function selectToPageRequest($model, $pageData, $baseFilter = array())
    {
        $primaryKey = $model->schema['idColumn'];
        if ($_POST['isSelectedAll'] == '_ALL_') {

            if (empty($baseFilter)) {
                $view = intval($_POST['view']);
                if (method_exists($this, '_views')) {
                    $this->noViewCount = true; #页签 _views 中使用，避免浪费资源
                    $subMenu           = $this->_views();
                    $baseFilter        = $subMenu[$view]['filter'];
                    $this->noViewCount = false;
                }
            }
            $param                  = array_merge($baseFilter, $_POST);
            $model->defaultOrder    = '';
            $model->filter_use_like = true;
            $selData                = $model->getList($primaryKey, $param, 0, -1);
            $arrRequestId           = array();
            foreach ($selData as $val) {
                $arrRequestId[] = $val[$primaryKey];
            }
        } else {
            $arrRequestId = $_POST[$primaryKey];
        }
        if (empty($arrRequestId)) {
            die('缺少选择的数据!');
        }
        $this->pagedata['billName']      = $pageData['billName'];
        $this->pagedata['request_url']   = $pageData['request_url'];
        $this->pagedata['maxProcessNum'] = $pageData['maxProcessNum'] ? $pageData['maxProcessNum'] : 10;
        $this->pagedata['close']         = $pageData['close'] ? true : false;
        $this->pagedata['custom_html']   = $pageData['custom_html'] ? : '';
        $this->pagedata['queueNum']      = $pageData['queueNum'] ? : 1;
        $this->pagedata['count']         = count($arrRequestId);
        $this->pagedata['arrId']         = json_encode($arrRequestId);
        $this->display('admin/request_add.html', 'ome');
        exit();
    }

    /**
     * 批量操作对话框
     * 
     * @return void
     * @author
     */
    public function dialog_batch($full_object_name = '', $allow_selected_all = false, $limit = 100, $offset = 'zero')
    {

        $this->pagedata['ifrm_dom_id'] = uniqid();

        if ($full_object_name) {
            list($app_id, $object_name) = explode('_mdl_', $full_object_name);
            $object                     = app::get($app_id)->model($object_name);
            $object->filter_use_like    = true;

            $count = $object->count($_POST);

            $GroupList = array();
            for ($i = 0; $i < $count; $i += $limit) {
                $_POST['offset'] = $offset == 'zero' ? '0' : $i;
                $_POST['limit']  = $limit;

                $filter = array('f' => $_POST);

                $GroupList[] = http_build_query($filter);
            }

            $this->pagedata['itemCount'] = $count;
            $this->pagedata['GroupList'] = json_encode($GroupList);
            $this->pagedata['maxNum']    = 1;
        }

        $this->display('dialog_batch.html', 'desktop');
    }



    /**
     * 导入页面
     * 
     * @return void
     * @author 
     * */
    public function execlImportDailog($type = '')
    {
        $this->pagedata['type'] = $type;

        $this->display('import/execl.html', 'desktop');
    }

    /**
     * 导入模板
     * 
     * @return void
     * @author 
     * */
    public function execlImportTmpl($type = '')
    {
        try {
            $class_name = $_GET['app'] . '_' . $type . '_import';

            $obj = kernel::single($class_name);

            if (!method_exists($obj, 'getExcelTitle')){
                throw new Exception("Error Processing Request", 1);
            }

            list($filename, $data) = call_user_func_array([$obj, 'getExcelTitle'],[]);
        } catch (Exception $e) {
            $filename = '空.xlsx';
            $data = [];
        }

        kernel::single('omecsv_phpoffice')->export($filename, $data);
    }

    /**
     * 导入执行
     * 
     * @return void
     * @author 
     * */
    public function doExcelImport($type = '')
    {
        set_time_limit(0);
        ini_set('memory_limit', '768M');

        try {
            $class_name = $_GET['app'] . '_' . $type.'_import';

            $obj = kernel::single($class_name);

            if (!method_exists($obj, 'processExcelRow')){
                throw new Exception("Error Processing Request", 1);
            }

            list($rs, $msg) = call_user_func_array([$obj, 'processExcelRow'],[$_FILES['import_file'], $_POST]);

        } catch (Exception $e) {
            $rs = false;
            $msg = $e->getMessage();
        }

        if ($rs) {
            $msg = str_replace(['"','\''], '', $msg);

            echo <<<JS
                <script>
                    parent.$('iMsg').setText('{$msg}');
                    // parent.$('ImportBtn').setStyle('cursor','not-allowed');
                    if (parent.$$('.notice-inline div').length == 1){
                        parent.$('import-form').getParent('.dialog').retrieve('instance').close();
                        parent.finderGroup["{$_GET['finder_id']}"].refresh();
                    }

                </script>

JS;
            flush();
            ob_flush();
            exit;
        }

    }

    public function returnJson(array $data,$status = true,$msg = '',$code = 200)
    {
        $params = [
            'rsp'       => $status ? 'succ' : 'fail',
            'msg'       => $msg ? $msg : $status,
            'data'      => $data,
            'code'      => $code,
            'message'   => $msg ? $msg : $status,
        ];
        $this->splash($status ? 'success' : 'fail', null, $msg, 'redirect', $params);
    }

    /**
     * 批量操作对话框
     * 
     * @return void
     * @author
     */
    public function dialog_promise($totalCount = 0, $pageSize = 20)
    {
        $this->pagedata['totalPages'] = ceil($totalCount / $pageSize);
        $this->pagedata['totalCount'] = $totalCount;
        $this->pagedata['pageSize']   = $pageSize;

        $this->display('dialog_promise.html', 'desktop');
    }

    /**
     * 订单单切片导入页面
     * @date 2024-10-11 4:05 下午
     */
    public function displayImportV2($type='', $extraParams=[])
    {
        $finder_id = $_GET['_finder']['finder_id'];
        $this->pagedata['type']           = $type;
        $this->pagedata['download_url']   = sprintf('index.php?app=%s&ctl=%s&act=exportTemplateV2&finder_id=%s', $_GET['app'], $_GET['ctl'], $finder_id);
        
        // 传递额外参数到模板
        $this->pagedata['extra_hidden_fields'] = $extraParams;
        
        $this->display('admin/order/order_import.html', 'ome');
    }
    
    /**
     * 更新导入页面
     *
     * @return void
     * @author
     **/
    public function importUpdateExcel($type = '')
    {
        $this->pagedata['type'] = $type;
        
        $this->display('import/import_update_excel.html', 'desktop');
    }
    
    /**
     * 导入更新模板
     *
     * @return void
     * @author
     **/
    public function importExcelTmpl($type = '')
    {
        try {
            //导入Lib类名称
            $class_name = $_GET['app'] . '_' . $type . '_import_update';
            
            $obj = kernel::single($class_name);
            
            if (!method_exists($obj, 'getExcelTitle')){
                throw new Exception("Error Processing Request", 1);
            }
            
            list($filename, $data) = call_user_func_array([$obj, 'getExcelTitle'],[]);
        } catch (Exception $e) {
            $filename = '空.xlsx';
            $data = [];
        }
        
        kernel::single('omecsv_phpoffice')->export($filename, $data);
    }
    
    /**
     * 更新导入执行
     *
     * @return void
     * @author
     **/
    public function doImportUpdateExcel($type = '')
    {
        set_time_limit(0);
        ini_set('memory_limit', '768M');
        
        try {
            //导入Lib类名称
            $class_name = $_GET['app'] . '_' . $type.'_import_update';
            
            $obj = kernel::single($class_name);
            
            if (!method_exists($obj, 'processUpdateExcelRow')){
                throw new Exception("Error Processing Request", 1);
            }
            
            list($rs, $msg) = call_user_func_array([$obj, 'processUpdateExcelRow'], [$_FILES['import_file'], $_POST]);
            
        } catch (Exception $e) {
            $rs = false;
            $msg = $e->getMessage();
        }
        
        if ($rs) {
            $msg = str_replace(['"','\''], '', $msg);
            
            echo <<<JS
                <script>
                    parent.$('iMsg').setText('{$msg}');
                    
                    if (parent.$$('.notice-inline div').length == 1){
                        parent.$('import-form').getParent('.dialog').retrieve('instance').close();
                        parent.finderGroup["{$_GET['finder_id']}"].refresh();
                    }
                </script>

JS;
            flush();
            ob_flush();
            exit;
        }
        
    }

}
