<?php

class o2o_finder_extend_filter_store_refuse_analysis
{
    public function get_extend_colums()
    {
        $branchRows = app::get('ome')->model('branch')->getList(
            'branch_id, name',
            array('b_type' => '2'),
            0,
            -1
        );

        $branchList = array();
        foreach ((array) $branchRows as $branch) {
            if (!empty($branch['branch_id'])) {
                $branchList[$branch['branch_id']] = $branch['name'];
            }
        }

        $db['store_refuse_analysis'] = array(
            'columns' => array(
                'branch_id' => array(
                    'type'          => $branchList,
                    'editable'      => false,
                    'label'         => '仓库',
                    'filtertype'    => 'normal',
                    'filterdefault' => true,
                ),
            ),
        );

        return $db;
    }
}
