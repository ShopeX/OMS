<?php
/**
 * 报价系统授权 finder
 *
 * @author wangbiao@shopex.cn
 * @version 2024.08.22
 */
class wmsmgr_finder_smart
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private $_appName = 'wmsmgr';

    var $addon_cols = "channel_id,channel_name,node_id,channel_type,node_type,config";

    var $column_edit = "操作";
    var $column_edit_width = "260";
    var $column_edit_order = "1";
    function column_edit($row)
    {
        // legacy builder 注入 col_prefix；vue 模式未设置 ⇒ 优先读 addon_cols 顺序下发的原始值别名
        if ($this->vue_mode) {
            $channel_id = $row['_0_channel_id'] ?? $row['channel_id'] ?? 0;
            $node_id    = $row['_2_node_id'] ?? $row['node_id'] ?? '';
            $adapter    = kernel::single('wmsmgr_func')->getAdapterByChannelId($channel_id);

            // Vue Finder 契约：结构化行操作（不再下发 legacy <a> HTML 片段，SPA 不解析 legacy 链接）
            $actions = array(
                array(
                    'key'    => 'edit',
                    'label'  => '编辑',
                    'action' => 'index.php?app=' . $this->_appName . '&ctl=admin_smart&act=edit&p[0]=' . $channel_id,
                ),
            );

            if (in_array($adapter, array('matrixwms'))) {
                $actions[] = empty($node_id)
                    ? array('key' => 'apply_bind', 'label' => '申请绑定', 'action' => 'bind')
                    : array('key' => 'bound', 'label' => '已绑定', 'disabled' => true);
            }

            return $actions;
        }

        $finder_id = $_GET['_finder']['finder_id'];
        $node_type = $row[$this->col_prefix.'node_type'];
        $node_id = $row[$this->col_prefix.'node_id'];
        $channel_id = $row[$this->col_prefix.'channel_id'];

        $config = unserialize($row[$this->col_prefix . 'config']);

        //url
        $url = 'index.php?app=wmsmgr&ctl=admin_smart';

        //adapter
        $adapter = kernel::single('wmsmgr_func')->getAdapterByChannelId($channel_id);

        //callback url
        $callback_url = urlencode(kernel::openapi_url('openapi.wmsmgr','bindCallback',array('channel_id'=>$channel_id)));

        //api url
        $api_url = kernel::base_url(true).kernel::url_prefix().'/api';

        //button
        $edit_btn = '<a href="'. $url .'&act=edit&p[0]='.$row[$this->col_prefix.'channel_id'].'&finder_id='.$finder_id.'&finder_vid='.$finder_vid.'" target="dialog::{width:500,height:400,title:\'编辑报价系统授权\'}">编辑</a>';

        $bind_btn = '';
        $app_id = "ome";
        $api_url = urlencode($api_url);
        if (in_array($adapter,array('matrixwms'))) {
            switch ($config['node_type']) {
                case 'other':
                default:
                    $bind_btn .= empty($node_id) ?
                ' | <a href="'. $url .'&act=apply_bindrelation&p[0]='.$app_id.'&p[1]='.$callback_url.'&p[2]='.$api_url.'" target="_blank">申请绑定</a>' : ' | 已绑定';
                break;
            }
        }

        if(in_array($adapter, array('matrixwms','mixturewms'))){
            return $edit_btn.$bind_btn;
        }else{
            return $edit_btn;
        }
    }
}
