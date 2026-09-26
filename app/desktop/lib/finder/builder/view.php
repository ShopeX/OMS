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


class desktop_finder_builder_view extends desktop_finder_builder_prototype {

    public $use_buildin_new_dialog = false;
    public $use_buildin_set_tag = false;
    public $use_buildin_recycle = true;
    public $use_buildin_export = false;
    public $use_buildin_import = false;
    public $use_buildin_importxls = false;
    public $use_buildin_filter = false;
    public $use_buildin_setcol = true;
    public $use_buildin_refresh = true;
    public $title_help_message = false;
    public $use_buildin_selectrow = true;
    public $use_buildin_tagedit = true;
    public $allow_detail_popup = false;
    public $use_buildin_customcols = false;

    public $use_save_filter = true; //是否显示保存搜索结果
    public $max_actions = 7;
    public $filter = array();
    public $delete_confirm_tip = '';
    public $base_query_string = '';
    public $use_unify_order_center = false; //是否显示指定视图栏目
    public $unify_order_center_views = array();
    public $unify_order_center_view = '';

    /**
     * @var 全局变量,控制视图
     */
    private $__view = array();

    function main() {
        $this->html_script = '';
        $this->html_header = '';
        $this->html_body = '';
        $this->html_footer = '';
        $this->html_pager = '';
        $this->html_actions = '';

        $this->short_object_name = substr($this->object_name, strpos($this->object_name, '_mdl_') + 5);
        $this->__view = $this->get_views();
        
        $view_filter = array();
        if (count($this->__view) && $this->use_view_tab) {
            $this->tab_view_count = 0;
            foreach ((array) $this->__view as $view) {
                if (is_numeric($view['addon']))
                    $this->tab_view_count += $view['addon'];
            }
            $view_filter = (array) $this->__view[$_GET['view']]['filter'];
        }
        $this->__view_filter = $view_filter;

        if ($_GET['filter']) {
            $get_filter = (array) $_GET['filter'];
            if (!is_array($_GET['filter'])) {
                if (isset($_GET['filter']) && $_GET['filter'] = (array) unserialize(urldecode($_GET['filter']))) {
                    $get_filter = (array) $_GET['filter'];
                }
            }
        }
        #if( $_POST ) $view_filter = array();
        $this->params = array_merge(
                (array) $view_filter, (array) $_POST, (array) $get_filter, (array) $this->base_filter);

        unset($this->params['_finder']);

        foreach ($this->params as $k => $v) {
            if (!is_array($v) && $v !== false)
                $this->params[$k] = trim($v);
            if ($this->params[$k] === '') {
                unset($this->params[$k]);
            }
        }

        $this->getColumns();
        $this->getOrderBy();

        $this->pagelimit = $this->getPageLimit();

        $this->var_name = 'window.finderGroup[\'' . $this->name . '\']';

        if ($this->detail_pages) {
            $this->detail_url = $this->url . '&action=detail&finder_id=' . $this->name;
        }


        $render = $this->render = new base_render(app::get('desktop'));

        $render->pagedata['title'] = $this->title;
        $render->pagedata['name'] = $this->name;
        $render->pagedata['var_name'] = $this->var_name;
        $render->pagedata['url'] = $this->url;
        $render->pagedata['use_save_filter'] = $this->use_save_filter;
        
        //是否显示指定视图栏目
        $render->pagedata['use_unify_order_center'] = (bool) $this->use_unify_order_center;
        $render->pagedata['unify_order_center_views'] = (array) $this->unify_order_center_views;
        $render->pagedata['unify_order_center_view'] = (string) $this->unify_order_center_view;
        
        if ($this->top_extra_view) {
            $render->pagedata['top_extra'] = "";
            foreach ($this->top_extra_view as $app => $view) {
                $_render = new base_render(app::get($app));
                
                $_render->pagedata = $render->pagedata;
                $render->pagedata['top_extra'].= mb_substr($view,-5,5) == '.html' ? $_render->fetch($view) : $view;
            }
        }

        $this->createView();
        $this->_pager();

        $output = '';
        if (!$_POST['_finder']['in_pager']) {

            $output.= $this->controller->sidePanel();

            $this->_script();
            $output.= $this->html_script;

            $this->_actions();


            $this->_header($this->html_subheader);

            $this->_footer($this->html_pager);

            $output.= '<!-----.mainHead-----' .
                    $render->fetch('finder/view/finder_title.html') .
                    $this->html_actions .
                    $this->html_header .
                    '-----.mainHead----->' .
                    $this->html_body .
                    '<!-----.mainFoot-----' .
                    $this->html_footer .
                    '-----.mainFoot----->';
        } else {

            $output.= '<!-----.pager-----' . $this->html_pager . '-----.pager----->' .
                    $this->html_body .
                    '<!-----.innerheader-----' . $this->html_subheader . '-----.innerheader----->';
        }

        echo $output;
        return $output;
    }

    function _script() {

        //$data = $this->get_views();
        $finderOptions = array(
            'selectName' => $this->dbschema['idColumn'] . '[]',
            'object_name' => $this->object_name,
            'finder_aliasname' => $this->finder_aliasname,
            //'packet' => $this->__view && $this->use_view_tab,
            'packet' => false,
        );
        //if($finderOptions['packet']) $finderOptions['packet'] = (count($this->__view)>0)?true:false;
        /** 判断是否要显示归类视图 * */
//        $is_display_packet = 'false';
//        if ($finderOptions['packet']) {
//            foreach ($this->__view as $arr) {
//                if ($arr['addon']) {
//                    $is_display_packet = 'true';
//                    break;
//                }
//                else
//                    $is_display_packet = 'false';
//            }
//        }
//        if ($is_display_packet == 'true')
//            $finderOptions['packet'] = true;
//        else
//            $finderOptions['packet'] = false;
        /** end * */
        if ($this->options) {
            $finderOptions = array_merge($finderOptions, $this->options);
        }

        //$arrow_down = $this->ui->img('bundle/arrow-down.gif',array('style'=>'margin-left:8px;'));

        $finderOptions = json_encode($finderOptions);

        $this->html_script .=<<<EOF
<script>
Ex_Loader('finder',function(){
 finderDestory();
 var finderOption={$finderOptions};
 {$this->var_name} = new Finder("{$this->name}",finderOption);
EOF;
        $this->html_script.='});</script>';
    }

    function _header($subheader = '') {


        $render = $this->render;

        $render->pagedata['inputhtml'] = $this->toinput($this->params);

        $render->pagedata['subheader'] = $subheader;

        $query = $_GET;
        unset($query['page']);
        $query = utils::http_build_query($query);
        $render->pagedata['query'] = $query;


        $this->html_header = $render->fetch('finder/view/header.html');
    }

    function _subheader() {

    }

    function _body() {

    }

    function _footer($pager) {

        $render = $this->render;
        $render->pagedata['pager'] = $pager;
        $this->html_footer = $render->fetch('finder/view/footer.html');
    }

    function _pager() {

        $pre_btn_addon = $this->pager_info['current'] > 1 ? '' : 'disabled="disabled"';
        $next_btn_addon = $this->pager_info['current'] < $this->pager_info['total'] ? '' : 'disabled="disabled"';

        $nextpage = $this->pager_info['current'] + 1;
        $prevpage = $this->pager_info['current'] - 1;


        $from = $this->pagelimit * ($this->pager_info['current'] - 1) + 1;
        $to = $from + $this->pager_info['list'] - 1;



        $pager = $this->ui->pager(array(
            'current' => $this->pager_info['current'],
            'total' => $this->pager_info['total'],
            'link' => 'javascript:' . $this->var_name . '.page(%d);'
                ));

        $plimit_sel = '';

        foreach ($this->plimit_in_sel as $sel) {
            $checkcode = $this->pagelimit == $sel ? ' checked="checked" ' : '';
            $___t = app::get('desktop')->_('条');
            $plimit_sel .= <<<EOF
            <div class="item" onclick="{$this->var_name}.request({data:'plimit={$sel}'})">
            <input type="radio" name="finder_plimit" $checkcode id=""/>
            <label>{$sel}$___t</label>
            </div>
EOF;
        }

        $render = $this->render;
        $render->pagedata['plimit'] = $this->pagelimit;
        $render->pagedata['plimit_sel'] = $plimit_sel;


        $render->pagedata['from'] = $from;
        $render->pagedata['to'] = $to;

        $render->pagedata['pre_btn_addon'] = $pre_btn_addon;
        $render->pagedata['next_btn_addon'] = $next_btn_addon;
        $render->pagedata['pager'] = $pager;

        $this->html_pager = $render->fetch('finder/view/pager.html');
    }

    function _actions() {
        $finder_name = $this->name;
        $actions = $this->actions;

        if ($this->use_buildin_new_dialog) {
            $actions[] = array('label' => app::get('desktop')->_('新建'), 'icon' => 'add.gif', 'href' => $this->url . '&action=new_item', 'target' => 'dialog::{width:400,title:\'' . app::get('desktop')->_('新建') . '\'}');
        }

        if ($this->use_buildin_set_tag) {
            $_tagaction = array(
                'label' => app::get('b2c')->_('标签'),
                'icon' => 'label.gif',
                'group' => array(
                    array('label' => app::get('desktop')->_('为选中项打标签'), 'submit' => $this->url . '&action=tag', 'target' => 'dialog::{width:400,title:\'' . app::get('desktop')->_('设置标签') . '\'}')
                )
            );


            if ($this->has_tag == true && $this->use_buildin_tagedit) {
                $_tagediturl = '';
                if (app::get('tags')->is_actived()) {
                    $_tagediturl = 'index.php?app=desktop&ctl=default&act=alertpages&nobuttion=1&goto='
                            . urlencode('index.php?app=tags&ctl=admin_tags&act=index&nobuttion=1&type=' . $this->short_object_name . '&app_id=' . $this->app->app_id);
                } else {
                    $_tagediturl = 'index.php?app=desktop&ctl=default&act=alertpages&nobuttion=1&goto=' . urlencode('index.php?app=' . $this->app->app_id . '&ctl=' . $_GET['ctl'] . '&act=tags&nobuttion=1&type=' . $this->short_object_name);
                }
                array_push($_tagaction['group'], array('label' => '_SPLIT_'), array('label' => app::get('desktop')->_('标签设置'), 'href' => $_tagediturl, 'target' => '_blank'));
            }

            $actions[] = $_tagaction;
        }

        if ($this->use_buildin_recycle) {
            $actions[] = array('label' => app::get('desktop')->_('删除'), 'icon' => 'del.gif', 'confirm' => $this->delete_confirm_tip ? $this->delete_confirm_tip : app::get('desktop')->_('确定删除选中项？删除后可进入回收站恢复'), 'submit' => $this->url . '&action=dorecycle');
        }

        if ($this->use_buildin_export) {
            if(isset($this->object->has_export_cnf) && $this->object->has_export_cnf){
                $actions[] = array('label' => app::get('desktop')->_('导出'), 'icon' => 'download.gif', 'submit' => $this->url . '&action=export&finder_aliasname='.$this->finder_aliasname, 'target' => 'dialog::{width:600,height:300,title:\'' . app::get('desktop')->_('导出') . '\'}');
            }else{
                $actions[] = array('label' => app::get('desktop')->_('导出'), 'icon' => 'download.gif', 'submit' => $this->url . '&action=export&finder_aliasname='.$this->finder_aliasname, 'target' => 'dialog::{width:600,height:300,title:\'' . app::get('desktop')->_('导出') . '\'}');
            }
        }

        if ($this->use_buildin_import) {
            $getUrl = '';
            $get = $_GET;
            unset($get['app'],$get['ctl'],$get['act'],$get['_finder']);
            if ($get) {
                $getUrl .= '&'. http_build_query($get);
            }

            $url = 'index.php?app=omecsv&ctl=admin_import&act=main&ctler='.get_class($this->object).'&add='.$this->app->app_id.$getUrl;
            $actions[] = array('label' => app::get('desktop')->_('导入'), 'icon' => 'upload.gif', 'href' => $url, 'target' => 'dialog::{width:500,height:250,title:\'' . app::get('desktop')->_('导入') . '\'}');
        }



        foreach ((array) $this->service_object as $object) {

            $actions = array_merge((array) $actions, (array) $object->actions);
        }
        foreach (kernel::servicelist('finder_actions.' . $this->object_name) as $key => $service_object) {
            if (method_exists($service_object, 'action_modify')) {
                $service_object->action_modify($actions);
            }
        }
        $max_action = $this->max_actions;
        $i = 0;

        if (isset($actions) && $actions) {
            foreach ($actions as $key => $item) {

                //  if(!$item['label']){continue;}

                if ($item['href']) {
                    $item['href'] = $item['href'] . '&_finder[finder_id]=' . $finder_name . '&finder_id=' . $finder_name.'&finder_vid='.$_GET['finder_vid'];
                } else {
                    $item['href'] = "javascript:void(0);";
                }
                if ($item['submit']) {
                    $item['submit'] = $item['submit'] . '&finder_id=' . $finder_name;
                }
                
                if(is_array($item['group']) && count($item['group'])) {
                    foreach($item['group'] as $gKey => $gItem) {
                        if ($item['group'][$gKey]['href']) {
                            $item['group'][$gKey]['href'] = $item['group'][$gKey]['href'] . '&_finder[finder_id]=' . $finder_name . '&finder_id=' . $finder_name;
                        } else {
                            $item['group'][$gKey]['href'] = "javascript:void(0);";
                        }
                        if ($item['group'][$gKey]['submit']) {
                            $item['group'][$gKey]['submit'] = $item['group'][$gKey]['submit'] . '&finder_id=' . $finder_name;
                        }
                    }
                }

                $show_actions[] = $item;
                unset($actions[$key]);
                if ($i++ == $max_action - 1) {
                    break;
                }
            }
            $other_actions = $actions;
        }

        $render = $this->render;

        $render->pagedata['data'] = $this->__view;
        $render->pagedata['show_actions'] = $show_actions;
        $render->pagedata['other_actions'] = $other_actions;
        $render->pagedata['finder_aliasname'] = $this->finder_aliasname;
        $render->pagedata['finder_name'] = $finder_name;
        $render->pagedata['use_buildin_filter'] = $this->use_buildin_filter;
        $render->pagedata['use_buildin_setcol'] = $this->use_buildin_setcol;
        $render->pagedata['use_buildin_refresh'] = $this->use_buildin_refresh;
        $render->pagedata['title_help_message'] = $this->title_help_message;
        $render->pagedata['use_buildin_settab'] = $this->use_buildin_settab;
        $render->pagedata['use_buildin_customcols'] = $this->use_buildin_customcols;

        // 超管
        $render->pagedata['is_super'] = kernel::single('desktop_user')->is_super();

        //$use_view_tab_data = $this->get_views();
        $render->pagedata['haspacket'] = $this->__view && $this->use_view_tab;
        //if($render->pagedata['haspacket']) $render->pagedata['haspacket'] = (count($this->get_views())>0)?true:false;
        /** 判断是否要显示归类视图 * */
        $is_display_packet = 'false';
        foreach ($this->__view as $arr) {
            if ($arr['addon']) {
                $is_display_packet = 'true';
                break;
            } else {
                $is_display_packet = 'false';
            }
        }
        $render->pagedata['haspacket'] = ($is_display_packet == 'true') ? true : false;

        if (method_exists($this->object, 'searchOptions'))
            $searchOptions = $this->object->searchOptions();


        if (is_array($searchOptions) && $this->__view_filter) {
            foreach ($searchOptions as $key => $val) {
                if (isset($this->__view_filter[$key])) {
                    unset($searchOptions[$key]);
                }
            }
        }
        $render->pagedata['searchOptions'] = $searchOptions;
        $render->pagedata['__search_options_default_label'] = current($searchOptions);

        $this->html_actions = $render->fetch('finder/view/actions.html');
    }

    function toinput($params) {
        $html = null;
        $this->_toinput($params['from'], $ret, $params['name']);
        foreach ((array) $ret as $k => $v) {
            $html.='<input type="hidden" name="' . $k . '" value="' . $v . "\" />\n";
        }
        return $html;
    }

    function _toinput($data, &$ret, $path=null) {
        foreach ((array) $data as $k => $v) {
            $d = $path ? $path . '[' . $k . ']' : $k;
            if (is_array($v)) {
                $this->_toinput($v, $ret, $d);
            } else {
                $ret[$d] = $v;
            }
        }
    }

    function createView() {

        $page = $_GET['page'] ? $_GET['page'] : 1;

        $allCols = $this->all_columns();

        $modifiers = array();

        //屏蔽获取自定义宽度
        //$col_width_set = app::get('desktop')->getConf('colwith.' . $this->object_name . '_' . $this->finder_aliasname . '.' . $this->controller->user->user_id);
        $filter_builder = new desktop_finder_builder_filter_render();
        //  $fb_return_data = $filter_builder->main($this->object->table_name(),$this->app,$filter,$this->controller,true);

        $type_modifier = array();
        $key_modifier = array();
        $object_modifier = array();
        $modifier_object = new modifiers;

        $tmparr_columns = array();
        foreach ($this->columns as $col) {
            if (isset($allCols[$col])) {
                $colArray[$col] = &$allCols[$col];
                if (method_exists($this->object, 'modifier_' . $col)) {
                    $key_modifier[$col] = 'modifier_' . $col;
                } elseif (is_string($colArray[$col]['type'])) {
                    if (substr($colArray[$col]['type'], 0, 6) == 'table:') {
                        $object_modifier[$colArray[$col]['type']] = array();
                    } elseif (method_exists($modifier_object, $colArray[$col]['type'])) {
                        $type_modifier[$colArray[$col]['type']] = array();
                    }
                }
                if (isset($col_width_set[$col]))
                    $colArray[$col]['width'] = $col_width_set[$col];

                if (isset($allCols[$col]['sql'])) {
                    $sql[] = $allCols[$col]['sql'] . ' as ' . $col;
                } elseif ($col == '_tag_') {
                    $sql[] = $dbschema['idColumn'] . ' as _tag_';
                } elseif (isset($this->extra_cols[$col])){
                    $sql[] = '1 as '.$col;
                } else {
                    $sql[] = '`' . $col . '`';
                }

                $label = app::get('desktop')->_($colArray[$col]['label']);

                $tmp_col_width = ($colArray[$col]['width'] ? $colArray[$col]['width'] : 150);

                if ($this->orderBy == $col) {
                    $col_set_class = 'class="orderCell"';
                    if ($this->orderType == 'desc') {
                        $col_class = 'highlight-down borderdown orderable';
                    } else {
                        $col_class = 'highlight-up borderdown orderable';
                    }
                } elseif (isset($allCols[$col]['orderby']) && $allCols[$col]['orderby'] == false) {
                    $col_set_class = $col_class = '';
                } elseif (strpos($col, 'column_') === false) {
                    $col_set_class = 'class="orderCell"';
                    $col_class = 'orderable';
                } elseif (strpos($col, 'column_') !== false && $allCols[$col]['order_field']) {
                    $col_set_class = 'class="orderCell"';
                    if ($this->orderBy == $allCols[$col]['order_field']) {
                        if ($this->orderType == 'desc') {
                            $col_class = 'highlight-down borderdown orderable';
                        } else {
                            $col_class = 'highlight-up borderdown orderable';
                        }
                    } else {
                        $col_class = 'orderable';
                    }
                    $col = $allCols[$col]['order_field'];
                } else {
                    $col_set_class = $col_class = '';
                }
                $___dragwidth = app::get('desktop')->_('拖动改变列宽');
                $___width = app::get('desktop')->_('列宽');

                $column_col_html.="<col style=\"width:" . $tmp_col_width . "px\" {$col_set_class}></col>\n";

                $column_td_html.=<<<EOF
<td>
  <div class="cell {$col_class}" key="{$col}" order="{$this->orderType}">
    <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td>
    <div class='finder-col-label'>{$label}</div>
EOF;
                if ($desc = $colArray[$col]['desc']) {
                    $column_td_html.="<div class='finder-col-desc desc-tip' onmouseover='bindFinderColTip(event);'><textarea>" . $desc . "</textarea>i</div>";
                }
                $column_td_html.=<<<EOF
    </td>
    <td width='8px'>
    <div class="finder-col-resizer" title=$___dragwidth onclick='new Event(event).stopPropagation()'>$___width</div>
    </td>
    </tr>
    </table>
  </div>
</td>
EOF;

                if ($fcol = $fb_return_data['filter_cols'][$col]) {
                    $___limitless = app::get('desktop')->_('不限');
                    $filter_column_html.=<<<EOF
        <td>
           <div class="cell">
             <div class="finder-filter-comb" dropMenu="x-dropMenu-{$col}"><button class="btn arrow"></button><span>$___limitless</span></div>
             <div class="x-drop-menu" id="x-dropMenu-{$col}">
             <textarea>{$fcol['addon']}{$fcol['inputer']}<tscript></tscript>
              <hr/>
              <div>$___limitless</div>
             </textarea></div>

           </div>
        </td>
EOF;
                } else {
                    $filter_column_html.=<<<EOF
        <td>
           <div class="cell">
            &nbsp;
           </div>
        </td>
EOF;
                }
            }
        }

        foreach ((array) $this->service_object as $k => $object) {
            if ($object->addon_cols) {
                $object->col_prefix = '_' . $k . '_';
                foreach (explode(',', $object->addon_cols) as $col) {
                    $sql[] = $col . ' as ' . $object->col_prefix . $col;
                }
            }
        }
        $sql = (array) $sql;
        if (!isset($colArray[$this->dbschema['idColumn']]))
            array_unshift($sql, $this->dbschema['idColumn']);
        if ($this->params === -1) {
            $list = array();
        } else {
            $this->object->filter_use_like = true;
            $count_method = $this->object_method['count'];
            $item_count = $this->object->$count_method($this->params);
            $total_pages = ceil($item_count / $this->pagelimit);
            if ($page < 0 || ($page > 1 && $page > $total_pages)) {
                $page = 1;
            }
            $getlist_method = $this->object_method['getlist'];
            $order = $this->orderBy ? $this->orderBy . ' ' . $this->orderType : '';

            $list = $this->object->$getlist_method(implode(',', $sql), $this->params, ($page - 1) * $this->pagelimit, $this->pagelimit, $order);

            if(is_array($this->extra_cols) && count($this->extra_cols)>0){
                foreach($this->extra_cols as $ek => $extra_col){
                    $extra_col_method = '';
                    if(method_exists($this->object,'extra_'.$extra_col['func_suffix'])){
                        $extra_col_method = 'extra_'.$extra_col['func_suffix'];
                        $list = $this->object->$extra_col_method($list);
                    }
                }
            }

            $body = $this->item_list_body($page, $list, $colArray, $key_modifier, $object_modifier, $type_modifier);
            $count = is_array($list) ? count($list) : 0;
            $total_pages = ceil($item_count / max($count, $this->pagelimit));

            $this->pager_info = array(
                'current' => $page,
                'list' => $count,
                'count' => $item_count,
                'total' => $total_pages ? $total_pages : 1,
            );
            $this->object->filter_use_like = false;
        }




        if ($this->detail_url) {
            $detail_td_html = '<td class="col-opt"><div class="cell">' . app::get('desktop')->_('查看') . '</div></td>';
            $detail_col_html = '<col class="col-opt"></col>';

            $filter_td_html = '<td class="col-filter" colspan="2"><div class="cell">' . app::get('desktop')->_('筛选条件') .'> :</div></td>';
            $filter_col_html = '<col class="col-filter"></col>';
        } else {
            $detail_td_html = $detail_col_html = '';
            $filter_td_html = $filter_col_html = '';
        }

        $render = $this->render;





        $render->pagedata['detail_col_html'] = $detail_col_html;
        $render->pagedata['column_col_html'] = $column_col_html;

        $render->pagedata['detail_td_html'] = $detail_td_html;
        $render->pagedata['column_td_html'] = $column_td_html;
        $render->pagedata['pinfo'] = $this->pager_info;
        $render->pagedata['body'] = $body;

        $render->pagedata['filter_td_html'] = $filter_td_html;
        $render->pagedata['filter_column_html'] = $filter_column_html;
        $render->pagedata['filterhandle'] = $render->fetch('finder/view/filterhandle.html');
        $render->pagedata['use_buildin_selectrow'] = $this->use_buildin_selectrow;


        $this->html_subheader = $render->fetch('finder/view/subheader.html');
        $this->html_body = $render->fetch('finder/view/body.html');
        $this->html_pager = $render->fetch('finder/view/pager.html');
    }

    function &item_list_body(&$page, &$list, &$colArray, &$key_modifier, &$object_modifier, &$type_modifier, $ident='col') {
        $body = array();

        $favstar_rows = app::get('desktop')->getConf('favstar.' . $this->object_name . '_' . $this->finder_aliasname . '.' . $this->controller->user->user_id);
        $icon_drop_arrow = $this->ui->img(array('src' => 'bundle/finder_drop_arrow.gif', 'alt' => app::get('desktop')->_('展开'), 'title' => app::get('desktop')->_('展开')));
        $icon_new_window = $this->ui->img(array('src' => 'bundle/new_window.gif', 'alt' => app::get('desktop')->_('新窗'), 'title' => app::get('desktop')->_('新窗')));
        $icon_fav_start = $this->ui->img('bundle/fav_start.png');
        if (!$list) {
            return '';
        }
        if (is_array($this->detail_pages)) {
            $default_detail = '&finderview=' . key($this->detail_pages);
        }
        foreach ($list as $i => $row) {
            $row_style = array();

            if ($this->row_style_func) {
                foreach ($this->row_style_func as $object) {
                    $row_style[] = $object->row_style($row,$list);
                }
            }

            $zebra_class = $i % 2 ? 'even' : 'odd';

            if ($i == 0) {
                $zebra_class.=' first';
            }
            if ($i == (count($list) - 1)) {
                $zebra_class.=' last';
            }

            $id = htmlspecialchars($row[$this->dbschema['idColumn']]);
            $body[] = '<tr class="row ' . $zebra_class . ' ' . implode(';', $row_style) . '" item-id="' . $id . '">';
            $tag = $this->has_tag ? (' tags="' . htmlspecialchars($row['_tags']) . '"') : '';

            $singleselect = $_GET['singleselect'];

            if ($this->use_buildin_selectrow) {
                if ($singleselect) {
                    $singleselect = 'radio';
                } else {
                    $singleselect = 'checkbox';
                }
                $star_class = '';
                if (intval($favstar_rows['id-' . $id]) == 1) {
                    $star_class = 'fav-star-on';
                }
                $body[] = '<td>
                        <div class="clearfix">
                            <div class="span-auto">
                                <input type="' . $singleselect . '"' . $tag . ' class="sel" name="items[]" rowindex="' . (($this->pagelimit * ($page - 1)) + $i) . '" value="' . $id . '">
                            </div>
                            <div class="flt">
                                <i class="fav-star ' . $star_class . '">' . $icon_fav_start . '</i>
                            </div>
                        </div></td>';
            }

            if ($this->detail_url) {
                if ($this->base_query_string) {
                    $this->base_query_string = '&' . $this->base_query_string;
                }
                if ($this->allow_detail_popup) {
                    $detail_popup_btn = "<a title='" . app::get('desktop')->_('在新窗口查看') . "' href='{$this->detail_url}&id={$id}&singlepage=true{$default_detail}{$this->base_query_string}' target='_blank'>
                {$icon_new_window}</a>";
                } else {
                    $detail_popup_btn = "";
                }
                $___look = app::get('desktop')->_('展开查看');

                $body[] = <<<EOF
    <td class="finder-list-command">
    <span title=$___look class="btn-detail-open" detail="{$this->detail_url}&id={$id}{$default_detail}{$this->base_query_string}" >
    {$icon_drop_arrow}
    </span>
    {$detail_popup_btn}
</td>
EOF;
            }
            //$funcs = $this->func_columns();
            foreach ((array) $colArray as $k => $col) {
                $body[] = '<td key="' . $k . '" ' . ($col['editable'] ? 'class="editable"' : '') . '><div class="cell">';

                if ($col['type'] == 'func') {
                    $row['idColumn'] = $this->dbschema['idColumn'];
                    $row['app_id'] = $row['app_id'] ? $row['app_id'] : $this->app->app_id;
                    $row['tag_type'] = $row['tag_type'] ? $row['tag_type'] : $this->short_object_name;
                    $body[] = $a = $col['ref'][0]->{$col['ref'][1]}($row,$list);
                } elseif (isset($key_modifier[$k])) {
                    $this->object->pkvalue = $row[$this->dbschema['idColumn']];
                    $body[] = $this->object->{$key_modifier[$k]}($row[$k],$list,$row);
                } elseif (is_array($col['type']) && !is_null($row[$k])) {
                    $body[] = &$col['type'][$row[$k]];
                } elseif (!is_array($col['type']) && isset($object_modifier[$col['type']])) {
                    $object_modifier[$col['type']][$row[$k]] = $row[$k];
                    $body[] = &$object_modifier[$col['type']][$row[$k]];
                } elseif (!is_array($col['type']) && isset($type_modifier[$col['type']])) {
                    if(is_float($row[$k])){
                        $number = md5($row[$k]);
                    }else{
                        $number = $row[$k];
                    }
                    $type_modifier[$col['type']][$number] = $row[$k];
                    $body[] = &$type_modifier[$col['type']][$number];
                } else {
                    $body[] = $row[$k];
                }
                $body[] = '</div></td>';
            }

            $body[] = '<td>&nbsp;</td></tr>';
        }

        /* 下面的代码仅仅为了补全记录未满设定的每页显示数量
          $list_count = count($list);
          $cols_count = 1;
          if($this->detail_url){
          $cols_count++;
          }
          $cols_count += count($colArray);
          if($list_count<$this->pagelimit){
          for($i=0;$i<($this->pagelimit)-count($list_count);$i++){
          $zebra_class = $zebra_class=='even' ? 'odd' : 'even';
          $body[] = '<tr class="row '.$zebra_class.'" >';
          for($k=0; $k<$cols_count; $k++){
          $body[] = '<td><div class="cell">-</div></td>';
          }
          $body[] = '<td>&nbsp;</td></tr>';

          }
          }
          结束 */

        if ($type_modifier) {
            $type_modifier_object = new modifiers;
            foreach ($type_modifier as $type => $val) {
                if ($val) {
                    $type_modifier_object->$type($val);
                }
            }
        }

        foreach ($object_modifier as $target => $val) {
            if ($val) {
                list(, $obj_name, $fkey) = explode(':', $target);
                if ($p = strpos($obj_name, '@')) {
                    $app_id = substr($obj_name, $p + 1);
                    $obj_name = substr($obj_name, 0, $p);
                    $o = app::get($app_id)->model($obj_name);
                } else {
                    $o = $this->object->app->model($obj_name);
                }
                if (!$fkey)
                    $fkey = $o->textColumn;
                $rows = $o->getList($o->idColumn . ',' . $fkey, array($o->idColumn => $val));
                foreach ($rows as $r) {
                    $object_modifier[$target][$r[$o->idColumn]] = $r[$fkey];
                }
                $app_id = null;
            }
        }

        $body = implode('', $body);
        return $body;
    }

    /**
     * 获取_views
     * @param mixed $source source
     * @return mixed 返回结果
     */
    public function get_views($source = '')
    {
        $this->_tab_view_data = parent::get_views($source);

        // TAB 过滤
        $tab_style = app::get('ome')->getConf('desktop.finder.tab');
        if ($tab_style == '1') {
            $tabview = $this->app->getConf('tabview.'.$_GET['app'].$_GET['ctl'].$_GET['act'].$this->object_name.'.'.$this->finder_aliasname.'.'.$this->controller->user->user_id);
            if (!is_null($tabview)) {        
                $tabview = explode(',', $tabview);
                foreach ((array) $this->_tab_view_data as $key => $value) {
                    if (!in_array($key,$tabview) || $tabview == array('')) {
                        unset($this->_tab_view_data[$key]);
                    }
                }
            }
        }

        return $this->_tab_view_data;
    }

}
