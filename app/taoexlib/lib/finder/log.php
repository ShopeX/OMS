<?php
class taoexlib_finder_log{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

	var $detail_edit = '详细列表';
    function detail_edit($id){
        $oItem = kernel::single("taoexlib_mdl_log");
        $items = $oItem->getList('*',
                     array('id' => $id), 0, 1);

        // Vue Finder：结构化返回（desktop_finder_vue_detail 分发到本方法）
        if ($this->vue_mode) {
            return array('detail' => $items[0]);
        }

        $render = app::get('taoexlib')->render();
        $render->pagedata['item'] = $items[0];
        $render->display('admin/logdetail.html');
    }	
}
