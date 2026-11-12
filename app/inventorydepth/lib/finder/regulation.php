<?php

class inventorydepth_finder_regulation {
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    public $column_operator = '操作';
    public $column_operator_order = 1;
    public function column_operator($row)
    {
        $regulation_id = $row['regulation_id']; $finder_id = $_GET['_finder']['finder_id'];
        $return = <<<EOF
        <a target="_blank" href="index.php?app=inventorydepth&ctl=regulation&act=edit&p[0]={$regulation_id}&finder_id={$finder_id}">编辑</a>
EOF;
        return $return;

    }

}
