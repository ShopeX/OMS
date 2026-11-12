<?php
/**
 * @author chenping<chenping@shopex.cn>
 */

class inventorydepth_finder_regulation_productselect {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $column_operator = '操作';
    public $column_operator_order = 1;
    public function column_operator($row)
    {
        $id = $row['id'];
        $init_bn = $_GET['init_bn'];
        $condition = $_GET['condition'];
        $finder_id = $_GET['_finder']['finder_id'];
        $url = "index.php?app=inventorydepth&ctl=regulation_apply&act=removeFilter&p[0]={$id}&p[1]={$init_bn}&p[2]={$condition}";
        $url .= '&finder_id='.$finder_id;

        $button = <<<EOF
        <a href='javascript:void(0);' onclick='
        if(confirm("确认要移除吗？")){
            W.page("{$url}",{
            });
            window.finderGroup["{$finder_id}"].refresh(true);
        }
        '>移除</a>
EOF;
        return $button;
    }

}
