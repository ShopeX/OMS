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
