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
 * 售后服务类
 **/
class ome_return 
{
    const __EDIT_RETURN_CODE = 0x00020;
    const __EDIT_CHANGE_CODE = 0x00040;
    private $boolStatus = array(
        self::__EDIT_RETURN_CODE => array('identifier'=>'换转退', 'text'=>'换转退', 'color'=>'LimeGreen','search'=>'true'),
        self::__EDIT_CHANGE_CODE => array('identifier'=>'退转换', 'text'=>'退转换', 'color'=>'#D2B48C','search'=>'true'),
    );
    
    function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * @description 权限
     * @access public
     * @param void
     * @return void
     */

    public function chkground($workground,$url_params,$permission_id='') 
    {
        static $group;

        if($workground == 'desktop_ctl_recycle') { return true;}
        if($workground == 'desktop_ctl_dashboard') { return true;}
        if($workground == ''){return true;}
        if($_GET['ctl'] == 'adminpanel') return true;
        $menus = app::get('desktop')->model('menus');

        if (!$group) {
            $userLib = kernel::single('desktop_user');
            $group = $userLib->group();
        }
        
        $permission_id = $permission_id ? $permission_id : $menus->permissionId($url_params);
        if($permission_id == '0'){return true;}

        return in_array($permission_id,$group) ? true : false;
    }
    
    /**
     * 生成售后单.
     * @param   type    $varname    description
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function add($sdf,$shop_id,&$msg="操作失败",&$logTitle="",&$logInfo=""){
        $shop = app::get("ome")->model("shop");
        $shop_row = $shop->db->selectrow("select node_id,node_type from sdb_ome_shop where shop_id='".$shop_id."'");
        $log = app::get('ome')->model('api_log');
        $sdf['node_id'] = $shop_row['node_id'];
        base_rpc_service::$node_id = $sdf['node_id'];
        $rs = kernel::single('erpapi_router_response')
            ->set_node_id($sdf['node_id'])
            ->set_api_name('shop.aftersalev2.add')
            ->dispatch($sdf, false);
        $data = array('tid'=>$sdf['tid']);
        $rs['rsp'] == 'success';
        $logTitle = $rs['logTitle'];
        $logInfo = $rs['logInfo'];
        $msg = '';
        
        return true;
    }

    function get_return_log($sdf_return,$shop_id,&$msg){
        $log = app::get('ome')->model('api_log');

        $result = $this->add($sdf_return,$shop_id,$msg,$logTitle,$logInfo);

        $class = 'ome_rpc_response_aftersalev2';

        $method = 'add';

        $rsp = 'fail';

        if($result){
            $rsp = 'success';
        }

        return $result;
    }
    
    /**
     * 此方法已弃用，请不要使用
     * 
     * 
     * 抖音平台与京东云交易售后原因
     */
    public function getYjdfReshipResaon()
    {
        $shopResaon = array();
        
        //退货
        $return = array(
                '5' => array(
                        'reason_code'=>'5',
                        'reason_name'=>'收到商品少件 / 错件 / 空包裹',
                        'jd_reason_code'=>'6',
                        'jd_reason_name'=>'少/错商品',
                ),
                '20' => array(
                        'reason_code'=>'20',
                        'reason_name'=>'少件／漏发',
                        'jd_reason_code'=>'6',
                        'jd_reason_name'=>'少/错商品',
                ),
                '8' => array(
                        'reason_code'=>'8',
                        'reason_name'=>'功能故障',
                        'jd_reason_code'=>'193',
                        'jd_reason_name'=>'质量问题',
                ),
                '22' => array(
                        'reason_code'=>'22',
                        'reason_name'=>'商家发错货',
                        'jd_reason_code'=>'7',
                        'jd_reason_name'=>'发错货',
                ),
                '6' => array(
                        'reason_code'=>'6',
                        'reason_name'=>'不喜欢 / 效果不好',
                        'jd_reason_code'=>'205',
                        'jd_reason_name'=>'不合适/不满意',
                ),
                '31' => array(
                        'reason_code'=>'31',
                        'reason_name'=>'做工粗糙 / 有瑕疵 / 有污渍',
                        'jd_reason_code'=>'5',
                        'jd_reason_name'=>'商品损坏/包装脏污',
                ),
                '10' => array(
                        'reason_code'=>'10',
                        'reason_name'=>'商品材质 / 品牌 / 外观等描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '11' => array(
                        'reason_code'=>'11',
                        'reason_name'=>'生产日期 / 保质期 / 规格等描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '16' => array(
                        'reason_code'=>'16',
                        'reason_name'=>'大小／尺寸／重量与商品描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '18' => array(
                        'reason_code'=>'18',
                        'reason_name'=>'品种／规格／成分等描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '25' => array(
                        'reason_code'=>'25',
                        'reason_name'=>'品种／产品／规格／成分等描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '28' => array(
                        'reason_code'=>'28',
                        'reason_name'=>'规格等描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '15' => array(
                        'reason_code'=>'15',
                        'reason_name'=>'其他',
                        'jd_reason_code'=>'9',
                        'jd_reason_name'=>'其他',
                ),
        );
        
        //换货
        $change = array(
                '10' => array(
                        'reason_code'=>'10',
                        'reason_name'=>'商品材质 / 品牌 / 外观等描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '11' => array(
                        'reason_code'=>'11',
                        'reason_name'=>'生产日期 / 保质期 / 规格等描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '16' => array(
                        'reason_code'=>'16',
                        'reason_name'=>'大小／尺寸／重量与商品描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '18' => array(
                        'reason_code'=>'18',
                        'reason_name'=>'品种／规格／成分等描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '25' => array(
                        'reason_code'=>'25',
                        'reason_name'=>'品种／产品／规格／成分等描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '28' => array(
                        'reason_code'=>'28',
                        'reason_name'=>'规格等描述不符',
                        'jd_reason_code'=>'8',
                        'jd_reason_name'=>'商品与页面描述不符',
                ),
                '5' => array(
                        'reason_code'=>'5',
                        'reason_name'=>'收到商品少件 / 错件 / 空包裹',
                        'jd_reason_code'=>'6',
                        'jd_reason_name'=>'少/错商品',
                ),
                '20' => array(
                        'reason_code'=>'20',
                        'reason_name'=>'少件／漏发',
                        'jd_reason_code'=>'6',
                        'jd_reason_name'=>'少/错商品',
                ),
                '8' => array(
                        'reason_code'=>'8',
                        'reason_name'=>'功能故障',
                        'jd_reason_code'=>'193',
                        'jd_reason_name'=>'质量问题',
                ),
                '22' => array(
                        'reason_code'=>'22',
                        'reason_name'=>'商家发错货',
                        'jd_reason_code'=>'7',
                        'jd_reason_name'=>'发错货',
                ),
                '31' => array(
                        'reason_code'=>'31',
                        'reason_name'=>'做工粗糙 / 有瑕疵 / 有污渍',
                        'jd_reason_code'=>'5',
                        'jd_reason_name'=>'商品损坏/包装脏污',
                ),
                '15' => array(
                        'reason_code'=>'15',
                        'reason_name'=>'其他',
                        'jd_reason_code'=>'402',
                        'jd_reason_name'=>'其他',
                ),
        );
        
        //return
        $shopResaon['return'] = $return;
        $shopResaon['change'] = $change;
        
        return $shopResaon;
    }
    
    public function getBoolTypeText($num = null) {
        if($num) {
            return (array) $this->boolStatus[$num];
        }
        return $this->boolStatus;
    }

    /**
     * 获取BoolTypeIdentifier
     * @param mixed $boolType boolType
     * @param mixed $shop_type shop_type
     * @return mixed 返回结果
     */
    public function getBoolTypeIdentifier($boolType,$shop_type = 'taobao') {
        $str = '';
        foreach ($this->boolStatus as $k => $val) {

            if ($boolType & $k) {
                
                $str .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#ffffff;'>%s</span>", $val['text'], $val['color'], $val['identifier']);
            }
        }
        return $str;
    }

    /**
     * 换转退
     * @param  
     * @return 
     */
    public function processReturn($sdf){
        $reshipMdl = app::get('ome')->model('reship');
        $return_productMdl = app::get('ome')->model('return_product');
        $logMdl = app::get('ome')->model('operation_log');
        $return_id = $sdf['return_product']['return_id'];
        $reship = $sdf['reship'];
        $itemsMdl = app::get('ome')->model('reship_items');
        $reshipMdl = app::get('ome')->model('reship');
        $log_msg = '换转退';
        if($sdf['change_return_type']==true){
            if($reship){
                $reship_id = $reship['reship_id'];
                // 释放换货冻结
                kernel::single('console_reship')->releaseChangeFreeze($reship_id);
                //删除明细作变化处理
                if($reship['change_order_id']>0 || $reship['change_status']=='1'){
                   

                    $orderMdl = app::get('ome')->model('orders');
                    $rs = $orderMdl->cancel($reship['change_order_id'], '线上取消换货', false, 'async');
                    if($rs['rsp'] == 'fail') {
                        $orderMdl->pauseOrder($reship['change_order_id'], false,'线上取消换货而取消换出订单失败');

                        $changeorders = $orderMdl->dump(array('order_id'=>$reship['change_order_id']),'order_id,pause,order_bn');

                        if($changeorders['pause']!='true'){

                            kernel::single('ome_bill_label_delivery')->ToChangeOrderLabel($reship['change_order_id']);

                        }
                    } else {
                        $tmpOrder = $orderMdl->db_dump(array('order_id'=>$reship['change_order_id']),'order_id,order_bn,payed,shop_id');
                        if($tmpOrder['payed'] > 0) {
                            kernel::single('ome_reship_luban')->refundOrder($tmpOrder);
                        }
                    }
                    
                    
                    $log_msg.= '暂停换出订单';
                }else{
                    $itemsMdl->delete(['reship_id'=>$reship_id,'return_type'=>'change']);
                    $log_msg.= '删除换货明细';
                }
                $upData['return_type'] = 'return';
                $upData['totalmoney'] = $sdf['refund_fee'];
                $reshipMdl->update($upData,array('reship_id'=>$reship_id));
            } elseif ($return_id) {
                // 释放换货冻结
                kernel::single('ome_return_product')->releaseChangeFreeze($return_id);
            }
            
            $flag_type = ome_reship_const::__EDIT_RETURN_CODE;
            
            $updata = array(
                'flag_type'     =>  $flag_type,
                'return_type'   => 'return',
            );
            $return_productMdl->update($updata,array('return_id'=>$return_id));
            
            // log
            $logMdl->write_log('return@ome', $return_id, $log_msg);
        }
    }

    /**
     * 退转换
     * @param  
     * @return 
     */
    public function processChange($sdf){
        $return_productMdl = app::get('ome')->model('return_product');
        $reship = $sdf['reship'];
        $logMdl = app::get('ome')->model('operation_log');
        $return_id = $sdf['return_product']['return_id'];
        $log_msg = '';
        if($sdf['change_return_type']==true){

            $log_msg = '退转换';
            $flag_type = ome_reship_const::__EDIT_CHANGE_CODE;
            $updata = array(
                'flag_type' =>  $flag_type,
                'return_type'=> 'change',
                'status'    =>'1',
            );
            $return_productMdl->update($updata,array('return_id'=>$return_id));
            if($reship){
                $reship_id = $reship['reship_id'];
                
                if(!in_array($reship['is_check'],array('7','11'))){
                 
                    if (in_array($sdf['status'],array('3','6'))) {
                        $data = array(
                            'status'    => '3',
                            'return_id' => $return_id,
                            'outer_lastmodify' => $sdf['modified'],
                            'content'       =>$sdf['reason'],
                            'choose_type_flag' => '1',
                        );
                        $log_msg.= '变更类型:'.$data['status'];
                        $return_productMdl->tosave($data, true, $error_msg);
                    }
                }
            }
            
            $logMdl->write_log('return@ome', $return_id, $log_msg);

        }
    }

    /**
     * cancelChangeOrder
     * @param mixed $change_order_id ID
     * @return mixed 返回值
     */
    public function cancelChangeOrder($change_order_id){
        $orderMdl = app::get('ome')->model('orders');
        $rs = $orderMdl->cancel($change_order_id, '线上取消换货', false, 'async');
        if($rs['rsp'] == 'fail') {
            $orderMdl->pauseOrder($change_order_id);

            $changeorders = $orderMdl->dump(array('order_id'=>$change_order_id),'order_id,pause,order_bn');

            if($changeorders['pause']!='true'){
                kernel::single('ome_bill_label_delivery')->ToChangeOrderLabel($change_order_id);
            }
        }else{
            $tmpOrder = $orderMdl->db_dump(array('order_id'=>$change_order_id),'order_id,order_bn,payed,shop_id');
            if($tmpOrder['payed'] > 0) {
                kernel::single('ome_reship_luban')->refundOrder($tmpOrder);
            }
        }
    }
    
    public function pauseChangeOrder($change_order_id){
        $orderMdl = app::get('ome')->model('orders');
        $orderMdl->pauseOrder($change_order_id);
        kernel::single('ome_bill_label_delivery')->ToChangeOrderLabel($change_order_id);
        $orderMdl->db->exec("UPDATE sdb_ome_orders  set  is_delivery='N' where order_id=".$change_order_id."");
        $logMdl = app::get('ome')->model('operation_log');
        $logMdl->write_log('order_modify@ome',$change_order_id,'换出订单:暂停并更新为:不可以发货状态');
    }

    /**
     * changeToRefund
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function changeToRefund($data){
        $order_bn = $data['order_bn'];
        $oid = $data['oid'];
       
        $db = kernel::database();
        if($order_bn=='' || $oid==''){
          
            return true;
        }

        $shop_type = $data['shop_type'];

        if($shop_type=='tmall'){
            $sql = "SELECT b.change_order_id,b.reship_bn FROM sdb_ome_return_product_tmall AS a LEFT JOIN sdb_ome_reship AS b ON a.return_id=b.return_id
                WHERE a.oid='". $oid ."' AND  a.refund_type='change' AND b.change_order_id>0 AND b.return_type='change'";
            $reshipInfo = $db->selectrow($sql);
        }
        
        if(empty($reshipInfo)){
            return false;
        }
        
        //获取天猫订单换货生成的OMS新订单
        $sql = "SELECT order_id ,order_bn,payed,shop_id FROM sdb_ome_orders WHERE relate_order_bn='". $order_bn ."' AND order_id=". $reshipInfo['change_order_id'];

        $tmpOrder = $db->selectrow($sql);
        if(empty($tmpOrder)){
            return false;
        }

        if($tmpOrder['payed'] > 0) {
            $label_code = 'SOMS_MREFUND';
            kernel::single('ome_bill_label')->markBillLabel($tmpOrder['order_id'], '', $label_code, 'order', $err, 0);
            kernel::single('ome_reship_luban')->refundOrder($tmpOrder);
        }
    }
}
