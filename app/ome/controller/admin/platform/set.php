<?php

/**
 * 平台相关设置控制器
 * @author yaokangming
 */
class ome_ctl_admin_platform_set extends desktop_controller {

    var $name = "平台相关设置";
    var $workground = "channel_center";

    /**
     * 列表页
     */
    public function index() {
        $actions = array();
        $actions[] = array(
            'label'  => '新增',
            'href'   => $this->url . '&act=add',
            'target' => 'dialog::{width:770,height:450,title:\'新增\'}',
        );
        $params = array(
            'title' => '平台相关设置',
            'use_buildin_set_tag' => false,
            'use_buildin_filter' => false,
            'use_buildin_export' => false,
            'use_buildin_recycle' => true,
            'actions' => $actions,
            'orderBy' => 'id desc',
        );
        $this->finder('ome_mdl_platform_set', $params);
    }

    public function add() {
        $shopTypeList = ome_shop_type::get_shop_type();
        $shopTypeList['taobao'] = '淘宝/天猫';
        unset($shopTypeList['tmall']);
        $this->pagedata['optShopType'] = $shopTypeList;
        $this->display('admin/platform/set/add.html');
    }

    public function save() {
        $data = $_POST;
        // 排除已经新建的（同平台编码+场景+配置键名不能重复）
        $mdl = app::get('ome')->model('platform_set');
        $filter = array(
            'shop_type' => $data['shop_type'],
            'scene' => $data['scene'],
            'kname' => $data['kname'],
        );
        // 编辑时排除自身
        if (!empty($data['id'])) {
            $filter['id|notin'] = array($data['id']);
        }
        $exists = $mdl->count($filter);
        if ($exists > 0) {
            $this->splash('fail', null, '该平台编码、场景和配置键名的组合已存在，不能重复新建');
        }
        if ($mdl->save($data)) {
            $this->splash('success', $this->url, '新增成功');
        } else {
            $this->splash('fail', null, '新增失败');
        }
    }

    public function edit($id) {
        $mdl = app::get('ome')->model('platform_set');
        $row = $mdl->db_dump(array('id'=>$id));
        if (!$row) {
            $this->splash('fail', null, '数据不存在');
        }
        $shopTypeList = ome_shop_type::get_shop_type();
        $shopTypeList['taobao'] = '淘宝/天猫';
        unset($shopTypeList['tmall']);
        $this->pagedata['optShopType'] = $shopTypeList;
        $this->pagedata['data'] = $row;
        $this->display('admin/platform/set/add.html');
    }

    /**
     * 根据平台获取场景列表（异步请求）
     */
    public function getScenes() {
        $shop_type = $_GET['shop_type'];
        
        // 从Model根据平台获取场景
        $scenes = ome_mdl_platform_set::get_scene_map($shop_type);
        
        $this->pagedata['scenes'] = $scenes;
        $this->display('admin/platform/set/scenes.html');
    }

    /**
     * 根据平台和场景获取需要显示的HTML（异步请求）
     */
    public function getSceneHtml() {
        $shop_type = $_GET['shop_type'];
        $scene = $_GET['scene'];
        $kname = isset($_GET['kname']) ? $_GET['kname'] : '';
        $kvalue = isset($_GET['kvalue']) ? $_GET['kvalue'] : '';
        
        $this->pagedata['shop_type'] = $shop_type;
        $this->pagedata['scene'] = $scene;
        $this->pagedata['kname'] = $kname;
        $this->pagedata['kvalue'] = $kvalue;
        
        // 根据场景名称动态加载模板文件：admin/platform/set/$scene.html
        $template = 'admin/platform/set/' . $scene . '.html';
        
        // 检查模板文件是否存在，不存在则提示错误
        $templatePath = APP_DIR . '/ome/view/' . $template;
        if (!file_exists($templatePath)) {
            echo '<tr><td colspan="2" style="color:red;text-align:center;">模板文件不存在：' . $template . '</td></tr>';
            return;
        }
        
        $this->display($template);
    }

}

