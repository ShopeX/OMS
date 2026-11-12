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

class desktop_ctl_users extends desktop_controller
{

    public $workground = 'desktop_ctl_system';

    /**
     * 仓储权限标记
     * @var int
     */
    const __BRANCH_ROLE = 2;

    /**
     * 订单分组权限标记
     * @var int
     */
    const __ORDER_ROLE = 3;

    /**
     * 门店权限标记
     * @var int
     */
    const __STORE_ROLE = 99;

    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */
    public function __construct($app)
    {
        parent::__construct($app);
        header("cache-control: no-store, no-cache, must-revalidate");
    }
    #[RequirePermission('users')]
    public function index()
    {
        $params = array(
            'title'               => app::get('desktop')->_('操作员管理'),
            'actions'             => array(
                array(
                    'label'  => app::get('desktop')->_('添加管理员'),
                    'href'   => 'index.php?ctl=users&act=addnew',
                    'target' => 'dialog::{width:750,height:600,title:\'' . app::get('desktop')->_('添加管理员') . '\'}',
                ),
                array(
                    'label'  => '导出模板',
                    'href'   => $this->url.'&act=exportTemplate',
                    'target' => '_blank',
                ),
            ),
            'use_buildin_export'  => true,
            'use_buildin_import'  => true,
            'use_import_template' => true,
        );
        $is_operator_add = kernel::single('desktop_user')->has_permission('operator_add');
        if(!$is_operator_add){
            unset($params['actions']);
        }
        $this->finder('desktop_mdl_users',$params);

    }

    #[RequirePermission('operator_add')]
    public function addnew()
    {
        $roles = $this->app->model('roles');
        $users = $this->app->model('users');

        $operationOrgObj        = app::get('ome')->model('operation_organization');
        $orgs                   = $operationOrgObj->getList('*', array(), 0, -1);
        $this->pagedata['orgs'] = $orgs;

        // 门店组织权限数据初始化
        $storeOrgList = ['orgCollectName'=>'', 'orgIds'=>''];
        $this->pagedata['store_org'] = $storeOrgList;

        if ($_POST) {
            $_POST['super'] = 0;
            $_POST['mcp_enabled'] = $this->normalizeMcpEnabled($_POST['mcp_enabled'] ?? 1);
            $_POST['account_type'] = ($_POST['account_type'] ?? '') === 'service' ? 'service' : 'human';
            $this->begin('index.php?app=desktop&ctl=users&act=index');
            $_POST['pam_account']['login_name'] = trim($_POST['pam_account']['login_name']);
            $_POST['op_no']                     = strtoupper(trim($_POST['op_no']));
            $msg='';
            if ($users->validate($_POST, $msg)) {
                if ($_POST['super'] == 0 && (!$_POST['role'])) {
                    $this->end(false, app::get('desktop')->_('请至少选择一个工作组'));
                } elseif ($_POST['super'] == 0 && ($_POST['role'])) {
                    foreach ((array) $_POST['role'] as $roles) {
                        $_POST['roles'][] = array('role_id' => $roles);
                    }

                }
                $pwd = $_POST['pam_account']['login_password'];
                // 新添加的用户使用 MD5+SHA256 加密方式（is_hash256=1）
                $_POST['pam_account']['login_password'] = pam_encrypt::get_encrypted_password($_POST['pam_account']['login_password'], pam_account::get_account_type($this->app->app_id), 1);
                $_POST['pam_account']['account_type']   = pam_account::get_account_type($this->app->app_id);
                // 设置 is_hash256 字段为 1（新加密方式）
                $_POST['pam_account']['is_hash256'] = '1';
                $_POST['create_time']                   = $_POST['last_modify']                   = time();
                if ($users->save($_POST)) {
                    foreach (kernel::servicelist('desktop_useradd') as $key => $service) {
                        if ($service instanceof desktop_interface_useradd) {
                            $service->useradd($_POST);
                        }
                    }

                    // 门店组织权限处理 - 双重保存策略
                    if (isset($_POST['store_org_conf']['orgIds'][0]) && !empty($_POST['store_org_conf']['orgIds'][0])) {
                        $orgIds = explode(',', $_POST['store_org_conf']['orgIds'][0]);
                        
                        // 1. 保存原始组织权限到 organization_ops（新增）
                        $this->saveOrganizationPermission($_POST['user_id'], $orgIds);
                        
                        // 2. 展开并保存具体门店到 branch_ops（向下兼容）
                        $_POST['store_id'] = $this->expandOrgIdsToStoreIds($orgIds);
                    }

                    if ($_POST['super'] == 0) {
                        //是超管就不保存
                        $this->save_ground($_POST);
                    }

                    //数据权限
                    if ($_POST['org_id']) {
                        $users->save_operation_permission($_POST);
                    }
                    // 一件代发yjdf经销组织权限
                    if ($_POST['dealer_shop_conf']) {
                        kernel::single('organization_cos')->save_operation_permission($_POST);
                    }
                    if($_POST['email']) {
                        $receiveMail = trim($_POST['email']) . '#' . $_POST['name'];
                        $subject = "账号开通";
                        $body = "    您的账号是 " . $_POST['pam_account']['login_name'] ."\n密码是 ". $pwd ."\n请尽快登录，并修改密码";
                        kernel::single('console_email')->send($receiveMail,$subject,$body);
                    }
                    //这里新增插入日志
                    $this->app->model('user_logs')->addUser($_POST);
    
                    // IDAAS
                    list($rs,$msg) = kernel::single('desktop_user_auth')->sync_account(array(
                        'account_id'        => $_POST['pam_account']['account_id'],
                        'login_name'        => $_POST['pam_account']['login_name'],
                        'login_password'    => $_POST['pam_account']['login_password'],
                    ),'add');
                    if ($rs === false) {
                        $this->end(false,$msg);
                    }
                    
                    $this->end(true, app::get('desktop')->_('保存成功'));
                } else {
                    $this->end(false, app::get('desktop')->_('保存失败'));
                }

            } else {
                $this->end(false, '操作失败:'.$msg);
            }
        } else {
            $workgroups = $roles->getList('*');

            $workgroup_dealer = [];            
            foreach ($workgroups as $workgroup) {
                if ($this->get_show_branch($workgroup['role_id'])) {
                    $workgroup_branch[] = $workgroup;
                } elseif ($this->get_o2o_branch($workgroup['role_id'])) {
                    $workgroup_o2o_branch[] = $workgroup;
                } else {
                    $workgroup_order[] = $workgroup;
                }
                // 一件代发，独立出来，只要有符合的都展示
                if ($this->get_dealer_workgroup($workgroup['role_id'])){
                    $workgroup_dealer[] = $workgroup;
                } 
            }

            #线下门店权限组
            $this->pagedata['workgroup_o2o_branch'] = $workgroup_o2o_branch;

            $this->pagedata['workgroup_branch'] = $workgroup_branch;
            $this->pagedata['workgroup_order']  = $workgroup_order;
            $this->pagedata['workgroup_dealer']  = $workgroup_dealer;
            $this->pagedata['workgroup']        = $workgroups;
            $this->pagedata['mcp_enabled']      = '1';
            $this->display('users/users_add.html');
        }
    }

    ####修改密码
    #[RequirePermission('operator_edit')]
    public function chkpassword()
    {
        $this->begin('index.php?app=desktop&ctl=users&act=index');
        $users = $this->app->model('users');
        if ($_POST) {
            $userLib                  = kernel::single('desktop_user');
            $sdf                      = $users->dump($_POST['user_id'], '*', array(':account@pam' => array('*'), 'roles' => array('*')));
            $old_password             = $sdf['account']['login_password'];
            $super_row                = $users->getList('user_id', array('super' => '1'));
            
            // 查询超级管理员的 is_hash256 值，用于验证旧密码
            $superAccount = app::get('pam')->model('account')->dump(array(
                'account_id' => $super_row[0]['user_id'],
                'account_type' => pam_account::get_account_type($this->app->app_id)
            ), 'is_hash256');
            $superIsHash256 = isset($superAccount['is_hash256']) ? intval($superAccount['is_hash256']) : 1;
            
            $filter['account_id']     = $super_row[0]['user_id'];
            $filter['account_type']   = pam_account::get_account_type($this->app->app_id);
            // 根据超级管理员的 is_hash256 值选择加密方式验证旧密码
            $filter['login_password'] = pam_encrypt::get_encrypted_password(trim($_POST['old_login_password']), pam_account::get_account_type($this->app->app_id), $superIsHash256);

            $pass_row = app::get('pam')->model('account')->getList('account_id', $filter);
            
            // 修改密码时，使用新加密方式（is_hash256=1）
            $loginPassword = pam_encrypt::get_encrypted_password(trim($_POST['new_login_password']), pam_account::get_account_type($this->app->app_id), 1);
            if (!$pass_row) {
                $this->end(false, app::get('desktop')->_('超级管理员密码不正确'));
            } elseif ($_POST['new_login_password'] != $_POST['pam_account']['login_password']) {
                $this->end(false, app::get('desktop')->_('两次密码不一致'));
            } elseif (!$userLib->checkRepeatPassWord($_POST['user_id'], $loginPassword, $error_msg)) {
                $this->end(false, app::get('desktop')->_($error_msg));
            }  elseif (!$userLib->checkUserPassWordLength($_POST['user_id'], $_POST['new_login_password'], $error_msg)) {
                $this->end(false, app::get('desktop')->_($error_msg));
            } elseif (!$userLib->validPassWord($_POST['new_login_password'], $error_msg,$sdf['name'])) {
                $this->end(false, app::get('desktop')->_($error_msg));
            } else {
                $_POST['pam_account']['account_id']     = $_POST['user_id'];
                $_POST['pam_account']['login_password'] = $loginPassword;
                // 修改密码时，将 is_hash256 设置为 1（新加密方式）
                $_POST['pam_account']['is_hash256'] = '1';
                $users->save($_POST);
                $userLib->checkUpdatePwd($_POST['pam_account']['account_id'], true);
                //新增插入日志
                $this->app->model('user_logs')->changePwd($_POST['user_id']);
    
                list($rs,$msg) = kernel::single('desktop_user_auth')->sync_account(array(
                    'account_id'        => $_POST['pam_account']['account_id'],
                    'login_name'        => $sdf['account']['login_name'],
                    'login_password'    => $_POST['pam_account']['login_password'],
                ),'password');
                if ($rs === false) {
                    $this->end(false,$msg);
                }
                
                $this->end(true, app::get('desktop')->_('密码修改成功'));
            }
        }
        $this->pagedata['user_id'] = $_GET['id'];
        $this->page('users/chkpass.html');

    }

    /**
     * This is method saveUser
     * 添加编辑
     * @return mixed This is the return value description
     * 
     */

    #[RequirePermission('operator_edit')]
    public function saveUser()
    {
        $this->begin();
        $users     = $this->app->model('users');
        $roles     = $this->app->model('roles');
        $workgroup = $roles->getList('*');
        $param_id  = $_POST['account_id'];
        if (!$param_id) {
            $this->end(false, app::get('desktop')->_('编辑失败,参数丢失！'));
        }

        $sdf_users = $users->dump($param_id);
        if (!$sdf_users) {
            $this->end(false, app::get('desktop')->_('编辑失败,参数错误！'));
        }
        if(!kernel::single('desktop_user')->has_permission('users')) {
            $this->end(false, app::get('desktop')->_('您没有权限进行此操作！'));
        }
        //if($sdf_users['super']==1) $this->end(false, app::get('desktop')->_('不能编辑超级管理员！'));
        if ($_POST['mobile'] && (strlen($_POST['mobile']) != '11' || !is_numeric($_POST['mobile']))) {
            $this->end(false, app::get('desktop')->_('手机号码格式不正确！'));
        }
        if ($_POST) {
            //新增插入日志
            $this->app->model('user_logs')->userEdit($_POST);

            $_POST['name']                      = trim($_POST['name']);
            $_POST['pam_account']['account_id'] = $param_id;
            $_POST['op_no']                     = strtoupper(trim($_POST['op_no']));
            $_POST['mcp_enabled']               = $this->normalizeMcpEnabled($_POST['mcp_enabled'] ?? ($sdf_users['mcp_enabled'] ?? 1));

            // 编辑用户时，将 is_hash256 设置为 1（新加密方式）
            $_POST['pam_account']['is_hash256'] = '1';

            if ($sdf_users['super'] == 1) {
                // Legacy 编辑页不传 super 字段；Vue 也不应覆盖超管标识
                unset($_POST['super']);
                $users->editUser($_POST);
                //保存成功后加判断是否启用状态有变更
                $user_data  = $users->dump($sdf_users['user_id'], '*', array(':account@pam' => array('*')));
                if ($sdf_users && ($sdf_users['status'] != $_POST['status'])) {
                    $_inner_key = sprintf("account_user_%s", $sdf_users['user_id']);
                    cachecore::store($_inner_key, $user_data, 60 * 15);
                }
    
                // IDAAS同步账号
                list($rs,$msg) = kernel::single('desktop_user_auth')->sync_account(array(
                    'account_id'        => $_POST['pam_account']['account_id'],
                    'login_name'        => $user_data['account']['login_name'],
                    'login_password'    => $user_data['account']['login_password'],
                ),'update');
                if ($rs === false) {
                    $this->end(false,$msg);
                }
                
                $this->end(true, app::get('desktop')->_('编辑成功！'));
            } elseif ($_POST['super'] == 0 && $_POST['role']) {
                foreach ((array) $_POST['role'] as $roles) {
                    $_POST['roles'][] = array('role_id' => $roles);
                }
                // 编辑用户时，将 is_hash256 设置为 1（新加密方式）
                $_POST['pam_account']['is_hash256'] = '1';
                $users->editUser($_POST);
                
                // 门店组织权限处理 - 独立保存，不影响门店角色权限
                $storeOrgIds = $_POST['store_org_conf']['orgIds'] ?? [];
                if (!empty($storeOrgIds)) {
                    // 兼容两种格式：数组 ['5','6'] 或逗号拼接 ['5,6']
                    if (count($storeOrgIds) === 1 && strpos($storeOrgIds[0], ',') !== false) {
                        $orgIds = explode(',', $storeOrgIds[0]);
                    } else {
                        $orgIds = array_filter($storeOrgIds);
                    }
                    
                    // 保存组织权限到 organization_ops（数据权限维度）
                    $this->saveOrganizationPermission($_POST['user_id'], $orgIds);
                } else {
                    // 如果没有组织权限，清空原有的组织权限
                    $this->saveOrganizationPermission($_POST['user_id'], []);
                }
                
                // 注意：store_id[] 通过 save_per 保存到 branch_ops（角色权限维度），与组织权限独立
                
                $users->save_per($_POST);

                //数据权限
                if ($_POST['org_id']) {
                    $users->save_operation_permission($_POST);
                }
                // 一件代发yjdf经销组织权限
                if ($_POST['dealer_shop_conf']) {
                    kernel::single('organization_cos')->save_operation_permission($_POST);
                }
                
                //保存成功后加判断是否启用状态有变更
                $user_data  = $users->dump($sdf_users['user_id'], '*', array(':account@pam' => array('*')));
                if ($sdf_users && ($sdf_users['status'] != $_POST['status'])) {
                    $_inner_key = sprintf("account_user_%s", $sdf_users['user_id']);
                    cachecore::store($_inner_key, $user_data, 60 * 15);
                }
    
                // IDAAS同步账号
                list($rs,$msg) = kernel::single('desktop_user_auth')->sync_account(array(
                    'account_id'        => $_POST['pam_account']['account_id'],
                    'login_name'        => $user_data['account']['login_name'],
                    'login_password'    => $user_data['account']['login_password'],
                ),'update');
                if ($rs === false) {
                    $this->end(false,$msg);
                }
                
                $this->end(true, app::get('desktop')->_('编辑成功！'));
            } else {
                $this->end(false, app::get('desktop')->_('请至少选择一个工作组！'));
            }
        }
    }
    /**
     * This is method edit
     * 添加编辑
     * @return mixed This is the return value description
     * 
     */
    #[RequirePermission('operator_edit')]
    public function edit($param_id)
    {
        $users     = $this->app->model('users');
        $roles     = $this->app->model('roles');
        $workgroup = $roles->getList('*');
        $user      = kernel::single('desktop_user');
        $sdf_users = $users->dump($param_id);
        if (empty($sdf_users)) {
            echo app::get('desktop')->_('无内容');exit();
        }

        $hasrole = $this->app->model('hasrole');
        foreach ($workgroup as $key => $group) {
            $rolesData = $hasrole->getList('*', array('user_id' => $param_id, 'role_id' => $group['role_id']));
            if ($rolesData) {
                $check_id[]                 = $group['role_id'];
                $workgroup[$key]['checked'] = "true";
            } else {
                $workgroup[$key]['checked'] = "false";
            }
        }
        $workgroups = $workgroup;
        $workgroup_dealer = [];
        foreach ($workgroups as $workgroup) {
            if ($this->get_show_branch($workgroup['role_id'])) {
                $workgroup_branch[] = $workgroup;
            } elseif ($this->get_o2o_branch($workgroup['role_id'])) {
                $workgroup_o2o_branch[] = $workgroup;
            } else {
                $workgroup_order[] = $workgroup;
            }

            // 一件代发，独立出来，只要有符合的都展示
            if ($this->get_dealer_workgroup($workgroup['role_id'])){
                $workgroup_dealer[] = $workgroup;
            } 
        }

        $operationOrgObj = app::get('ome')->model('operation_organization');
        $orgs            = $operationOrgObj->getList('*', array(), 0, -1);

        $operationOpsObj = app::get('ome')->model('operation_ops');
        $oper_per        = $operationOpsObj->getList('org_id', array('op_id' => $param_id), 0, -1);
        //$this->pagedata['org_id'] = $oper_per[0]['org_id'];

        $org_ids = array_column($oper_per, 'org_id');
        array_walk($orgs, function (&$item, $k, $org_ids) {
            $item['checked'] = in_array($item['org_id'], $org_ids);
        }, $org_ids);
        $this->pagedata['orgs'] = $orgs;

        #线下门店权限组
        $this->pagedata['workgroup_o2o_branch'] = $workgroup_o2o_branch;

        #echo('<pre>');print_r($workgroup_branch);exit;
        $this->pagedata['workgroup_branch'] = $workgroup_branch;
        $this->pagedata['workgroup_order']  = $workgroup_order;
        $this->pagedata['workgroup_dealer'] = $workgroup_dealer;
        #$this->pagedata['workgroup']        = $workgroups;
        $this->pagedata['account_id'] = $param_id;
        $this->pagedata['op_no']      = $sdf_users['op_no'];
        $this->pagedata['name']       = $sdf_users['name'];
        $this->pagedata['super']      = $sdf_users['super'];
        $this->pagedata['status']     = $sdf_users['status'];
        $this->pagedata['mcp_enabled'] = $sdf_users['mcp_enabled'] ?? 1;
        $this->pagedata['email']     = $sdf_users['email'];
        $this->pagedata['ismyself']   = $user->user_id === $param_id ? 'true' : 'false';

        if (!$sdf_users['super']) {
            $this->pagedata['per'] = $users->detail_per_group($check_id, $param_id);
        }

        //云生意或套件
        if (app::get('bizsuite')->is_actived()) {
            $bind = app::get('bizsuite')->model('relation')->getList('shop_id', array('node_type' => 'bizsuite', 'status' => 'bind'));
        }

        if ((app::get('suitclient')->is_installed() && app::get('suitclient')->getConf('client_id')) || $bind) {
            $this->pagedata['ban_edit'] = true;
        } else {
            $this->pagedata['ban_edit'] = false;
        }
        #登陆人员不是超级管理员，不能修改超级管理员密码
        if (($user->user_id != 1) && ($param_id == 1)) {
            $this->pagedata['ban_edit'] = true;
        }
        $this->pagedata['mobile']    = $sdf_users['mobile'];
        $this->pagedata['now_super'] = $user->is_super();
        if ($sdf_users['mobile']) {
            $this->pagedata['hidemobile'] = substr_replace($sdf_users['mobile'], "****", 3, 4);
        }

        // 一件代发yjdf 获取 经销组织权限
        $cosMdl     = app::get('organization')->model('cos');
        $cosOpsMdl  = app::get('organization')->model('cos_ops');
        $cosOpsInfo = $cosOpsMdl->db_dump(['op_id' => $param_id]);
        $cosList    = $cosMdl->getList('*', ['cos_id|in'=>explode(',', $cosOpsInfo['cos_ids'])]);
        $cosOpsList = ['cosCollectName'=>[], 'cosIds'=>[]];
        foreach ($cosList as $k => $v) {
            $cosOpsList['cosCollectName'][] = $v['cos_name'];
            $cosOpsList['cosIds'][]         = $v['cos_id'];
        }
        $cosOpsList['cosCollectName'] = implode(',', $cosOpsList['cosCollectName']);
        $cosOpsList['cosIds']         = implode(',', $cosOpsList['cosIds']);
        $this->pagedata['area']       = $cosOpsList;

        // 门店组织权限 - 从新的organization_ops表直接获取
        $storeOrgList = ['orgCollectName'=>[], 'orgIds'=>[]];
        
        // 直接从organization_ops表获取完整的组织权限
        if (app::get('organization')->is_installed()) {
            $orgOpsModel = app::get('organization')->model('organization_ops');
            $userOrgs = $orgOpsModel->getUserOrganizations($param_id);
            
            if (!empty($userOrgs)) {
                $organizationModel = app::get('organization')->model('organization');
                
                foreach ($userOrgs as $orgId) {
                    if (empty($orgId)) continue;
                    
                    // 直接获取组织信息
                    $orgInfo = $organizationModel->dump(['org_id' => $orgId], 'org_id,org_name,org_type');
                    if ($orgInfo) {
                        $storeOrgList['orgCollectName'][] = $orgInfo['org_name'];
                        $storeOrgList['orgIds'][] = $orgInfo['org_id'];
                    }
                }
                
                if (!empty($storeOrgList['orgCollectName'])) {
                    $storeOrgList['orgCollectName'] = implode(',', $storeOrgList['orgCollectName']);
                    $storeOrgList['orgIds'] = implode(',', $storeOrgList['orgIds']);
                } else {
                    $storeOrgList['orgCollectName'] = '';
                    $storeOrgList['orgIds'] = '';
                }
            } else {
                $storeOrgList['orgCollectName'] = '';
                $storeOrgList['orgIds'] = '';
            }
        }
        
        $this->pagedata['store_org'] = $storeOrgList;

        if($_GET['clone']) {
            $this->display('users/users_add.html');
        } else {
            $this->page('users/users_detail.html');
        }

    }

    //获取工作组细分
    #[RequirePermission('operator_edit')]
    public function detail_ground()
    {
        // //获取订单角色中的选中项
        // $check_group_id = json_decode($_POST['checkedName_group']);
        // //获取仓库角色中的选择项
        // $check_brach_id = json_decode($_POST['checkedName_branch']);
        // //获取仓库或订单角色类型
        // if (isset($_POST['role'])) {
        //     $role = $_POST['role'];
        // }
        // $role_id  = $_POST['name'];
        // $check_id = json_decode($_POST['checkedName']);
        // $branches = $_POST['branch'];

        $user_id = $_GET['user_id'];
        $role    = $_GET['role'];
        $check_id = explode(',', $_POST['checkedName']);

        echo kernel::single('desktop_user_access')->role($role, $check_id, $user_id, $_POST);exit;
    }

    protected function getBackInfo($role = null, $check_id)
    {

        $roles = $this->app->model('roles');
        $menus = $this->app->model('menus');
        if ($role == self::__BRANCH_ROLE) {
            //仓库角色，没有任何选中项
            if (empty($check_id)) {
                return array('group_info' => '', 'branch_info' => '');
                exit;
            }
        } elseif ($role == self::__STORE_ROLE) {
            //仓库角色，没有任何选中项
            if (empty($check_id)) {
                return array('o2o_branch_info' => '');
                exit;
            }
        } else {
            //非仓库角色
            if (!$check_id) {
                echo '';exit;
            }
        }
        $aPermission = array();
        /* if(!$check_id) {
        echo '';exit;
        } */
        foreach ($check_id as $val) {
            $result = $roles->dump($val);
            $data   = unserialize($result['workground']);
            foreach ((array) $data as $row) {
                $aPermission[] = $row;
            }
        }
        $aPermission = array_unique($aPermission);
        if (!$aPermission) {
            echo '';exit;
        }
        $addonmethod = array();
        $group_info = '';
        $branch_info = '';
        $o2o_branch_info = '';
        $html = '';
        foreach ((array) $aPermission as $val) {
            $sdf   = $menus->dump(array('menu_type' => 'permission', 'permission' => $val));
            $addon = unserialize($sdf['addon']);
            if ($addon['show'] && $addon['save']) {
                //如果存在控制
                if (!in_array($addon['show'], $addonmethod)) {
                    $access    = explode(':', $addon['show']);
                    $classname = $access[0];
                    $method    = $access[1];
                    $obj       = kernel::single($classname);
                    //仓库角色
                    if ($role == self::__BRANCH_ROLE) {
                        //检测是否包含订单确认
                        if ('show_group' == $method) {
                            $group_info .= $obj->$method() . "<br />";
                        }
                        //检测是否包含仓库选择
                        if ('show_branch' == $method) {
                            $branch_info .= $obj->$method() . "<br />";
                        }
                    } elseif ($role == self::__STORE_ROLE) {
                        //检测是否包含o2o门店仓库选择
                        $o2o_branch_info .= $obj->$method() . "<br />";

                    } else {
                        //订单角色(包含其他角色)
                        $html .= $obj->$method() . "<br />";
                    }
                }
                $addonmethod[] = $addon['show'];
            } else {
                echo '';
            }
        }
        //仓库角色的返回数据
        if ($role == self::__BRANCH_ROLE) {
            return $backDate = array('group_info' => $group_info, 'branch_info' => $branch_info);
        } elseif ($role == self::__STORE_ROLE) {
            return $backDate = array('group_info' => $group_info, 'o2o_branch_info' => $o2o_branch_info);
        } else {
            //订单角色(包含其他角色)的返回数据
            return $backDate = $html;
        }
    }

    //保存工作组细分
    /**
     * 保存_ground
     * @param mixed $aData 数据
     * @return mixed 返回操作结果
     */
    public function save_ground($aData)
    {
        $workgrounds = (array) ($aData['role'] ?? []);
        $menus       = $this->app->model('menus');
        $roles       = $this->app->model('roles');
        foreach ($workgrounds as $val) {
            $result = $roles->dump($val);
            $data   = unserialize($result['workground']);
            foreach ((array) $data as $row) {
                $aPermission[] = $row;
            }
        }
        $aPermission = array_unique($aPermission);
        if ($aPermission) {
            $addonmethod = array();
            foreach ((array) $aPermission as $key => $val) {
                $sdf   = $menus->dump(array('menu_type' => 'permission', 'permission' => $val));
                $addon = unserialize($sdf['addon']);
                if ($addon['show'] && $addon['save']) {
                    //如果存在控制
                    if (!in_array($addon['save'], $addonmethod)) {
                        $access    = explode(':', $addon['save']);
                        $classname = $access[0];
                        $method    = $access[1];
                        $obj       = kernel::single($classname);
                        $obj->$method($aData['user_id'], $aData);
                    }
                    $addonmethod[] = $addon['save'];
                }
            }
        }
    }

    /**
     * 获取仓库权限分组
     * 
     * @param  void
     * @return void
     * @author
     * */
    public function get_show_branch($role_id)
    {
        $roles  = $this->app->model('roles');
        $menus  = $this->app->model('menus');
        $result = $roles->dump($role_id);
        $data   = unserialize($result['workground']);
        foreach ((array) $data as $row) {
            $aPermission[] = $row;
        }
        $aPermission = array_unique($aPermission);
        if (!$aPermission) {
            return false;
        }
        $addonmethod = array();
        foreach ((array) $aPermission as $val) {

            #过滤线下门店权限分组
            if ($val == 'o2o_store_self' || $val == 'o2o_center') {
                return false;
            }

            $sdf   = $menus->dump(array('menu_type' => 'permission', 'permission' => $val));
            $addon = unserialize($sdf['addon']);
            if ($addon['show'] == 'ome_roles:show_branch') {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取线下门店权限分组
     * 
     * @param  void
     * @return void
     * @author
     * */
    public function get_o2o_branch($role_id)
    {
        $roles  = $this->app->model('roles');
        $menus  = $this->app->model('menus');
        $result = $roles->dump($role_id);
        $data   = unserialize($result['workground']);
        foreach ((array) $data as $row) {
            $aPermission[] = $row;
        }
        $aPermission = array_unique($aPermission);
        if (!$aPermission) {
            return false;
        }
        $addonmethod = array();
        foreach ((array) $aPermission as $val) {
            #Wap手机端门店权限分组
            if (strpos($val, 'wap_') === false) {
                continue;
            }

            $sdf   = $menus->dump(array('menu_type' => 'permission', 'permission' => $val));
            $addon = unserialize($sdf['addon']);

            if ($addon['show'] == 'ome_roles:show_o2o_branch') {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取一件代发的分销权限分组
     * 
     * @param  void
     * @return void
     * @author
     * */
    public function get_dealer_workgroup($role_id)
    {
        $roles  = $this->app->model('roles');
        $menus  = $this->app->model('menus');
        $result = $roles->dump($role_id);
        $data   = unserialize($result['workground']);
        foreach ((array) $data as $row) {
            $aPermission[] = $row;
        }
        $aPermission = array_unique($aPermission);
        if (!$aPermission) {
            return false;
        }
        $addonmethod = array();
        foreach ((array) $aPermission as $val) {
            #Wap手机端门店权限分组
            if (strpos($val, 'dealer_') === false) {
                continue;
            }

            $sdf   = $menus->dump(array('menu_type' => 'permission', 'permission' => $val));
            $addon = unserialize($sdf['addon']);

            if ($addon['show'] == 'ome_roles:show_dealer') {
                return true;
            }
        }

        return false;
    }

        /**
     * showCosTreeList
     * @param mixed $serid ID
     * @param mixed $multi multi
     * @return mixed 返回值
     */
    public function showCosTreeList($serid,$multi=false)
    {
         if ($serid)
         {
            $this->pagedata['sid'] = $serid;
         }
         else
         {
            $this->pagedata['sid'] = substr(time(),6,4);
         }

         $this->pagedata['multi'] = $multi;
         $this->pagedata['remoteURL'] = 'index.php?app=desktop&ctl=users&act=getCosById&{param}={value}';
         $this->pagedata['checkboxName'] = 'region';
         $this->pagedata['closeText'] = '全团队';
         $this->pagedata['dataMap'] = array(
             'PID' => 'parent_id',
             'NID' => 'cos_id', 
             'CNAME' => 'cos_name',
             'HASC' => 'child_count'
         );
         $this->singlepage('common/treeSelect.html');
    }

    /**
     * showStoreOrgTreeList
     * @param mixed $serid ID
     * @param mixed $multi multi
     * @return mixed 返回值
     */
    public function showStoreOrgTreeList($serid,$multi=false)
    {
         if ($serid)
         {
            $this->pagedata['sid'] = $serid;
         }
         else
         {
            $this->pagedata['sid'] = substr(time(),6,4);
         }

         $this->pagedata['multi'] = $multi;
         $this->pagedata['remoteURL'] = 'index.php?app=desktop&ctl=users&act=getOrgById&{param}={value}';
         $this->pagedata['checkboxName'] = 'organization';
         $this->pagedata['closeText'] = '全组织';
         $this->pagedata['dataMap'] = array(
             'PID' => 'parent_id',
             'NID' => 'org_id',
             'CNAME' => 'org_name', 
             'HASC' => 'child_count'
         );
         $this->singlepage('common/treeSelect.html');
    }

    /**
     * 获取CosById
     * @param mixed $pregionid ID
     * @return mixed 返回结果
     */
    public function getCosById($pregionid = 1)
    {
        !$pregionid && $pregionid = 1;
        $list = kernel::single('organization_cos')->getChildCosById($pregionid);
        if ($list[0] && $list[1]) {
            echo json_encode($list[1]);
        } else {
            echo json_encode([]);
        }
    }

    /**
     * 获取OrgById
     * @param mixed $porgid ID
     * @return mixed 返回结果
     */
    public function getOrgById($porgid = 0)
    {
        !$porgid && $porgid = 0;
        $organizationObj = kernel::single('organization_operation');
        $result = $organizationObj->getOrgForTreeSelect($porgid);
        
        echo json_encode($result);
    }

    #[RequirePermission('operator_add')]
    public function exportTemplate()
    {
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=操作员导入模板" . date('Ymd') . ".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $title = $this->app->model('users')->getTemplateColumn();
        echo '"' . implode('","', $title) . '"';
    }

    #[RequirePermission('operator_edit')]
    public function unLock($userId) {
        app::get('desktop')->model('users')->update(['is_lock'=>'0','lock_reason'=>''],['user_id'=>$userId]);
        $this->splash('success','index.php?app=desktop&ctl=users&act=index','操作成功');
    }

    #[RequirePermission('operator_edit')]
    public function showSensitiveData($id) {
        $data = app::get('desktop')->model('users')->db_dump(['user_id'=>$id]);
        $row = app::get('pam')->model('account')->db_dump(['account_id'=>$id], 'login_name');
        $data['login_name'] = $row['login_name'];
        $this->splash('success',null,null,'redirect',$data);
    }

    /**
     * 将门店组织ID转换为store_id数组
     * @param array $orgIds 组织ID数组
     * @return array store_id数组
     */
    private function convertOrgIdsToStoreIds($orgIds)
    {
        if (!app::get('o2o')->is_installed() || empty($orgIds)) {
            return [];
        }

        $organizationMdl = app::get('organization')->model('organization');
        $storeMdl = app::get('o2o')->model('store');
        $storeIds = [];

        foreach ($orgIds as $orgId) {
            if (empty($orgId)) continue;

            // 根据org_id获取组织信息
            $orgInfo = $organizationMdl->dump(['org_id' => $orgId], 'org_no,org_type');
            
            if ($orgInfo && $orgInfo['org_type'] == 2) { // org_type=2表示门店
                // 根据org_no获取门店信息
                $storeInfo = $storeMdl->dump(['store_bn' => $orgInfo['org_no']], 'store_id');
                
                if ($storeInfo) {
                    $storeIds[] = $storeInfo['store_id'];
                }
            }
        }

        return $storeIds;
    }
    
    /**
     * 保存用户的组织权限到 organization_ops 表
     * @param int $user_id 用户ID
     * @param array $orgIds 组织ID数组
     * @return bool 操作结果
     */
    private function saveOrganizationPermission($user_id, $orgIds) {
        if (empty($user_id)) {
            return false;
        }
        
        if (!app::get('organization')->is_installed()) {
            return true;
        }
        
        try {
            $orgOpsMdl = app::get('organization')->model('organization_ops');
            return $orgOpsMdl->saveUserOrganizations($user_id, $orgIds);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 展开组织权限为具体的store_id数组（支持经销商继承）
     * @param array $orgIds 组织ID数组
     * @return array store_id数组
     */
    private function expandOrgIdsToStoreIds($orgIds) {
        if (!app::get('o2o')->is_installed() || empty($orgIds)) {
            return [];
        }
        
        try {
            // 使用权限继承服务类来展开组织权限
            $permissionService = kernel::single('organization_organization_permission');
            
            // 模拟用户ID来获取展开的branch_id（这里用于store权限转换）
            $organizationMdl = app::get('organization')->model('organization');
            $storeMdl = app::get('o2o')->model('store');
            $storeIds = [];
            
            foreach ($orgIds as $orgId) {
                if (empty($orgId)) continue;
                
                // 获取组织信息
                $orgInfo = $organizationMdl->dump(['org_id' => $orgId], 'org_no,org_type');
                if (!$orgInfo) continue;
                
                if ($orgInfo['org_type'] == 3) { // 经销商
                    // 获取经销商下的所有门店
                    $dealerStores = $this->getStoresByDealerOrgId($orgId);
                    $storeIds = array_merge($storeIds, $dealerStores);
                    
                } elseif ($orgInfo['org_type'] == 2) { // 门店
                    // 直接获取门店信息
                    $storeInfo = $storeMdl->dump(['store_bn' => $orgInfo['org_no']], 'store_id');
                    if ($storeInfo) {
                        $storeIds[] = $storeInfo['store_id'];
                    }
                }
            }
            
            return array_unique($storeIds);
            
        } catch (Exception $e) {
            // 降级到原有逻辑
            return $this->convertOrgIdsToStoreIds($orgIds);
        }
    }
    
    /**
     * 获取经销商下的所有门店store_id
     * @param int $dealerOrgId 经销商组织ID
     * @return array store_id数组
     */
    private function getStoresByDealerOrgId($dealerOrgId) {
        $organizationMdl = app::get('organization')->model('organization');
        $storeMdl = app::get('o2o')->model('store');
        $storeIds = [];
        
        // 查找经销商下的所有门店组织 (parent_id = dealerOrgId, org_type = 2)
        $storeOrgs = $organizationMdl->getList('org_id,org_no', [
            'parent_id' => $dealerOrgId,
            'org_type' => 2
        ], 0, -1);
        
        if (!$storeOrgs) {
            return [];
        }
        
        foreach ($storeOrgs as $storeOrg) {
            $storeInfo = $storeMdl->dump(['store_bn' => $storeOrg['org_no']], 'store_id');
            if ($storeInfo) {
                $storeIds[] = $storeInfo['store_id'];
            }
        }
        
        return $storeIds;
    }

    private function normalizeMcpEnabled($value): string
    {
        return in_array($value, [true, 1, '1', 'true', 'on'], true) ? '1' : '0';
    }
}
