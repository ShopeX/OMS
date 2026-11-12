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
class logisticsmanager_finder_channel
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    public $addon_cols           = "channel_id,status,channel_type,logistics_code,shop_id,bind_status";
    public $column_control       = '操作';
    // public $column_control_width = '60';
    public $column_control_order = COLUMN_IN_HEAD;
    public $detail_shop_address  = '发货地址';
    public $detail_log           = '导入面单号记录';
    /**
     * column_control
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_control($row)
    {
        $channel_id = $row[$this->col_prefix . 'channel_id'];
        $bind_status  = $row[$this->col_prefix.'bind_status'];
        $channel_type = $row[$this->col_prefix.'channel_type'];
        $finder_id    = $_GET['_finder']['finder_id'];

        $button = "<a href='javascript:void(0);' onclick=\"new Dialog('index.php?app=logisticsmanager&ctl=admin_channel&act=edit&p[0]={$channel_id}&finder_id={$_GET['_finder']['finder_id']}',{width:620,height:260,title:'来源添加/编辑'}); \">编辑</a>";

        if ($channel_type == '360buy' || $channel_type == 'jdalpha') {
            list($jdbusinesscode,$shop_id) = explode('|||',$row[$this->col_prefix.'shop_id']);
            if ($shop_id === '00000000') {
                $api_ur = urlencode(kernel::base_url(true).kernel::url_prefix().'/api');
                $callback_url = urlencode(kernel::openapi_url('openapi.logisticsmanager.channel','callback',array('channel_id'=>$channel_id)));
                $button .=  $bind_status == 'false' ? sprintf(" | <a href='index.php?app=logisticsmanager&ctl=admin_channel&act=apply_bindrelation&p[0]=%s&p[1]=%s' target='dialog::{width:800,title:\"京东授权\",onClose:function(){window.finderGroup[\"%s\"].refresh();}}'>京东授权</a>", $api_ur,$callback_url,$finder_id)  : sprintf(" | <a class='c-red' href='javascirpt:void(0);' url='index.php?app=logisticsmanager&ctl=admin_channel&act=cancel_bindrelation&p[0]=%s&finder_id=%s' onclick='javascript:if(confirm(\"确认取消授权？\")){W.page(this.get(\"url\"));}'>取消授权</a>", $channel_id,$finder_id);
            }
        }
        return $button;
    }

    public $column_channel_type       = '来源类型';
    public $column_channel_type_width = '80';
    public $column_channel_type_order = COLUMN_IN_TAIL;
    /**
     * column_channel_type
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_channel_type($row)
    {
        $funcObj      = kernel::single('logisticsmanager_waybill_func');
        $channel_type = $row[$this->col_prefix . 'channel_type'];
        $channels     = $funcObj->channels($channel_type);
        if ($channels) {
            return $channels['name'];
        } else {
            return '未知';
        }
    }

    public $column_logistics       = '物流公司';
    public $column_logistics_width = '80';
    public $column_logistics_order = COLUMN_IN_TAIL;
    /**
     * column_logistics
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_logistics($row)
    {
        $channel_type = $row[$this->col_prefix . 'channel_type'];
        $logistics = array();
        // class_exists 会走自动加载；lib 文件不存在时直接抛 Don't find lib file，不能用来探测类。
        if ($channel_type && preg_match('/^[A-Za-z0-9_]+$/', $channel_type)) {
            $waybillLib = app::get('logisticsmanager')->app_dir . '/lib/waybill/' . $channel_type . '.php';
            if (file_exists($waybillLib)) {
                $wlbObj         = kernel::single('logisticsmanager_waybill_' . $channel_type);
                $logistics_code = $row[$this->col_prefix . 'logistics_code'];
                $logistics      = $wlbObj->logistics($logistics_code);
            }
        }

        if ($logistics) {
            return $logistics['name'];
        } else {
            return '未知';
        }
    }

    public $column_waybillnum       = '本地可用';
    public $column_waybillnum_width = '80';
    public $column_waybillnum_order = COLUMN_IN_TAIL;
    /**
     * column_waybillnum
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_waybillnum($row)
    {
        $waybillObj               = app::get('logisticsmanager')->model('waybill');
        $filter                   = array('status' => 0);
        $filter['channel_id']     = $row[$this->col_prefix . 'channel_id'];
        $filter['logistics_code'] = $row[$this->col_prefix . 'logistics_code'];

        $count = $waybillObj->count($filter);

        return "<span class=show_list channel_id=" . $filter['channel_id'] . " billtype='active'><a >" . $count . "</a></span>";
    }

    public $column_shop = '适用店铺';
    public $column_shop_width = '150';
    public $column_shop_order = COLUMN_IN_TAIL;
    /**
     * column_shop
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_shop($row)
    {

        if (in_array($row[$this->col_prefix . 'channel_type'], ['wlb', 'taobao', 'kuaishou', 'wphvip'])) {
            $shopObj = app::get('ome')->model('shop');
            $shop    = $shopObj->dump($row[$this->col_prefix . 'shop_id'], 'name');

            return $shop['name'];
        } elseif ($row[$this->col_prefix . 'channel_type'] == 'ems') {
            if ($row[$this->col_prefix . 'bind_status'] == 'true') {
                return '全部';
            } else {
                return '未绑定';
            }
        } elseif ($row[$this->col_prefix . 'channel_type'] == '360buy') {
            $logistics_code = $row[$this->col_prefix . 'logistics_code'];
            if (strtoupper($logistics_code) == 'SOP') {
                return '京东' . $logistics_code;
            } else {
                return '京东';
            }
        } else {
            return '全部';
        }
    }

    /**
     * 作废物流单号.
     * @param
     * @return
     * @access
     * @author sunjing@shopex.cn
     */
    public $column_recycle_waybill       = '本地作废';
    public $column_recycle_waybill_width = '80';
    /**
     * column_recycle_waybill
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_recycle_waybill($row)
    {
        $waybillObj               = app::get('logisticsmanager')->model('waybill');
        $filter                   = array('status' => 2);
        $filter['channel_id']     = $row[$this->col_prefix . 'channel_id'];
        $filter['logistics_code'] = $row[$this->col_prefix . 'logistics_code'];

        $count = $waybillObj->count($filter);

        return "<span class='show_list' channel_id=" . $filter['channel_id'] . " billtype='recycle' ><a >" . $count . "</a></span>";
    }

    /**
     * 作废物流单号.
     * @param
     * @return
     * @access
     * @author sunjing@shopex.cn
     */
    public $column_use_waybill       = '本地已用';
    public $column_use_waybill_width = '80';
    /**
     * column_use_waybill
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_use_waybill($row)
    {
        $waybillObj               = app::get('logisticsmanager')->model('waybill');
        $filter                   = array('status' => 1);
        $filter['channel_id']     = $row[$this->col_prefix . 'channel_id'];
        $filter['logistics_code'] = $row[$this->col_prefix . 'logistics_code'];

        $count = $waybillObj->count($filter);

        return "<span class='show_list' channel_id=" . $filter['channel_id'] . " billtype='used' ><a >" . $count . "</a></span>";
    }
    /**
     * 店铺地址
     * @param
     * @return
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function detail_shop_address($channel_id)
    {
        $htmlUrl                            = 'admin/channel/detail_address.html';
        $render                             = app::get('logisticsmanager')->render();
        $channelObj                         = app::get('logisticsmanager')->model('channel');
        $channel_detail                     = $channelObj->dump($channel_id, 'channel_type,bind_status');
        $render->pagedata['channel_id']     = $channel_id;
        $render->pagedata['channel_detail'] = $channel_detail;

        $extendObj = app::get('logisticsmanager')->model('channel_extend');
        $extend    = $extendObj->dump(array('channel_id' => $channel_id), '*');
        $extend['addon'] = is_array($extend['addon']) ? $extend['addon'] : [];

        if (in_array($channel_detail['channel_type'], app::get('logisticsmanager')->model('channel')->getWaybillAccountFromApi)) {
            $htmlUrl = 'admin/channel/detail_address_wxshipin.html';
        }

        // $render->pagedata['show_shop_address'] = $show_shop_address;
        $render->pagedata['extend_detail']     = $extend;
        unset($extend);

        return $render->fetch($htmlUrl);
    }

    public $detail_electron = '面单使用情况';
    /**
     * detail_electron
     * @param mixed $channelId ID
     * @return mixed 返回值
     */
    public function detail_electron($channelId)
    {
        $sql = 'SELECT
                    COUNT(*) AS count,status,channel_id,logistics_code
                    FROM
                        sdb_logisticsmanager_waybill
                    WHERE channel_id = ' . $channelId . '
                    GROUP BY status';
        $result       = kernel::database()->select($sql);
        $waybillCount = array();
        foreach ($result as $arr) {
            $waybillCount[$arr['status']] = $arr['count'];
        }
        $render                            = app::get('logisticsmanager')->render();
        $render->pagedata['channel_id']    = $channelId;
        $render->pagedata['waybill_count'] = $waybillCount;
        return $render->fetch('admin/channel/waybill_detail.html');
    }

    /**
     * detail_log
     * @param mixed $channel_id ID
     * @return mixed 返回值
     */
    public function detail_log($channel_id)
    {

        $render         = app::get('logisticsmanager')->render();
        $oOperation_log = app::get('ome')->model('operation_log');
        $log_list       = $oOperation_log->read_log(array('obj_id' => $channel_id, 'obj_type' => 'channel@logisticsmanager'), 0, -1);

        foreach ($log_list as $k => $v) {
            $log_list[$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
        }
        $channelObj                       = app::get('logisticsmanager')->model('channel');
        $channel_detail                   = $channelObj->getlist('channel_type', array('channel_id' => $channel_id), 0, 1);
        $render->pagedata['channel_type'] = $channel_detail[0]['channel_type'];
        $render->pagedata['channel_id']   = $channel_id;
        $render->pagedata['log_list']     = $log_list;
        return $render->fetch('admin/channel/detail_log.html');
    }
}
