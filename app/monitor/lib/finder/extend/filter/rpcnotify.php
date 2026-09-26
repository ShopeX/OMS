<?php

class monitor_finder_extend_filter_rpcnotify
{
    function get_extend_colums()
    {
        $eventTemplateLib = kernel::single('monitor_event_template');
        $eventType        = $eventTemplateLib->getEventType();
        $db['rpcnotify']  = array(
            'columns' => array(
                'event_type' => array(
                    'type'          => $eventType,
                    'label'         => '模板类型',
                    'width'         => 120,
                    'editable'      => false,
                    'in_list'       => true,
                    'filtertype'    => 'fuzzy_search_multiple',
                    'filterdefault' => true,
                ),
            ),
        );
        return $db;
    }
}
