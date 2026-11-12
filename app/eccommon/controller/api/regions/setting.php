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

use app\desktop\lib\annotation\RequirePermission;

class eccommon_ctl_api_regions_setting extends desktop_ctl_api_base
{
    /**
     * 获取地区设置页面数据
     */
    #[RequirePermission('regions')]
    public function index(): void
    {
        try {
            $package = kernel::single('eccommon_regions_mainland');

            $result = [
                'setting' => $package->setting,
                'name' => $package->name,
            ];

            $objRegion = app::get('eccommon')->model('regions');
            $row = $objRegion->getList('MAX(region_grade) AS _max_grade');
            if ($row) {
                $maxGrade = $row[0]['_max_grade'] > 5 ? 5 : $row[0]['_max_grade'];
                $result['maxdepth'] = $maxGrade;
            }

            $areaDepth = app::get('eccommon')->getConf('system.area_depth');
            $result['area_depth'] = $areaDepth > 5 ? 5 : $areaDepth;

            $result['installed'] = $objRegion->is_installed();

            $result['selected_package'] = app::get('base')->getConf('service.eccommon_regions.eccommon_mdl_regions');

            $list = [];
            $o = app::get('base')->model('services');
            $services = $o->getList('content_path', [
                'content_type' => 'service',
                'app_id' => 'eccommon',
                'disabled' => 'false',
                'content_name' => 'eccommon_regions.eccommon_mdl_regions'
            ]);
            foreach ($services as $v) {
                $oItem = kernel::single($v['content_path']);
                $list[] = [
                    'content_path' => $v['content_path'],
                    'name' => $oItem->name,
                ];
            }
            $result['package_list'] = $list;

            $this->succ($result);
        } catch (Throwable $e) {
            $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * POST - 保存地区包选择（迁移自 legacy ctl=regions_setting&act=save_regions_package）
     */
    #[RequirePermission('regions')]
    public function save_regions_package(): void
    {
        $this->begin('index.php?app=eccommon&ctl=regions_setting&act=index');
        try {
            $rs = app::get('base')->setConf(
                'service.eccommon_regions.eccommon_mdl_regions',
                $_POST['service']['eccommon_regions.eccommon_mdl_regions']
            );
        } catch (Throwable $e) {
            $this->endonly(false);
            $this->fail($e->getMessage(), 500);
            return;
        }
        $this->endonly($rs);
        $rs ? $this->succ(['saved' => true]) : $this->fail('保存失败');
    }

    /**
     * POST - 保存地区层级（迁移自 legacy ctl=regions_setting&act=save_depth）
     */
    #[RequirePermission('regions')]
    public function save_depth(): void
    {
        $this->begin('index.php?app=eccommon&ctl=regions_setting&act=index');
        try {
            $rs = app::get('eccommon')->setConf('system.area_depth', $_POST['area_depth']);
        } catch (Throwable $e) {
            $this->endonly(false);
            $this->fail($e->getMessage(), 500);
            return;
        }
        $this->endonly($rs);
        $rs ? $this->succ(['saved' => true]) : $this->fail('保存失败');
    }

    /**
     * POST - 导入/安装地区包（迁移自 legacy ctl=regions_setting&act=install）
     */
    #[RequirePermission('regions')]
    public function install(): void
    {
        set_time_limit(0);
        $this->begin('index.php?app=eccommon&ctl=regions_setting&act=index');
        try {
            $rs = kernel::single('eccommon_regions_mainland')->install();
        } catch (Throwable $e) {
            $this->endonly(false);
            $this->fail($e->getMessage(), 500);
            return;
        }
        $this->endonly($rs);
        $rs ? $this->succ(['installed' => true]) : $this->fail('导入失败');
    }

    /**
     * POST - 恢复地区初始数据（迁移自 legacy ctl=regions_setting&act=setDefault）
     * 注意：会清空并重装地区表，属破坏性操作
     */
    #[RequirePermission('regions')]
    public function setDefault(): void
    {
        set_time_limit(0);
        $this->begin('index.php?app=eccommon&ctl=regions_setting&act=index');
        try {
            app::get('eccommon')->model('regions')->clearOldData();
            $rs = kernel::single('eccommon_regions_mainland')->install();
        } catch (Throwable $e) {
            $this->endonly(false);
            $this->fail($e->getMessage(), 500);
            return;
        }
        $this->endonly($rs);
        $rs ? $this->succ(['restored' => true]) : $this->fail('恢复失败');
    }
}
