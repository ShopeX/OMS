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

class logisticsmanager_ctl_admin_channel extends desktop_controller
{
    //1.渠道能获取哪些快递单号
    //2.渠道获取到的快递单号哪些店铺能用。
    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $this->finder('logisticsmanager_mdl_channel', array(
            'actions'             => array(
                array('label' => '添加来源', 'href' => 'index.php?app=logisticsmanager&ctl=admin_channel&act=add', 'target' => 'dialog::{width:620,height:600,title:\'来源添加/编辑\'}'),
                array('label' => '启用', 'submit' => 'index.php?app=logisticsmanager&ctl=admin_channel&act=toStatus&status=true', 'target' => 'refresh'),
                array('label' => '停用', 'submit' => 'index.php?app=logisticsmanager&ctl=admin_channel&act=toStatus&status=false', 'target' => 'refresh'),
                array('label' => '查询订购关系', 'href' => $this->url.'&act=queryNetsiteDialog', 'target' => 'dialog::{width:720,height:560,title:\'查询订购关系\'}'),
            ),
            'title'               => '电子面单来源',
            'use_buildin_recycle' => false,
            'use_buildin_setcol'  => false,
            'orderBy'             => 'status ASC',
        ));

        $html = <<<EOF
        <script>
              $$(".show_list").addEvent('click',function(e){
                  var billtype = this.get('billtype');
                  var channel_id = this.get('channel_id');
                  var t_url ='index.php?app=logisticsmanager&ctl=admin_waybill&act=findwaybill&channel_id='+channel_id+'&billtype='+billtype;
              var url='index.php?app=desktop&act=alertpages&goto='+encodeURIComponent(t_url);
        Ex_Loader('modedialog',function() {
            new finderDialog(url,{width:1000,height:660,

            });
        });
              });

        </script>
EOF;
        echo $html;exit;
    }

    /**
     * 添加
     * @return mixed 返回值
     */
    public function add()
    {
        $this->_edit();
    }

    /**
     * edit
     * @param mixed $channel_id ID
     * @return mixed 返回值
     */
    public function edit($channel_id)
    {
        $this->_edit($channel_id);
    }

    private function _edit($channel_id = null)
    {
        if ($channel_id) {
            $channelObj = $this->app->model("channel");
            $channel    = $channelObj->dump($channel_id);
            if ($channel['channel_type'] == 'ems') {
                $emsinfo              = explode('|||', $channel['shop_id']);
                $channel['emsuname']  = $emsinfo[0];
                $channel['emspasswd'] = $emsinfo[1];
            } elseif ($channel['channel_type'] == '360buy') {
                $jdinfo                    = explode('|||', $channel['shop_id']);
                $channel['shop_id']        = $jdinfo[1];
                $channel['jdbusinesscode'] = $jdinfo[0];
            } elseif ($channel['channel_type'] == 'sf') {
                $sfinfo                    = explode('|||', $channel['shop_id']);
                $channel['sfbusinesscode'] = $sfinfo[0];
                $channel['sfpassword']     = $sfinfo[1];
                $channel['pay_method']     = $sfinfo[2];
                $channel['sfcustid']       = $sfinfo[3];
                $channel['sfapiversion']   = $sfinfo[4];
            } elseif ($channel['channel_type'] == 'yunda') {
                $yundainfo                = explode('|||', $channel['shop_id']);
                $channel['yundauname']    = $yundainfo[0];
                $channel['yundapassword'] = $yundainfo[1];
            } elseif ($channel['channel_type'] == 'sto') {
                $stoinfo                 = explode('|||', $channel['shop_id']);
                $channel['sto_custname'] = $stoinfo[0];
                $channel['sto_cutsite']  = $stoinfo[1];
                $channel['sto_cutpwd']   = $stoinfo[2];
            } elseif ($channel['channel_type'] == 'hqepay') {
                $hqepay_info                  = explode('|||', $channel['shop_id']);
                $channel['hqepay_uname']      = $hqepay_info[0];
                $channel['hqepay_password']   = $hqepay_info[1];
                $channel['pay_method']        = $hqepay_info[2];
                // $channel['hqepay_month_code'] = $hqepay_info[3];
                $channel['exp_type']          = $hqepay_info[4]; #快递类型在第5个位置
                $channel['hqepay_safemail']   = $hqepay_info[5]; #隐私面单在第6个位置
            } elseif ($channel['channel_type'] == 'taobao') {
              
            } elseif ($channel['channel_type'] == 'unionpay') {
                $unionpay_info                  = explode('|||', $channel['shop_id']);
                $channel['unionpay_uname']      = $unionpay_info[0];
                $channel['unionpay_password']   = $unionpay_info[1];
                $channel['pay_method']          = $unionpay_info[2];
                $channel['unionpay_month_code'] = $unionpay_info[3];

            } elseif ($channel['channel_type'] == 'jdalpha') {
                $jdalphainfo                     = explode('|||', $channel['shop_id']);
                $channel['shop_id']              = $jdalphainfo[1];
                $channel['jdalpha_businesscode'] = $jdalphainfo[0];
                $channel['jdalpha_vendorcode']   = $jdalphainfo[2];
                $channel['pay_method']           = $jdalphainfo[3];
                $channel['exp_type']             = $jdalphainfo[4]; #快递类型在第5个位置
            } elseif ($channel['channel_type'] == 'aikucun') {

            } elseif ($channel['channel_type'] == 'pinjun') {
                $pj_info                      = explode('|||', $channel['shop_id']);
                $channel['pinjun_uname']      = $pj_info[0];
                $channel['pinjun_password']   = $pj_info[1];
                $channel['pay_method']        = $pj_info[2]; //pinjun_month_code
                $channel['pinjun_month_code'] = $pj_info[3];

            } elseif ($channel['channel_type'] == 'pdd') {
                if ($_POST['pdd_shop_id']) {
                    $_POST['shop_id'] = $_POST['pdd_shop_id'];
                    $channel['shop_id']  = $_POST['shop_id'];
                }
            } elseif ($channel['channel_type'] == 'douyin') {
                if ($_POST['douyin_shop_id']) {
                    $_POST['shop_id'] = $_POST['douyin_shop_id'];
                    $channel['shop_id']  = $_POST['shop_id'];
                }
            } elseif ($channel['channel_type'] == 'kuaishou') {
                if ($_POST['kuaishou_shop_id']) {
                    $_POST['shop_id'] = $_POST['kuaishou_shop_id'];
                    $channel['shop_id']  = $_POST['shop_id'];
                }
            }elseif ($channel['channel_type'] == 'dewu') {
                if ($_POST['dewu_shop_id']) {
                    $_POST['shop_id'] = $_POST['dewu_shop_id'];
                    $channel['shop_id']  = $_POST['shop_id'];
                }
            } elseif ($channel['channel_type'] == 'jdgxd') {
                $jdgxdinfo             = explode('|||', $channel['shop_id']);
                $channel['shop_id']    = $jdgxdinfo[0];
                $channel['pay_method'] = $jdgxdinfo[1];
                $channel['jdgxd_vendorcode'] = $jdgxdinfo[2];
                $channel['jdgxd_month_code'] = $jdgxdinfo[3];
            }
            $shopSql = "SELECT shop_id,name FROM sdb_ome_shop";
        } else {
            $shopSql = "SELECT shop_id,name FROM sdb_ome_shop WHERE node_type in ('taobao','alibaba') and node_id IS NOT NULL AND node_id!=''";
        }

        //来源类型信息
        $funcObj                    = kernel::single('logisticsmanager_waybill_func');
        $channels                   = $funcObj->channels();
        if ($channel_id) {
            $channels['youzan']     = ['code'=>'youzan', 'name'=>'有赞电子面单'];
        }
        $this->pagedata['channels'] = $channels;

        $shopModel = app::get('ome')->model('shop');

        // 京东店铺
        $this->pagedata['jdshopList'] = array(0=>array('shop_id' => '00000000','name'=>'--无京东店铺--'));
        $jdshopList = $shopModel->getList('shop_id,name',array('node_type'=>['360buy','jd'],'filter_sql'=>'node_id IS NOT NULL'));
        $this->pagedata['jdshopList'] = array_merge($this->pagedata['jdshopList'],$jdshopList);

        // 爱库存店铺
        $this->pagedata['akcshopList'] = array(0 => array('shop_id' => '00000000', 'name' => '--无爱库存店铺--'));
        $akcshopList                   = $shopModel->getList('shop_id,name', array('node_type' => 'aikucun', 'filter_sql' => 'node_id IS NOT NULL'));
        $this->pagedata['akcshopList'] = array_merge($this->pagedata['akcshopList'], $akcshopList);

        // 拼多多店铺
        $this->pagedata['pddshopList'] = $shopModel->getList('shop_id,name',array('node_type'=>'pinduoduo','filter_sql'=>'node_id IS NOT NULL'));
        //美团电商
        $this->pagedata['meituan4bulkpurchasingshopList'] = $shopModel->getList('shop_id,name',array('node_type'=>'meituan4bulkpurchasing','filter_sql'=>'node_id IS NOT NULL'));

        //获取店铺列表
        $shopList                   = kernel::database()->select($shopSql);
        $this->pagedata['shopList'] = $shopList;

        // 抖音店铺
        $this->pagedata['douyinshopList'] = $shopModel->getList('shop_id,name', array('node_type' => 'luban', 'filter_sql' => 'node_id IS NOT NULL'));

        // 快手店铺
        $this->pagedata['kuaishoushopList'] = $shopModel->getList('shop_id,name',array('node_type'=>['kuaishou','ks'],'filter_sql'=>'node_id IS NOT NULL'));

        //唯品会店铺
        $this->pagedata['wphvipshopList'] = $shopModel->getList('shop_id,name',array('node_type'=>['vop'],'filter_sql'=>'node_id IS NOT NULL'));

        // 新小红书
        $this->pagedata['xhsshopList'] = $shopModel->getList('shop_id,name',array('node_type'=>['xhs'],'filter_sql'=>'node_id IS NOT NULL'));
        $this->pagedata['xhsbillversion'] = [
            ['billVersion'=>'1', 'name'=>'老版本'],
            ['billVersion'=>'2', 'name'=>'新版本'],
        ];

        // 微信视频
        $this->pagedata['wxshipinshopList'] = $shopModel->getList('shop_id,name',array('node_type'=>['wxshipin'],'filter_sql'=>'node_id IS NOT NULL'));
    
        // 得物店铺
        $this->pagedata['dewushopList'] = $shopModel->getList('shop_id,name',array('node_type'=>['dewu'],'filter_sql'=>'node_id IS NOT NULL'));

        // 有赞店铺
        $this->pagedata['youzanshopList'] = $shopModel->getList('shop_id,name',array('node_type'=>['youzan'],'filter_sql'=>'node_id IS NOT NULL'));

        //物流公司信息
        if ($channel['channel_type']) {
            $wlbObj                      = kernel::single('logisticsmanager_waybill_' . $channel['channel_type']);
            $logistics                   = $wlbObj->logistics('', $channel['shop_id']);
            $this->pagedata['logistics'] = $logistics;
        }
        if (isset($channel['pay_method'])) {
            $wlbObj                       = kernel::single('logisticsmanager_waybill_' . $channel['channel_type']);
            $pay_method                   = $wlbObj->pay_method();
            $this->pagedata['pay_method'] = $pay_method;
        }
        $this->pagedata['channel'] = $channel;

        $this->display("admin/channel/channel.html");
    }

    /**
     * do_save
     * @return mixed 返回值
     */
    public function do_save()
    {
        $data                 = array();
        $data['name']         = $_POST['name'];
        $data['channel_type'] = $_POST['channel_type'];
        $channelObj           = $this->app->model('channel');
        if ($_POST['channel_id']) {
            $channelRow    = $channelObj->db_dump(array('channel_id' => $_POST['channel_id']), 'logistics_code');
            $logisticsCode = $channelRow['logistics_code'];
        } else {
            $logisticsCode = $_POST['logistics_code'];
        }
        $channelLogistics = '';
        $channelTypeAllIndex = $data['channel_type'] . '_' . $logisticsCode . '_all';
        if ($_POST[$channelTypeAllIndex]) {
            $data['service_code'] = '';
            $channelLogisticsAll  = json_decode($_POST[$channelTypeAllIndex], 1);
            $channelLogistics     = $_POST[$data['channel_type'] . '_' . $logisticsCode];
            if ($channelLogisticsAll) {
                foreach ($channelLogisticsAll as $key => &$value) {
                    $value['value'] = isset($channelLogistics[$key]) ? $channelLogistics[$key] : 0;
                }
                $data['service_code'] = json_encode($channelLogisticsAll);
            }
        }

        if ($data['channel_type'] == 'ems') {
            $_POST['shop_id'] = $_POST['emsuname'] . '|||' . $_POST['emspasswd'];
            $data['shop_id']  = $_POST['shop_id'];

        } elseif ($data['channel_type'] == '360buy') {
            $_POST['shop_id'] = $_POST['jdbusinesscode'] . '|||' . $_POST['jd_shop_id'];
            $data['shop_id']  = $_POST['shop_id'];
        } elseif ($data['channel_type'] == 'taobao') {
            if ($_POST['taobao_shop_id']) {
                $_POST['shop_id'] = $_POST['taobao_shop_id'];
                $data['shop_id']  = $_POST['shop_id'];
            }
            if($_POST['exp_type']) $data['exp_type']  = $_POST['exp_type'];
        } elseif ($data['channel_type'] == 'aikucun') {
            if ($_POST['aikucun_shop_id']) {
                $_POST['shop_id'] = $_POST['aikucun_shop_id'];
                $data['shop_id']  = $_POST['shop_id'];
            }
        }elseif ($data['channel_type'] == 'pdd'){
            if ($_POST['pdd_shop_id']) {
                $_POST['shop_id'] = $_POST['pdd_shop_id'];
                $data['shop_id'] = $_POST['shop_id'];
            }
        }elseif($data['channel_type'] == 'dewu'){
            if ($_POST['dewu_shop_id']) {
                $_POST['shop_id'] = $_POST['dewu_shop_id'];
                $data['shop_id']  = $_POST['shop_id'];
            }
        }
        elseif ($data['channel_type'] == 'sf') {
            if ($_POST['channel_id']) {
                $channel                = $channelObj->dump(array('channel_id' => $_POST['channel_id']));
                $sfinfo                 = explode('|||', $channel['shop_id']);
                $_POST['sf_pay_method'] = $sfinfo[2];
            }
            if (!$_POST['sfapiversion'] || $_POST['sfapiversion'] != 'v2') {
                $_POST['sfapiversion'] = 'v1';
            }
            $_POST['shop_id'] = $_POST['sfbusinesscode'] . '|||' . $_POST['sfpassword'] . '|||' . $_POST['sf_pay_method'] . '|||' . $_POST['sfcustid'] . '|||' . $_POST['sfapiversion'];
            $data['shop_id']  = $_POST['shop_id'];

        } elseif ($data['channel_type'] == 'yunda') {
            $_POST['shop_id'] = $_POST['yundauname'] . '|||' . $_POST['yundapassword'];
            $data['shop_id']  = $_POST['shop_id'];

        } elseif ($data['channel_type'] == 'sto') {
            $_POST['shop_id'] = $_POST['sto_custname'] . '|||' . $_POST['sto_cutsite'] . '|||' . $_POST['sto_cutpwd'];
            $data['shop_id']  = $_POST['shop_id'];

        } elseif ($data['channel_type'] == 'customs') {
            $_POST['shop_id'] = $_POST['sto_custname'] . '|||' . $_POST['sto_cutsite'] . '|||' . $_POST['sto_cutpwd'];
            $data['shop_id']  = $_POST['shop_id'];
        } elseif ($data['channel_type'] == 'hqepay') {
            #编辑的时候，支付方式,参照顺丰，不允许改
            if ($_POST['channel_id']) {
                $channel                    = $channelObj->dump(array('channel_id' => $_POST['channel_id']));
                $sfinfo                     = explode('|||', $channel['shop_id']);
                $_POST['hqepay_pay_method'] = $sfinfo[2];
            }
            $_POST['hqepay_month_code'] = '';
            if ($channelLogistics['month_code']) {
                $_POST['hqepay_month_code'] = $channelLogistics['month_code'];
            }
            !$_POST['hqepay_safemail'] && $_POST['hqepay_safemail'] = 0;
            $_POST['shop_id'] = $_POST['hqepay_uname'] . '|||' . $_POST['hqepay_password'] . '|||' . $_POST['hqepay_pay_method'] . '|||' . $_POST['hqepay_month_code'] . "|||" . $_POST['exp_type'] . "|||" . $_POST['hqepay_safemail'];
            $data['shop_id']  = $_POST['shop_id'];

        } elseif ($data['channel_type'] == 'unionpay') {
            if ($_POST['channel_id']) {
                $channel                      = $channelObj->dump(array('channel_id' => $_POST['channel_id']));
                $sfinfo                       = explode('|||', $channel['shop_id']);
                $_POST['unionpay_pay_method'] = $sfinfo[2];

            }
            $_POST['shop_id'] = $_POST['unionpay_uname'] . '|||' . $_POST['unionpay_password'] . '|||' . $_POST['unionpay_pay_method'] . '|||' . $_POST['unionpay_month_code'];
            $data['shop_id']  = $_POST['shop_id'];

            $is_ome_bind_unionpay = false;
            
            //检测是否已绑定银联
            base_kvstore::instance('ome/bind/unionpay')->fetch('ome_bind_unionpay', $is_ome_bind_unionpay);
            if (!$is_ome_bind_unionpay) {
                $bind_status = kernel::single('erpapi_router_request')->set('unionpay', '1705101437')->unionpay_bind();

                if ($bind_status == true) {

                    $data['bind_status'] = 'true';
                }
            }
        } elseif ($data['channel_type'] == 'jdalpha') {
            //编辑的时候，支付方式,不允许改
            if ($_POST['channel_id']) {
                $channel = $channelObj->dump(array('channel_id' => $_POST['channel_id']));
                $sfinfo  = explode('|||', $channel['shop_id']);
                if ($channel['logistics_code'] == 'SF') {
                    $_POST['logistics_code']     = 'SF';
                    $_POST['jdalpha_pay_method'] = $sfinfo[3];
                }
                if ($sfinfo[1]) {
                    $_POST['jdalpha_shop_id'] = $sfinfo[1];
                }

            }
            if ($_POST['logistics_code'] == 'SF') {
                $_POST['shop_id'] = $_POST['jdalpha_businesscode'] . '|||' . $_POST['jdalpha_shop_id'] . '|||' . $_POST['jdalpha_vendorcode'] . '|||' . $_POST['jdalpha_pay_method'] . "|||" . $_POST['exp_type'];
            } else {
                $_POST['shop_id'] = $_POST['jdalpha_businesscode'] . '|||' . $_POST['jdalpha_shop_id'] . '|||' . $_POST['jdalpha_vendorcode'];
            }
            $data['shop_id'] = $_POST['shop_id'];
        } else if ($data['channel_type'] == 'pinjun') {
            if ($_POST['channel_id']) {
                $channel                    = $channelObj->dump(array('channel_id' => $_POST['channel_id']));
                $pjinfo                     = explode('|||', $channel['shop_id']);
                $_POST['pinjun_pay_method'] = $pjinfo[2];

            }
            $data['shop_id'] = $_POST['shop_id'] =  $_POST['pinjun_uname'].'|||'. $_POST['pinjun_password'] . '|||' . $_POST['pinjun_pay_method'].'|||'.$_POST['pinjun_month_code'];
        } elseif ($data['channel_type'] == 'douyin') {
            if ($_POST['douyin_shop_id']) {
                $_POST['shop_id'] = $_POST['douyin_shop_id'];
                $data['shop_id']  = $_POST['shop_id'];
            }
        } elseif ($data['channel_type'] == 'meituan4bulkpurchasing') {
            if ($_POST['meituan4bulkpurchasing_shop_id']) {
                $_POST['shop_id'] = $_POST['meituan4bulkpurchasing_shop_id'];
                $data['shop_id']  = $_POST['shop_id'];
            }
        } elseif($data['channel_type'] == 'kuaishou'){
            if ($_POST['kuaishou_shop_id']) {
                $_POST['shop_id'] = $_POST['kuaishou_shop_id'];
                $data['shop_id']  = $_POST['shop_id'];
            }
        } elseif($data['channel_type'] == 'wphvip'){
            if ($_POST['wphvip_shop_id']) {
                $_POST['shop_id'] = $_POST['wphvip_shop_id'];
                $data['shop_id']  = $_POST['shop_id'];
            }
        } elseif ($data['channel_type'] == 'vopjitx') {
            $_POST['shop_id'] = 'vopjitx';
        } elseif($data['channel_type'] == 'xhs'){
            if ($_POST['xhs_shop_id']) {
                $_POST['shop_id'] = $_POST['xhs_shop_id'];
                $data['shop_id']  = $_POST['shop_id'];
            }
            $data['ver'] = '1';
            if ($_POST['billVersion'] == '2') {
                $data['ver'] = '2';
            }
        } elseif($data['channel_type'] == 'wxshipin'){
            if ($_POST['wxshipin_shop_id']) {
                $_POST['shop_id'] = $_POST['wxshipin_shop_id'];
                $data['shop_id']  = $_POST['shop_id'];
            }
        } elseif ($data['channel_type'] == 'jdgxd') {
            if ($_POST['channel_id']) {
                $channel                    = $channelObj->dump(array('channel_id' => $_POST['channel_id']));
                $jdgxdinfo                     = explode('|||', $channel['shop_id']);
                $_POST['jdgxd_pay_method']    = $jdgxdinfo[1];
            }
            $_POST['shop_id'] = $_POST['jdgxd_shop_id'] . '|||' . $_POST['jdgxd_pay_method']. '|||' . $_POST['jdgxd_vendorcode']. '|||' . $_POST['jdgxd_month_code'];
            $data['shop_id']  = $_POST['shop_id'];
        }

        if ($_POST['channel_id']) {
            //更新渠道
            $channelObj->update($data, array('channel_id' => $_POST['channel_id']));
            $data['channel_id'] = $_POST['channel_id'];
        } else {
            if (!$_POST['shop_id']) {
                echo '请选择主店铺!';
                exit;
            }
            if (!$_POST['logistics_code']) {
                echo '请选择物流公司!';
                exit;
            }
            $filter = array(
                'shop_id'        => $_POST['shop_id'],
                'logistics_code' => $_POST['logistics_code'],
                'name'           => $_POST['name'],
            );
            if ($data['channel_type'] == 'ems') {

                $filter['channel_type'] = 'ems';
                unset($filter['shop_id']);
                $filter['shop_id|head'] = $_POST['emsuname'];
            } elseif ($data['channel_type'] == '360buy') {
                $filter['channel_type'] = '360buy';
                $filter['shop_id']      = $_POST['jdbusinesscode'] . '|||';
                //unset($filter['shop_id']);
            } elseif ($data['channel_type'] == 'taobao') {
                $filter['channel_type'] = 'taobao';
            } elseif ($data['channel_type'] == 'sf') {
                $filter['channel_type'] = 'sf';
            } elseif ($data['channel_type'] == 'yunda') {
                $filter['channel_type'] = 'yunda';

                unset($filter['shop_id']);
            } elseif ($data['channel_type'] == 'hqepay') {
                $filter['channel_type'] = 'hqepay';
                unset($filter['shop_id']);
            } elseif ($data['channel_type'] == 'unionpay') {
                $filter['channel_type']   = 'unionpay';
                $filter['logistics_code'] = $_POST['logistics_code'];
                unset($filter['shop_id']);
            } elseif ($data['channel_type'] == 'jdalpha') {
                $filter['channel_type']   = 'jdalpha';
                $filter['shop_id']        = $filter['shop_id'];
                $filter['logistics_code'] = $_POST['logistics_code'];
            } elseif ($data['channel_type'] == 'vopjitx') {
                $filter['channel_type'] = 'vopjitx';
                unset($filter['name']);
            }elseif($data['channel_type'] == 'dewu'){
                $filter['channel_type'] = 'dewu';
            } elseif ($data['channel_type'] == 'jdgxd') {
                $filter['channel_type']   = 'jdgxd';
                $filter['shop_id']        = $filter['shop_id'];
                $filter['logistics_code'] = $_POST['logistics_code'];
            }
            $channel = $channelObj->dump($filter, 'channel_id');
            if ($channel) {
                echo '已经添加过相同来源，无需重复添加!';
                exit;
            }

            //添加渠道
            $data['shop_id']        = $_POST['shop_id']; //不允许更新
            $data['logistics_code'] = $_POST['logistics_code']; //不允许更新
            $data['create_time']    = time();
            $channelObj->insert($data);

        }
        $channel_detail = $channelObj->dump(array('channel_id' => $data['channel_id']), 'bind_status');

        //发送绑定关系
        if ($channel_detail['bind_status'] == 'false' && in_array($data['channel_type'], array('sto', 'yunda', 'sf', 'ems', 'pinjun'))) {

            $bind_status = kernel::single('erpapi_router_request')->set('logistics', $data['channel_id'])->electron_bind();

            if ($bind_status) {
                $channelObj->update(array('bind_status' => 'true'), array('channel_id' => $data['channel_id']));
                $channel_detail['bind_status'] = 'true';
            }

        }
        if ($channel_detail['bind_status'] == 'true' && $data['channel_type'] == 'sto') {
            kernel::single('wms_event_trigger_logistics_electron')->bufferGetWaybill($data['channel_id']);
        }
        if (in_array($data['channel_type'], ['taobao','jdalpha'])) {
            //默认获取发货地址
            $extendObj = app::get('logisticsmanager')->model('channel_extend');
            $extend    = $extendObj->dump(array('channel_id' => $data['channel_id']), 'id');
            if (!$extend) {
                kernel::single('erpapi_router_request')->set('logistics', $data['channel_id'])->electron_getWaybillISearch();
            }
        }
        echo "SUCC";
    }

    /**
     * toStatus
     * @return mixed 返回值
     */
    public function toStatus()
    {
        $this->begin('index.php?app=logisticsmanager&ctl=admin_channel&act=index');
        if ($_GET['status'] && $_GET['status'] == 'true') {
            $data['status'] = 'true';
        } else {
            $data['status'] = 'false';
        }

        if ($_POST['channel_id'] && is_array($_POST['channel_id'])) {
            $filter = array('channel_id' => $_POST['channel_id']);
        } elseif ($_POST['isSelectedAll'] && $_POST['isSelectedAll'] == '_ALL_') {
            $filter = array();
        } else {
            $this->end(false, '操作失败。');
        }

        $channelObj = app::get('logisticsmanager')->model('channel');
        $channelObj->update($data, $filter);
        $this->end(true, '操作成功。');
    }

    /**
     * 获取Logistics
     * @return mixed 返回结果
     */
    public function getLogistics()
    {
        $type      = $_POST['type'];
        $wlbObj    = kernel::single('logisticsmanager_waybill_' . $type);
        $logistics = $wlbObj->logistics();
        $result    = $logistics ? json_encode($logistics) : '';

        echo $result;
    }

    /**
     * 获取_ExpType
     * @return mixed 返回结果
     */
    public function get_ExpType()
    {
        $logistics_cod = $_POST['logistics_cod'];
        $channel_type  = $_POST['channel_type'];
        $wlbObj        = kernel::single('logisticsmanager_waybill_' . $channel_type);
        $logistics     = $wlbObj->get_ExpType($logistics_cod);
        $result        = $logistics ? json_encode($logistics) : '';
        echo $result;
    }

    /**
     * 获取LogisticsSpecial
     * @return mixed 返回结果
     */
    public function getLogisticsSpecial()
    {
        $type   = $_POST['type'];
        $method = $_POST['method'];
        try {
            $className = 'logisticsmanager_waybill_' . $type;
            if (class_exists($className)) {
                $obj = kernel::single($className);
                if (method_exists($obj, $method)) {
                    $result = $obj->$method($_POST);
                }
            }
        } catch (Exception $e) {}
        $result = $result ? json_encode($result) : '';
        echo $result;
    }

    /**
     * 获取PayMethod
     * @return mixed 返回结果
     */
    public function getPayMethod()
    {
        $type           = $_POST['type'];
        $wlbObj         = kernel::single('logisticsmanager_waybill_' . $type);
        $payMethondList = $wlbObj->pay_method();
        $result         = $payMethondList ? json_encode($payMethondList) : '';

        echo $result;
    }

    /**
     * 获取发货地址.
     * @param  $channel_id
     * @return  address
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function get_ship_address($channel_id)
    {
        $this->pagedata['finder_id']  = $_GET['finder_id'];
        $this->pagedata['channel_id'] = $channel_id;
        $this->display('admin/channel/download_address.html');
    }

    /**
     * 下载发货地址
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function download_address($channel_id)
    {
        $rsp = array('rsp' => 'succ', 'msg' => '获取成功');

        $rsp = kernel::single('erpapi_router_request')->set('logistics', $channel_id)->electron_getWaybillISearch();
        echo json_encode($rsp);
    }

    /**
     * 保存地址
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function save_address()
    {

        $rsp       = array('rsp' => 'succ', 'msg' => '获取成功');
        $extendObj = app::get('logisticsmanager')->model('channel_extend');

        !$_POST['addon'] && $_POST['addon'] = [];
        if (!$_POST['addon']['use_branch_addr']) {
            $_POST['addon']['use_branch_addr'] = 0;
        }
        $_POST['site_code'] && $_POST['addon']['site_code'] = $_POST['site_code'];
        $_POST['acct_id']   && $_POST['addon']['acct_id']   = $_POST['acct_id'];
        $_POST['shop_id']   && $_POST['addon']['shop_id']   = $_POST['shop_id'];

        $ext_data = array(
            'province'       => $_POST['province'],
            'city'           => $_POST['city'],
            'area'           => $_POST['area'],
            'address_detail' => $_POST['address_detail'],
            'street'         => $_POST['street'],
            'default_sender' => $_POST['default_sender'],
            'mobile'         => $_POST['mobile'],
            'tel'            => $_POST['tel'],
            'shop_name'      => $_POST['shop_name'],
            'zip'            => $_POST['zip'],
            'addon'          => $_POST['addon'],
        );
        if ($_POST['id']) {
            $ext_data['id'] = $_POST['id'];
        }

        if ($_POST['channel_id']) {
            $ext_data['channel_id'] = $_POST['channel_id'];
        }
        $extendObj->save($ext_data);
        echo json_encode($rsp);
    }

    /**
     * 选择店铺.
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function select_shop()
    {
        $shopObj = app::get('ome')->model('shop');
        $shop    = $shopObj->getlist('area,zip,addr,default_sender,mobile');

    }

    public function findShop()
    {

        $params = array(
            'title'                  => '店铺列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,

        );
        $this->finder('ome_mdl_shop', $params);

    }

    /*
     * 通过id获取地址
     */

    public function getShopById()
    {

        $shop_id = $_POST['id'];
        if ($shop_id) {
            $shopObj = app::get('ome')->model('shop');
            $shop    = $shopObj->dump(array('shop_id' => $shop_id));
            $area    = explode(':', $shop['area']);
            $area    = explode('/', $area[1]);
            $tmp     = array(
                'province'       => $area[0],
                'city'           => $area[1],
                'area'           => $area[2],
                'address_detail' => $shop['addr'],
                'default_sender' => $shop['default_sender'],
                'tel'            => $shop['tel'],
                'mobile'         => $shop['mobile'],
                'shop_name'      => $shop['name'],
                'zip'            => $shop['zip'],
            );
            echo json_encode($tmp);

        }
    }

    /**
     * 导出模板
     * 
     * @return void
     * @author
     * */
    public function exportTemplate()
    {
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=" . date('Ymd') . ".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $title = array();

        #跨境
        $title[] = kernel::single('base_charset')->utf2local('*:运单号');
        #output
        echo '"' . implode('","', $title) . '"';
    }

    /**
     * 申请绑定关系
     */
    function apply_bindrelation($api_url,$callback_url) {

        $apply['certi_id'] = base_certificate::get('certificate_id');
        if ($node_id = base_shopnode::node_id('ome')) $apply['node_idnode_id'] = $node_id;
        $apply['sess_id'] = kernel::single('base_session')->sess_id();

        $apply['certi_ac'] = base_certificate::getCertiAC($apply);

        $app_xml = kernel::single('ome_rpc_func')->app_xml();

        //给矩阵发送session过期的回打地址。
        // $sess_callback = kernel::base_url(true).kernel::url_prefix().'/openapi/ome.shop/shop_session';

        $params = array(
            'source'           => 'apply',
            'api_v'            => $app_xml['api_ver'],
            'certi_id'         => $apply['certi_id'],
            'node_id'          => $apply['node_idnode_id'],
            'sess_id'          => $apply['sess_id'],
            'certi_ac'         => $apply['certi_ac'],
            'callback'         => $callback_url,
            'api_url'          => $api_url,
            // 'sess_callback' => $sess_callback,
            'jdnoshop'         => '1',
            'bind_type'        => '360buy',
        );

        echo sprintf('<iframe width="100%%" frameborder="0" height="99%%" id="iframe" src="%s" ></iframe>',MATRIX_RELATION_URL . '?' . http_build_query($params));exit;
    }

    /**
     * 取消绑定关系
     */
    function cancel_bindrelation($channel_id) {
        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');

        $channelMdl = app::get('logisticsmanager')->model('channel');

        $channel = $channelMdl->db_dump(intval($channel_id),'node_id');
        if (!$channel) {
            $this->end(false,'渠道不存在');
        }

        // 请求接口取消
        $rs   = kernel::single('erpapi_router_request')->set('bind', 'other')->bind_unbind(array(
            'to_node'   => $channel['node_id'],
            'node_type' => '360buy',
        ));
        if (!$rs) {
            $this->end(false,'解除绑定失败');
        }


        $affect_rows = $channelMdl->update(array('bind_status'=>'false'),array('channel_id'=>intval($channel_id) ));

        $this->end(true);
    }

    /**
     * 获取发货地址.
     * @param  $channel_id
     * @return  address
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function get_shop_address($channel_id)
    {
        $res = kernel::single('erpapi_router_request')->set('logistics', $channel_id)->electron_getWaybillISearch();

        $accountList = [];
        if ($res['data']) {
            foreach ($res['data']['account_list'] as $k => $v) {
                if ($res['request_logistics_code']!=$v['delivery_id']) {
                    continue;
                }
                // 与云起一致：xhs/美团医药/爱库存等接口可能返回空 acct_id，不能用 acct_id 作数组键，否则单选 value 为空且 POST 校验失败
                $uniqueKey = $v['acct_id'];
                if (in_array($res['channel_type'], array('xhs', 'meituan4medicine', 'aikucun'))) {
                    $uniqueKey = $k + 1;
                } elseif (isset($v['_unique_acct_id']) && $v['_unique_acct_id']) {
                    $uniqueKey = $v['_unique_acct_id'];
                }
                $accountList[$uniqueKey] = [
                    'available'      => $v['available'],
                    'status'         => $v['status'],
                    'delivery_id'    => $v['delivery_id'],
                    'acct_id'        => $v['acct_id'],
                    'recycled'       => $v['recycled'],
                    'shop_id'        => $v['shop_id'],
                    'cancel'         => $v['cancel'],
                    'allocated'      => $v['allocated'],
                    'site_name'      => $v['site_name'],
                    'site_status'    => $v['site_status'],
                    'site_code'      => $v['site_code'],
                    'site_fullname'  => $v['site_fullname'],
                    'mobile'         => $v['mobile'],
                    'phone'          => $v['phone'],
                    'name'           => $v['name'],
                    'province_code'  => $v['province_code'],
                    'street_name'    => $v['street_name'],
                    'street_code'    => $v['street_code'],
                    'city_name'      => $v['city_name'],
                    'country_code'   => $v['country_code'],
                    'district_code'  => $v['district_code'],
                    'detail_address' => $v['detail_address'],
                    'district_name'  => $v['district_name'],
                    'city_code'      => $v['city_code'],
                    'province_name'  => $v['province_name'],
                    'bizLine'        => isset($v['bizLine']) ? $v['bizLine'] : '',
                ];
            }
        }
        $this->pagedata['logistics_code']    = $res['request_logistics_code'];
        $this->pagedata['account_list']      = $accountList;
        $this->pagedata['account_list_json'] = json_encode($accountList);
        $this->pagedata['finder_id']         = $_GET['finder_id'];
        $this->pagedata['channel_id']        = $channel_id;
        $this->pagedata['channel_type']      = $res['channel_type'];
        $this->display('admin/channel/download_shop_address.html');
    }

    /**
     * 保存_shop_address
     * @return mixed 返回操作结果
     */
    public function save_shop_address()
    {
        $this->begin();
        if (!$_POST['_unique_acct_id']) {
            $this->end(false, '请选择一个签约信息');
        }

        $accountList = json_decode($_POST['account_list_json'], 1);
        $pickKey       = (string) $_POST['_unique_acct_id'];
        $accountInfo   = isset($accountList[$pickKey]) ? $accountList[$pickKey] : null;
        if (!$accountInfo) {
            $this->end(false, '电子面单账号id无效');
        }

        if (!$_POST['channel_id']) {
            $this->end(false, 'channel_id无效');
        }

        $channelObj = app::get('logisticsmanager')->model('channel');
        $extendObj  = app::get('logisticsmanager')->model('channel_extend');

        $ext_data = array(
            'channel_id'     => $_POST['channel_id'],
            'province'       => $accountInfo['province_name'],
            'city'           => $accountInfo['city_name'],
            'area'           => $accountInfo['district_name'],
            'address_detail' => $accountInfo['detail_address'],
            'street'         => $accountInfo['street_name'],
            'default_sender' => $accountInfo['name'],
            'mobile'         => $accountInfo['mobile'] ? $accountInfo['mobile'] : $accountInfo['phone'],
            'tel'            => $accountInfo['phone'] ? $accountInfo['phone'] : $accountInfo['mobile'],
            'shop_name'      => $accountInfo['site_name'],
            'seller_id'      => $accountInfo['acct_id'],
        );

        // 保存channelObj表的service_code
        if (in_array($_POST['channel_type'], app::get('logisticsmanager')->model('channel')->getWaybillAccountFromApi)) {
            $ext_data['addon'] = [
                'site_code' => $accountInfo['site_code'],
                'acct_id'   => $accountInfo['acct_id'],
                'shop_id'   => $accountInfo['shop_id'],
                'bizLine'   => isset($accountInfo['bizLine']) ? $accountInfo['bizLine'] : '',
            ];
        }

        $has = $extendObj->db_dump(['channel_id' => $_POST['channel_id']]);
        if ($has) {
            $extendObj->update($ext_data, ['channel_id' => $_POST['channel_id']]);
        } else {
            $extendObj->insert($ext_data);
        }

        $this->end(true, '确认成功');
    }

    /**
     * 查询订购关系对话框
     *
     * @author chenping@shopex.cn
     * @since 2024-09-19 13:57:23
     **/
    public function queryNetsiteDialog()
    {
        $this->display('admin/channel/netsite/add.html');
    }

    /**
     * 获取订购店铺
     *
     * @author chenping@shopex.cn
     * @since 2024-09-19 14:46:57
     **/
    public function queryNetsiteShop()
    {
        $channel_type = $_GET['channel_type'];

        $shopList = app::get('ome')->model('shop')->getList('name,shop_id', [
            'node_type' => $channel_type
        ]);
        
        try {
            $obj = kernel::single('logisticsmanager_waybill_'.$channel_type);

            // 获取支持的物流公司编码
            $cpList = [];
            if ($shopList){
                $cpList = $obj->logistics('', $shopList[0]['shop_id']);
            }

            $this->pagedata['cpList'] = $cpList;
            
        } catch (\Throwable $th) {
            //throw $th;
        }

        $this->pagedata['shopList'] = $shopList;

        $this->display('admin/channel/netsite/shop.html');
    }

    /**
     * 订购关系展示
     *
     *
     * @author  chenping@shopex.cn 
     * @since 2024-09-19 22:05:41
     **/
    public function queryNetsiteDetail($cp_code='')
    {
        $shop_id = $_GET['shop_id'];

        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->logistics_getWaybillNetSite(['cp_code' => $cp_code]);

        $this->pagedata['result'] = $result;

        $this->display('admin/channel/netsite/lattice_point.html');
    }

    /**
     * 订购关系增值服务
     *
     *
     * @author  chenping@shopex.cn 
     * @since 2024-09-19 22:05:41
     **/
    public function queryNetsiteServices($cp_code='', $payment_type = '', $brand_code= '', $lattice_point_no = '')
    {
        $channel_type = $_GET['channel_type'];
        $shop_id = $_GET['shop_id'];

        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->logistics_getCorpServiceCode(['cp_code' => $cp_code]);

        // $this->pagedata['result'] = $result;

        try {
            $obj = kernel::single('logisticsmanager_waybill_'.$channel_type);

            // 获取支持的物流公司编码
            $expType = [];
            
            if (in_array($cp_code, ['SF', '7','42','129'])){
                $expType = $obj->get_ExpType('SF');
            }

            $this->pagedata['expType'] = $expType;
            
        } catch (\Throwable $th) {
            //throw $th;
        }

        $this->display('admin/channel/netsite/services.html');
    }

    /**
     * 保存订购关系
     *
     * @author chenping@shopex.cn 
     * @since 2024-09-22 15:10:04
     **/
    public function saveNetsite()
    {
        $this->begin();

        $post = kernel::single('base_component_request')->get_post();

        if (!$post['shop_id']){
            $this->end(false, '请选择店铺');
        }

        if (!$post['logistics_code']){
            $this->end(false, '请选择物流公司');
        }

        if (!$post['channel_type']) {
            $this->end(false, '电子面单渠道平台');
        }

        if (!$post['name']){
            $this->end(false, '渠道名称');
        }

        if (!$post['netsite_address']){
            $this->end(false, '网点信息');
        }

        $shop = app::get('ome')->model('shop')->dump($post['shop_id'], 'node_id');
        if (!$shop['node_id']){
            $this->end(false, '店铺未绑定');
        }

        $netsite_address =  @json_decode(base64_decode($post['netsite_address']), 1);
        
        if (!$netsite_address){
            $this->end(false, '网点信息格式错误');
        }

        $channel = [
            // 'channel_id' => '',
            'name' => $post['name'],
            'shop_id' => $post['shop_id'],
            'channel_type' => $post['channel_type'],
            'logistics_code' => $post['logistics_code'],
            'exp_type' => $post['exp_type'],
            'create_time' => time(),
            'bind_status' => 'true',
            'status' => 'true',
            // 'service_code' => '',
            'node_id' => $shop['node_id'],
            'addon' => $netsite_address,
            // 'outer_id' => '',
            'ver' => '2',
        ];
        if ($post['brand_code']) {
            $channel['service_code'] = json_encode([
                'brand_code' => [
                    'text' => 'brand_code',
                    'code'=>'brand_code',
                    'input_type'=>'input', 
                    'value' => $post['brand_code'],
                ]
            ], JSON_UNESCAPED_UNICODE);
        }

        $channelMdl = app::get('logisticsmanager')->model('channel');

        if (!$channelMdl->save($channel)) {
            $this->end(false, '保存失败：'.$channelMdl->db->errorinfo());
        }
        
        $channelExt = [
            'channel_id' => $channel['channel_id'],
            'province' => $netsite_address['province_name'],
            'city' => $netsite_address['city_name'],
            'area' => $netsite_address['county_name'],
            'street' => '',
            'address_detail' => $netsite_address['address'],
            'default_sender' => $netsite_address['consignor_name'],
            'mobile' => $netsite_address['consignor_phone'],
            'tel' => $netsite_address['consignor_tel'],
            
        ];

        $channelExtMdl = app::get('logisticsmanager')->model('channel_extend');
        
        if (!$channelExtMdl->save($channelExt)) {
            $this->end(false, '保存失败：'. $channelExtMdl->db->errorinfo());
        }

        $this->end(true);
    }

}
