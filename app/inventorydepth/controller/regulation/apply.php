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
 * 规则应用类
 *
 * @author chenping
 * @version 2012-6-7 14:22
 */
class inventorydepth_ctl_regulation_apply extends desktop_controller
{
    public $workground = 'resource_center';

    public function __construct($app)
    {
        parent::__construct($app);
        $this->_request = kernel::single('base_component_request');
    }

    /**
     * 生成URL
     *
     * @return void
     * @author
     **/
    private function gen_url($params = array(), $full = false)
    {
        $params['app'] = isset($params['app']) ? $params['app'] : $this->app->app_id;
        $params['ctl'] = isset($params['ctl']) ? $params['ctl'] : 'regulation_apply';
        $params['act'] = isset($params['act']) ? $params['act'] : 'index';

        return kernel::single('desktop_router')->gen_url($params, $full);
    }

    public function index()
    {
        $actions = array(
            'title'               => $this->app->_('规则应用列表'),
            'actions'             => array(
                array(
                    'label'  => $this->app->_('新建上下架应用'),
                    'href'   => $this->gen_url(array('act' => 'add', 'p[0]' => 'frame')),
                    'target' => '_blank',
                ),
                array(
                    'label'  => $this->app->_('新建库存回写应用'),
                    'href'   => $this->gen_url(array('act' => 'add', 'p[0]' => 'stock')),
                    'target' => '_blank',
                ),
                array(
                    'label'   => $this->app->_('启用'),
                    'submit'  => $this->gen_url(array('act' => 'using')),
                    'confirm' => $this->app->_('确定启用选中项？'),
                    'target'  => 'refresh',
                ),
                array(
                    'label'   => $this->app->_('停用'),
                    'submit'  => $this->gen_url(array('act' => 'unusing')),
                    'confirm' => $this->app->_('确定停用选中项？'),
                    'target'  => 'refresh',
                ),

            ),
            'use_buildin_filter'  => true,
            'use_buildin_recycle' => true,
        );
        $this->finder(
            'inventorydepth_mdl_regulation_apply',
            $actions
        );
    }

    public function stockIndex()
    {
        $actions = array(
            'title'               => $this->app->_('回写库存规则应用列表'),
            'actions'             => array(
                array(
                    'label'  => $this->app->_('新建库存回写应用'),
                    'href'   => $this->gen_url(array('act' => 'add', 'p[0]' => 'stock')),
                    'target' => '_blank',
                ),
                array(
                    'label'   => $this->app->_('启用'),
                    'submit'  => $this->gen_url(array('act' => 'using')),
                    'confirm' => $this->app->_('确定启用选中项？'),
                    'target'  => 'refresh',
                ),
                array(
                    'label'   => $this->app->_('停用'),
                    'submit'  => $this->gen_url(array('act' => 'unusing')),
                    'confirm' => $this->app->_('确定停用选中项？'),
                    'target'  => 'refresh',
                ),
                array(
                    'label'  => '库存规则货品导入模板',
                    'href'   => 'index.php?app=inventorydepth&ctl=regulation_apply&act=exportTemplate',
                    'target' => '_blank',
                ),
                array(
                    'label'  => '库存规则SKUID导入模板',
                    'href'   => 'index.php?app=inventorydepth&ctl=regulation_apply&act=exportSkuIdTemplate',
                    'target' => '_blank',
                ),
            ),
            'use_buildin_filter'  => true,
            'use_buildin_recycle' => true,
            'base_filter'         => array('condition' => 'stock', 'type' => '2'),
        );
        $this->finder(
            'inventorydepth_mdl_regulation_apply',
            $actions
        );
    }

    public function frameIndex()
    {
        $actions = array(
            'title'               => $this->app->_('回写上下架规则应用列表'),
            'actions'             => array(
                array(
                    'label'  => $this->app->_('新建上下架应用'),
                    'href'   => $this->gen_url(array('act' => 'add', 'p[0]' => 'frame')),
                    'target' => '_blank',
                ),
                array(
                    'label'   => $this->app->_('启用'),
                    'submit'  => $this->gen_url(array('act' => 'using')),
                    'confirm' => $this->app->_('确定启用选中项？'),
                    'target'  => 'refresh',
                ),
                array(
                    'label'   => $this->app->_('停用'),
                    'submit'  => $this->gen_url(array('act' => 'unusing')),
                    'confirm' => $this->app->_('确定停用选中项？'),
                    'target'  => 'refresh',
                ),
            ),
            'use_buildin_filter'  => true,
            'use_buildin_recycle' => true,
            'base_filter'         => array('condition' => 'frame'),
        );
        $this->finder(
            'inventorydepth_mdl_regulation_apply',
            $actions
        );
    }

    /**
     * 添加规则应用
     *
     * @return void
     * @author
     **/
    public function add($condition = 'stock')
    {
        $this->title = $this->app->_('添加规则应用');

        $this->pagedata['options'] = $this->options();

        # 获取仓库选项 - 只选择线上仓库且具有发货属性且管控库存的仓库
        $branchModel = app::get('ome')->model('branch');
        $branches = $branchModel->getList('branch_id,branch_bn,name', array(
            'disabled' => 'false',
            'attr' => 'true', // 线上仓库
            'is_deliv_branch' => 'true', // 发货仓库
            'is_ctrl_store' => '1', // 管控库存
            'b_type' => 1 // 只显示类型为1的仓库
        ));
        $branchOptions = array();
        foreach ($branches as $branch) {
            $branchOptions[$branch['branch_id']] = $branch['name'] . ' (' . $branch['branch_bn'] . ')';
        }
        $this->pagedata['branchOptions'] = $branchOptions;

        # 在没有应用编号的情况下，临时编号
        $this->pagedata['init_bn'] = uniqid();

        # 获取所有已经联通的店铺
        $filter = array('filter_sql' => '({table}node_id is not null AND {table}node_id!="" )', 's_type' => 1);
        if (app::get('drm')->is_installed()) {
            $channelShopObj = app::get('drm')->model('channel_shop');
            $rows           = $channelShopObj->getList('shop_id');
            foreach ($rows as $val) {
                $shopIds[]               = $val['shop_id'];
                $filter['shop_id|notin'] = $shopIds;
            }
        }
        $data['shops'] = $this->app->model('shop')->getList('shop_id,name', $filter);

        $data['condition'] = $condition;

        $this->pagedata['data']  = $data;
        $this->pagedata['title'] = $this->title;
        
        # 获取regulation选项
        $regulationModel = $this->app->model('regulation');
        $regulationFilter = array(
            'type' => '2',
            'condition' => $condition
        );
        $regulations = $regulationModel->getList('regulation_id,heading,`using`', $regulationFilter, 0, -1, 'FIELD(`using`, "true") DESC, regulation_id DESC');
        $regulationOptions = array();
        foreach ($regulations as $regulation) {
            $status = $regulation['using'] == 'true' ? ' (已启用)' : ' (未启用)';
            $regulationOptions[$regulation['regulation_id']] = $regulation['heading'] . $status;
        }
        $this->pagedata['regulationOptions'] = $regulationOptions;

        # 获取客户分类选项
        $customerClassifyModel = app::get('material')->model('customer_classify');
        $customerClassifies = $customerClassifyModel->getList('class_id,class_name', array(), 0, -1, 'class_id ASC');
        $customerClassifyOptions = array();
        foreach ($customerClassifies as $classify) {
            $customerClassifyOptions[$classify['class_id']] = $classify['class_name'];
        }
        $this->pagedata['customerClassifyOptions'] = $customerClassifyOptions;

        if ($condition == 'stock') {
            $this->singlepage('regulation/stock_apply.html');
        } else {
            $this->singlepage('regulation/frame_apply.html');
        }
        //$this->pagedata['condition'] = $condition;
        //$this->singlepage('regulation/apply.html');
    }

    /**
     * @description 编辑应用
     * @access public
     * @param void
     * @return void
     */
    public function edit($id)
    {
        $this->title = $this->app->_('编辑规则应用');

        $applyModel = $this->app->model('regulation_apply');
        $data       = $applyModel->select()->columns('*')->where('id=?', $id)->instance()->fetch_row();

        # 获取ID范围
        if ($data['apply_goods'] && $data['apply_goods'] != '_ALL_') {
            $data['pgid'] = explode(',', $data['apply_goods']);
        }
        
        $data['shop_id'] = explode(',', $data['shop_id']);
        
        // 处理专用供货仓字段，转换为数组格式供tail组件使用
        if ($data['supply_branch_id']) {
            $data['supply_branch_id'] = explode(',', $data['supply_branch_id']);
            $data['supply_branch_id'] = array_filter(array_map('trim', $data['supply_branch_id']));
        } else {
            $data['supply_branch_id'] = array();
        }

        # 获取所有已经联通的店铺
        $filter = array('filter_sql' => '({table}node_id is not null AND {table}node_id!="" )');
        if (app::get('drm')->is_installed()) {
            $channelShopObj = app::get('drm')->model('channel_shop');
            $rows           = $channelShopObj->getList('shop_id');
            foreach ($rows as $val) {
                $shopIds[]               = $val['shop_id'];
                $filter['shop_id|notin'] = $shopIds;
            }
        }
        $data['shops'] = $this->app->model('shop')->getList('shop_id,name', $filter);

        $this->pagedata['options'] = $this->options();
        
        # 获取仓库选项 - 只选择线上仓库且具有发货属性且管控库存的仓库
        $branchModel = app::get('ome')->model('branch');
        $branches = $branchModel->getList('branch_id,branch_bn,name', array(
            'disabled' => 'false',
            'attr' => 'true', // 线上仓库
            'is_deliv_branch' => 'true', // 发货仓库
            'is_ctrl_store' => '1', // 管控库存
            'b_type' => 1 // 只显示类型为1的仓库
        ));
        $branchOptions = array();
        foreach ($branches as $branch) {
            $branchOptions[$branch['branch_id']] = $branch['name'] . ' (' . $branch['branch_bn'] . ')';
        }
        $this->pagedata['branchOptions'] = $branchOptions;
        
        # 获取regulation选项
        $regulationModel = $this->app->model('regulation');
        $regulationFilter = array(
            'type' => '2',
            'condition' => $data['condition']
        );
        $regulations = $regulationModel->getList('regulation_id,heading,`using`', $regulationFilter, 0, -1, 'FIELD(`using`, "true") DESC, regulation_id DESC');
        $regulationOptions = array();
        foreach ($regulations as $regulation) {
            $status = $regulation['using'] == 'true' ? ' (已启用)' : ' (未启用)';
            $regulationOptions[$regulation['regulation_id']] = $regulation['heading'] . $status;
        }
        $this->pagedata['regulationOptions'] = $regulationOptions;

        # 获取客户分类选项
        $customerClassifyModel = app::get('material')->model('customer_classify');
        $customerClassifies = $customerClassifyModel->getList('class_id,class_name', array(), 0, -1, 'class_id ASC');
        $customerClassifyOptions = array();
        foreach ($customerClassifies as $classify) {
            $customerClassifyOptions[$classify['class_id']] = $classify['class_name'];
        }
        $this->pagedata['customerClassifyOptions'] = $customerClassifyOptions;

        # 处理应用对象类型字段
        if (!isset($data['apply_object_type']) || empty($data['apply_object_type'])) {
            // 根据apply_goods和apply_object_filter判断应用对象类型
            if ($data['apply_object_filter']) {
                $applyObjectFilter = json_decode($data['apply_object_filter'], true);
                if ($applyObjectFilter && isset($applyObjectFilter['customer_classify'])) {
                    $data['apply_object_type'] = 3; // 客户分类商品
                } else {
                    $data['apply_object_type'] = 1; // 全部商品
                }
            } elseif ($data['apply_goods'] && $data['apply_goods'] != '_ALL_') {
                $data['apply_object_type'] = 2; // 指定商品
            } else {
                $data['apply_object_type'] = 1; // 全部商品
            }
        }
        
        # 处理应用对象筛选条件字段
        if ($data['apply_object_filter']) {
            $applyObjectFilter = json_decode($data['apply_object_filter'], true);
            
            if ($applyObjectFilter && isset($applyObjectFilter['customer_classify'])) {
                $data['selected_customer_classify'] = $applyObjectFilter['customer_classify'];
            } else {
                $data['selected_customer_classify'] = array();
            }
        } else {
            $data['selected_customer_classify'] = array();
        }
        
        // 确保selected_customer_classify是一个数组
        if (!is_array($data['selected_customer_classify'])) {
            $data['selected_customer_classify'] = array();
        }

        $this->pagedata['data']              = $data;
        $this->pagedata['init_bn']           = $data['bn'];
        $this->pagedata['apply_goods_query'] = is_array($data['apply_goods']) ? http_build_query($data['apply_goods']) : $data['apply_goods'];
        $this->pagedata['title']             = $this->title;
        //$this->singlepage('regulation/apply.html');

        if ($data['condition'] == 'stock') {
            $this->singlepage('regulation/stock_apply.html');
        } else {
            $this->singlepage('regulation/frame_apply.html');
        }
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    private function options()
    {
        $reguObj          = kernel::single('inventorydepth_regulation');
        $options['style'] = $reguObj->get_style();
        $options['model'] = $reguObj->get_condition_model();
        return $options;
    }

    /**
     * 启用应用
     *
     * @return void
     * @author
     **/
    public function using()
    {
        $bool = $this->app->model('regulation_apply')->update(array('using' => 'true'), $_POST);
        $this->splash($bool ? 'success' : 'error', 'javascript:finderGroup["' . $_GET['finder_id'] . '"].refresh();', $this->app->_('启用成功'));
    }

    /**
     * 停用应用
     *
     * @return void
     * @author
     **/
    public function unusing()
    {
        $bool = $this->app->model('regulation_apply')->update(array('using' => 'false'), $_POST);
        $this->splash($bool ? 'success' : 'error', 'javascript:finderGroup["' . $_GET['finder_id'] . '"].refresh();', $this->app->_('停用成功'));
    }

    /**
     * 前端商品绑定列表
     *
     * @return void
     * @author
     **/
    public function merchandise_finder()
    {
        $params = array(
            'title'               => $this->app->_('店铺商品关系列表'),
            'use_buildin_filter'  => true,
            'use_buildin_recycle' => false,
            'alertpage_finder'    => true,
            'use_view_tab'        => false,
        );

        $condition = $this->_request->get_get('condition');

        $model = ($condition == 'stock') ? 'inventorydepth_mdl_shop_skus' : 'inventorydepth_mdl_shop_items';

        $shop_id = $this->_request->get_get('shop_id');
        if ($shop_id) {
            $params['base_filter']['shop_id'] = $shop_id;
        }

        $this->finder($model, $params);
    }

    public function merchandise_dialog_filter()
    {
        $condition = $this->_request->get_get('condition');
        $init_bn   = $this->_request->get_get('init_bn');
        $model     = kernel::single('inventorydepth_regulation')->get_condition_model($condition);

        $get = $this->_request->get_get();
        if ($init_bn) {
            $g              = kernel::single('inventorydepth_regulation_apply')->fetch_merchandise_filter($init_bn);
            $get            = $g ? $g : $get;
            $get['init_bn'] = $init_bn;
        }

        $this->main($model, $this->app, $get, $this);
    }

    private function main($object_name, $app, $filter = null, $controller = null, $cusrender = null)
    {
        if (strpos($_GET['object'], '@') !== false) {
            $tmp         = explode('@', $object_name);
            $app         = app::get($tmp[1]);
            $object_name = $tmp[0];
        }
        $object = $app->model($object_name);
        $ui     = new base_component_ui($controller, $app);
        require APP_DIR . '/base/datatypes.php';
        $this->dbschema = $object->get_schema();

        foreach (kernel::servicelist('extend_filter_' . get_class($object)) as $extend_filter) {
            $colums = $extend_filter->get_extend_colums();
            if ($colums[$object_name]) {
                $this->dbschema['columns'] = array_merge((array) $this->dbschema['columns'], (array) $colums[$object_name]['columns']);
            }
        }

        foreach ($this->dbschema['columns'] as $c => $v) {
            if (!$v['filtertype']) {
                continue;
            }

            /*
            if( isset($filter[$c]) ) {
            continue;
            }*/

            if (isset($filter['init_bn'])) {
                if ($filter[$c]) {
                    $v['filterdefault'] = true;
                }

                if (!$filter[$c]) {
                    $v['filterdefault'] = false;
                }

            }

            if (!is_array($v['type'])) {
                if (strpos($v['type'], 'decimal') !== false && $v['filtertype'] == 'number') {
                    $v['type'] = 'number';
                }
            }

            $columns[$c] = $v;
            if (!is_array($v['type']) && $v['type'] != 'bool' && isset($datatypes[$v['type']]) && isset($datatypes[$v['type']]['searchparams'])) {
                $addon = '<select search="1" name="_' . $c . '_search" class="x-input-select  inputstyle">';
                foreach ($datatypes[$v['type']]['searchparams'] as $n => $t) {
                    $addon .= "<option value='{$n}'>{$t}</option>";
                }
                $addon .= '</select>';
            } elseif ($v['type'] == 'skunum') {
                $addon    = '<select search="1" name="_' . $c . '_search" class="x-input-select  inputstyle">';
                $__select = array('nequal' => app::get('base')->_('='), 'than' => app::get('base')->_('>'), 'lthan' => app::get('base')->_('<'));
                foreach ($__select as $n => $t) {
                    $addon .= "<option value='{$n}'>{$t}</option>";
                }
                $addon .= '</select>';
            } else {
                if ($v['type'] != 'bool') {
                    $addon = app::get('desktop')->_('是');
                } else {
                    $addon = '';
                }

            }
            $columns[$c]['addon'] = $addon;
            if ($v['type'] == 'last_modify') {
                $v['type'] = 'time';
            }
            $params = array(
                'type' => $v['type'],
                'name' => $c,
            );

            if ($filter[$c]) {
                $params['value'] = $filter[$c];
            }
            if ($v['type'] == 'bool' && $v['default']) {
                $params = array_merge(array('value' => $v['default']), $params);
            }
            if ($this->name_prefix) {
                $params['name'] = $this->name_prefix . '[' . $params['name'] . ']';
            }
            if ($v['type'] == 'region') {
                $params['app'] = 'eccommon';
            }

            $inputer                = $ui->input($params);
            $columns[$c]['inputer'] = $inputer;
        }

        if ($cusrender) {
            return array('filter_cols' => $columns, 'filter_datatypes' => $datatypes);
        }

        if ($object->has_tag) {
            $this->pagedata['app_id']   = $app->app_id;
            $this->pagedata['tag_type'] = $object_name;
            $tag_inputer                = $this->fetch('finder/tag_inputer.html');
            $columns['tag']             = array('filtertype' => true, 'filterdefault' => true, 'label' => app::get('desktop')->_('标签'), 'inputer' => $tag_inputer);
        }

        $this->pagedata['columns']   = $columns;
        $this->pagedata['datatypes'] = $datatypes;
        $this->pagedata['finder_id'] = uniqid();

        $this->display('finder/finder_filter.html');
    }

    /**
     * undocumented function
     *
     * @return void
     * @author
     **/
    public function _views()
    {
        $view = array(
            0 => array('label' => $this->app->_('全部'), 'href' => ''),
        );

        return $view;
    }

    /**
     * 前端商品条件集
     *
     * @return void
     * @author
     **/
    public function merchandise_filter()
    {
        $condition = $this->_request->get_post('condition');
        if (!$condition) {
            $this->splash('error', '', $this->app->_('规则类型错误!'));
        }

        $shop_id            = $this->_request->get_post('shop_id');
        $init_bn            = $this->_request->get_post('init_bn');
        $id['id']           = $this->_request->get_post('id');
        $merchandise_filter = $this->_request->get_post('filter');

        if ($shop_id) {
            $shop_id                       = http_build_query(array('shop_id' => $shop_id));
            $shop_id                       = str_replace('&', ',', $shop_id);
            $merchandise_filter['advance'] = $merchandise_filter['advance'] ? $merchandise_filter['advance'] . ',' . $shop_id : $shop_id;
        }

        $msg = kernel::single('inventorydepth_regulation_apply')->choice_callback($condition, $init_bn, $id, $merchandise_filter);

        $this->splash('success', '', $msg);
    }

    public function merchandise_filter_array()
    {
        $condition = $this->_request->get_post('condition');
        $init_bn   = $this->_request->get_post('init_bn');

        if (!$init_bn) {
            $this->splash('error', '', $this->app->_('规则类型错误!'));
        }
        $merchandise_filter = $this->_request->get_post('filter');

        $msg = kernel::single('inventorydepth_regulation_apply')->choice_callback_array($condition, $init_bn, $merchandise_filter);
        $this->splash('success', '', $msg);
    }

    /**
     * 已选择的商品和货品
     *
     * @return void
     * @author
     **/
    public function finder_choice()
    {
        $init_bn   = $this->_request->get_get('init_bn');
        $condition = $this->_request->get_get('condition');
        if (!$init_bn) {
            echo "参数错误";exit;
        }
        $filter = array(
            'init_bn'   => $init_bn,
            'condition' => $condition,
        );
        $tt     = ($condition == 'stock') ? '货品' : '商品';
        $title  = $this->app->_("已选择的{$tt}列表");
        $params = array(
            'title'               => $tt,
            'use_buildin_filter'  => true,
            'use_buildin_recycle' => false,
            'base_filter'         => $filter,
            'use_buildin_setcol'  => false,
        );

        $model = ($condition == 'stock') ? 'inventorydepth_mdl_regulation_productselect' : 'inventorydepth_mdl_regulation_goodselect';

        $this->finder($model, $params);

    }

    /**
     * 删除已选择的商品/货品
     *
     * @param Int $merchandise_id 映射关系ID
     * @param String $init_bn 应用编号
     * @parma String $condition 规则类型
     *
     * @return void
     * @author
     **/
    public function removeFilter($id, $init_bn, $condition)
    {
        $this->begin();
        if (!$id || !$init_bn || !$condition) {
            $this->end(false, $this->app->_('错误参数'));
        }

        $model  = ($condition == 'stock') ? 'regulation_productselect' : 'regulation_goodselect';
        $result = $this->app->model($model)->doRemove($init_bn, $id);
        $this->end($result);
    }

    /**
     * 保存规则应用
     *
     * @return void
     * @author
     **/
    public function save()
    {
        $this->begin();
        $post = $this->_request->get_post();
        $data = $this->check_params($post, $msg);
        if ($data === false) {
            $this->end(false, $msg);
        }

        $applyModel = $this->app->model('regulation_apply');

        $result = $applyModel->save($data);

        $url = $this->gen_url(array('act' => 'index'));
        $msg = $result ? $this->app->_('保存成功') : $this->app->_('保存失败');
        $this->end($result, $msg);
    }

    /**
     * @description 检查提交参数是否合法
     * @access public
     * @param void
     * @return void
     */
    public function check_params($post, &$msg)
    {
        if (empty($post['bn'])) {
            $msg = $this->app->_('应用规则不能为空!');
            return false;
        }

        $applyModel = $this->app->model('regulation_apply');
        $count      = $applyModel->count(array('bn' => $post['bn'], 'condition' => $post['condition']));
        if ((int) $count > 0 && empty($post['id'])) {
            $msg = $this->app->_('应用规则已经存在!');
            return false;
        }

        if (empty($post['heading'])) {
            $msg = $this->app->_('应用名称不能为空!');
            return false;
        }

        if (!kernel::single('inventorydepth_regulation')->get_condition($post['condition'])) {
            $msg = $this->app->_('规则类型不存在!');
            return false;
        }

        if (!kernel::single('inventorydepth_regulation')->get_style($post['style'])) {
            $msg = $this->app->_('触发类型不存在!');
            return false;
        }
        
        if (!$post['shop_id']) {
            $msg = $this->app->_('店铺不能为空!');
            return false;
        }
        $post['shop_id'] = implode(',', $post['shop_id']);
        
        // 根据应用对象类型处理应用对象
        if (!isset($post['apply_object_type']) || !in_array($post['apply_object_type'], array(1, 2, 3))) {
            $post['apply_object_type'] = 1; // 默认全部商品
        }
        
        if ($post['apply_object_type'] == 1) {
            // 全部商品
            $post['apply_goods'] = '_ALL_';
        } elseif ($post['apply_object_type'] == 2) {
            // 指定商品
        if ($post['condition'] == 'stock') {
                if ((!is_array($post['sm_id']) || empty($post['sm_id'])) && (!is_array($post['pkg_id']) || empty($post['pkg_id'])) && (!is_array($post['pko_id']) || empty($post['pko_id']))) {
                $msg = $this->app->_('应用对象不能空!');
                return false;
            }
            
            if ($post['sm_id'] && is_array($post['sm_id'])) {
                $post['apply_goods'] = implode(',', $post['sm_id']);
            } else {
                $post['apply_goods'] = '';
            }
        } elseif ($post['condition'] == 'frame') {
                if (!is_array($post['goods_id']) || empty($post['goods_id'])) {
                    $msg = $this->app->_('应用对象不能空!');
                    return false;
                }
                $post['apply_goods'] = implode(',', $post['goods_id']);
        } else {
            $msg = $this->app->_('应用对象不能空!');
            return false;
            }
        } elseif ($post['apply_object_type'] == 3) {
            // 客户分类商品，应用对象为空，通过筛选条件控制
            $post['apply_goods'] = '_ALL_';
        }

        // 平台SKU ID
        if ($post['shop_sku_id']) {
            $post['shop_sku_id'] = trim($post['shop_sku_id']);
            $post['shop_sku_id'] = str_replace('，', ',', $post['shop_sku_id']);
        } else {
            $post['shop_sku_id'] = '';
        }

        // 专用供货仓
        if ($post['supply_branch_id'] && is_array($post['supply_branch_id'])) {
            $branch_bns = array_filter($post['supply_branch_id']);
            if (!empty($branch_bns)) {
                $post['supply_branch_id'] = implode(',', $branch_bns);
            } else {
                $post['supply_branch_id'] = '';
            }
        } else {
            $post['supply_branch_id'] = '';
        }

        // 应用对象类型
        if (!isset($post['apply_object_type']) || !in_array($post['apply_object_type'], array(1, 2, 3))) {
            $post['apply_object_type'] = 1; // 默认全部商品
        }

        // 应用对象筛选条件
        if ($post['apply_object_type'] == 3 && isset($post['apply_object_filter'])) {
            // 客户分类商品，处理客户分类筛选条件
            if (is_array($post['apply_object_filter'])) {
                $customerClassifyIds = array_filter($post['apply_object_filter']);
            } else {
                // 处理从JavaScript传来的逗号分隔的字符串
                $customerClassifyIds = array_filter(explode(',', $post['apply_object_filter']));
            }
            
            // 去除重复值并重新索引数组
            $customerClassifyIds = array_values(array_unique($customerClassifyIds));
            
            if (!empty($customerClassifyIds)) {
                $post['apply_object_filter'] = json_encode(array('customer_classify' => $customerClassifyIds));
            } else {
                $post['apply_object_filter'] = '';
            }
        } else {
            $post['apply_object_filter'] = '';
        }

        // 处理回写方式字段
        $post['sync_mode'] = 'total';
        
        // 回写区域仓
        if (isset($post['is_sync_subwarehouse']) && $post['is_sync_subwarehouse'] == '1') {
            $post['is_sync_subwarehouse'] = '1';
        } else {
            $post['is_sync_subwarehouse'] = '0';
        }
        
        // 回写门店
        if (isset($post['is_sync_store']) && $post['is_sync_store'] == '1') {
            $post['is_sync_store'] = '1';
        } else {
            $post['is_sync_store'] = '0';
        }
        


        if (empty($post['regulation_id'])) {
            $msg = $this->app->_('规则不能为空!');
            return false;
        }

        $regulation = $this->app->model('regulation')->select()->columns('`condition`,`type`')
            ->where('regulation_id=?', $post['regulation_id'])->instance()->fetch_row();
        if ($regulation['condition'] != $post['condition']) {
            $msg = $this->app->_('请选择符合类型的规则!');
            return false;
        }
        $post['type'] = $regulation['type'];

        $start_time = strtotime($post['start_time'] . ' ' . $post['_DTIME_']['H']['start_time'] . ':' . $post['_DTIME_']['M']['start_time']);
        $end_time   = strtotime($post['end_time'] . ' ' . $post['_DTIME_']['H']['end_time'] . ':' . $post['_DTIME_']['M']['end_time']);
        if ($end_time < time()) {
            $msg = $this->app->_('当前时间大于结束时间!');
            return false;
        }
        if ($end_time && $start_time > $end_time) {
            $msg = $this->app->_('开始时间大于结束时间');
            return false;
        }

        $post['start_time']  = $start_time;
        $post['end_time']    = $end_time;
        $post['operator']    = $this->user->user_id;
        $post['operator_ip'] = $this->_request->get_remote_ip();
        $post['using']       = 'false';
        $post['al_exec']     = 'false';
        return $post;
    }

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

                    $page = str_replace($one, '&nbsp', $page);

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



    public function finder_common()
    {
        $params = array(
            'title'                  => app::get('desktop')->_('列表'),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_buildin_setcol'     => true,
            'use_buildin_refresh'    => true,
            'finder_aliasname'       => 'finder_common',
            'alertpage_finder'       => true,
            'use_buildin_tagedit'    => false,
        );
        if ($_GET['findercount']) {
            $params['object_method']['count'] = $_GET['findercount'];
        }
        if ($_GET['findergetlist']) {
            $params['object_method']['getlist'] = $_GET['findergetlist'];
        }
        if (substr($_GET['name'], 0, 7) == 'adjunct') {
            $params['orderBy'] = 'goods_id desc';
        }

        $this->finder($_GET['app_id'] . '_mdl_' . $_GET['object'], $params);
    }

    /**
     * @description AJax加载选择销售物料模板
     * @param $id    int
     * @param $shop_ids    String
     * @return void
     */
    /**
     * @description 显示已选择的销售物料
     * @return void
     */
    public function showProducts()
    {
        $salesMaterialObj = app::get('material')->model('sales_material');

        $sm_id = kernel::single('base_component_request')->get_post('sm_id');

        if ($sm_id) {
            //前端店铺_规则应用传值
            if (!is_array($sm_id)) {
                $sm_id = explode(',', $sm_id);
            }

            $this->pagedata['_input'] = array(
                'name'     => 'sm_id',
                'idcol'    => 'sm_id',
                '_textcol' => 'sales_material_name',
            );

            $list = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn', array('sm_id' => $sm_id, 'sales_material_type|noequal' => 3), 0, -1, 'sm_id asc');

            $this->pagedata['_input']['items'] = $list;
        }

        $this->display('regulation/show_products.html');
    }

    public function ajax_sales_material_html($id = '', $shop_ids = '')
    {
        #规则应用详细信息
        if ($id) {
            $applyModel = $this->app->model('regulation_apply');
            $data       = $applyModel->select()->columns('*')->where('id=?', $id)->instance()->fetch_row();

            # 获取ID范围
            if ($data['apply_goods'] && $data['apply_goods'] != '_ALL_') {
                $data['pgid'] = explode(',', $data['apply_goods']);

                // 这里需要展示一下replacehtml
                $count                         = count($data['pgid']);
                $this->pagedata['replacehtml'] = <<<EOF
    <div id='hand-selected-product'>已选择了{$count}销售物料,<a href='javascript:void(0);' onclick='product_selected_show();'>查看选中销售物料.</a></div>
    EOF;

            }
        }

        #店铺
        $shop_id = array();
        if ($shop_ids && $shop_ids != '_ALL_') {
            $shop_id_list = explode(',', $shop_ids);
            foreach ($shop_id_list as $key => $val) {
                if (empty($shop_id_list)) {
                    unset($shop_id_list[$key]);
                }
            }

            $shop_id = $this->app->model('shop')->getList('shop_id', array('shop_id' => $shop_id_list));
            $shop_id = array_map('current', $shop_id);
        }

        $this->pagedata['data']       = $data;
        $this->pagedata['in_shop_id'] = ($shop_id ? implode(',', $shop_id) . ',_ALL_' : '');
        $this->display('regulation/select_sales_material.html');
    }

    public function object_rows()
    {
        @ini_set('memory_limit','128M');
        if ($_POST['data']) {
            if ($_POST['app_id']) {
                $app = app::get($_POST['app_id']);
            } else {
                $app = $this->app;
            }

            $obj        = $app->model($_POST['object']);
            $schema     = $obj->get_schema();
            $textColumn = $_POST['textcol'] ? $_POST['textcol'] : $schema['textColumn'];
            $textColumn = explode(',', $textColumn);
            $_textcol   = $textColumn;
            $textColumn = $textColumn[0];

            $keycol = $_POST['key'] ? $_POST['key'] : $schema['idColumn'];

            $is_filter_advance = false;

            //统一做掉了。
            if ($_POST['data'][0] === '_ALL_') {
                $is_filter_advance = true;

                if (isset($_POST['filter']['advance']) && $_POST['filter']['advance']) {
                    $arr_filters = explode(',', $_POST['filter']['advance']);
                    foreach ($arr_filters as $obj_filter) {
                        $arr                      = explode('=', $obj_filter);
                        $_POST['filter'][$arr[0]] = $arr[1];
                    }
                    unset($_POST['filter']['advance']);
                }

                $all_filter    = !empty($obj->__all_filter) ? $obj->__all_filter : array();
                $filter        = !empty($_POST['filter']) ? $_POST['filter'] : $all_filter;
                $arr_list      = $obj->getList($keycol, $filter);
                $_POST['data'] = array_map('current', $arr_list);
            }

            $items = $obj->getList('*', array($keycol => $_POST['data']));
            $name  = $items[0][$textColumn];
            if ($_POST['type'] == 'radio') {
                if (strpos($textColumn, '@') !== false) {
                    list($field, $table, $app_) = explode('@', $textColumn);
                    if ($app_) {
                        $app = app::get($app_);
                    }
                    $mdl    = $app->model($table);
                    $schema = $mdl->get_schema();
                    $row    = $mdl->getList('*', array($schema['idColumn'] => $items[0][$keycol]));
                    $name   = $row[0][$field];

                }
                echo json_encode(array('id' => $items[0][$keycol], 'name' => $name));
                exit;
            }

            $this->pagedata['_input'] = array('items' => $items,
                'idcol'                                   => $schema['idColumn'],
                'keycol'                                  => $keycol,
                'textcol'                                 => $textColumn,
                '_textcol'                                => $_textcol,
                'name'                                    => $_POST['name'],
                'value'                                   => implode(',',array_column($items, $keycol)),
                'domid'                                  => $_POST['domid'], 
                'is_filter_advance'                      => $is_filter_advance,
            );
            $this->pagedata['_input']['view_app'] = 'desktop';
            $this->pagedata['_input']['view']     = $_POST['view'];
            if ($_POST['view_app']) {
                $this->pagedata['_input']['view_app'] = $_POST['view_app'];
            }

            if (strpos($_POST['view'], ':') !== false) {
                list($view_app, $view)                = explode(':', $_POST['view']);
                $this->pagedata['_input']['view_app'] = $view_app;
                $this->pagedata['_input']['view']     = $view;

            }

            $this->display('finder/input-row.html');
        }
    }

    public function import_goods($id)
    {
        $this->pagedata['id'] = $id;
        $this->display('regulation/import_goods.html');
    }

    public function exportTemplate()
    {
        $oObj  = $this->app->model('regulation_apply');
        $row = $oObj->exportTemplate('csv');
        $data[0] = array('sales001', '商品001', '普通');
        $data[1] = array('sales002', '组合002', '组合');
        $data[2] = array('sales003', '多选一003', '多选一');
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($data, '库存规则货品导入模板'. date('Ymd'), 'xls', $row);
    }

    public function exportSkuIdTemplate()
    {
        $oObj  = $this->app->model('regulation_apply');
        $row = $oObj->exportTemplate('shop_sku_id');
        $data[0] = array('10031110597837');
        $data[1] = array('5257145962954');
        $data[2] = array('5765642571319');
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($data, '库存规则SKUID导入模板'. date('Ymd'), 'xls', $row);
    }
    
    /**
     * 导出库存回写规则绑定的商品
     * @param $id
     * @date 2024-11-28 2:24 下午
     */
    public function export_goods($id)
    {
        ini_set('memory_limit', '1024M');
        
        $salesMLib = kernel::single('material_sales_material');
        $csvObj                  = kernel::single('desktop_io_type_csv');
        $applyObj                = $this->app->model('regulation_apply');
        $apply_detail            = $applyObj->dump(array('id' => $id), '*');
        $apply_detail['heading'] = str_replace('%', '', $apply_detail['heading']);
        $data['name']            = $apply_detail['heading'] . '货品列表';
        
        // 设置正确的 Content-Type（包含 charset）
        header("Content-Type: text/csv; charset=UTF-8");
        
        // 设置文件下载响应头
        $filename = $data['name'] . ".csv";
        $encoded_filename = urlencode($filename);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);
        $ua = $_SERVER["HTTP_USER_AGENT"];
        if (preg_match("/MSIE/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if (preg_match("/Firefox\/8/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else if (preg_match("/Firefox/", $ua)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        
        // 添加 UTF-8 BOM 标记，解决 Office 打开 CSV 乱码问题
        echo "\xEF\xBB\xBF";
        
        $title              = ['*:销售物料编码', '*:销售物料名称', '*:销售物料类型'];
        $salesMaterialCsv[] = '"' . implode('","', $title) . '"';
        
        //获取销售物料类型
        $typeList = $salesMLib->getSalesMaterialTypes();
        
        //apply_goods
        if ($apply_detail['apply_goods'] && $apply_detail['apply_goods'] != '_ALL_') {
            $goods_ids  = explode(',', $apply_detail['apply_goods']);
            $page       = ceil(count($goods_ids) / 10);
            $page_array = array_chunk($goods_ids, 10, true);
            for ($i = 0; $i < $page; $i++) {
                $apply_goods = $applyObj->db->select("SELECT sm_id,sales_material_name,sales_material_bn,sales_material_type FROM sdb_material_sales_material WHERE sm_id in (" . implode(',', $page_array[$i]) . ")");
                foreach ($apply_goods as $goods)
                {
                    $sales_material_type = $goods['sales_material_type'];
                    
                    $newSalesMaterial['sales_material_bn']   = $goods['sales_material_bn'] ? $goods['sales_material_bn'] : '-';
                    $newSalesMaterial['sales_material_name'] = $goods['sales_material_name'] ? $goods['sales_material_name'] : '-';
                    
                    //sales_material_type
                    $newSalesMaterial['sales_material_type'] = $typeList[$sales_material_type];
                    
                    $salesMaterialCsv[]                      = '"' . implode('","', (array)$newSalesMaterial) . '"';
                }
            }
        }
        
        echo implode("\n", $salesMaterialCsv);
    }

    public function export_skuid($id)
    {
        ini_set('memory_limit', '1024M');
        $applyObj                = $this->app->model('regulation_apply');
        $apply_detail            = $applyObj->dump(array('id' => $id), 'shop_sku_id,heading');
        $apply_detail['heading'] = str_replace('%', '', $apply_detail['heading']);
        
        $skuIdCsv = [];
        if ($apply_detail['shop_sku_id']) {
            $skuIdList = explode(',', $apply_detail['shop_sku_id']);
            if ($skuIdList) {
                foreach ($skuIdList as $skuId) {
                    $skuIdCsv[] = ["\t".trim($skuId)];
                }
            }
        }
        $row = $applyObj->exportTemplate('shop_sku_id');
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($skuIdCsv, $apply_detail['heading'] . 'SKUID列表', 'xls', $row);
    }
}
