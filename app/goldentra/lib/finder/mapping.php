<?php

class goldentra_finder_mapping
{
    var $column_items = '数量';
    var $column_items_width = '80';
    var $column_items_order = COLUMN_IN_TAIL;

    public function column_items($row)
    {
        $mapping_id = intval($row['mapping_id']);
        $count = app::get('goldentra')->model('mapping_item')->count(array('mapping_id' => $mapping_id));
        $html = <<<EOF
<span class="goldentra_mapping_show_list" mapping_id="{$mapping_id}"><a href="javascript:void(0);" style="color:#0033cc;text-decoration:underline;cursor:pointer;">{$count}</a></span>
<script>
if (!window.__goldentra_mapping_show_bind) {
    window.__goldentra_mapping_show_bind = true;
    document.addEvent('click', function (e) {
        var el = e.target;
        if (!el) {
            return;
        }
        var node = el.get ? el : $(el);
        var span = node.hasClass('goldentra_mapping_show_list')
            ? node
            : (node.getParent ? node.getParent('.goldentra_mapping_show_list') : null);
        if (!span) {
            return;
        }
        e.stop();
        var mapping_id = span.get('mapping_id');
        if (!mapping_id) {
            return;
        }
        var t_url = 'index.php?app=goldentra&ctl=admin_mapping&act=show&p[0]=' + mapping_id;
        var url = 'index.php?app=desktop&ctl=default&act=alertpages&goto=' + encodeURIComponent(t_url);
        Ex_Loader('modedialog', function () {
            new finderDialog(url, { width: 1000, height: 660 });
        });
    });
}
</script>
EOF;
        return $html;
    }

    var $column_edit = '操作';
    var $column_edit_width = '200';

    public function column_edit($row)
    {
        $mapping_id = $row['mapping_id'];
        $mold_code = $row['mold_code'];
        $type = $row['type'];
        $finder_id = $_GET['_finder']['finder_id'];
        $button = array();

        if ($type == 'gold') {
            $button[] = '<a href="index.php?app=goldentra&ctl=admin_mapping&act=edit&p[0]=' . $mapping_id . '&finder_id=' . $finder_id . '" target="_blank">编辑</a>';
        } else {
            $button[] = '<a href="index.php?app=goldentra&ctl=admin_mapping&act=editDiamondPage&p[0]=' . $mapping_id . '&finder_id=' . $finder_id . '" target="_blank">编辑</a>';
        }

        if ($row['disabled'] == 0) {
            $button[] = '<a href="javascript:if(confirm(\'是否禁用当前模号[' . $mold_code . ']\')){new Request.JSON({url:\'index.php?app=goldentra&ctl=admin_mapping&act=disabled&id=' . $mapping_id . '\',method:\'GET\',onSuccess:function(res){if(res.status==200){MessageBox.success(res.msg);window.finderGroup[\'' . $finder_id . '\'].refresh(true);}else{MessageBox.error(res.msg);}}}).send();}">禁用</a>';
        }

        return implode('|', $button);
    }
}
