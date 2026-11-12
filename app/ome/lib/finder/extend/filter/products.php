<?php
class ome_finder_extend_filter_products{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $db['products']=array (
            'columns' => array (
                'brand_id' => array (
                    'type' => 'table:brand@ome',
                    'required' => true,
                    'label' => '品牌',
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'type_id' => array (
                    'type' => 'table:goods_type@ome',
                    'required' => true,
                    'label' => '类型',
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
                'branch_id' => array (
                    'type' => 'table:branch@ome',
                    'required' => true,
                    'label' => '仓库',
                    'editable' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                ),
            )
        );
        return $db;
    }
}

