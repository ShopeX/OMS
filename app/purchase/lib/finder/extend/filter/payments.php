<?php
class purchase_finder_extend_filter_payments{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
            $db['purchase_payments']=array (
              'columns' =>
              array (
                'po_bn' =>
                array (
                  'type' => 'varchar(50)',
                  'label' => '采购单编号',
                  'width' => 75,
                  'editable' => true,
                  'filtertype' => 'has',
                  'filterdefault' => true,
                ),
              ));
        return $db;
    }
}

