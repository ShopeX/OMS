<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

/**
 * mdl_user
 *
 * @uses modelFactory
 * @package
 * @version $Id: mdl.user.php 1985 2008-04-28 06:36:02Z flaboy $
 * @copyright 2003-2007 ShopEx
 * @author Likunpeng <leoleegood@zovatech.com>
 * @license Commercial
 */

class desktop_mdl_users extends dbeav_model
{
    //是否有导出配置
    var $has_export_cnf = true;
    var $export_name = '管理员';

    public $has_parent = array(
        'pam_account' => 'account@pam',
    );
    public $has_many = array(
        'roles' => 'hasrole:replace',
    );
    public $subSdf = array(
        'default' => array(
            'pam_account:account@pam' => array('*'),
        ),
        'delete'  => array(
            'pam_account:account@pam' => array('*'),
            'roles'                   => array('*'),
        ),
    );

    private $templateColumn = array(
        '*:用户名*'       => 'login_name',
        '*:姓名*'        => 'name',
        '*:手机号码*'      => 'mobile',
        '*:密码*'        => 'login_password',
        '*:工号'         => 'op_no',
        '*:工作角色(以|间隔)' => 'role',
        '*:仓库编码(以|间隔)' => 'branch',
        '*:运营组织(以|间隔)' => 'org_id',
        '*:启用'         => 'status',
        '*:备注'         => 'memo',
        '*:邮箱*'        => 'email',
    );

    public function pre_recycle($data)
    {
        $obj_pam = app::get('pam')->model('account');
        $falg    = true;
        $users   = kernel::single('desktop_user');
        foreach ($data as $val) {
            if ($users->user_id == $val['user_id']) {
                $this->recycle_msg = app::get('desktop')->_('自己不能删除自己');
                $falg              = false;
                break;
            } else {
                if ($val['user_id'] == 1) {
                    $this->recycle_msg = app::get('desktop')->_('子账号不能删除超级管理员');
                    $falg              = false;
                    break;

                }
            }
        }

        $this->app->model('user_logs')->deleteUser($data);

        return $falg;
    }

    public function pre_restore(&$data, $restore_type = 'add')
    {
        if (!($this->check_name($data['pam_account']['login_name']))) {
            $data['need_delete'] = true;
            return true;
        } else {
            if ($restore_type == 'add') {
                $new_name = $data['pam_account']['login_name'] . '_1';
                while ($this->check_name($new_name)) {
                    $new_name = $new_name . '_1';
                }
                $data['pam_account']['login_name'] = $new_name;
                $data['need_delete']               = true;
                return true;
            }
            if ($restore_type == 'none') {
                $data['need_delete'] = false;
                return true;
            }
        }
    }

    /**
     * editUser
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function editUser(&$data)
    {
        if ($data['userpass']) {
            // 编辑用户时，如果传入了 is_hash256，使用传入的值；否则使用新加密方式（1）
            $is_hash256 = 1; // 默认使用新加密方式
            if (isset($data['pam_account']['is_hash256'])) {
                // 如果传入了 is_hash256，使用传入的值
                $is_hash256 = intval($data['pam_account']['is_hash256']);
            }
            // 使用指定的加密方式生成新密码
            $data[':account@pam']['login_password'] = pam_encrypt::get_encrypted_password(trim($data['userpass']), pam_account::get_account_type($this->app->app_id), $is_hash256);
        }
        /*
        else{
        $data[':account@pam']['login_password'] = trim($data['oldpass']);
        }
         */
        $data['pam_account']['account_type'] = pam_account::get_account_type($this->app->app_id);
        //$data['pam_account']['createtime']   = time();

        if (isset($data['email'])) {
            $data['email'] = trim($data['email']);
        }

        $data['mobile'] = trim($data['mobile']);

        return parent::save($data);
    }
    ###

    ##检查用户名
    public function check_name($login_name)
    {
        $pam          = app::get('pam')->model('account');
        $account_type = pam_account::get_account_type($this->app->app_id);
        $aData        = $pam->getList('*', array('login_name' => $login_name, 'account_type' => $account_type));
        $result       = $aData[0]['account_id'];
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    ###更新登陆信息

    public function update_admin($user_id)
    {

        $aUser                             = $this->dump($user_id, '*');
        $sdf[':account@pam']['account_id'] = $user_id;
        $sdf['lastlogin']                  = time();
        $sdf['logincount']                 = $aUser['logincount'] + 1;
        $this->save($sdf);
    }

    ##检查
    public function validate($aData, &$msg)
    {

        if ($aData['pam_account']['login_name'] == '' || $aData['pam_account']['login_password'] == '' || $aData['name'] == '') {
            $msg = app::get('desktop')->_('必填项不能为空');
            return false;
        }

        $userLib = kernel::single('desktop_user');
        if (!$userLib->checkUserPassWordLength('',$aData['pam_account']['login_password'], $error_msg)) {
            $msg = app::get('desktop')->_($error_msg);
            return false;
        }
        if (!$userLib->validPassWord($aData['pam_account']['login_password'], $error_msg)) {
            $msg = app::get('desktop')->_($error_msg);
            return false;
        }

        if (!$userLib->checkPassword($aData['pam_account']['login_password'], $error_msg)) {
            $msg = app::get('desktop')->_($error_msg);
            return false;
        }

        if ($aData['pam_account']['login_password'] != $_POST['re_password']) {
            $msg = app::get('desktop')->_('两次密码输入不一致');
            return false;
        }
        if (strlen($aData['mobile']) != '11' || !is_numeric($aData['mobile'])) {
            $msg = app::get('desktop')->_('手机号码格式不正确');
            return false;
        }
        $result = $this->check_name($aData['pam_account']['login_name']);

        if ($result) {
            $msg = app::get('desktop')->_('该用户名已存在');
            return false;

        }
        return true;
    }

    //获取工作组细分
    public function detail_per($check_id, $user_id)
    {
        $roles       = $this->app->model('roles');
        $menus       = $this->app->model('menus');
        $aPermission = array();
        if (!$check_id) {
            echo '';exit;
        }
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
        $html = '';
        $addonmethod = array();
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
                    $html .= $obj->$method($user_id);
                }
                $addonmethod[] = $addon['show'];
            } else {
                echo '';
            }
        }
        return $html;
    }

    //保存工作组细分
    public function save_per($aData)
    {
        $workgrounds = $aData['role'];
        $menus       = $this->app->model('menus');
        $roles       = $this->app->model('roles');
        $aPermission = [];
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
    //获取工作组细分
    public function detail_per_group($check_id, $user_id)
    {
        $roles       = $this->app->model('roles');
        $menus       = $this->app->model('menus');
        $aPermission = array();
        if (!$check_id) {
            return [];
        }
        foreach ($check_id as $val) {
            $result = $roles->dump($val);
            $data   = unserialize($result['workground']);
            foreach ((array) $data as $row) {
                $aPermission[] = $row;
            }
        }
        $aPermission = array_unique($aPermission);
        if (!$aPermission) {
            return [];
        }
        $addonmethod = array();
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

                    $info = $obj->$method($user_id);
                    if ($addon['show'] == 'ome_roles:show_branch') {
                        $html['branch'] = $info;
                    } elseif ($addon['show'] == 'ome_roles:show_o2o_branch') {
                        $html['o2o_branch'] = $info; #o2o门店线下仓库
                    } else {
                        $html['order'] = $info;
                    }
                }

                $addonmethod[] = $addon['show'];
            } else {
                //echo '';
            }
        }
        return $html;
    }

    /**
     *
     * 彻底删除管理员的同时，清除相关的角色权限关联信息
     * @param int $userid
     */
    public function suf_delete($userid)
    {
        $hasRoleObj = app::get('desktop')->model('hasrole');
        $hasRoleObj->delete(array('user_id' => $userid));
        return true;
    }

    public function modifier_mobile($col, $list, $row)
    {
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($col);

        if (!$is_encrypt) return $col;

        $id = $row['user_id'];
        $encryptCol = kernel::single('ome_view_helper2')->modifier_ciphertext($col,'member','name');

        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=desktop&ctl=users&act=showSensitiveData&p[0]={$id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="mobile">{$encryptCol}</span></span>
HTML;
        return $col?$return:$col;
    }

    public function modifier_name($col, $list, $row)
    {
        if ($this->is_export_data) {
            return $col;
        }

        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($col);

        if (!$is_encrypt) return $col;

        $id = $row['user_id'];
        $encryptCol = kernel::single('ome_view_helper2')->modifier_ciphertext($col,'member','name');

        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=desktop&ctl=users&act=showSensitiveData&p[0]={$id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="name">{$encryptCol}</span></span>
HTML;
        return $col?$return:$col;
    }

    public function modifier_user_id($col, $list, $row)
    {
        $rowAc = app::get('pam')->model('account')->db_dump(['account_id'=>$col], 'login_name');
        $col = $rowAc['login_name'];
        $is_encrypt = kernel::single('ome_security_hash')->check_encrypt($col);

        if (!$is_encrypt) return $col;

        $id = $row['user_id'];
        $encryptCol = kernel::single('ome_view_helper2')->modifier_ciphertext($col,'member','name');

        $return =<<<HTML
<a class="data-hide" href="javascript:void(0);" onclick="Ex_Loader('security',function(){new Security({url:'index.php?app=desktop&ctl=users&act=showSensitiveData&p[0]={$id}',clickElement:\$(event.target)}).desHtml(\$(event.target).getNext()); });"></a><span><span sensitive-field="login_name">{$encryptCol}</span></span>
HTML;
        return $col?$return:$col;
    }

    public function searchOptions()
    {
        $parentOptions = parent::searchOptions();
        $childOptions  = array(
            'login_name' => app::get('ome')->_('用户名'),
            'name'       => app::get('ome')->_('姓名'),
        );
        return $Options = array_merge($parentOptions, $childOptions);
    }

    public function _filter($filter, $tableAlias = null, $baseWhere = null)
    {
        $where = array();
        //pam_account表 用户名
        if (isset($filter['login_name'])) {
            $mdl_pam_account = app::get('pam')->model('account');
            $rs_pam_account  = $mdl_pam_account->dump(array("login_name" => addslashes($filter['login_name'])));
            if (!empty($rs_pam_account)) {
                $where[] = "user_id=" . $rs_pam_account["account_id"];
            } else {
                $where[] = "user_id=-1";
            }
            unset($filter['login_name']);
        }
        if (isset($filter['name'])) {
            $where[] = "name='" . addslashes($filter['name']) . "'";
            unset($filter['name']);
        }
        //desktop_users表 姓名
        $sWhere = parent::_filter($filter, $tableAlias, $baseWhere);
        if (!empty($where)) {
            $sWhere .= " AND " . implode(" and ", $where);
        }
        return $sWhere;
    }

    public function save_operation_permission($params)
    {
        if (!$params['user_id'] || !$params['org_id']) {
            return false;
        }

        $operationOpsObj = app::get('ome')->model('operation_ops');
        //删除原权限
        $operationOpsObj->delete(array('op_id' => $params['user_id']));

        foreach ($params['org_id'] as $org_id) {
            //保存现有
            $addOperPer = array(
                'org_id' => $org_id,
                'op_id'  => $params['user_id'],
            );
            $operationOpsObj->insert($addOperPer);
        }

    }

    public function getTemplateColumn()
    {
        $title = array();
        foreach (array_keys($this->templateColumn) as $v) {
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        return $title;
    }

    public function prepared_import_csv()
    {
        $this->ioObj->cacheTime = time();
    }

    public function prepared_import_csv_row($row, &$title, &$tmpl, &$mark, &$newObjFlag, &$msg)
    {
        if (empty($row)) {
            return false;
        }

        foreach ($row as $k => $v) {
            $encode = mb_detect_encoding($v, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            if ('UTF-8' != $encode) {
                $v = mb_convert_encoding($v, 'UTF-8', $encode);
            }

            $row[$k] = $v;
        }

        if (substr($row[0], 0, 1) == '*') {
            $this->nums         = 1;
            $this->allLoginName = array();
            $this->import_data  = array();
            $title              = array_flip($row);

            foreach ($this->templateColumn as $k => $val) {
                if (!isset($title[$k])) {
                    $msg['error'] = '请使用正确的模板';
                    return false;
                }
            }
            return false;
        }
        if (empty($title)) {
            $msg['error'] = "请使用正确的模板格式！";
            return false;
        }
        $arrData = array();
        foreach ($this->templateColumn as $k => $val) {
            $arrData[$val] = trim($row[$title[$k]]);
        }
        if (isset($this->nums)) {
            $this->nums++;
            if ($this->nums > 5000) {
                $msg['error'] = "导入的数据量过大，请减少到5000条以下！";
                return false;
            }
        }
        if (empty($arrData['login_name'])) {
            $msg['warning'][] = 'Line ' . $this->nums . '：用户名不能为空！';
            return false;
        }

        // 判断账号是否存在
        $account_id = null;
        $account = app::get('pam')->model('account')->db_dump(['login_name' => $arrData['login_name']], 'login_name,account_id');
        if ($account) {
            $user = app::get('desktop')->model('users')->db_dump(['user_id' => $account['account_id']], 'user_id,super');
            if ($user && $user['super'] == '1') {
                $msg['warning'][] = 'Line ' . $this->nums . '：超级管理员不能导入！';
                return false;
            }

            $account_id = $account['account_id'];
        }

        if (empty($arrData['name'])) {
            $msg['warning'][] = 'Line ' . $this->nums . '：姓名不能为空！';
            return false;
        }

        if (empty($arrData['login_password']) && !$account_id) {
            $msg['warning'][] = 'Line ' . $this->nums . '：密码不能为空！';
            return false;
        }

        if (in_array($arrData['login_name'], $this->allLoginName)) {
            $msg['warning'][] = 'Line ' . $this->nums . '：用户名重复！';
            return false;
        }

        $this->allLoginName[$this->nums] = $arrData['login_name'];

        if (strlen($arrData['mobile']) != '11' || !is_numeric($arrData['mobile'])) {
            $msg['warning'][] = 'Line ' . $this->nums . '：手机号码格式不正确！';
            return false;
        }
        $roleId  = array();
        $rolesId = array();
        if ($arrData['role']) {
            $roleName  = explode('|', $arrData['role']);
            $rolesData = app::get('desktop')->model('roles')->getList('role_id', array('role_name' => $roleName));
            if (empty($rolesData)) {
                $msg['warning'][] = 'Line ' . $this->nums . '：工作角色没有找到！';
                return false;
            }
            foreach ($rolesData as $roles) {
                $roleId[]  = $roles['role_id'];
                $rolesId[] = $roles;
            }
        }

        $branchId          = array();
        $selected_store_bn = '';
        if ($arrData['branch']) {
            $branchBn   = explode('|', $arrData['branch']);
            $branchData = app::get('ome')->model('branch')->getList('branch_id,b_type,branch_bn', array('branch_bn' => $branchBn, 'check_permission' => 'false'));
            if (empty($branchData)) {
                $msg['warning'][] = 'Line ' . $this->nums . '：仓库未找到！';
                return false;
            }
            foreach ($branchData as $val) {
                if ($val['b_type'] == '2') {
                    $selected_store_bn = $val['branch_bn'];
                } else {
                    $branchId[] = $val['branch_id'];

                }
            }
        }
        $orgId = [];
        if($arrData['org_id']) {
            $orgName = explode('|', $arrData['org_id']);
            $orgData = app::get('ome')->model('operation_organization')->getList('org_id', ['name'=>$orgName]);
            if (empty($orgData)) {
                $msg['warning'][] = 'Line ' . $this->nums . '：组织未找到！';
                return false;
            }
            $orgId = array_column($orgData, 'org_id');
        }
        $accountType = pam_account::get_account_type('desktop');
        $sdf         = array(
            'pam_account'       => array(
                'login_name'     => $arrData['login_name'],
                'login_password' => pam_encrypt::get_encrypted_password($arrData['login_password'], $accountType),
                'account_type'   => $accountType,
                'create_time'    => time(),
                'account_id'     => $account_id,
            ),
            'op_no'             => $arrData['op_no'],
            'memo'              => $arrData['memo'],
            'mobile'            => $arrData['mobile'],
            'email'             => $arrData['email'],
            'name'              => $arrData['name'],
            'status'            => $arrData['status'] == '否' ? '0' : '1',
            'role'              => $roleId,
            'roles'             => $rolesId,
            'selected_store_bn' => $selected_store_bn,
            'branch'            => $branchId,
            'org_id'            => $orgId,

        );
        $this->import_data[] = $sdf;
        $mark                = 'contents';
        return true;
    }

    public function prepared_import_csv_obj($data, $mark, $tmpl, &$msg = '')
    {
        // $shop = app::get('pam')->model('account')->db_dump(array('login_name' => $this->allLoginName), 'login_name');
        // if ($shop) {
        //     $key          = array_search($shop['login_name'], $this->allLoginName);
        //     $msg['error'] = 'Line ' . $key . '：用户名已经存在！';
        //     return false;
        // }

        return null;
    }

    public function finish_import_csv()
    {
        $oQueue    = app::get('base')->model('queue');
        $queueData = array(
            'queue_title' => '操作人员信息导入',
            'start_time'  => time(),
            'params'      => array(
                'sdfdata' => $this->import_data,
            ),
            'worker'      => 'desktop_mdl_users.import_run',
        );
        $oQueue->save($queueData);

        $oQueue->flush();
    }

    public function import_run($cursor_id, $params, $errormsg)
    {
        $imData = $params['sdfdata'];
        foreach ($imData as $opData) {
            try {

                $account_id = $opData['pam_account']['account_id'];
                if ($account_id) {
                    // 不允许更新账号及密码
                    $opData['pam_account'] = ['account_id' => $account_id];
                    // 更新账号
                    if ($this->editUser($opData)) {
                        $this->save_per($opData);
                        if ($opData['org_id']) {
                            $this->save_operation_permission($opData);
                        }

                        app::get('desktop')->model('user_logs')->userEdit($opData);
                        
                        // IDAAS同步账号 - 更新
                        $user_data = $this->dump($account_id, '*', array(':account@pam' => array('*')));
                        if ($user_data) {
                            kernel::single('desktop_user_auth')->sync_account(array(
                                'account_id'        => $account_id,
                                'login_name'        => $user_data['account']['login_name'],
                                'login_password'    => $user_data['account']['login_password'],
                            ), 'update');
                        }
                    }
                } else {
                    // 创建账号
                    if ($this->save($opData)) {
                        foreach (kernel::servicelist('desktop_useradd') as $key => $service) {
                            if ($service instanceof desktop_interface_useradd) {
                                $service->useradd($opData);
                            }
                        }
                        $this->save_per($opData);
                        $this->save_operation_permission($opData);
    
                        app::get('desktop')->model('user_logs')->addUser($opData);
                        
                        // IDAAS同步账号 - 新增
                        $user_data = $this->dump($opData['pam_account']['account_id'], '*', array(':account@pam' => array('*')));
                        if ($user_data) {
                            kernel::single('desktop_user_auth')->sync_account(array(
                                'account_id'        => $opData['pam_account']['account_id'],
                                'login_name'        => $user_data['account']['login_name'],
                                'login_password'    => $user_data['account']['login_password'],
                            ), 'add');
                        }
                    }
                }


            } catch (Exception $e) {}
        }
        return false;
    }
    
    function fgetlist_csv( &$data,$filter,$offset,$exportType = 1 )
    {
        $fields = $filter['export_fields'];
        unset($filter['export_fields']);
        $params = array(
            'fields' => $fields,
            'filter' => $filter,
            'has_detail' => false,
            'curr_sheet' => 1,
        );
        
        $exportLib = kernel::single('desktop_finder_export');
        $data = $exportLib->work(__CLASS__,$params);
        
        return false;
    }
    
}
