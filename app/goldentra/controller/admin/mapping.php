<?php

class goldentra_ctl_admin_mapping extends desktop_controller
{
    public $workground = 'goldentra_center';

    public function index()
    {
        $params = array(
            'title' => '定价/钻石物料Mapping',
            'actions' => array(
                array(
                    'label' => '新建定价Mapping',
                    'href' => 'index.php?app=goldentra&ctl=admin_mapping&act=add',
                    'target' => '_blank',
                ),
                array(
                    'label' => '导入定价Mapping模版',
                    'href' => 'index.php?app=goldentra&ctl=admin_mapping&act=exportTemplateOfDj',
                    'target' => 'download',
                ),
                array(
                    'label' => app::get('desktop')->_('导入定价Mapping'),
                    'icon' => 'upload.gif',
                    'href' => 'index.php?app=goldentra&ctl=admin_mapping&act=index&action=import',
                    'target' => 'dialog::{width:400,height:150,title:\'' . app::get('desktop')->_('导入定价Mapping') . '\'}',
                ),
                array(
                    'label' => '新建钻石Mapping',
                    'href' => 'index.php?app=goldentra&ctl=admin_mapping&act=addDiamond',
                    'target' => '_blank',
                ),
                array(
                    'label' => '导入钻石Mapping',
                    'href' => 'index.php?app=goldentra&ctl=admin_import&act=progress_import&import_app=goldentra&import_ctl=admin_mapping&import_act=importDiamondMapping&import_tmpl_name=导入钻石Mapping&import_tmpl_act=exportTemplateOfDiamond',
                    'target' => 'dialog::{width:600,height:300,title:\'导入钻石Mapping\'}',
                ),
            ),
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'use_buildin_import' => false,
        );
        $this->finder('goldentra_mdl_mapping', $params);
    }

    public function add()
    {
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->singlepage('admin/mapping/add_items.html');
    }

    public function addDiamond()
    {
        $diamond = kernel::single('goldentra_mapping_diamond');
        $this->pagedata['color_rows'] = json_encode($diamond->getColorRows());
        $this->pagedata['clarity_rows'] = json_encode($diamond->getClarityRows());
        $this->pagedata['diamond_cut_rows'] = json_encode($diamond->getDiamondCutRows());
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->singlepage('admin/mapping/diamond/add_items.html');
    }

    public function save()
    {
        $this->begin('index.php?app=goldentra&ctl=admin_mapping&act=index');
        $data = $_POST;
        $msg = '';
        if (!kernel::single('goldentra_mapping_validate')->check($data, $msg)) {
            $this->end(false, $msg);
        }

        $mappingMdl = app::get('goldentra')->model('mapping');
        $filter = array('disabled' => '0', 'mold_code' => $data['mold_code']);
        if ($mappingMdl->getList('*', $filter, 0, 1)) {
            $this->end(false, '模号已存在');
        }

        $now = time();
        $main = array_merge($filter, array(
            'type' => 'gold',
            'at_time' => $now,
            'up_time' => $now,
        ));
        $mappingMdl->insert($main);
        $data['mapping_id'] = $main['mapping_id'];

        $items = array();
        foreach ($data['min_weight'] as $k => $v) {
            $item = array(
                'mapping_id' => $data['mapping_id'],
                'mold_code' => $data['mold_code'],
                'min_weight' => $v,
                'max_weight' => $data['max_weight'][$k],
                'weight' => $data['weight'][$k],
                'min_main' => $data['min_main'][$k],
                'max_main' => $data['max_main'][$k],
                'min_extra' => $data['min_extra'][$k],
                'max_extra' => $data['max_extra'][$k],
                'extra_weight' => $data['extra_weight'][$k],
                'min_tag_price' => $data['min_tag_price'][$k],
                'max_tag_price' => $data['max_tag_price'][$k],
                'tag_price' => $data['tag_price'][$k],
                'main_weight' => $data['main_weight'][$k],
                'at_time' => $now,
                'up_time' => $now,
            );
            $item['bn'] = goldentra_mapping_code::genGoldMaterialBn($item);
            $items[] = $item;
        }

        $itemsObj = app::get('goldentra')->model('mapping_item');
        kernel::database()->exec(ome_func::get_insert_sql($itemsObj, $items));
        $this->end(true, '操作成功');
    }

    public function saveDiamondMapping()
    {
        $this->begin('index.php?app=goldentra&ctl=admin_mapping&act=index');
        $post_data = $_POST;
        $filter = array('disabled' => '0', 'mold_code' => $post_data['mold_code']);
        $mappingMdl = app::get('goldentra')->model('mapping');

        if ($mappingMdl->getList('*', $filter, 0, 1)) {
            return $this->end(false, '模号已存在');
        }

        if (!kernel::single('goldentra_mapping_diamond')->checkWeightRange(
            goldentra_mapping_diamond::getWeightRange($post_data),
            $err_msg
        )) {
            return $this->end(false, $err_msg);
        }

        $now = time();
        $main = array_merge($filter, array(
            'type' => 'diamond',
            'at_time' => $now,
            'up_time' => $now,
        ));
        $mappingMdl->insert($main);
        $post_data['mapping_id'] = $main['mapping_id'];

        $items = array();
        foreach ($post_data['main_weight'] as $k => $v) {
            $items[] = array(
                'mapping_id' => $post_data['mapping_id'],
                'mold_code' => $post_data['mold_code'],
                'min_main' => $post_data['min_main'][$k],
                'max_main' => $post_data['max_main'][$k],
                'main_weight' => $post_data['main_weight'][$k],
                'tag_price' => $post_data['tag_price'][$k],
                'color' => $post_data['color'][$k],
                'clarity' => $post_data['clarity'][$k],
                'diamond_cut' => $post_data['diamond_cut'][$k],
                'max_weight' => 0,
                'weight' => 0,
                'min_extra' => 0,
                'max_extra' => 0,
                'extra_weight' => 0,
                'at_time' => $now,
                'up_time' => $now,
            );
        }

        $itemsObj = app::get('goldentra')->model('mapping_item');
        kernel::database()->exec(ome_func::get_insert_sql($itemsObj, $items));
        $this->end(true, '操作成功');
    }

    public function show($mapping_id = null)
    {
        if (empty($mapping_id) && !empty($_GET['p'][0])) {
            $mapping_id = intval($_GET['p'][0]);
        }
        $mapping_id = intval($mapping_id);
        if ($mapping_id <= 0) {
            echo '无效的 Mapping ID';
            return;
        }

        $this->finder('goldentra_mdl_mapping_item', array(
            'title' => 'Mapping明细',
            'base_filter' => array('mapping_id' => $mapping_id),
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
        ));
    }

    public function exportTemplateOfDj()
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=定价物料Mapping模板-' . date('Ymd') . '.csv');
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $titles = array('*:模号', '*:最小重量', '*:最大重量', '*:显示克重', '*:主石最小重量', '*:主石最大重量', '*:主石重量', '*:最小辅石重', '*:最大辅石重', '*:配石重', '*:最小标签价', '*:最大标签价', '*:显示标签价');
        array_walk($titles, function (&$title) {
            $title = kernel::single('base_charset')->utf2local($title);
        });
        echo '"' . implode('","', $titles) . '"';
        exit;
    }

    public function exportTemplateOfDiamond()
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=钻石物料Mapping模板-' . date('Ymd') . '.csv');
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $titles = array('*:模号', '*:颜色', '*:净度', '*:切工', '*:主石最小重量', '*:主石最大重量', '*:主石重量', '*:标签价');
        array_walk($titles, function (&$title) {
            $title = kernel::single('base_charset')->utf2local($title);
        });
        echo '"' . implode('","', $titles) . '"';
        exit;
    }

    public function batch_check()
    {
        $this->begin('');
        kernel::database()->exec('commit');
        $ids = $_POST['mapping_id'];
        $mappingObj = app::get('goldentra')->model('mapping');
        if (!empty($ids)) {
            foreach ($ids as $mapping_id) {
                $mappingObj->db->exec(
                    "UPDATE sdb_goldentra_mapping_item SET status='1' WHERE status='0' AND mapping_id=" . intval($mapping_id)
                );
                $mappingObj->batch_save_mold($mapping_id);
            }
        }
        $this->end(true, '审核成功！！');
    }

    public function edit($mapping_id)
    {
        $mappingMdl = app::get('goldentra')->model('mapping');
        $itemsObj = app::get('goldentra')->model('mapping_item');
        $mold_detail = $mappingMdl->dump(array('mapping_id' => $mapping_id), '*');
        $items_list = $itemsObj->getList('*', array('mapping_id' => $mapping_id));
        $mappingLib = kernel::single('goldentra_mapping');

        foreach ($items_list as &$v) {
            if ($mappingLib->checkGoldMaterialInfo($v['mold_code'], $v['weight'], $v['main_weight'], $v['extra_weight'])
                || !empty($v['bn'])
                || intval($v['bm_id']) > 0
            ) {
                $v['edit_flag'] = 1;
            }
        }

        $this->pagedata['mold_detail'] = $mold_detail;
        $this->pagedata['items_list'] = $items_list;
        $this->singlepage('admin/mapping/edit_items.html');
    }

    public function editDiamondPage($mapping_id)
    {
        $diamond = kernel::single('goldentra_mapping_diamond');
        $this->pagedata['color_rows'] = json_encode($diamond->getColorRows());
        $this->pagedata['clarity_rows'] = json_encode($diamond->getClarityRows());
        $this->pagedata['diamond_cut_rows'] = json_encode($diamond->getDiamondCutRows());
        $this->pagedata['mold_mapping'] = json_encode(
            app::get('goldentra')->model('mapping')->dump(array('mapping_id' => $mapping_id), '*')
        );
        $this->pagedata['mold_mapping_items'] = json_encode(
            app::get('goldentra')->model('mapping_item')->getList('*', array('mapping_id' => $mapping_id))
        );
        $this->singlepage('admin/mapping/diamond/edit_items.html');
    }

    public function updateDiamondMapping()
    {
        $this->begin();
        $post_data = $_POST;
        $mapping_id = $post_data['mapping_id'];
        $itemsMdl = app::get('goldentra')->model('mapping_item');
        $old_ids = array_column($itemsMdl->getList('item_id', array('mapping_id' => $mapping_id)), 'item_id');

        if (!kernel::single('goldentra_mapping_diamond')->checkWeightRange(
            goldentra_mapping_diamond::getWeightRange($post_data),
            $err_msg
        )) {
            return $this->end(false, $err_msg);
        }

        foreach ($post_data['min_main'] as $item_id => $v) {
            $item_data = array(
                'mapping_id' => $mapping_id,
                'min_main' => $post_data['min_main'][$item_id],
                'max_main' => $post_data['max_main'][$item_id],
                'main_weight' => $post_data['main_weight'][$item_id],
                'tag_price' => $post_data['tag_price'][$item_id],
                'mold_code' => $post_data['mold_code'],
                'color' => $post_data['color'][$item_id],
                'clarity' => $post_data['clarity'][$item_id],
                'diamond_cut' => $post_data['diamond_cut'][$item_id],
                'up_time' => time(),
            );
            if (in_array($item_id, $old_ids)) {
                $itemsMdl->update($item_data, array('item_id' => $item_id));
            } else {
                $item_data['at_time'] = time();
                $item_data['max_weight'] = 0;
                $item_data['weight'] = 0;
                $item_data['min_extra'] = 0;
                $item_data['max_extra'] = 0;
                $item_data['extra_weight'] = 0;
                $itemsMdl->insert($item_data);
            }
        }
        $this->end(true, '操作成功');
    }

    public function save_edit()
    {
        $this->begin('');
        $data = $_POST;
        if (empty($data['max_weight'])) {
            $this->end(true);
        }

        $msg = '';
        if (!kernel::single('goldentra_mapping_validate')->check($data, $msg)) {
            $this->end(false, $msg);
        }

        $mappingMdl = app::get('goldentra')->model('mapping');
        $itemsObj = app::get('goldentra')->model('mapping_item');
        $mold_detail = $mappingMdl->dump(array('mapping_id' => $data['mapping_id']), 'mold_code');
        $data['mold_code'] = $mold_detail['mold_code'];
        $item_id = $data['item_id'];
        $new_item = array();
        $now = time();

        foreach ($data['min_weight'] as $k => $v) {
            $item = array(
                'mapping_id' => $data['mapping_id'],
                'mold_code' => $data['mold_code'],
                'min_weight' => $v,
                'max_weight' => $data['max_weight'][$k],
                'weight' => $data['weight'][$k],
                'min_main' => $data['min_main'][$k],
                'max_main' => $data['max_main'][$k],
                'min_extra' => $data['min_extra'][$k],
                'max_extra' => $data['max_extra'][$k],
                'extra_weight' => $data['extra_weight'][$k],
                'min_tag_price' => $data['min_tag_price'][$k],
                'max_tag_price' => $data['max_tag_price'][$k],
                'tag_price' => $data['tag_price'][$k],
                'main_weight' => $data['main_weight'][$k],
                'up_time' => $now,
            );
            if (!empty($item_id) && !in_array($k, $item_id)) {
                $item['at_time'] = $now;
                $item['bn'] = goldentra_mapping_code::genGoldMaterialBn($item);
                $new_item[] = $item;
            } else {
                $item['item_id'] = $k;
                $itemsObj->save($item);
            }
        }

        if ($new_item) {
            kernel::database()->exec(ome_func::get_insert_sql($itemsObj, $new_item));
        }
        $this->end(true, '操作成功');
    }

    public function deleteItems()
    {
        $item_id = intval($_GET['item_id']);
        if ($item_id) {
            kernel::database()->exec('DELETE FROM sdb_goldentra_mapping_item WHERE item_id=' . $item_id);
        }
    }

    public function disabled()
    {
        $id = intval($_GET['id']);
        $mappingMdl = app::get('goldentra')->model('mapping');
        $flag = $mappingMdl->update(
            array('disabled' => '1', 'up_time' => time()),
            array('mapping_id' => $id)
        );

        if (!is_numeric($flag) || $flag <= 0) {
            echo json_encode(array('status' => 250, 'msg' => '禁用失败'));
            return;
        }

        $syncErr = '';
        $synced = kernel::single('goldentra_mapping_label')->syncDisabledByMappingId($id, $syncErr);
        $msg = '禁用成功';
        if ($synced) {
            $msg .= '，已同步 MAPPING禁用 标记';
        } elseif ($syncErr !== '') {
            $msg .= '，标记同步：' . $syncErr;
        }

        // 按基础物料找在库唯一码 → 调整单出库清空
        $clearErr = '';
        $cleared = kernel::single('goldentra_material_pricetype')->clearByMappingDisabled($id, $clearErr);
        if ($cleared) {
            $msg .= '，已按调整单出库清空库存';
        } elseif ($clearErr !== '') {
            $msg .= '，清库存：' . $clearErr;
        }

        app::get('ome')->model('operation_log')->write_log(
            'mapping_item_import@goldentra',
            $id,
            $msg
        );

        echo json_encode(array('status' => 200, 'msg' => $msg));
    }

    public function refresh()
    {
        $this->begin('index.php?app=goldentra&ctl=admin_mapping&act=index');
        if (!empty($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_') {
            $this->end(false, '不支持全选');
        }
        kernel::single('goldentra_mapping')->refresh($_POST['mapping_id']);
        $this->end(true, 'success');
    }

    public function importDiamondMapping()
    {
        $rows = explode(',', $_POST['content']);
        array_walk($rows, function (&$row) {
            $row = trim($row);
        });
        list($mold_code, $color, $clarity, $diamond_cut, $min_main, $max_main, $main_weight, $tag_price) = $rows;
        $data = compact('mold_code', 'color', 'clarity', 'diamond_cut', 'min_main', 'max_main', 'main_weight', 'tag_price');

        kernel::database()->beginTransaction();
        list($flag, $err_msg) = kernel::single('goldentra_mapping_diamond')->import($data);
        if ($flag == false) {
            kernel::database()->rollBack();
            echo json_encode(array('error' => $err_msg)) . 'ok.';
            exit;
        }
        kernel::database()->commit();
        echo json_encode(array('success' => '成功')) . 'ok.';
        exit;
    }
}
