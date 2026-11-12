<?php
class iostock_finder_extend_filter_iostock{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    function get_extend_colums(){
        $brObj = app::get('ome')->model('branch');

        $is_super = kernel::single('desktop_user')->is_super();

        //过滤o2o门店虚拟仓库
        if ($is_super){
            $branch_rows = $brObj->getList('branch_id,name',array('b_type'=>1),0,-1);
        }else{
            $branch_rows = $brObj->getBranchByUser();
        }
        $branch_list = array();
        foreach($branch_rows as $branch){
            $branch_list [$branch['branch_id']] = $branch['name'];
        }
        $db['iostock']['columns']['branch_id']=array (
                    'type' => $branch_list,
                    'editable' => false,
                    'label' => '仓库',
                    'width' => 110,
                    'default_value'=>$branch_rows[0]['branch_id'],
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'panel_id' => 'iostock_finder_top',
        );
        $db['iostock']['columns']['name']=array (
                      'type' => 'table:basic_material@material',
                      'label' => '基础物料名称',
                    //   'searchtype' => 'has',
                      'filtertype' => 'normal',
                      'filterdefault' => true,
                      'in_list' => true,
                      'default_in_list' => true,
                  );
        
        //bill_type
        $sisoIostockLib = kernel::single('siso_receipt_iostock');
        $billList = $sisoIostockLib->getIostockBillTypes();
        if($billList){
            $db['iostock']['columns']['bill_type'] =array (
                'type' => $billList,
                'label' => '业务类型',
                'width' => 120,
                'editable' => false,
                'filtertype' => 'normal',
                'filterdefault' => true,
                'in_list' => true,
                'default_in_list' => true,
            );
        }
        
        return $db;
    }
}
//库房，出入库时间（开始时间 至 结束时间），出入库单据号，货号，货品名称，出入库类型（多选）
