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

class ome_task{

    function post_install($options){

        // base_initial 已在 base_application_manage::install() 中于 post_install 前执行，勿重复 init，
        // 否则 initial/*.sql（如 ome.branch_type.sql）会执行两次并触发唯一键冲突。

        $this->_insert_role();

        if ($options['data'] == 1){
            //增加默认打印快递模板
            $this->_default_dlytpl();
        }

        if (isset($options['pd_ver'])){
            //存储当前的安装模式
            app::get('base')->setConf('ome.install.mode',$options['pd_ver']);
        }

        // 插入默认打印模板
        $this->insert_otmpl_default();

        //默认运营组织
        $this->_insert_operation_organization();

        if (!base_shopnode::node_id('ome') && base_certificate::certi_id()){
			base_shopnode::active('ome');
		}
    }


    function install_options(){
        return array(
                'data'=>array('type'=>'select','required'=>true,'vtype'=>'required','default'=>1,'title'=>'安装初始数据','options'=>array(
                    1=>'安装初始数据',0=>'不安装初始数据',
                )),
                'pd_ver'=>array('type'=>'select','required'=>true,'vtype'=>'required','default'=>1,'title'=>'安装模式','options'=>array(
                    1=>'正式环境',2=>'开发环境',
                )),
            );
    }

    function pre_uninstall()
    {
    	if (base_shopnode::node_id('ome')){
			base_shopnode::delete_node_id('ome');
		}
    }

    //$params['dbver'] 为当前版本号，当前本地版本号，就是待升级的版本号
    function pre_update($params){

    }

    //$params['dbver'] 为当前版本号，当前本地版本号，就是待升级的版本号
    function post_update($params){

        $dbver = $params['dbver'];

        if (version_compare($dbver, '1.0.9','<')) {
            // 插入默认打印模板
            $this->insert_otmpl_default();
        }
    }


    /*----------------------------私有方法---------------------------*/
    private function _current_version(){
        $appxml = kernel::single('base_xml')->xml2array(file_get_contents(ROOT_DIR.'/app/ome/app.xml'),'base_app');
        return $appxml['version'];
    }

    private function _set_current_version($ver){
        kernel::database()->exec("UPDATE sdb_base_apps SET local_ver='".$ver."' WHERE app_id='ome'");
    }

    private function _set_current_db_version($ver){
        kernel::database()->exec("UPDATE sdb_base_apps SET dbver='".$ver."' WHERE app_id='ome'");
    }


    //内置ome角色
    private $roles = array(
                        '接单员' => array('order_view','storage_stock_search'),      //角色名=>array('desktop.xml定义的permission','desktop.xml定义的permission')
                        '订单调度员' => array('order_dispatch'),
                        '订单确认员' => array('order_view','order_confirm'),
                        '异常订单处理员' => array('order_abnormal'),
                        '单据打印员' => array('wms_process_receipts_print','wms_process_product_refunded'),
                        '出库校验员' => array('wms_process_product_check'),
                        '发货员' => array('wms_process_consign'),
//                        '售后服务审核员' => array('aftersale_return_apply'),
//                        '退货收货员' => array('aftersale_sv_charge'),
//                        '退货质检员' => array('aftersale_sv_process'),
//                        '单据查看员' => array('invoice_order_payment','invoice_order_refund','invoice_delivery','invoice_reship','invoice_purchase_payments','invoice_credit_sheet','invoice_purchase_refunds','invoice_purchase_payments_cancel','invoice_clearingtables','invoice_countertables'),
                        '财务（订单）' => array('finance_payment_confirm','finance_refund_confirm'),
//                        '财务（采购）' => array('finance_purchase_payments','finance_credit_sheet','finance_purchase_refunds','finance_inventory_confirm'),
                        '仓库管理员' => array('wms_storage_stock_search','wms_storage_stock','wms_setting_branch_pos','wms_storage_branch_product_pos','wms_branch_pos_added','wms_storage_inventory_list','wms_storage_inventory_confirm'),
                        '商品管理员' => array('goods_view','goods_add','goods_type','goods_spec','goods_brand','goods_import'),
//                        '采购员' => array('purchase_need','purchase_po','purchase_supplier'),
//                        '入库员' => array('purchase_do_eo','purchase_eo'),
                    );

    private function _insert_role(){
        $oRoles = app::get('desktop')->model('roles');
        $db = kernel::database();
        foreach($this->roles as $key=>$role){
            $workground = array();
            foreach($role as $v){
                $workground[] = $v;
            }
            $data = array(
                'role_name' => $key,
                'workground' => $workground,
            );
            $oRoles->save($data);
        }
    }

    private function _update_role(){
        $oRoles = app::get('desktop')->model('roles');
        $db = kernel::database();
        foreach($this->roles as $key=>$role){
            $workground = array();
            foreach($role as $v){
                $workground[] = $v;
            }
            $data = array(
                'workground' => $workground,
            );
            $oRoles->update($data,array('role_name' => $key));
        }
    }

    private function _default_dlytpl(){
        $dlytpl_dir = ROOT_DIR."/app/ome/initial/dlytpl/";

        if($handle = opendir($dlytpl_dir)){
            while(false !== ($dtp = readdir($handle))){
                $path_parts = pathinfo($dtp);
                if($path_parts['extension'] == 'dtp'){
                    $file['tmp_name'] = ROOT_DIR."/app/ome/initial/dlytpl/".$dtp;
                    $file['name'] = $dtp;
                    $result = kernel::single("ome_print_tmpl")->upload_tmpl($file);
                }
            }

            closedir($handle);
        }
    }

    /**
     * 插入打印发货备货单默认值
     *
     * @return void
     * @author chenping<chenping@shopex.cn>
     **/
    private function insert_otmpl_default()
    {
        $otmplModel = app::get('ome')->model('print_otmpl');
        $count = $otmplModel->count();
        $defaultTmpl = $otmplModel->otmpl;
        // 插入默认模板
        if ($count == 0) {
            $dbTmpl = app::get('ome')->model('print_tmpl_diy');
            foreach ($defaultTmpl as $type => $tmpl) {
                $printTxt = $dbTmpl->get($tmpl['app'], $tmpl['defaultPath']);
                if ($printTxt) {
                    $printTxt = $otmplModel->bodyFilter($printTxt,true,$type);
                    $data = array(
                        'title' => app::get('ome')->_('默认').$tmpl['name'],
                        'type' => $type,
                        'content' => $printTxt,
                        'is_default' => 'true',
                        'last_modified' => time(),
                        'open' => 'true',
                    );
                    $otmplModel->save($data);
                    $path = 'admin/print/otmpl/'.$data['id'];
                    $otmplModel->update(array('path'=>$path),array('id'=>$data['id']));
                }
            }
        }
    }

    private function _insert_operation_organization(){
        $operationOrgObj = app::get('ome')->model('operation_organization');
        $org_data = array(
            'name' => '总公司',
            'code' => 'head_office',
            'status' => '1',
        );
        $operationOrgObj->insert($org_data);
    }
}
