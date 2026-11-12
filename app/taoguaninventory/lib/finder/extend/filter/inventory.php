<?php
class taoguaninventory_finder_extend_filter_inventory{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

     function get_extend_colums(){
//        $brObj = app::get('ome')->model('branch');
//
//        $is_super = kernel::single('desktop_user')->is_super();
//
//        if ($is_super){
//            $branch_rows = $brObj->getList('branch_id,name','',0,-1);
//        }else{
//            $branch_rows = $brObj->getBranchByUser();
//        }
//        $branch_list = array();
//        foreach($branch_rows as $branch){
//            $branch_list [$branch['branch_id']] = $branch['name'];
//        }
//       
//        $db['inventory']=array (
//            'columns' => array (
//               'branch_id' =>
//                    array (
//                    'type' => $branch_list,
//                    'editable' => false,
//                    'label' => '仓库',
//                    'width' => 110,
//                    'filtertype' => 'normal',
//                    'filterdefault' => true,
//                    'in_list' => true,
//                    'panel_id' => 'inventory_finder_top',
//                ),
//            )
//        );
//        return $db;
     }
}
