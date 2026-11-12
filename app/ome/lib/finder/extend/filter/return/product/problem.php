<?php
class ome_finder_extend_filter_return_product_problem{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $obj_problem = app::get('ome')->model('return_product_problem');
        $all_problem = $obj_problem->getList('*',array());
        $arr_problem = array();
        foreach($all_problem as $v){
            $_key = $v['problem_id'];
            $arr_problem[$_key] = $v['problem_name'];
        } 
        $db['return_product_problem']=array (
            'columns' => array (
                'problem_id' =>
                    array(
                            'type' => $arr_problem,
                            'filtertype' => 'normal',
                            'required' => true,
                            'label' => '售后类型',
                            'editable' => false,
                            'in_list' => true,
                            'default_in_list' => true,
                            'default' => 0,
                            'filterdefault' => true,
                            'panel_id' => 'ome_branch_finder_top',
                    ),
            )
        );
        return $db;
    }


}
