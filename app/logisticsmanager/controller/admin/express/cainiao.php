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
 * @author ykm 2016/6/16
 * @describe 菜鸟快递单模板二期
 */
class logisticsmanager_ctl_admin_express_cainiao extends desktop_controller {

    /**
     * preSyncTpl
     * @return mixed 返回值
     */

    public function preSyncTpl() {
        $this->pagedata['logistics_type'] = [
            'taobao'=>'淘宝电子面单',
            'pdd'=>'拼多多电子面单',
            '360buy'=>'京东快递',
            'jdalpha'=>'京东电子面单',
            'douyin'=>'抖音电子面单',
            'kuaishou'=>'快手电子面单',
            'wphvip'=>'唯品会vip',
            'sf'=>'顺丰电子面单',
            'xhs'=>'小红书电子面单',
            'wxshipin'=>'微信视频号电子面单',
            'dewu'=>'得物品牌直发电子面单',
            'meituan4bulkpurchasing'=>'美团电商',
            'youzan'=>'有赞电子面单',
            'aikucun'=>'爱库存电子面单',
        ];
        $this->display('admin/express/pre_sync_tpl.html');
    }

    /**
     * syncTpl
     * @return mixed 返回值
     */
    public function syncTpl() {
        $logisticsType = $_GET['logisticsType'];
        try {
            $templateObj = kernel::single('logisticsmanager_waybill_' . $logisticsType);
            $templateCfg = $templateObj->template_cfg();
        } catch (Exception $e){
            die('没有该类型');
        }
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->pagedata['channel_type'] = $logisticsType;
        $this->pagedata['template_cfg'] = $templateCfg;
        if($logisticsType == 'sf') {
            $this->display('admin/express/sync_tpl_sf.html');
            die();
        }
        $this->display('admin/express/sync_tpl_cainiao.html');
    }

    /**
     * doSyncTpl
     * @return mixed 返回值
     */
    public function doSyncTpl() {
        $channelType = $_POST['channel_type'];
        $rs = array();
        $channel = app::get('logisticsmanager')->model('channel')->getList('channel_id,shop_id,name,channel_type', array('channel_type'=>$channelType, 'status'=>'true'));
        if($channel) {
            $msg = '';
            $erpApi = kernel::single('erpapi_router_request');
            $logisticsType = kernel::single('logisticsmanager_waybill_' . $channelType);
            $templateCfg = $logisticsType->template_cfg();
            $modelTpl = app::get('logisticsmanager')->model('express_template');
            $tplIdFilter = $dealData = $allCpCode = array();
            $tplIdFilter['control_type'] = $templateCfg['control_type'];
            $rs = $erpApi->set('logistics', $channel[0]['channel_id'])->template_syncStandardTpl();

            
            if($rs['rsp'] == 'succ') {
                $requestResult = true;
                foreach($rs['data'] as $data) {
                    // $logistics = $logisticsType->logistics($data['cp_code']);
                    // if($logistics['name']) {
                        $data['source'] = $channel[0]['shop_id'];
                        $tplIdFilter['out_template_id'][] = $data['out_template_id'];
                        $allCpCode[$data['cp_code']] = $data['cp_code'];
                        $dealData[$data['tpl_index']] = $data;
                    // } 
                }
                $shopMdl = app::get('ome')->model('shop');

                $hasShopId = array();
                foreach($channel as $val) {
                    if(!$templateCfg['request_again'] && in_array($val['shop_id'], $hasShopId)) {continue;}

                    if(in_array($val['channel_type'], ['360buy', 'jdalpha'])){
                        list($jdbusinesscode,$jd_shop_id) = explode('|||',$val['shop_id']);
                        $val['shop_id']                = $jd_shop_id;
                    }

                    // if(!in_array($val['shop_id'], $bindShop)) continue;
                    if ($val['shop_id'] != '00000000' && !$shopMdl->count(['shop_id' => $val['shop_id'],'filter_sql'=>'node_id is not null and node_id!=""'])){
                        continue;
                    }

                    $hasShopId[] = $val['shop_id'];
                    $rs = $erpApi->set('logistics', $val['channel_id'])->template_syncUserTpl();
                   
                    if($rs['rsp'] == 'succ') {
                        foreach($rs['data'] as $data) {
                            $data['source'] = $val['shop_id'];
                            $tplIdFilter['out_template_id'][] = $data['out_template_id'];
                            $allCpCode[$data['cp_code']] = $data['cp_code'];
                            $dealData[$data['tpl_index']] = $data;
                        }
                    } else {
                        $requestResult = false;
                        $msg .= "<br/> 电子面单来源：{$val['name']} {$templateCfg['template_name']}模板同步失败,{$rs['msg']}";
                    }
                }
                $tplData = $modelTpl->getList('template_id, out_template_id, template_type', $tplIdFilter);
                foreach($tplData as $tVal) {
                    $tplIndex = '';
                    if(strpos($tVal['template_type'] , 'standard') !== false) {
                        $tplIndex = 'standard' . '-' . $tVal['out_template_id'];
                    } elseif(strpos($tVal['template_type'] , 'user') !== false) {
                        $tplIndex = 'user' . '-' . $tVal['out_template_id'];
                    }
                    if($tplIndex && $dealData[$tplIndex]) {
                        $dealData[$tplIndex]['template_id'] = $tVal['template_id'];
                    }
                }
                $updateTplId = array();
                $printTpl = kernel::single('logisticsmanager_print_tmpl');
               
                foreach($dealData as $dVal) {
                    $dVal['control_type'] = $templateCfg['control_type'];

                    $rs = $printTpl->save($dVal);

                    if($rs['rs'] == 'succ') {
                        $updateTplId[] = $rs['data']['template_id'];
                    } else {
                        if($dVal['template_id']) {
                            $updateTplId[] = $dVal['template_id'];
                            $msg .= "<br/> template_id：{$dVal['template_id']} 更新失败,{$rs['msg']}";
                        } else {
                            $msg .= "<br/> out_template_id：{$dVal['out_template_id']} 写入失败,{$rs['msg']}";
                        }
                    }
                }

                #删除没有涉及的菜鸟模板
                if($requestResult) {
                    // $modelTpl->delete(array(
                    //     'template_id|notin' => $updateTplId,  
                    //     'control_type'      => $templateCfg['control_type'], 
                    //     'cp_code'           => $allCpCode,
                    //     'template_type|notin'     => 'jd_user',
                    // ));
                }
            } else {
                $shopData = app::get('ome')->model('shop')->db_dump(array('shop_id'=>$channel[0]['shop_id']), 'name');
                $msg .= "<br/> 标准{$templateCfg['template_name']}模板获取失败(店铺:{$shopData['name']}),{$rs['msg']}。";
            }
            $rs['msg'] = $msg;
        } else {
            $rs['msg'] = '电子面单来源没有'.$channelType.'类型，无需同步';
        }
        if($rs['msg']) {
            $rs['rsp'] = 'fail';
        } else {
            $rs['rsp'] = 'succ';
            if($hasShopId) {
                $shopData = app::get('ome')->model('shop')->getList('name', array('shop_id' => $hasShopId));
                $rs['msg'] = '同步店铺(';
                foreach ($shopData as $val) {
                    $rs['msg'] .= $val['name'] . ',';
                }
                $rs['msg'] = trim($rs['msg'], ',') . ')的模板完成';
            }
        }
        echo json_encode($rs);
    }

    /**
     * 保存Tpl
     * @return mixed 返回操作结果
     */
    public function saveTpl() {
        $channelType = $_POST['channel_type'];
        $logisticsType = kernel::single('logisticsmanager_waybill_' . $channelType);
        $templateCfg = $logisticsType->template_cfg();
        $modelTpl = app::get('logisticsmanager')->model('express_template');
        $out_template_id = $_POST['templateCode'].'-'.$_POST['customTemplateCode'];
        $tplIdFilter = array();
        $tplIdFilter['control_type'] = $templateCfg['control_type'];
        $tplIdFilter['out_template_id'] = $out_template_id;
        $tplData = $modelTpl->db_dump($tplIdFilter, 'template_id, out_template_id, template_type');
        $data = array(
            'cp_code' => 'SF',
            'out_template_id' => $out_template_id,
            'template_name' => $_POST['temlateName'],
            'template_type' => 'sf',
            'template_data' => json_encode($_POST, JSON_UNESCAPED_UNICODE),
            'control_type' => $templateCfg['control_type']
        );
        if($tplData) {
            $data['template_id'] = $tplData['template_id'];
        }
        $printTpl = kernel::single('logisticsmanager_print_tmpl');
        $rs = $printTpl->save($data);
        if($rs['rs'] == 'succ') {
            $this->splash('success', 'index.php?app=logisticsmanager&ctl=admin_express_template', '操作成功');
        }
        $this->splash('error', 'index.php?app=logisticsmanager&ctl=admin_express_template', $rs['msg']);
    }
}