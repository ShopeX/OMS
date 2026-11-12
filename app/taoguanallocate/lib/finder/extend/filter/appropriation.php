<?php
class taoguanallocate_finder_extend_filter_appropriation{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
       
        $db['appropriation']=array (
            'columns' => array (
              
                'product_bn' => array (
                    'type' => 'varchar(30)',
                    'label' => '货号',
                    'width' => 85,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                'product_barcode' => array (
                    'type' => 'varchar(32)',
                    'label' => '条形码',
                    'width' => 110,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
                
            )
        );
        return $db;
    }
}

