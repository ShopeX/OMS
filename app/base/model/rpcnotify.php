<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 

class base_mdl_rpcnotify extends base_db_model{
    
    var $defaultOrder = array('id','DESC');

    public function filter($filter)
    {
        // Finder 高级筛选（时间范围、包含等）需走 dbeav_filter；
        // base_db_tools::filter2sql 仅支持等值，无法支持 notifytime / msg 筛选。
        unset($filter['use_like']);
        $dbeav_filter = kernel::single('dbeav_filter');
        return $dbeav_filter->dbeav_filter_parser($filter, null, null, $this);
    }
    
    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderby=null)
    {
        $orderType = $orderby ? : $this->defaultOrder;
        $sql = 'SELECT '.$cols.' FROM `'.$this->table_name(true).'` WHERE '.$this->filter($filter);
        if($orderType)$sql.=' ORDER BY '.(is_array($orderType)?implode(' ', $orderType):$orderType);
        $data = $this->db->selectLimit($sql,$limit,$offset);
        $this->tidy_data($data, $cols);
        return $data;
    }
    
    
    public function modifier_status( $val ) {
        if( $val=='false' ) {
            return '<a href="javascript:;" onclick="_get_rpcnotify_num(this)" >'.app::get('base')->_('未读').'</a>';
        } else {
            return app::get('base')->_('已读');
        }
    }

    public function modifier_event_type($type)
    {
        if (!$type) {
            return '-';
        }
        if (app::get('monitor')->is_installed()) {
            $eventType = kernel::single('monitor_event_template')->getEventType();
            return isset($eventType[$type]) ? $eventType[$type] : $type;
        }
        return $type;
    }
    
    /**
     * 运营组织
     *
     * @param $org_id
     * @param $list
     * @param $row
     * @return mixed
     */
    public function modifier_org_id($org_id, $list, $row)
    {
        static $orgList;
        
        if (isset($orgList)) {
            return $orgList[$org_id];
        }
        
        $orgIds  = array_unique(array_column($list, 'org_id'));
        $orgList = app::get('ome')->model('operation_organization')->getList('org_id,name', ['org_id'=>$orgIds]);
        $orgList = array_column($orgList, 'name', 'org_id');
        
        return $orgList[$org_id];
    }
}
