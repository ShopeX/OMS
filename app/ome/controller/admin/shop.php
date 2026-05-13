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

class ome_ctl_admin_shop extends desktop_controller {

    var $name       = "店铺管理";
    var $workground = "channel_center";

    function index() {
        $Certi = base_certificate::get('certificate_id');
        $Node_id = base_shopnode::node_id('ome');
        $Certi = $Certi ? $Certi : '-';
        $Node_id = $Node_id ? $Node_id : '-';
        $title = '前端店铺管理(证书：' . $Certi . '&nbsp;&nbsp;节点：' . $Node_id . ')';
        
        //filter(过滤掉一件代发类型)
        $base_filter = array('s_type'=>1, 'delivery_mode|notin'=>'shopyjdf');
        
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['user_org_id'] = $organization_permissions;
        }

        $this->finder('ome_mdl_shop', array(
            'title'                  => $title,
            'base_filter'            => $base_filter,//只显示线上店铺，不包含门店线下店铺
            'actions'                => array(
                array('label' => '添加店铺', 'href' => 'index.php?app=ome&ctl=admin_shop&act=addterminal&finder_id=' . $_GET['finder_id'], 'target' => 'dialog::{width:900,height:600,title:\'添加店铺\'}'),
                array('label' => '查看绑定关系', 'href' => 'index.php?app=ome&ctl=admin_shop&act=view_bindrelation', 'target' => '_blank'),
                array('label' => '消息订阅', 'submit' => 'index.php?app=ome&ctl=admin_shop&act=invoiceAddGroup', 'target' => 'dialog::{width:600,height:200,title:\'消息订阅\'}'),
            ),
            'finder_cols'            => 'column_bind_status,column_edit,shop_bn,name,shop_type,node_id,node_type,active',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => true,
            'use_buildin_export'     => true,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_buildin_customcols' => true,
            'orderBy' => 's_status DESC, node_type DESC',
        ));
    }

    /**
     * 网店节点显示
     * @param null
     * @return null
     */
    public function shopnode() {
        $this->pagedata['node_id'] = base_shopnode::node_id($this->app->app_id);
        $this->pagedata['node_type'] = base_shopnode::node_type($this->app->app_id);

        $this->page('admin/system/shopnode.html');
    }

    /*
     * 添加前端店铺
     */

    function addterminal() {
        $this->_editterminal();
    }

    /*
     * 编辑前端店铺
     */

    function editterminal($shop_id) {
        $this->_editterminal($shop_id);
    }

    function _editterminal($shop_id = NULL) {
        $oShop = $this->app->model("shop");
        $operationOrgObj = $this->app->model('operation_organization');

        $orgs = $operationOrgObj->getList('*', array(), 0, -1);
        $this->pagedata['orgs'] = $orgs;

        $shoptype = ome_shop_type::get_shop_type();
        $shop_type = array();
        $i = 0;
        if ($shoptype){
            foreach ($shoptype as $k => $v) {
                $shop_type[$i]['type_value'] = $k;
                $shop_type[$i]['type_label'] = $v;
                $i++;
            }
        }

        if ($shop_id) {
            $shop = $oShop->dump($shop_id);
            $shop_config = unserialize($shop['config']);

            $this->pagedata['shop'] = $shop;
            $this->pagedata['shop_config'] = $shop_config;
            $propsMdl = app::get('ome')->model('shop_props');

            $propsList = $propsMdl->getlist('*', ['shop_id' => $shop_id]);

            $arr_props = array();
            foreach($propsList as $v){

                $arr_props[$v['props_col']] = $v['props_value'];

            }
        }

        $this->pagedata['shop_type'] = $shop_type;
        $this->pagedata['title'] = '添加/编辑店铺';
        
        //自定义
        
        
        $customcols = kernel::single('ome_shop')->getcols();
        foreach($customcols as $k=>$v){
            if($arr_props[$v['col_key']]){
                $customcols[$k]['col_value'] = $arr_props[$v['col_key']];
            }
        }
        
        $this->pagedata['customcols'] = $customcols;

        $this->display("admin/system/terminal.html");
    }

    /**
     * 申请绑定关系
     * @param string $app_id
     * @param string $callback 异步返回地址
     * @param string $api_url API通信地址
     */
    /**
     * 店铺绑定三步引导页面
     */
    function bind_guide() {
        $shop_id = $_GET['shop_id'];
        $step = isset($_GET['step']) ? intval($_GET['step']) : null; // null 表示未指定，会自动跳转到未完成的步骤
        
        if (!$shop_id) {
            $this->splash('error', '', '店铺ID不能为空');
            return;
        }
        
        // 获取店铺信息
        $shopObj = app::get('ome')->model('shop');
        $shop = $shopObj->dump($shop_id, '*');
        
        if (!$shop) {
            $this->splash('error', '', '店铺不存在');
            return;
        }
        
        // 判断系统节点注册状态（参考 system.php，与矩阵企业侧 ent_id 一致）
        $entId = base_enterprise::ent_id();
        $entAc = base_enterprise::ent_ac();
        $entEmail = base_enterprise::ent_email();
        $is_certified = !empty($entId); // 系统节点注册完成：ent_id 有值即视为已在矩阵完成注册
        // 系统节点与证书（展示用）
        $system_node_id = base_shopnode::node_id('ome');
        $system_certificate_id = base_certificate::get('certificate_id');
        
        // 获取 version_tier，如果为 tn-open，则按照非淘宝逻辑处理
        $version_tier = app::get('entermembercenter')->getConf('version_tier');
        $is_tn_open = ($version_tier == 'tn-open');

        // 判断是否需要奇门授权（淘系店铺：淘宝/天猫同一逻辑）
        // 注意：如果 version_tier=tn-open，即使店铺类型是淘宝/天猫，也按照非淘宝逻辑处理
        $is_taobao = in_array($shop['shop_type'], array('taobao', 'tmall')) && !$is_tn_open;
        $need_qimen = false;
        $is_qimen_binded = false;
        $secretKeyDisplay = '';
        $qimen_rebind_url = '';
        $qimen_channel_id = '';
        $is_super = kernel::single('desktop_user')->is_super();
        
        // 只有淘宝店铺才需要奇门授权（但 tn-open 版本不需要）
        if ($is_taobao) {
            // 检查奇门聚石塔内外互通渠道是否已配置
            $channelObj = kernel::single('channel_channel');
            $qimenChannel = $channelObj->getQimenJushitaErp();
            
            if (!empty($qimenChannel) && !empty($qimenChannel['channel_id'])) {
                $need_qimen = true;
                $qimen_channel_id = $qimenChannel['channel_id'];
                $qimen_rebind_url = 'index.php?app=channel&ctl=admin_channel&act=apply_bindrelation&p[]=' . $qimen_channel_id;
                // 判断是否已授权：检查 app_key 和 secret_key 是否都有值
                if (!empty($qimenChannel['app_key']) && !empty($qimenChannel['secret_key'])) {
                    $is_qimen_binded = true;
                    
                    // 处理 secret_key 打码显示（参考 system.php）
                    $len = strlen($qimenChannel['secret_key']);
                    if ($len <= 4) {
                        // 长度小于等于4，全部显示星号
                        $secretKeyDisplay = str_repeat('*', $len);
                    } else {
                        // 显示前2位和后2位，中间用星号
                        $secretKeyDisplay = substr($qimenChannel['secret_key'], 0, 2) . str_repeat('*', $len - 4) . substr($qimenChannel['secret_key'], -2);
                    }
                }
            }
        } else {
            // 非淘宝店铺，不需要奇门授权
            $qimenChannel = array();
        }
        
        // 节点绑定状态（只检查 node_id）
        $is_node_binded = !empty($shop['node_id']);
        
        // 店铺绑定状态：对于淘宝店铺，需要同时满足 node_id 和奇门授权；对于非淘宝店铺（包括 version_tier=tn-open），只需要 node_id
        // 注意：步骤3（店铺绑定）的完成状态只检查 node_id，不依赖奇门授权
        // 但是最终的"店铺绑定完成"状态需要两者都完成
        // 注意：version_tier=tn-open 时，即使店铺类型是淘宝/天猫，也按照非淘宝逻辑处理（不需要奇门授权）
        if ($is_taobao) {
            // 淘宝店铺：需要同时满足 node_id 和奇门授权才算完全绑定完成
            $is_shop_binded = $is_node_binded && $is_qimen_binded;
        } else {
            // 非淘宝店铺（包括 version_tier=tn-open）：只需要 node_id
            $is_shop_binded = $is_node_binded;
        }
        
        // 获取对接方式（从店铺配置的adapter字段）
        $config = unserialize($shop['config']);
        if (!is_array($config)) {
            $config = array();
        }
        $adapter = isset($config['adapter']) ? $config['adapter'] : '';

        // 商家自研对接不需要奇门授权，直接走店铺绑定
        if ($adapter == 'openapi') {
            $need_qimen = false;
        }
        
        // 检查是否已有节点，如果有节点则不允许修改对接方式
        $has_node = !empty($shop['node_id']);
        
        // 获取适配器列表
        $adapter_list = kernel::single('ome_auth_config')->getAdapterList();
        $this->pagedata['adapter_list'] = $adapter_list;
        $this->pagedata['has_node'] = $has_node;
        
        // 计算当前步骤（现在有5步：0对接方式、1系统节点注册、2奇门授权（淘宝）/店铺绑定（非淘宝）、3店铺绑定（淘宝）、4完成）
        // 注意：如果对接方式是 openapi（商家自研对接），不需要系统节点注册步骤，跳过步骤1
        // 注意：对于淘宝店铺，步骤2是奇门授权，步骤3是店铺绑定
        $need_enterprise_auth = ($adapter != 'openapi'); // openapi 不强制系统节点注册
        
        if (empty($adapter)) {
            $current_step = 0;
            $status_text = '选择对接方式';
            $step_text = '请选择对接方式';
            $bind_url = '';
        } elseif ($need_enterprise_auth && !$is_certified) {
            // 需要系统节点注册且尚未完成
            $current_step = 1;
            $status_text = '系统节点未注册';
            $step_text = '请先完成系统节点注册';
            // 生成系统节点注册 URL（参考 system.php）
            $bind_url = base_enterprise::generate_auth_url();
        } elseif ($is_taobao && $need_qimen && !$is_qimen_binded) {
            // 淘宝店铺：步骤2是奇门授权
            $current_step = 2;
            $status_text = '待绑奇门';
            $step_text = '请完成奇门绑定授权';
            // 使用channel控制器的apply_bindrelation方法
            $qimen_channel_id = !empty($qimenChannel['channel_id']) ? $qimenChannel['channel_id'] : '';
            $bind_url = 'index.php?app=channel&ctl=admin_channel&act=apply_bindrelation&p[]=' . $qimen_channel_id;
        } elseif (!$is_node_binded) {
            // 节点未绑定：对于淘宝店铺是步骤3，对于非淘宝店铺是步骤2
            // 注意：这里只检查 node_id，不依赖奇门授权
            $current_step = $is_taobao ? 3 : 2;
            $status_text = '待绑店铺';
            $step_text = '请完成店铺绑定';
            // 如果是商家自研对接（openapi），使用 bindNodeId 页面，否则使用 apply_bindrelation iframe
            if ($adapter == 'openapi') {
                $bind_url = 'index.php?app=ome&ctl=admin_shop&act=bindNodeId&p[0]=' . $shop_id . '&p[1]=bind';
            } else {
                $bind_url = 'index.php?app=ome&ctl=admin_shop&act=apply_bindrelation&shop_id=' . $shop_id;
            }
        } else {
            // 已绑定节点的情况
            // 对于 openapi：仍然使用 bindNodeId 页面，传递 unbind，用于展示/解绑
            if ($adapter == 'openapi') {
                $bind_url = 'index.php?app=ome&ctl=admin_shop&act=bindNodeId&p[0]=' . $shop_id . '&p[1]=unbind';
                // 当前步骤仍保持在店铺绑定步骤，方便查看配置
                $current_step = $is_taobao ? 3 : 2;
                $status_text = '店铺绑定已完成';
                $step_text = '可查看或取消绑定';
            } else {
                // 所有步骤都已完成（对于淘宝店铺需要 node_id 和奇门授权都完成）
                $current_step = 4;
                $status_text = '已完成';
                $step_text = '';
                $bind_url = '';
            }
        }
        
        // 特殊处理：如果用户请求特定步骤
        // 步骤0（对接方式）是前提，必须完成才能进行其他步骤
        // 如果对接方式未选择，无论用户请求哪个步骤，都强制跳转到步骤0
        if (empty($adapter) && $step !== null && $step != 0) {
            // 对接方式未选择，强制跳转到步骤0
            $step = 0;
            $current_step = 0;
            $status_text = '选择对接方式';
            $step_text = '请先完成对接方式，才能进行后续步骤';
            $bind_url = '';
        } elseif ($step === null) {
            // 如果未指定 step 参数，使用计算出的 current_step（自动跳转到未完成的步骤）
            $step = $current_step;
        } elseif ($step == 0) {
            // 第0步：对接方式选择
            $current_step = 0;
            $status_text = '选择对接方式';
            $step_text = '请选择对接方式';
            $bind_url = '';
        } elseif ($step == 1) {
            // 第1步：系统节点注册
            // 如果对接方式是 openapi，不强制系统节点注册
            // 但是，如果用户直接点击步骤指示器中的「系统节点注册」，应允许显示步骤1的内容
            // 只有在点击「下一步」时，openapi 才会自动跳过步骤1
            // 这里直接显示步骤1的内容，允许用户查看
            $current_step = 1;
            $status_text = '系统节点注册';
            if (!$need_enterprise_auth) {
                // openapi 不强制注册，但仍展示矩阵注册页 iframe（可选完成）
                $step_text = '当前对接方式为商家自研对接，系统节点注册为可选；如需注册请完成下方流程';
                // 生成系统节点注册 URL（与矩阵一致）
                $bind_url = base_enterprise::generate_auth_url();
            } else {
                $step_text = '请完成系统节点注册';
                // 生成系统节点注册 URL（参考 system.php）
                $bind_url = base_enterprise::generate_auth_url();
            }
        } elseif ($step == 2) {
            // 第2步：根据是否是淘宝店铺显示不同内容
            $current_step = 2;
            if ($is_taobao) {
                // 淘宝店铺：步骤2是奇门授权
                $qimen_channel_id = !empty($qimenChannel['channel_id']) ? $qimenChannel['channel_id'] : '';
                if ($need_qimen) {
                    // 需要奇门授权
                    if (!$is_qimen_binded) {
                        // 奇门未绑定：显示待绑定状态
                        $status_text = '待绑奇门';
                        $step_text = '请完成奇门绑定授权';
                        // 使用channel控制器的apply_bindrelation方法
                        $bind_url = 'index.php?app=channel&ctl=admin_channel&act=apply_bindrelation&p[]=' . $qimen_channel_id;
                    } else {
                        // 奇门已绑定：显示已完成状态，但允许查看第2步
                        $status_text = '奇门授权已完成';
                        $step_text = '奇门授权已完成，可以继续下一步';
                        // 已授权也保持加载同一授权页，方便复查/重授权
                        $bind_url = 'index.php?app=channel&ctl=admin_channel&act=apply_bindrelation&p[]=' . $qimen_channel_id;
                    }
                } else {
                    // 商家自研：奇门为可选，但若未授权且有渠道，仍提供授权 iframe
                    if (!$is_qimen_binded && $qimen_channel_id) {
                        $status_text = '待绑奇门（可选）';
                        $step_text = '当前为商家自研对接，奇门授权可选；如需授权请完成下方流程';
                        $bind_url = 'index.php?app=channel&ctl=admin_channel&act=apply_bindrelation&p[]=' . $qimen_channel_id;
                    } elseif (!$is_qimen_binded) {
                        // 无渠道可授权
                        $status_text = '奇门授权（可选）';
                        $step_text = '当前未配置奇门渠道，如需使用奇门功能，请联系管理员配置';
                            $bind_url = '';
                    } else {
                        $status_text = '奇门授权已完成（可选）';
                        $step_text = '奇门授权已完成，可以继续下一步';
                            // 已授权也保持加载同一授权页，方便复查/重授权
                            $bind_url = 'index.php?app=channel&ctl=admin_channel&act=apply_bindrelation&p[]=' . $qimen_channel_id;
                    }
                }
            } else {
                // 非淘宝店铺：步骤2是店铺绑定（只检查 node_id）
                if ($is_node_binded) {
                    $status_text = '店铺绑定已完成';
                    $step_text = '可查看或取消绑定';
                    if ($adapter == 'openapi') {
                        $bind_url = 'index.php?app=ome&ctl=admin_shop&act=bindNodeId&p[0]=' . $shop_id . '&p[1]=unbind';
                    } else {
                        // 如果已有节点且非自研，不加载绑定页面
                        $bind_url = '';
                    }
                } else {
                    $status_text = '待绑店铺';
                    $step_text = '请完成店铺绑定';
                    // 如果是商家自研对接（openapi），使用 bindNodeId 页面，否则使用 apply_bindrelation iframe
                    if ($adapter == 'openapi') {
                        $bind_url = 'index.php?app=ome&ctl=admin_shop&act=bindNodeId&p[0]=' . $shop_id . '&p[1]=bind';
                    } else {
                        $bind_url = 'index.php?app=ome&ctl=admin_shop&act=apply_bindrelation&shop_id=' . $shop_id;
                    }
                }
            }
        } elseif ($step == 3) {
            // 第3步：仅淘宝店铺有步骤3（店铺绑定）
            if ($is_taobao) {
                // 淘宝店铺：步骤3是店铺绑定（只检查 node_id，不依赖奇门授权）
                $current_step = 3;
                if ($is_node_binded) {
                    $status_text = '店铺绑定已完成';
                    $step_text = '可查看或取消绑定';
                    if ($adapter == 'openapi') {
                        $bind_url = 'index.php?app=ome&ctl=admin_shop&act=bindNodeId&p[0]=' . $shop_id . '&p[1]=unbind';
                    } else {
                        $bind_url = '';
                    }
                } else {
                    $status_text = '待绑店铺';
                    $step_text = '请完成店铺绑定';
                    // 如果是商家自研对接（openapi），使用 bindNodeId 页面，否则使用 apply_bindrelation iframe
                    if ($adapter == 'openapi') {
                        $bind_url = 'index.php?app=ome&ctl=admin_shop&act=bindNodeId&p[0]=' . $shop_id . '&p[1]=bind';
                    } else {
                        $bind_url = 'index.php?app=ome&ctl=admin_shop&act=apply_bindrelation&shop_id=' . $shop_id;
                    }
                }
            } else {
                // 非淘宝店铺：步骤3不存在，自动跳转到第4步（完成页面）
                $step = 4;
                $current_step = 4;
                $status_text = '已完成';
                $step_text = '';
                $bind_url = '';
            }
        } elseif ($step == 4) {
            // 第4步：完成页面
            $current_step = 4;
            
            // 检查所有步骤是否完成（用于判断是否真正完成）
            $all_steps_completed = true;
            $incomplete_steps = array();
            
            // 步骤0：对接方式
            if (empty($adapter)) {
                $all_steps_completed = false;
                $incomplete_steps[] = array('step' => 0, 'name' => '对接方式');
            }
            
            // 步骤1：系统节点注册（openapi 跳过）
            if ($need_enterprise_auth && !$is_certified) {
                $all_steps_completed = false;
                $incomplete_steps[] = array('step' => 1, 'name' => '系统节点注册');
            }
            
            // 步骤2：对于淘宝店铺是奇门授权，对于非淘宝店铺是店铺绑定
            if ($is_taobao) {
                if ($need_qimen && !$is_qimen_binded) {
                    $all_steps_completed = false;
                    $incomplete_steps[] = array('step' => 2, 'name' => '奇门授权');
                }
            } else {
                if (!$is_node_binded) {
                    $all_steps_completed = false;
                    $incomplete_steps[] = array('step' => 2, 'name' => '店铺绑定');
                }
            }
            
            // 步骤3：对于淘宝店铺是店铺绑定
            if ($is_taobao && !$is_node_binded) {
                $all_steps_completed = false;
                $incomplete_steps[] = array('step' => 3, 'name' => '店铺绑定');
            }
            
            if ($all_steps_completed) {
                $status_text = '已完成';
                $step_text = '';
                $is_completed = true; // 所有步骤都完成
            } else {
                // 有未完成的步骤，列出未完成项
                $status_text = '未完成';
                $step_names = array();
                foreach ($incomplete_steps as $incomplete) {
                    $step_names[] = $incomplete['name'];
                }
                $step_text = '以下步骤尚未完成：' . implode('、', $step_names);
                $is_completed = false; // 有未完成的步骤
            }
            
            $bind_url = '';
        } elseif ($step > $current_step) {
            // 如果URL参数中的step与当前实际步骤不符，进行调整
            // 但第0步、第1步和第2步、第3步已经在上面特殊处理了，允许用户访问任何步骤
            // 不进行自动调整，允许用户访问任何步骤
        }
        
        // 判断是否所有步骤都已完成（用于显示"完成"按钮）
        // 判断逻辑：如果当前步骤是最后一步，或者所有必需的步骤都已完成，则显示"完成"按钮
        $is_completed = false;
        if ($step == 4) {
            // 第4步肯定是完成状态
            $is_completed = true;
        } elseif ($step == 3 && $is_taobao) {
            // 淘宝店铺：第3步（店铺绑定）是最后一步，应该显示完成按钮
            $is_completed = true;
        } elseif ($step == 2 && !$is_taobao) {
            // 非淘宝店铺：第2步（店铺绑定）是最后一步，应该显示完成按钮
            $is_completed = true;
        }
        
        // 计算每个步骤的实际完成状态（用于步骤指示器显示）
        // 如果对接方式是 openapi，步骤1（系统节点注册）在流程上可跳过（完成态见 step_completed）
        // 对于淘宝店铺：步骤2是奇门授权，步骤3是店铺绑定
        // 对于非淘宝店铺：步骤2是店铺绑定，步骤3不存在
        // 注意：步骤3（店铺绑定）的完成状态只检查 node_id，不依赖奇门授权
        // 步骤完成态（用于指示器显示）：openapi 时不自动勾选奇门，避免未绑定显示完成
        if ($is_taobao) {
            if ($adapter == 'openapi') {
                $step2_completed = $is_qimen_binded; // 可选，但未绑定不打勾
            } else {
                $step2_completed = $need_qimen ? $is_qimen_binded : true;
            }
        } else {
            $step2_completed = $is_node_binded;
        }

        $step_completed = array(
            0 => !empty($adapter), // 步骤0：对接方式已选择
            // 不再将可选注册默认标绿，只有真实完成才标记完成
            1 => $is_certified,
            2 => $step2_completed, // 步骤2：淘宝店铺是奇门授权（openapi 不自动完成），非淘宝店铺是店铺绑定
            3 => $is_taobao ? $is_node_binded : false, // 步骤3：淘宝店铺是店铺绑定（只检查 node_id），非淘宝店铺不存在
        );
        
        // 获取店铺绑定信息（如果有节点，即使奇门未授权也显示节点信息）
        $bind_info = array();
        if ($is_node_binded) {
            // 节点ID
            $bind_info['node_id'] = $shop['node_id'];
            // 节点类型
            $bind_info['node_type'] = $shop['node_type'];
            // 店铺名称
            $bind_info['shop_name'] = !empty($shop['name']) ? $shop['name'] : (!empty($shop['shop_name']) ? $shop['shop_name'] : '');
            // 授权到期时间（从 addon 字段中获取）
            $addon = !empty($shop['addon']) ? (is_string($shop['addon']) ? unserialize($shop['addon']) : $shop['addon']) : array();
            $bind_info['expire_time'] = '';
            if (!empty($addon['session_expire_time'])) {
                $bind_info['expire_time'] = $addon['session_expire_time'];
            }
        }
        
        // 设置页面数据
        $finder_id = isset($_GET['finder_id']) ? $_GET['finder_id'] : '';
        $this->pagedata['shop_id'] = $shop_id;
        $this->pagedata['shop'] = $shop;
        $this->pagedata['shop_name'] = $shop['shop_name'];
        $this->pagedata['shop_url'] = $shop['shop_url'];
        $this->pagedata['step'] = $step;
        $this->pagedata['current_step'] = $current_step;
        $this->pagedata['status_text'] = $status_text;
        $this->pagedata['step_text'] = $step_text;
        $this->pagedata['bind_url'] = $bind_url;
        $this->pagedata['need_qimen'] = $need_qimen;
        $this->pagedata['adapter'] = $adapter;
        $this->pagedata['is_taobao'] = $is_taobao;
        $this->pagedata['finder_id'] = $finder_id;
        $this->pagedata['is_completed'] = $is_completed;
        $this->pagedata['step_completed'] = $step_completed;
        // 未完成步骤列表（如果存在）
        $this->pagedata['incomplete_steps'] = isset($incomplete_steps) ? $incomplete_steps : array();
        // 店铺绑定信息（如果已绑定）
        $this->pagedata['bind_info'] = $bind_info;
        $this->pagedata['is_node_binded'] = $is_node_binded; // 节点绑定状态（只检查 node_id）
        $this->pagedata['is_shop_binded'] = $is_shop_binded; // 店铺绑定状态（对于淘宝店铺需要同时满足 node_id 和奇门授权）
        // 奇门渠道信息
        $this->pagedata['is_qimen_binded'] = $is_qimen_binded;
        $this->pagedata['qimen_channel'] = $qimenChannel;
        $this->pagedata['secret_key_display'] = $secretKeyDisplay;
        $this->pagedata['qimen_rebind_url'] = $qimen_rebind_url;
        $this->pagedata['qimen_channel_id'] = $qimen_channel_id;
        $this->pagedata['is_super'] = $is_super;
        // 系统节点与证书信息
        $this->pagedata['system_node_id'] = $system_node_id;
        $this->pagedata['system_certificate_id'] = $system_certificate_id;
        // 企业信息
        $this->pagedata['ent_id'] = $entId;
        $this->pagedata['ent_ac'] = $entAc;
        $this->pagedata['ent_email'] = $entEmail;
        $this->pagedata['is_certified'] = $is_certified;
        $this->pagedata['need_enterprise_auth'] = $need_enterprise_auth; // 是否需要系统节点注册步骤（openapi 不强制）
        // 绑定完成页：订单流量购买入口
        $this->pagedata['order_traffic_purchase_url'] = 'https://open-console.shopex.cn/organization/list';
        
        // 渲染模板
        $this->display('admin/shop/bind_guide.html');
    }


    function apply_bindrelation($app_id = 'ome', $callback = '', $api_url = '', $show_type = 'shop|shopex') {

        $Certi = base_certificate::get('certificate_id');
        $Token = base_certificate::get('token');
        $Node_id = base_shopnode::node_id($app_id);

        $token = $Token;
        $sess_id = kernel::single('base_session')->sess_id();
        $apply['certi_id'] = $Certi;

        if ($Node_id)
            $apply['node_idnode_id'] = $Node_id;
        $apply['sess_id'] = $sess_id;

        $apply['certi_ac'] = base_certificate::getCertiAC($apply);

        $Ofunc = kernel::single('ome_rpc_func');
        $app_xml = $Ofunc->app_xml();
        $api_v = $app_xml['api_ver'];

        //给矩阵发送session过期的回打地址。
        $sess_callback = kernel::base_url(true) . kernel::url_prefix() . '/openapi/ome.shop/shop_session';

        // 如果callback为空，则使用默认回调地址
        if (empty($callback)) {
            $callback = kernel::openapi_url('openapi.ome.shop', 'shop_callback', array('shop_id' => $_GET['shop_id']));
        }

        // 如果api_url为空，则使用默认api_url
        if (empty($api_url)) {
            $api_url = kernel::base_url(true) . kernel::url_prefix() . '/api';
        }

        $params = array(
            'source'        => 'apply',
            'api_v'         => $api_v,
            'certi_id'      => $apply['certi_id'],
            'node_id'       => $apply['node_idnode_id'],
            'sess_id'       => $apply['sess_id'],
            'certi_ac'      => $apply['certi_ac'],
            'callback'      => $callback,
            'api_url'       => $api_url,
            'sess_callback' => $sess_callback,
            'show_type'     => $show_type,
            'version_source' => 'onex-oms',//识别唯品会JIT绑定标识
        );

        // 检测绑定状态（针对淘宝店铺）
        $bind_step = 1; // 默认第一步：绑定店铺
        $shop_id = isset($_GET['shop_id']) ? $_GET['shop_id'] : null;
        $qimen_channel_bind_status = false;
        
        // 检查奇门channel是否已绑定（无论是否有shop_id都要检查）
        $channelMdl = app::get('channel')->model('channel');
        $qimenChannel = $channelMdl->getList('channel_id,node_id', array('channel_type' => 'qimen'), 0, 1);
        $qimen_channel_id = null;
        if (!empty($qimenChannel)) {
            $qimen_channel_id = $qimenChannel[0]['channel_id'];
            if (!empty($qimenChannel[0]['node_id'])) {
                $qimen_channel_bind_status = true;
            }
        }
        
        $bind_type = '';
        if ($shop_id) {
            $oShop = $this->app->model("shop");
            $shop = $oShop->dump($shop_id, 'node_id,node_type,shop_type');
            
            if ($shop) {
                // 将 shop_type 赋值给 bind_type
                if (!empty($shop['shop_type'])) {
                    $bind_type = $shop['shop_type'];
                }
                
                // 检测是否已完成第一步：店铺绑定（淘系店铺：淘宝/天猫）
                if (!empty($shop['node_id']) && in_array($shop['node_type'], array('taobao', 'tmall'))) {
                    $bind_step = 2; // 进入第二步：绑定奇门ERP
                }
            }
        }

        // 将 bind_type 添加到 params 中
        if ($bind_type) {
            $params['bind_type'] = $bind_type;
        }

        $finder_id = isset($_GET['finder_id']) ? $_GET['finder_id'] : '';
        
        $this->pagedata['bind_step'] = $bind_step;
        $this->pagedata['shop_id'] = $shop_id;
        $this->pagedata['qimen_channel_bind_status'] = $qimen_channel_bind_status;
        $this->pagedata['qimen_channel_id'] = $qimen_channel_id;
        $this->pagedata['finder_id'] = $finder_id;
        $this->pagedata['qimen_apply_url'] = 'https://open.taobao.com/docV3.htm?docId=109580&docType=1&source=search';

        $this->pagedata['license_iframe_url'] = MATRIX_RELATION_URL . '?' . http_build_query($params);

        // $this->pagedata['license_iframe'] = '<iframe width="100%" frameborder="0" height="99%" id="iframe" onload="this.height=document.documentElement.clientHeight-4" src="' . MATRIX_RELATION_URL . '?source=apply&api_v='.$api_v.'&certi_id=' . $apply['certi_id'] . '&node_id=' . $apply['node_idnode_id'] . '&sess_id=' . $apply['sess_id'] . '&certi_ac=' . $apply['certi_ac'] . '&callback=' . $callback . '&api_url=' . $api_url .'&show_type=shop|shopex&sess_callback='.$sess_callback.'" ></iframe>';

        $this->display('admin/system/apply_terminal.html');
    }

    /*
     * 查看绑定关系
     */

    function view_bindrelation() {
        $Certi = base_certificate::get('certificate_id');
        $Token = base_certificate::get('token');
        $Node_id = base_shopnode::node_id('ome');
        $token = $Token;
        $sess_id = kernel::single('base_session')->sess_id();
        $apply['certi_id'] = $Certi;
        $apply['node_idnode_id'] = $Node_id;
        $apply['sess_id'] = $sess_id;
        $apply['certi_ac'] = base_certificate::getCertiAC($apply);

        $Ofunc = kernel::single('ome_rpc_func');
        $app_xml = $Ofunc->app_xml();
        $api_v = $app_xml['api_ver'];

        $callback = urlencode(kernel::openapi_url('openapi.ome.shop', 'shop_callback', array()));
        $api_url = kernel::base_url(true) . kernel::url_prefix() . '/api';
        $api_url = urlencode($api_url);
        $op_id = kernel::single('desktop_user')->get_login_name();
        $op_user = kernel::single('desktop_user')->get_name();
        $params = '&op_id=' . $op_id . '&op_user=' . $op_user;
        $show_alipay_subscribe_auth = 0;
        if (app::get('finance')->is_installed()) {
            $show_alipay_subscribe_auth = 1;
        }

        $params = array(
            'op_id'                      => $op_id,
            'op_user'                    => $op_user,
            'source'                     => 'accept',
            'show_alipay_subscribe_auth' => $show_alipay_subscribe_auth,
            'api_v'                      => $api_v,
            'certi_id'                   => $apply['certi_id'],
            'node_id'                    => $Node_id,
            'sess_id'                    => $apply['sess_id'],
            'certi_ac'                   => $apply['certi_ac'],
            'callback'                   => $callback,
            'api_url'                    => $api_url,
            'show_type'                  => 'shop|shopex|pay',
        );

        echo sprintf('<title>查看绑定关系</title><iframe width="100%%" height="95%%" frameborder="0" src="%s" ></iframe>', MATRIX_RELATION_URL . '?' . http_build_query($params));
    }

    function saveterminal() {
        $oShop = $this->app->model("shop");

        $url = 'index.php?app=ome&ctl=admin_shop&act=index';
        $this->begin($url);
        $svae_data = $_POST['shop'];

        // 过滤掉所有字段的空格
        foreach ($svae_data as $key => $value) {
            if (is_string($value)) {
                $svae_data[$key] = trim($value);
            }
        }

        // 如果没有填写店铺编码，自动生成一个
        if (empty($svae_data['shop_bn']) && empty($svae_data['old_shop_bn'])) {
            $svae_data['shop_bn'] = $this->_generateShopBn($svae_data['name']);
        }

        if (!$svae_data['old_shop_bn']) {
            $shop_detail = $oShop->dump(array('shop_bn' => $svae_data['shop_bn']), 'shop_bn');
            if ($shop_detail['shop_bn']) {
                // 如果生成的编码已存在，重新生成
                if (empty($_POST['shop']['shop_bn'])) {
                    $svae_data['shop_bn'] = $this->_generateShopBn($svae_data['name'], true);
                    $shop_detail = $oShop->dump(array('shop_bn' => $svae_data['shop_bn']), 'shop_bn');
                    if ($shop_detail['shop_bn']) {
                        $this->end(false, app::get('base')->_('编码生成失败，请手动输入'));
                    }
                } else {
                    $this->end(false, app::get('base')->_('编码已存在，请重新输入'));
                }
            }
        }

        $shop_detail = $oShop->dump(array('shop_id' => $svae_data['shop_id']), 'config,shop_id');

        $config = unserialize($shop_detail['config']);
        $svae_data['config'] = serialize($config);
        // node_id 不应该通过表单修改，由系统在绑定/解绑时自动管理
        unset($svae_data['node_id']);
        $rt = $oShop->save($svae_data);
        $rt = $rt ? true : false;

        if($rt && $_POST['props']){
            $propsMdl = app::get('ome')->model('shop_props');

            $propsdata = array();

            foreach($_POST['props'] as $pk=>$pv){

                if($pv){
                    $propsdata = array(
                        'shop_id'       => $svae_data['shop_id'],
                        'props_col'     =>  is_string($pk) ? trim($pk) : $pk,
                        'props_value'   =>  is_string($pv) ? trim($pv) : $pv, // 过滤掉props_value的空格
                    );
                    $props = $propsMdl->db_dump(array('shop_id'=>$svae_data['shop_id'],'props_col'=>$pk),'id');
                    if($props){
                        $propsdata['id'] = $props['id'];
                    }
                    $propsMdl->save($propsdata);
                }
            }



        }
        //发送短信签名注册
        if (defined('APP_TOKEN') && defined('APP_SOURCE')) {
            base_kvstore::instance('taoexlib')->fetch('account', $account);
            if (unserialize($account)) {
                $sms_sign = '【' . $svae_data['name'] . '】';
                kernel::single('taoexlib_request_sms')->newoauth_request(array('sms_sign' => $sms_sign));
            }
        }

        $this->end($rt, app::get('base')->_($rt ? '保存成功' : '保存失败'));
    }

    /**
     * 自动生成店铺编码
     * 规则：SHOP + 日期(YYYYMMDD) + 时间戳后4位 + 随机数2位
     * 例如：SHOP20250115123456
     * 
     * @param string $shop_name 店铺名称（暂未使用，预留扩展）
     * @param bool $force_unique 是否强制生成唯一编码
     * @return string 店铺编码
     */
    private function _generateShopBn($shop_name = '', $force_unique = false) {
        $prefix = 'SHOP';
        $date = date('Ymd');
        $timestamp = substr(time(), -4);
        $random = str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT);
        $shop_bn = $prefix . $date . $timestamp . $random;
        
        // 如果强制唯一，检查并重新生成
        if ($force_unique) {
            $oShop = $this->app->model("shop");
            $max_attempts = 10;
            $attempt = 0;
            while ($attempt < $max_attempts) {
                $shop_detail = $oShop->dump(array('shop_bn' => $shop_bn), 'shop_bn');
                if (!$shop_detail['shop_bn']) {
                    break;
                }
                // 重新生成随机数
                $random = str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT);
                $timestamp = substr(time(), -4);
                $shop_bn = $prefix . $date . $timestamp . $random;
                $attempt++;
            }
        }
        
        return $shop_bn;
    }

    /**
     * 手动解除绑定关系
     * @access public
     * @param string $shop_id
     */
    public function unbind() {
        $shop_id = addslashes($_GET['shop_id']);
        $finder_id = addslashes($_GET['finder_id']);
        if ($_GET['unbind'] == 'true') {
            $this->begin('');
            $shopObj = app::get('ome')->model('shop');
            $update_data = array('node_id' => '', 'node_type' => '');
            $filter = array('shop_id' => $shop_id);
            $return = $shopObj->update($update_data, $filter);
            $return = $return ? true : false;
            $this->end($return, app::get('base')->_($return ? '解除成功' : '解除失败'));
        } else {
            $this->pagedata['finder_id'] = $finder_id;
            $this->pagedata['shop_id'] = $shop_id;
            $this->display('admin/system/unbind_terminal.html');
        }
    }

    function request_order() {
        $this->begin('index.php?app=ome&ctl=admin_shop&act=index');
        if ($_POST['start_time'] && $_POST['end_time']) {
            $oShop = app::get('ome')->model("shop");
            $shop_id = $_POST['shop_id'];
            $shop = $oShop->dump($shop_id, 'shop_type,node_id');
            if (!$shop) {
                $this->end(false, app::get('base')->_('前端店铺信息不存在'));
            }
            $start_time = strtotime($_POST['start_time'] . ' 00:00:00');
            $end_time = strtotime($_POST['end_time'] . ' 23:59:59');
            if ($start_time && $end_time) {
                $diff_time = ($end_time - $start_time) / (60 * 60 * 24);
                if ($diff_time < 0) {
                    $this->end(false, app::get('base')->_('结束日期不能小于开始日期'));
                }

                if ($diff_time > 8) {
                    $this->end(false, app::get('base')->_('只能下载7天之内的订单'));
                }
                foreach (kernel::servicelist('service.order') as $object => $instance) {
                    if (method_exists($instance, 'update_order')) {
                        $instance->notify_get_order($shop, $start_time, $end_time);
                    }
                }
                $this->end(true, app::get('base')->_('同步成功'));
            } else {
                $this->end(false, app::get('base')->_('请正确填写开始时间和结束时间'));
            }
        } else {
            $this->end(false, app::get('base')->_('请选择开始时间和结束时间'));
        }
    }

    /**
     * 保存前端回写设置
     * 
     * @param void
     * @return void
     */
    function request_config() {

        $this->begin('index.php?app=ome&ctl=admin_shop&act=index');

        if (!empty($_REQUEST['shop_id']) && !empty($_REQUEST['request_config'])) {

            $request_config = strtolower($_REQUEST['request_config']);
            app::get('ome')->setConf('request_auto_stock_' . $_REQUEST['shop_id'], $request_config);
            $this->end(true, app::get('base')->_('保存成功'));
        } else {

            $this->end(false, app::get('base')->_('输入的参数有误，请重新输入后再试！'));
        }
    }

    /*
    *获取前端订单详情
    */
    function sync_order() {
        if (empty($_POST['order_id'])) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '订单号不能为空!'));
            exit;
        }
        if (empty($_POST['shop_id'])) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '店铺ID不能为空!'));
            exit;
        }

        if (isset($_POST['order_type'])) {
            $order_type = $_POST['order_type'];
        } else {
            $order_type = 'direct';
        }

        $Oorders = $this->app->model('orders');

        $filter = array('order_bn' => trim($_POST['order_id']), 'shop_id' => $_POST['shop_id']);

        $order = $Oorders->dump($filter, 'outer_lastmodify');

        if ($order && !is_null($order['outer_lastmodify'])) {
            $Oorders->update(array('outer_lastmodify' => ($order['outer_lastmodify'] - 1)), $filter);
        }
        
        // 店铺信息
        $shopInfo = app::get('ome')->model('shop')->dump(array('shop_id'=>$_POST['shop_id']), '*');
        
        // 按店铺类型获取指定订单
        if(in_array($shopInfo['shop_type'], ['taobao', 'tmall'])){
            // 通过qimen路由拉取订单
            $rsp_data = kernel::single('erpapi_router_request')->set('qimen', $_POST['shop_id'])->order_get_order_detial(trim($_POST['order_id']));
        }else{
            // request shop order
            $rsp_data = kernel::single('erpapi_router_request')->set('shop', $_POST['shop_id'])->order_get_order_detial(trim($_POST['order_id']));
        }
        
        // rsp
        if ($rsp_data['rsp'] == 'succ') {
            $obj_syncorder = kernel::single('ome_syncorder');
            $sdf_order = $rsp_data['data']['trade'];

            // 允许自动审单
            $sdf_order['auto_combine'] = true;
            $msg = '';
            if ($obj_syncorder->get_order_log($sdf_order, $_POST['shop_id'], $msg)) {
                echo json_encode(array('rsp' => 'succ'));
                exit;
            } else {
                echo json_encode(array('rsp' => 'fail', 'msg' => $msg));
                exit;
            }
        } else {
            echo json_encode(array('rsp' => 'fail', 'msg' => $rsp_data['err_msg'] ? $rsp_data['err_msg'] : "同步订单失败。"));
            exit;
        }
    }


    function jzorder() {
        if (empty($_POST['shop_id'])) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '店铺ID不能为空!'));
            exit;
        }
        $jzorderconf = $_POST['jzorderconf'];
        app::get('ome')->setConf('shop.jzorder.config.' . $_POST['shop_id'], $jzorderconf);
        echo json_encode(array('rsp' => 'succ'));
        exit;
    }

    //保存阿里ag配置
    function aligenius() {
        if (empty($_POST['shop_id'])) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '店铺ID不能为空!'));
            exit;
        }
        $aligenius_conf = $_POST['aligenius_conf'];
        //退款方式
        $refund_aligenius_conf = $_POST['refund_aligenius_conf'];
        app::get('ome')->setConf('shop.aliag.config.' . $_POST['shop_id'], $aligenius_conf);
        app::get('ome')->setConf('shop.refund.aliag.config.' . $_POST['shop_id'], $refund_aligenius_conf);
        echo json_encode(array('rsp' => 'succ'));
        exit;
    }

    function change(){
        if (empty($_POST['shop_id'])) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '店铺ID不能为空!'));
            exit;
        }
        $change_conf = $_POST['change_conf'];
        app::get('ome')->setConf('shop.tmallchange.config.' . $_POST['shop_id'], $change_conf);
        echo json_encode(array('rsp' => 'succ'));
        exit;
    }
    
    /**
     * invoiceAddGroup
     * @return mixed 返回值
     */
    public function invoiceAddGroup() {
        $modelShop = app::get('ome')->model('shop');
        $shop = $modelShop->getList('shop_id, name, node_id, node_type', $_POST);
        $syncResult = array('none'=>array(),'succ'=>array(),'fail'=>array());
        foreach($shop as $val){
            if(empty($val['node_id']) || $val['node_type'] != 'taobao'){
                $syncResult['none'][] = $val['name'];
            }else{
                $sdf = array();
                $bind_status = false;//kernel::single('invoice_event_trigger_einvoice')->bindTbTmcGroup($val['shop_id'],$sdf);
                if ($bind_status == true){
                    $syncResult['succ'][] = $val['name'];
                }else{
                    $syncResult['fail'][] = array(
                        'name' => $val['name'],
                        'msg' => '订阅失败:方法未实现'
                    );
                }
            }
        }
        $this->pagedata['shop_result'] = $syncResult;
        $this->display('admin/system/shop_invoice_sync.html');
    }

    /**
     * summary
     * 
     * @return void
     * @author
     */
    public function saveConfig($shop_id)
    {
        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');

        $shopMdl = app::get('ome')->model('shop');

        $shop = $shopMdl->db_dump($shop_id,'config,shop_id,addon');


        if (!$shop) $this->end(false,'店铺不存在');
        $config = (array)$_POST['config'];
        $config['aging'] = array();
        if($_POST['time_plate_bn']) {
            foreach ($_POST['time_plate_bn'] as $key => $value) {
                if($value) {
                    $config['aging'][$value] = $_POST['time_branch_bn'][$key];
                }
            }
        }
        
        if($_POST['plat_cp_code']) {
            foreach ($_POST['plat_cp_code'] as $key => $value) {
                if($value) {
                    $config['cpmapping'][$value] = $_POST['erp_cp_code'][$key];
                }
            }
        }
        $shop['config'] = @unserialize($shop['config']);
        $shop['config'] = $shop['config'] ? $shop['config'] : array();
        $shop['config'] = array_merge($shop['config'],$config);

        $shop['config'] = serialize($shop['config']);

        $shop['addon'] = $shop['addon'] ? $shop['addon'] : array();
        $shop['addon'] = array_merge($shop['addon'], (array)$_POST['addon']);

        $rs = $shopMdl->save($shop);

        $this->end($rs);
    }

    /**
     * 保存对接方式（adapter）
     */
    public function save_integration_type()
    {
        $shop_id = $_POST['shop_id'];
        $adapter = $_POST['adapter'];
        
        if (!$shop_id) {
            $this->splash('error', '', '店铺ID不能为空');
            return;
        }
        
        if (!$adapter) {
            $this->splash('error', '', '请选择对接方式');
            return;
        }
        
        $shopObj = app::get('ome')->model('shop');
        $shop = $shopObj->dump($shop_id, '*');
        
        if (!$shop) {
            $this->splash('error', '', '店铺不存在');
            return;
        }
        
        // 如果店铺已有节点，不允许修改对接方式
        if (!empty($shop['node_id'])) {
            $this->splash('error', '', '店铺已绑定节点，不允许修改对接方式');
            return;
        }
        
        // 保存对接方式到店铺配置的adapter字段
        $config = unserialize($shop['config']);
        if (!is_array($config)) {
            $config = array();
        }
        $config['adapter'] = $adapter;
        
        $update_data = array(
            'shop_id' => $shop_id,
            'config' => serialize($config)
        );
        
        $result = $shopObj->save($update_data);
        
        if ($result) {
            $this->splash('success', '', '保存成功');
        } else {
            $this->splash('error', '', '保存失败');
        }
    }

    /**
     * 更换sku签约
     */
    public function updateSkuContract()
    {
        $shop_id = $_POST['shop_id'];
        $params = ['shop_id' => $shop_id];

        $res = kernel::single('erpapi_router_request')->set('shop', $shop_id)->qianniu_updateSkuContract($params);

        echo json_encode($res);
    }
    /**
     * 保存仓库最晚接单时间/最晚出库时间
     * 
     * @return void
     * @author
     * */
    public function saveShopBranch($shop_id)
    {
        $this->begin();

        $branchMdl = app::get('ome')->model('branch');

        $cutoff_time = $latest_delivery_time = 0;
        foreach ($_POST['branch'] as $branch_id => $value) {
            $branch = $branchMdl->db_dump($branch_id,'name');

            if (!$branch){
                $this->end(false, '仓库不存在');
            }

            if (!preg_match('/^(([0-1][0-9])|([2][0-3])):([0-5][0-9])$/', $value['cutoff_time'], $m)){
                $this->end(false, '【'.$branch['name'].'】最晚接单时间格式必须为：HH:MM');
            }
           if (!preg_match('/^(([0-1][0-9])|([2][0-3])):([0-5][0-9])$/', $value['latest_delivery_time'], $m)){
                $this->end(false, '【'.$branch['name'].'】最晚出库时间格式必须为：HH:MM');
            }


            $data = [
                'cutoff_time' => str_replace(':', '', $value['cutoff_time']),
                'latest_delivery_time'  => str_replace(':', '', $value['latest_delivery_time']),
            ];

            $branchMdl->update($data, ['branch_id' => $branch_id]);

            $cutoff_time = max($cutoff_time, strtotime($value['cutoff_time']));
            $latest_delivery_time = max($latest_delivery_time, strtotime($value['latest_delivery_time']));
        }


        if ($cutoff_time && $latest_delivery_time){
            $sdf = [
                'cutoff_time' => $cutoff_time,
                'latest_delivery_time' => $latest_delivery_time
            ];

            kernel::single('erpapi_router_request')->set('shop',$shop_id)->logistics_timerule($sdf);
        }

        $this->end(true);
    }

        /**
     * 设置BusinessType
     * @return mixed 返回操作结果
     */
    public function setBusinessType() {
        $shop_id = $_POST['shop_id'];
        $business_type = $_POST['business_type'];
        app::get('ome')->model('shop')->update(['business_type'=>$business_type], ['shop_id'=>$shop_id]);
        echo json_encode(['rsp'=>'succ']);
    }
    
    /**
     * 平台配置页
     * @param $shop_id
     * @param $adapter
     * @author db
     * @date 2023-06-15 3:16 下午
     */
    public function confightml($shop_id, $adapter)
    {
        $shop_type = '';

        switch ($adapter) {
            case 'openapi':
                if ($shop_id) {
                    $oShop                                 = app::get('ome')->model('shop')->db_dump(['shop_id' => $shop_id]);
                    $config                                = unserialize($oShop['config']);
                    $this->pagedata['config']['node_type'] = $config['node_type'];
                    $this->pagedata['shop_id']             = $shop_id;
                    $this->pagedata['node_id']             = $oShop['node_id'];

                    $shop_type = $oShop['shop_type'];
                }
                break;
            default:
                # code...
                break;
        }
        
        $platform_list                   = kernel::single('ome_auth_config')->getPlatformList($adapter,$shop_type);
        $this->pagedata['platform_list'] = $platform_list;
        $this->display('admin/auth/' . $adapter . '.html');
    }
    
    /**
     * 平台各自配置参数
     * @param $shop_id
     * @param $platform
     * @author db
     * @date 2023-06-15 3:17 下午
     */
    public function platformconfig($shop_id, $platform)
    {
        if ($shop_id) {
            $oShop  = app::get('ome')->model('shop')->db_dump(['shop_id' => $shop_id]);
            $config = @unserialize($oShop['config']);
            if ($platform == $config['node_type']) {
                $this->pagedata['config']  = $config;
                $this->pagedata['node_id'] = $oShop['node_id'];
            }
        }
        $platform_params = kernel::single('ome_auth_config')->getPlatformParam($platform);
        
        $this->pagedata['platform_params'] = $platform_params;
        $this->pagedata['platform']        = $platform;
        
        $this->display('admin/auth/platformconfig.html');
    }
    
    /**
     * gen_private_key
     * @return mixed 返回值
     */
    public function gen_private_key()
    {
        echo md5(uniqid());
        exit;
    }
    
    /**
     * 申请绑定页面
     * @param $shopId
     * @author db
     * @date 2023-06-20 5:25 下午
     */
    public function bindNodeId($shopId,$act_type = 'bind')
    {
        $shop = $this->app->model("shop")->db_dump($shopId);
        $shop_config = unserialize($shop['config']);
        $this->pagedata['shop'] = $shop;
        $this->pagedata['shop_config'] = $shop_config;
        //增加config配置
        $adapter_list = kernel::single('ome_auth_config')->getAdapterList();
        $this->pagedata['adapter_list'] = $adapter_list;
        $this->pagedata['act_type'] = $act_type;

        $this->display('admin/auth/channel.html');
    }
    
    /**
     * 保存店铺
     * @author db
     * @date 2023-06-20 5:16 下午
     */
    public function savechannel()
    {
        if ($_POST['act_type'] == 'unbind') {
            $this->begin('index.php?app=ome&ctl=admin_shop&act=index');
            list($rt,$msg) = $this->unbind_shop($_POST['shop']['shop_id']);
            $this->end($rt, app::get('base')->_($msg));
        }
        $this->begin('index.php?app=ome&ctl=admin_shop&act=index');
        list($rt,$msg) = kernel::single('ome_shop')->savechannel($_POST['shop']);
        $this->end($rt, app::get('base')->_($msg));
    }
    
    /**
     * 直连取消绑定
     * @param $shop_id
     * @author db
     * @date 2023-06-20 3:39 下午
     */
    public function unbind_shop($shop_id)
    {
        $shopMdl = app::get('ome')->model('shop');
        $affect_row = $shopMdl->update(['node_id'=>null,'node_type'=>null,'shop_type'=>null],['shop_id'=>$shop_id]);
        if (!$affect_row) {
            return [true,'解绑失败'];
        }
        return [true,'解绑成功'];
    }
    
    /**
     * 翱象配置
     * 
     * @param $shop_id
     * @return false|string|null
     */
    public function setAxoaing()
    {
        //shop_id
        $shop_id = trim($_POST['shop_id']);
        if (empty($shop_id)) {
            echo json_encode(array('rsp' => 'fail', 'msg' => '店铺ID不能为空!'));
            exit;
        }
    
        //setting
        $setting = array(
            'sync_branch' => $_POST['sync_branch'],
            'sync_logistics' => $_POST['sync_logistics'],
            'sync_product' => $_POST['sync_product'],
            'sync_stock' => $_POST['sync_stock'],
            'sync_delivery' => $_POST['sync_delivery'],
        );
    
        app::get('ome')->setConf('shop.aoxiang.config.'. $shop_id, json_encode($setting));
    
        echo json_encode(array('rsp' => 'succ'));
        exit;
    }


    /**
     * 店铺获取授权code
     * @Author: XueDing
     * @Date: 2024/12/9 3:10 PM
     * @return void
     */
    public function get_page_code()
    {
        $shopMdl  = app::get('ome')->model('shop');
        $list     = $shopMdl->getList('shop_id,node_id,node_type', ['filter_sql' => 'node_id is not null and node_id !=""', 's_type' => '1'], 0, 1);
        $pageCode = '';
        foreach ($list as $key => $val) {
            $result = kernel::single('erpapi_router_request')->set('shop', $val['shop_id'])->base_getPageCode();
            if ($result['rsp'] == 'succ' && $result['data']['pageCode']) {
                $pageCode = $result['data']['pageCode'];
                // setcookie($val['node_id'] . 'pdd_page_code', $pageCode);
                setcookie('pdd_page_code', $pageCode, time() + (60 * 60 * 24 * 365 * 30), '/');
            }
        }
        echo json_encode(['pageCode' => $pageCode]);
        exit;
    }

    /**
     * 设备信息保存session
     * @Author: XueDing
     * @Date: 2024/12/9 3:10 PM
     * @return void
     */
    public function set_code_pati()
    {
        $pageCode = $_POST['page_code'];
        $pati     = $_POST['pati'];
        if ($pageCode && $pati) {
            setcookie($pageCode,$pati);
        }
    }



    /**
     * 申请绑定关系
     */
    function noshopapply_bindrelation($api_url,$callback_url, $bind_type = '') {

        $token = base_certificate::get('token');

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
            'bind_type'        => $bind_type=='360buy' ? 'jingdong' : $bind_type,//'360buy|luban',
        );

        echo sprintf('<iframe width="100%%" frameborder="0" height="99%%" id="iframe" src="%s" ></iframe>',MATRIX_RELATION_URL . '?' . http_build_query($params));exit;
    }

}

