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


define('COLUMN_IN_HEAD', 'HEAD');
define('COLUMN_IN_TAIL', 'TAIL');
class desktop_finder_builder_prototype
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $plimit_in_sel = array(500, 200, 100, 50, 20, 10);
    public $has_tag       = 1;
    public $title         = '列表';
    public $object_method = array(
        'count'   => 'count', //获取数量的方法名
        'getlist' => 'getlist', //获取列表的方法名
    );

    public $addon_columns      = array();
    public $extra_columns      = array();
    public $detail_pages       = array();
    public $addon_actions      = array();
    public $finder_aliasname   = 'default';
    public $finder_cols        = '';
    public $alertpage_finder   = false;
    public $use_view_tab       = true;
    public $use_buildin_settab = true;

    private $_tab_view_data = null;

    /**
     * __construct
     * @param mixed $controller controller
     * @return mixed 返回值
     */
    public function __construct($controller)
    {
        $this->controller = $controller;
        //$this->app = &$this->controller->app;
        $this->ui = new base_component_ui($controller, app::get('desktop'));

        if ($_REQUEST['_finder']['finder_id']) {
            $this->name = $_REQUEST['_finder']['finder_id'];
        } else {
            $this->name = substr(md5($_SERVER['QUERY_STRING']), 5, 6);
        }

        $tab_style = app::get('ome')->getConf('desktop.finder.tab');
        if ($this->use_view_tab == false || $tab_style != '1') {
            $this->use_buildin_settab = false;
        }
    }

    /**
     * work
     * @param mixed $full_object_name full_object_name
     * @return mixed 返回值
     */
    public function work($full_object_name)
    {

        $this->url                    = 'index.php?';
        $_GET['ctl']                  = $_GET['ctl'] ? $_GET['ctl'] : 'default';
        $_GET['act']                  = $_GET['act'] ? $_GET['act'] : 'index';
        $_GET['_finder']['finder_id'] = $this->name;
        if ($_GET['action']) {
            unset($_GET['action']);
        }

        $query     = utils::http_build_query($_GET);
        $this->url = $this->url . $query;

        $this->object_name = $full_object_name;

        if ($p = strpos($full_object_name, '_mdl_')) {
            $object_app  = substr($full_object_name, 0, $p);
            $object_name = substr($full_object_name, $p + 5);
        } else {
            trigger_error('finder only accept full model name: ' . $full_object_name, E_USER_ERROR);
        }

        $service_list = array();
        foreach (kernel::servicelist('desktop_finder.' . $this->object_name) as $name => $object) {
            $service_list[$name] = $object;
        }
        foreach (kernel::servicelist('desktop_finder.' . $this->object_name . '.' . $this->finder_aliasname) as $name => $object) {
            $service_list[$name] = $object;
        }

        foreach ($service_list as $name => $object) {
            $tmpobj = $object;
            foreach (get_class_methods($tmpobj) as $method) {
                switch (substr($method, 0, 7)) {
                    case 'column_':
                        $this->addon_columns[] = array(&$tmpobj, $method);
                        break;
                    case 'detail_':
                        if (!$this->alertpage_finder) //如果是弹出页finder，则去详细查看按钮
                        {
                            $this->detail_pages[$method] = array(&$tmpobj, $method);
                        }

                        break;
                }
            }

            $this->service_object[] = &$tmpobj;

            if (method_exists($tmpobj, 'row_style')) {
                $this->row_style_func[] = &$tmpobj;
            }
            unset($tmpobj);
            $i++;
        }

        /**
         * 对额外添加的column和detail的修改
         */
        $obj_addon_cols = kernel::servicelist('desktop_finder_column_modifier.' . $this->object_name . '.' . $this->finder_aliasname);
        if ($obj_addon_cols) {
            foreach ($obj_addon_cols as $obj) {
                $obj->columns_modifier($this->addon_columns);
            }
        }
        $obj_addon_detail_cols = kernel::servicelist('desktop_finder_detail_modifier.' . $this->object_name . '.' . $this->finder_aliasname);
        if ($obj_addon_detail_cols) {
            foreach ($obj_addon_detail_cols as $obj) {
                $obj->detail_columns_modifier($this->detail_pages);
            }
        }
        /** end **/

        $this->object   = app::get($object_app)->model($object_name);
        $this->has_tag  = $this->object->has_tag;
        $this->dbschema = $this->object->schema;

        if (method_exists($this->object, 'extra_cols')) {
            $this->extra_cols = $this->object->extra_cols();
        }

        $this->main();
    }

    /**
     * 获取Columns
     * @return mixed 返回结果
     */
    public function getColumns()
    {
        if (!$this->columns) {
            $cols = $this->app->getConf('view.' . $this->object_name . '.' . $this->finder_aliasname . '.' . $this->controller->user->user_id);
            if ($cols) {
                $this->columns = explode(',', $cols);
            } elseif ($this->finder_cols) {
                $this->columns = explode(',', $this->finder_cols);
            } else {
                $func_columns = $this->func_columns();
                if ($func_columns) {
                    foreach ($func_columns as $key => $func_column) {
                        $col_keys[] = $key;
                    }
                }

                if (is_array($this->extra_cols) && count($this->extra_cols) > 0) {
                    foreach ($this->extra_cols as $key => $extra_cols) {
                        $col_keys[] = $key;
                    }
                }

                $columns  = array_merge((array) $col_keys, (array) $this->dbschema['default_in_list']);
                $all_cols = $this->all_columns();
                foreach ($all_cols as $key => $value) {
                    if (in_array($key, $columns)) {
                        $return[] = $key;
                    }
                }
                $this->columns = $return;
            }
        }
        return $this->columns;
    }

    /**
     * 获取OrderBy
     * @return mixed 返回结果
     */
    public function getOrderBy()
    {
        if (isset($_POST['_finder']['orderBy']) || isset($_GET['_finder']['orderBy'])) {
            $this->orderBy   = $_POST['_finder']['orderBy'] ? $_POST['_finder']['orderBy'] : $_GET['_finder']['orderBy'];
            $this->orderType = $_POST['_finder']['orderType'] ? $_POST['_finder']['orderType'] : $_GET['_finder']['orderType'];

            // 防SQL注入
            if ($this->orderType && !in_array($this->orderType, array('asc', 'desc'))) {
                unset($this->orderType);
            }

            if ($this->orderBy && !isset($this->dbschema['columns'][$this->orderBy])) {
                unset($this->orderBy);
            }

            $this->orderBy = '`'.$this->orderBy.'`';
        } else {
            return $this->dbschema['defaultOrder']; //todo 默认
        }
    }

    //页码处理
    /**
     * 获取PageLimit
     * @return mixed 返回结果
     */
    public function getPageLimit()
    {
        $user_id = $this->controller->user->user_id;
        if (isset($_POST['plimit']) && $_POST['plimit']) {
            if (!isset($this->controller->max_plimit)) {
                $this->controller->max_plimit = 500;
            }
            if ($_POST['plimit'] > $this->controller->max_plimit) {
                $_POST['plimit'] = $this->controller->max_plimit;
            }
            $this->app->setConf('lister.pagelimit.' . $user_id, $_POST['plimit']);
            return $_POST['plimit'];
        } else {
            $plimit = $this->app->getConf('lister.pagelimit.' . $user_id);
            return $plimit ? $plimit : 20;
        }
    }

    public function &all_columns()
    {
        if (!$this->alertpage_finder) {
            $func_columns  = $this->func_columns();
            $extra_columns = array();
            if (is_array($this->extra_cols) && count($this->extra_cols) > 0) {
                $extra_columns = $this->extra_cols;
            }
        }
        $columns = array();
        foreach ((array) $this->dbschema['in_list'] as $key) {
            $columns[$key] = &$this->dbschema['columns'][$key];
        }
        $return = array_merge((array) $func_columns, (array) $extra_columns, (array) $columns);
        foreach ($return as $k => $r) {
            if (!$r['order']) {
                $return[$k]['order'] = 100;

            }
            $orders[] = $return[$k]['order'];
        }
        array_multisort($orders, SORT_ASC, $return);
        return $return;
    }

    public function &func_columns()
    {
        if (!isset($this->func_list)) {
            $default_with    = app::get('desktop')->getConf('finder.thead.default.width');
            $return          = array();
            $this->func_list = &$return;
            //标签列
            if ($this->has_tag) {
                $this->addon_columns[] = array(kernel::single('desktop_finder_tagcols'), 'column_tag');
            }

            foreach ($this->addon_columns as $k => $function) {
                $func['type']  = 'func';
                $func['width'] = $function[0]->{$function[1] . '_width'} ? $function[0]->{$function[1] . '_width'} : $default_with;
                $func['label'] = $function[0]->{$function[1]};
                if ($function[0]->{$function[1] . '_order'} == COLUMN_IN_TAIL) {
                    $func['order'] = 100;
                } elseif ($function[0]->{$function[1] . '_order'} == COLUMN_IN_HEAD) {
                    $func['order'] = 1;
                } else {
                    $func['order'] = $function[0]->{$function[1] . '_order'};
                }

                $func['ref']         = $function;
                $func['sql']         = '1';
                $func['order_field'] = '';
                if ($function[0]->{$function[1] . '_order_field'}) {
                    $func['order_field'] = $function[0]->{$function[1] . '_order_field'};
                }
                $func['alias_name'] = $function[1];
                if ($func['label']) {
                    //只有有名称，才能被显示
                    $return[$function[1]] = $func;
                    //$return[$function[1]] = $func;
                }
            }
        }
        return $this->func_list;
    }

    /**
     * 获取_views
     * @param mixed $source source
     * @return mixed 返回结果
     */
    public function get_views($source = '')
    {

        if (!$this->use_view_tab) {
            return array();
        }

        list($app_id, $model) = explode('_mdl_', $this->object_name);
        if ($app_id != $this->controller->app->app_id) {
            return array();
        }

        if ($this->_tab_view_data === null) {

            if (method_exists($this->controller, '_views')) {
                $views = $this->controller->_views((array) $this->base_filter, $source);
                foreach ((array) $views as $k => $view) {

                    if (!$view){
                        continue;
                    }

                    $view['href'] = $view['href'] ?: kernel::router()->gen_url([
                        'app'     => $_GET['app'],
                        'ctl'     => $_GET['ctl'],
                        'act'     => $_GET['act'],
                        'view'    => $k
                    ]);

                    $finder_vid = $_GET['finder_vid'] ?: app::get('desktop')->router()->getFinderVid($view['href']);

                    $view['href'] .= '&finder_vid='.$finder_vid;

                    if (!isset($view['finder'])) {
                        $views_temp[$k] = $view;
                    } elseif (isset($view['finder'])) {
                        if (is_array($view['finder'])) {
                            if (in_array($this->finder_aliasname, $view['finder'])) {
                                $views_temp[$k] = $view;
                            }
                        } elseif ($this->finder_aliasname == $view['finder']) {
                            $views_temp[$k] = $view;
                        }

                    }
                }
            }

            //自定义筛选器
            $filter  = app::get('desktop')->model('filter');
            $_filter = array(
                'model'   => $this->object_name,
                'app'     => $_GET['app'],
                'ctl'     => $_GET['ctl'],
                'act'     => $_GET['act'],
                'user_id' => $this->controller->user->user_id,
            );
            $rows = $filter->getList('*', $_filter, 0, -1, 'create_time asc');
            if (!$views_temp && $rows[0]) {
                $object     = app::get($app_id)->model($model);
                $views_temp = array(
                    0 => array('label' => app::get('desktop')->_('全部'), 'optional' => false, 'filter' => "", 'addon' => $object->viewcount()),
                );
            }
            $extends = $this->_get_args();

            $o    = app::get('desktop')->router();
            $view = 999000; //2012.1.13 count($views_temp)修正我的待处理订单无法保存过滤条件的bug
            foreach ($rows as $row) {
                $_url_array = array('app' => $_filter['app'], 'act' => $_filter['act'], 'ctl' => $_filter['ctl'], 'view' => $view, 'fltId' => $row['filter_id']);
                $view++;
                if ($extends && is_array($extends)) {
                    foreach ($extends as $_key => $_val) {
                        if (array_key_exists($_key, $_url_array)) {
                            continue;
                        }

                        $_url_array[$_key] = $_val;
                    }
                }

                $url = $o->gen_url($_url_array); unset($_url_array);
                $url .= '&finder_vid='.app::get('desktop')->router()->getFinderVid($url);

                parse_str($row['filter_query'], $filter);
                $views_temp[$view - 1] = array(
                    'label'     => $row['filter_name'],
                    'optional'  => '',
                    'filter'    => $filter,
                    'filter_id' => $row['filter_id'],
                    'addon'     => '_FILTER_POINT_',
                    'custom'    => true,
                    'href'      => $url,
                );
            }
            $this->_tab_view_data = (array) $views_temp;
        }

        return $this->_tab_view_data;
    }

    protected function _get_args()
    {
        foreach ($_GET as $key => $val) {
            if ($key != 'app' && $key != 'act' && $key != 'ctl' && $key != '_finder') {
                $extends[$key] = $val;
            }

            if ($key == '_finder') {
                break;
            }

        }
        return $extends;
    }
}
