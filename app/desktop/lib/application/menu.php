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
class desktop_application_menu extends desktop_application_prototype_xml
{

    public $xml  = 'desktop.xml';
    public $xsd  = 'desktop_content';
    public $path = 'workground';

    public function current()
    {
        $this->current           = $this->iterator()->current();
        $this->current['action'] = $this->current['action'] ? $this->current['action'] : 'index';
        $this->key               = $this->target_app->app_id . '_ctl_' . $this->current['controller'];
        return $this;
    }

    public function row($fag, $key)
    {
        if ($this->current['menugroup'][$fag]['menu'][$key]['params']) {
            $a_tmp = explode('|', $this->current['menugroup'][$fag]['menu'][$key]['params']);
            foreach ((array) $a_tmp as $k => $v) {
                $a = explode(':', $v);
                if (strpos($a[0], 'p[') !== false) {
                    $a = explode(':', $v);
                    eval('$url_params' . str_replace('p', '["p"]', $a[0]) . ' = $a[1];');
                } else {
                    $url_params[$a[0]] = $a[1];
                }
            }
            $addon['url_params'] = $url_params;
            unset($url_params);
        }
        $row = array(
            'menu_type'    => $this->content_typename(),
            'app_id'       => $this->target_app->app_id,
            'workground'   => $this->current['id'],
            'content_name' => $this->key(),
            'menu_group'   => $this->current['menugroup'][$fag]['name'],
            'menu_order'   => $this->current['menugroup'][$fag]['menu'][$key]['order'],
            'addon'        => serialize($addon),
            'target'       => $this->current['menugroup'][$fag]['menu'][$key]['target'] ? $this->current['menugroup'][$fag]['menu'][$key]['target'] : '',
            'en'           => $this->current['menugroup'][$fag]['en'],
        );

        $this->current['menugroup'][$fag]['menu'][$key]['action'] = $this->current['menugroup'][$fag]['menu'][$key]['action'] ? $this->current['menugroup'][$fag]['menu'][$key]['action'] : 'index';
        $row['menu_path']                                         = "app={$this->target_app->app_id}&ctl={$this->current['menugroup'][$fag]['menu'][$key]['controller']}&act={$this->current['menugroup'][$fag]['menu'][$key]['action']}";
        $row['menu_title']                                        = $this->current['menugroup'][$fag]['menu'][$key]['value'];
        $row['permission']                                        = $this->current['menugroup'][$fag]['menu'][$key]['permission'];
        $row['display']                                           = $this->current['menugroup'][$fag]['menu'][$key]['display'] ? $this->current['menugroup'][$fag]['menu'][$key]['display'] : "true";
        return $row;
    }
    public function menu_row($fag)
    {
        if ($this->current['menu'][$fag]['params']) {
            $a_tmp = explode('|', $this->current['menu'][$fag]['params']);
            foreach ((array) $a_tmp as $k => $v) {
                $a                 = explode(':', $v);
                $url_params[$a[0]] = $a[1];
            }
            $addon['url_params'] = $url_params;
            unset($url_params);
        }
        $row = array(
            'menu_type'    => $this->content_typename(),
            'app_id'       => $this->target_app->app_id,
            'workground'   => $this->current['id'],
            'content_name' => $this->key(),
            'menu_group'   => '',
            'menu_order'   => $this->current['menu'][$fag]['order'],
            'addon'        => serialize($addon),
            'target'       => $this->current['menu'][$fag]['target'] ? $this->current['menu'][$fag]['target'] : '',
        );
        # $row = parent::row();

        $this->current['menu'][$fag]['action'] = $this->current['menu'][$fag]['action'] ? $this->current['menu'][$fag]['action'] : 'index';
        $row['menu_path']                      = "app={$this->target_app->app_id}&ctl={$this->current['menu'][$fag]['controller']}&act={$this->current['menu'][$fag]['action']}";
        $row['menu_title']                     = $this->current['menu'][$fag]['value'];
        $row['permission']                     = $this->current['menu'][$fag]['permission'];
        $row['display']                        = $this->current['menu'][$fag]['display'];
        return $row;
    }

    /**
     * 身份键列的归一化：NULL / 未设置 / serialize(NULL) 产物 'N;' / '' 全部归到 ''。
     */
    protected static function normalize_key_col($v)
    {
        if ($v === null) {
            return '';
        }
        $v = (string) $v;
        if ($v === 'N;') {
            return '';
        }
        return $v;
    }

    /**
     * 菜单身份键：由 desktop.xml 拥有的 7 个身份列拼接后取 sha1。
     * install() / update() / 回填脚本必须共用本函数，禁止各写一份。
     */
    public static function build_menu_key(array $row)
    {
        $parts = array();
        foreach (array('menu_type', 'app_id', 'workground', 'menu_group', 'menu_path', 'addon', 'permission') as $col) {
            $parts[] = self::normalize_key_col(isset($row[$col]) ? $row[$col] : null);
        }
        return sha1(implode("\x1f", $parts));
    }

    /**
     * 组装当前 workgroup($this->current) 对应的菜单行。
     * 返回 array($rows, $has_menugroup_rows)；$has_menugroup_rows 用于还原 install() 旧的 version 写入语义。
     */
    protected function build_rows($app_id)
    {
        $rows = array();
        $has_menugroup_rows = false;

        foreach ((array) $this->current['menugroup'] as $fag => $val) {
            foreach ((array) $val['menu'] as $key => $data) {
                $rows[] = $this->row($fag, $key);
                $has_menugroup_rows = true;
            }
        }
        if ($this->current['menu']) {
            foreach ((array) $this->current['menu'] as $fag => $val) {
                $rows[] = $this->menu_row($fag);
            }
        }

        return array($rows, $has_menugroup_rows);
    }

    /**
     * 菜单模型工厂，抽出便于单元测试注入假模型。
     */
    protected function menus_model()
    {
        return app::get('desktop')->model('menus');
    }

    public function install()
    {
        $model = $this->menus_model();

        list($rows, $has_menugroup_rows) = $this->build_rows($this->target_app->app_id);
        foreach ($rows as $row) {
            kernel::log('Installing ' . $this->content_typename() . ' ' . $this->key() . ' ' . $row['menu_path']);

            $row['menu_key'] = self::build_menu_key($row);
            $inserted = $model->insert($row);
            if (false === $inserted) {
                kernel::log('menu install failed: app=' . $this->target_app->app_id . ' workground=' . $row['workground'] . ' menu_path=' . $row['menu_path'] . ' insert_return=' . var_export($inserted, true));
            }
        }

        if ($has_menugroup_rows) {
            base_kvstore::instance('menudefine')->store('version', date('YmdHis'));
        }
    }

    /**
     * 差异同步核心：按身份键命中则 save() 复用 menu_id，否则 insert()；
     * 最后才删除本应用本类型中目标集合里已不存在的行。存量重复身份键保留 menu_id 最小者。
     */
    protected function sync_menu_rows($model, array $existing_rows, array $incoming)
    {
        $stats = array('insert' => 0, 'update' => 0, 'delete' => 0, 'failed' => 0);

        $existing = array();
        foreach ($existing_rows as $row) {
            $key = self::build_menu_key($row);
            if (!isset($existing[$key])) {
                $existing[$key] = $row;
                continue;
            }
            $kept = $existing[$key];
            if ($row['menu_id'] < $kept['menu_id']) {
                $stale = $kept;
                $existing[$key] = $row;
            } else {
                $stale = $row;
            }
            kernel::log('menu duplicate identity key, removing menu_id=' . $stale['menu_id'] . ' key=' . $key);
            $deleted = $model->delete(array('menu_id' => $stale['menu_id']));
            if (false === $deleted) {
                $stats['failed']++;
                kernel::log('menu delete failed: app=' . $stale['app_id'] . ' workground=' . $stale['workground'] . ' menu_path=' . $stale['menu_path'] . ' menu_id=' . $stale['menu_id'] . ' delete_return=' . var_export($deleted, true));
            }
        }

        foreach ($incoming as $key => $row) {
            if (isset($existing[$key])) {
                $row['menu_id'] = $existing[$key]['menu_id'];
                $saved = $model->save($row);
                if (false === $saved) {
                    $stats['failed']++;
                    kernel::log('menu update failed: app=' . $row['app_id'] . ' workground=' . $row['workground'] . ' menu_path=' . $row['menu_path'] . ' menu_id=' . $row['menu_id'] . ' save_return=' . var_export($saved, true));
                } else {
                    $stats['update']++;
                }
                unset($existing[$key]);
            } else {
                $inserted = $model->insert($row);
                if (false === $inserted) {
                    $stats['failed']++;
                    kernel::log('menu insert failed: app=' . $row['app_id'] . ' workground=' . $row['workground'] . ' menu_path=' . $row['menu_path'] . ' insert_return=' . var_export($inserted, true));
                } else {
                    $stats['insert']++;
                }
            }
        }
        foreach ($existing as $row) {
            kernel::log('menu removed: ' . $row['menu_path'] . ' | ' . $row['menu_title']);
            $deleted = $model->delete(array('menu_id' => $row['menu_id']));
            if (false === $deleted) {
                $stats['failed']++;
                kernel::log('menu delete failed: app=' . $row['app_id'] . ' workground=' . $row['workground'] . ' menu_path=' . $row['menu_path'] . ' menu_id=' . $row['menu_id'] . ' delete_return=' . var_export($deleted, true));
            } else {
                $stats['delete']++;
            }
        }

        return $stats;
    }

    /**
     * 重载父类 base_application_prototype_content::update()：用身份键差异同步替代「全删重插」。
     * 写入顺序为 update/insert 在前、delete 在后，中途失败不会删掉在用菜单，重跑即收敛。
     */
    public function update($app_id)
    {
        if (!$app_id) {
            return false;
        }
        $model = $this->menus_model();
        $existing_rows = $model->getList('*', array('app_id' => $app_id, 'menu_type' => $this->content_typename()));

        $incoming = array();
        $has_menugroup_rows = false;
        $workgroups = 0;
        foreach ($this->detect($app_id) as $name => $item) {
            $workgroups++;
            list($rows, $workgroup_has_menugroup_rows) = $this->build_rows($app_id);
            if ($workgroup_has_menugroup_rows) {
                $has_menugroup_rows = true;
            }
            foreach ((array) $rows as $row) {
                $key = self::build_menu_key($row);
                $row['menu_key'] = $key;
                if (isset($incoming[$key])) {
                    kernel::log('menu sync duplicate incoming key, overwriting menu_path=' . $incoming[$key]['menu_path'] . ' with menu_path=' . $row['menu_path'] . ' key=' . $key);
                }
                $incoming[$key] = $row;
            }
        }

        if (!$incoming) {
            kernel::log('menu sync skipped: 0 rows parsed');
            return false;
        }

        $db = kernel::database();
        $own_transaction = !$db->isInTransaction();
        try {
            if ($own_transaction && false === $db->beginTransaction()) {
                // 与下方 catch 分支对称：回滚复位 connections::_in_transaction（无活动事务时 'rollback' 为 no-op），
                // 否则同一 PHP 进程内后续 app 的 update() 会误判「已在事务中」而静默跳过事务保护
                $db->rollBack();
                kernel::log('menu sync failed: begin transaction returned false');
                return false;
            }
            $stats = $this->sync_menu_rows($model, (array) $existing_rows, $incoming);
            if ($own_transaction) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($own_transaction) {
                $db->rollBack();
            }
            kernel::log('menu sync failed: ' . $e->getMessage());
            return false;
        }

        kernel::log(sprintf(
            'menu sync app=%s workgroups=%d insert=%d update=%d delete=%d failed=%d',
            $app_id,
            $workgroups,
            $stats['insert'],
            $stats['update'],
            $stats['delete'],
            $stats['failed']
        ));

        if ($has_menugroup_rows) {
            base_kvstore::instance('menudefine')->store('version', date('YmdHis'));
        }

        return true;
    }

    public function clear_by_app($app_id)
    {
        if (!$app_id) {
            return false;
        }
        app::get('desktop')->model('menus')->delete(array(
            'app_id' => $app_id, 'menu_type' => $this->content_typename()));
    }

}
