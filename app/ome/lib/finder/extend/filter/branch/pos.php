<?php
class ome_finder_extend_filter_branch_pos{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $brObj = app::get('ome')->model('branch');

        $is_super = kernel::single('desktop_user')->is_super();

        $branch_ids = kernel::single('wms_branch')->getBranchwmsByUser($is_super);
        $branch_rows   = $brObj->getList('branch_id, name',array('branch_id'=>$branch_ids),0,-1);
        $branch_list = array();
        foreach($branch_rows as $branch){
            $branch_list [$branch['branch_id']] = $branch['name'];
        }
      
        
        $db['branch_pos']=array (
            'columns' => array (
               'branch_id' =>
                    array (
                    'type' => $branch_list,
                    'editable' => false,
                    'label' => '仓库',
                    'width' => 110,
                    'default_value'=>$branch_rows[0]['branch_id'],
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'ome_branch_pos_finder_top',
                ),
                
            )
        );
        return $db;
     }


}
