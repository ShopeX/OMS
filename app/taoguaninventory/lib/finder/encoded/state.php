<?php
class taoguaninventory_finder_encoded_state{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $column_view = '操作';
    var $column_view_width = "100";
    var $addon_cols = "eid";
     function column_view($row){
         $id = $row[$this->col_prefix.'eid'];
         $finder_id = $_GET['_finder']['finder_id'];
$button= <<<EOF
<a href="index.php?app=taoguaninventory&ctl=admin_codestate&act=edit_state&p[0]=$id&finder_id=$finder_id" class="lnk" " target="dialog::{width:600,height:400,title:'编码编辑'}">编辑</a>&nbsp;
&nbsp;
EOF;
return $button;

     }

}

?>
