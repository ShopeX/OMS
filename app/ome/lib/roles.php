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
class ome_roles
{
    public function show_group($user_id = null, $post = [])
    {
        $render = app::get('ome')->render();

        $groups = app::get('ome')->model('groups')->getList('group_id,name', [
            'g_type' => 'confirm',
        ]);

        if ($user_id){
            $groupOpsMdl = app::get('ome')->model('group_ops');
            $curGroups = $groupOpsMdl->getList('*', [
                'op_id' => $user_id
            ]);
            $group_id = $post['confirm_group'] ?: array_column($curGroups, 'group_id');

            foreach ($groups as $key => $value) {
                $groups[$key]['selected'] = in_array($value['group_id'], $group_id);
            }
        }

        $render->pagedata['groups'] = $groups;



        return $render->fetch('admin/roles/access/ordergroup.html', 'ome');
    }

    

    public function show_branch($user_id = null, $post = [])
    {

        $render = app::get('ome')->render();

        $branchList = app::get('ome')->model('branch')->getList('branch_id,name', [
            'b_type' => '1',
        ]);

        if ($user_id) {

            $curBranch = app::get('ome')->model('branch_ops')->getList('branch_id', [
                'op_id' => $user_id,
            ]);
            $curBranch = $post['branch'] ? $post['branch'] : array_column((array) $curBranch, 'branch_id');

            foreach ($branchList as $key => $value) {
                $branchList[$key]['checked'] = in_array($value['branch_id'], $curBranch);
            }
        }

        $render->pagedata['branchList'] = $branchList;

        $content = $render->fetch('admin/roles/access/branch.html', 'ome');

        return $content;
    }

    /**
     * 直营仓库列表（JSON 模式）
     */
    public function show_branch_json($user_id = null)
    {
        $branchList = app::get('ome')->model('branch')->getList('branch_id,name', [
            'b_type' => '1',
        ]);

        if ($user_id) {
            $curBranch = app::get('ome')->model('branch_ops')->getList('branch_id', [
                'op_id' => $user_id,
            ]);
            $curBranch = array_column((array) $curBranch, 'branch_id');

            foreach ($branchList as $key => $value) {
                $branchList[$key]['checked'] = in_array($value['branch_id'], $curBranch);
            }
        } else {
            foreach ($branchList as $key => $value) {
                $branchList[$key]['checked'] = false;
            }
        }

        foreach ($branchList as $key => $value) {
            $result[] = [
                'id'      => $value['branch_id'],
                'name'    => $value['name'],
                'checked' => $value['checked'] ?? false,
            ];
        }

        return $result;
    }

    /**
     * O2O门店列表（JSON 模式）
     */
    public function show_o2o_branch_json($user_id = null)
    {
        if (!app::get('o2o')->is_installed()) {
            return [];
        }

        $storeList = app::get('o2o')->model('store')->getList('store_id,name,store_mode');
        foreach ($storeList as $key => $value) {
            $storeList[$key]['store_mode'] = app::get('o2o')->model('store')->schema['columns']['store_mode']['type'][$value['store_mode']] ?? '';
        }

        if ($user_id) {
            $ops = app::get('ome')->model('branch_ops')->getList('branch_id', [
                'op_id' => $user_id,
            ]);
            if ($branch_id = array_column($ops, 'branch_id')) {
                $branches = app::get('ome')->model('branch')->getList('store_id', [
                    'branch_id' => $branch_id,
                    'check_permission' => 'false',
                ]);
                $store_id = array_unique(array_column($branches, 'store_id'));

                foreach ($storeList as $key => $value) {
                    $storeList[$key]['selected'] = in_array($value['store_id'], $store_id);
                }
            }
        }

        $result = [];
        foreach ($storeList as $item) {
            $result[] = [
                'id'       => $item['store_id'],
                    'name'     => $item['name'],
                    'store_mode' => $item['store_mode'],
                    'checked'  => $item['selected'] ?? false,
            ];
        }
        return $result;
    }

    /**
     * 订单组列表（JSON 模式）
     */
    public function show_group_json($user_id = null)
    {
        $groups = app::get('ome')->model('groups')->getList('group_id,name', [
            'g_type' => 'confirm',
        ]);

        if ($user_id) {
            $curGroups = app::get('ome')->model('group_ops')->getList('group_id', [
                'op_id' => $user_id
            ]);
            $curGroupIds = array_column($curGroups, 'group_id');

            foreach ($groups as $key => $value) {
                $groups[$key]['selected'] = in_array($value['group_id'], $curGroupIds);
            }
        } else {
            foreach ($groups as $key => $value) {
                $groups[$key]['selected'] = false;
            }
        }

        $result = [];
        foreach ($groups as $item) {
            $result[] = [
                'id'       => $item['group_id'],
                'name'     => $item['name'],
                'checked'  => $item['selected'] ?? false,
            ];
        }
        return $result;
    }

    public function save_role($user_id, $data)
    {
        $group_id   = $data['confirm_group'];
        $branch_ids = $data['branch'];
        if ($group_id) {
            $groupOpsMdl = app::get('ome')->model('group_ops');

            $groupOpsMdl->delete(['op_id' => $user_id]);
            foreach ($group_id as $gid) {
                $t_data = array(
                    'group_id' => $gid, 
                    'op_id' => $user_id
                );

                $groupOpsMdl->save($t_data);
            }

            // $user_count = app::get('ome')->model('group_ops')->count(array('op_id' => $user_id));
            // if ($user_count == 1) {
            //     app::get('ome')->model('group_ops')->update(array('group_id' => $group_id), array('op_id' => $user_id));
            // } else {
            //     $t_data = array('group_id' => $group_id, 'op_id' => $user_id);
            //     app::get('ome')->model('group_ops')->save($t_data);
            // }
        }

        // 仓库权限
        app::get('ome')->model('branch_ops')->delete(array('op_id' => $user_id));
        if ($branch_ids) {
            foreach ($branch_ids as $branch_id) {
                $t_data = array('branch_id' => $branch_id, 'op_id' => $user_id);
                app::get('ome')->model('branch_ops')->save($t_data);
            }
        }

        #o2o门店线下仓库
        if (app::get('o2o')->is_installed()) {
            $o2oBrPosMdl = app::get('ome')->model('branch_ops');
            $o2oStoreMdl = app::get('o2o')->model('store');

            // 删除全部门店仓权限 
            $o2oAllBra = app::get('ome')->model('branch')->getList('branch_id', [
                'b_type'            => '2',
                'check_permission'  => 'false',
            ]);
            if ($o2oAllBra) {
                $o2oBrPosMdl->delete(['branch_id' => array_column($o2oAllBra, 'branch_id')]);
            }

            if ($data['store_id']){
                // 保存门店
                $o2oBranches = app::get('ome')->model('branch')->getList('branch_id', [
                    'store_id' => $data['store_id'],
                    'check_permission' => 'false',
                ]);

                foreach ($o2oBranches as $key => $value) {
                    $t_data = array(
                        'branch_id' => $value['branch_id'],
                        'op_id'     => $user_id,
                        'org'       => $data['selected_org'],
                    );

                    $o2oBrPosMdl->save($t_data);
                }
            }

            // elseif ($data['selected_org']) {
            //     $t_data = array(
            //         'branch_id' => 0,
            //         'op_id'     => $user_id,
            //         'org'       => $data['selected_org'],
            //     );

            //     $o2oBrPosMdl->save($t_data);
            // }
        }
    }

    public function show_o2o_branch($user_id = null, $post = [])
    {
        $render = app::get('ome')->render();

        $storeList = app::get('o2o')->model('store')->getList('store_id,name,store_mode');
        $storeList = array_column($storeList, null, 'store_id');
        foreach ($storeList as $key => $value) {
            $storeList[$key]['store_mode'] = app::get('o2o')->model('store')->schema['columns']['store_mode']['type'][$value['store_mode']];
        }
        $p_org_id = '';
        if ($user_id) {

            // 绑一个门店
            $ops = app::get('ome')->model('branch_ops')->getList('*',[
                'op_id' => $user_id,
            ]);

            // $selected_org = $ops[0]['org'];


            if ($branch_id = array_column($ops, 'branch_id')) {
                $branches = app::get('ome')->model('branch')->getList('store_id', [
                    'branch_id' => $branch_id,
                    'check_permission' => 'false',
                ]);

                $store_id = array_unique(array_column($branches, 'store_id'));

                foreach ($storeList as $key => $value) {
                    $storeList[$key]['selected'] = in_array($value['store_id'], $store_id);
                }
                // dump([
                //     'branch_id' => $branch_id,
                //     'type' => 'main',
                // ], 'store_id');

                // $org = app::get('organization')->model('organization')->dump(['org_no' => $branch['branch_bn']]);

                // mainOrganization:BTQ:1
                // list($a, $b, $c) = explode(':', $org['org_parents_structure']);

                // $selected_org = $post['selected_org'] ?: $a.':'.$b.'/'.$org['org_name'].':'.$org['org_id'];
            }

            // $render->pagedata['selected_org'] = $selected_org;
        }



        $render->pagedata['storeList'] = $storeList;

        return $render->fetch('admin/roles/access/store.html', 'ome');
    }
}
