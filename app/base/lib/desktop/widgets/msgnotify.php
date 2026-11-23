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

class base_desktop_widgets_msgnotify implements desktop_interface_widget
{
    var $order = 7;
    
    function __construct($app)
    {
        $this->app    = $app;
        $this->render = new base_render(app::get('ome'));
    }
    
    function get_title()
    {
        return '系统消息';
    }
    
    function get_html()
    {
        $render = $this->render;
        
        $info = $this->fetchDataFromCache();
        if (empty($info)) {
            $noticeMdl = app::get('base')->model('rpcnotify');
            
//            // 已经绑定的店铺列表
//            $shopNodeList = array();
//            $shopMdl   = app::get('ome')->model('shop');
//            $shopList  = $shopMdl->getList('name,node_id', array('node_id|noequal' => ''));
//            if ($shopList) {
//                $shopNodeList = array_column($shopList, null, 'node_id');
//            }

            // 系统消息列表：按 shop_bn + shop_type + event_type 去重，保留最新，最多 8 条
            $info = array();
            $seen = array();
            $list = $noticeMdl->getList('*', array('msg_type'=>'system'), 0, 200, 'notifytime DESC');
            foreach ($list as $value)
            {
                if (count($info) >= 8) {
                    break;
                }
                
                $uniqKey = $value['shop_bn'] . "\0" . $value['shop_type'] . "\0" . $value['event_type'];
                if (isset($seen[$uniqKey])) {
                    continue;
                }
                
                $notice = json_decode($value['msg'], true);
                if (isset($notice['dead_line'])) {
                    $notice['notifytime'] = date('Y-m-d H:i:s', $value['notifytime']);
                    $notice['status']     = $value['status'];
                    $notice['callback']   = '';
                    $notice['id']         = $value['id'];
                    
                    // desc
                    $notice['original_info'] = $notice['info'];
                    
                    // info信息截图字符长度(汉字最长为25个字符)
                    $notice['info'] = mb_strlen($notice['info']) > 23 ? mb_substr($notice['info'], 0, 23) .'...' : $notice['info'];
                    
                    // 消息列表
                    $info[] = $notice;
                    
                    // 记录消息
                    $seen[$uniqKey]       = true;
                }
            }
            
            // 查看更多
            if ($info) {
                $look = [
                    'info'     => '查看更多...',
                    'status'   => 'look',
                    'callback' => 'index.php?app=desktop&ctl=rpcnotify&act=index',
                ];
                array_push($info, $look);
                $this->saveDataToCache($info);
            }
        }

        $render->pagedata['info'] = $info;
        $html = $render->fetch('desktop/widgets/system_notice.html', 'base');
        return $html;
    }
    
    function fetchDataFromCache()
    {
        return cachecore::fetch('system_notice_data');
    }
    
    function saveDataToCache($info)
    {
        cachecore::store('system_notice_data', $info, 1800);
    }
    
    function get_className()
    {
        return "";
    }
    
    function get_width()
    {
        return "l-2";
    }
    
}

?>