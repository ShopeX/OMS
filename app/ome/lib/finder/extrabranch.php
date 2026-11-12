<?php
class ome_finder_extrabranch{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "查看";
    var $detail_basic_width = "50";
    function detail_basic($branch_id){
        $render = app::get('ome')->render();
        $oExtrabranch = app::get('ome')->model('extrabranch');

        $render->pagedata['branch'] = $oExtrabranch->dump($branch_id);

        return $render->fetch('admin/extrabranch/branch_detail.html');
    }

    var $addon_cols = "branch_id";
    var $column_edit = "操作";
    var $column_edit_width = "50";
    function column_edit($row){
        $finder_id = $_GET['_finder']['finder_id'];
        return '<a href="index.php?app=ome&ctl=admin_extrabranch&act=editbranch&p[0]='.$row[$this->col_prefix.'branch_id'].'&p[1]=true&_finder[finder_id]='.$finder_id.'&finder_id='.$finder_id.'" target="_blank">编辑</a>';
    }
}
?>
