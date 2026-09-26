<?php

class goldentra_finder_metadata
{
    var $column_items = '字典值数';
    var $column_items_width = '80';
    var $column_items_order = COLUMN_IN_TAIL;

    public function column_items($row)
    {
        $metadata_id = $row['metadata_id'];
        $count = app::get('goldentra')->model('metadata_item')->count(array('metadata_id' => $metadata_id));
        $html = <<<EOF
<span class="goldentra_metadata_show_list" metadata_id="{$metadata_id}"><a href="javascript:void(0);">{$count}</a></span>
<script>
if (!window.__goldentra_metadata_show_bind) {
    window.__goldentra_metadata_show_bind = true;
    $$(".goldentra_metadata_show_list[metadata_id]").addEvent('click', function (e) {
        if (e) {
            e.stop();
        }
        var metadata_id = this.get('metadata_id');
        if (!metadata_id) {
            return;
        }
        var t_url = 'index.php?app=goldentra&ctl=admin_metadata&act=show&p[0]=' + metadata_id;
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

    var $detail_basic = '基本信息';

    public function detail_basic($metadata_id)
    {
        $row = app::get('goldentra')->model('metadata')->dump($metadata_id, '*');
        $render = app::get('goldentra')->render();
        $render->pagedata['row'] = $row;
        $render->pagedata['ext_rows'] = goldentra_metadata::decodeExtFieldRows($row['ext_field']);
        return $render->fetch('admin/metadata/detail_basic.html');
    }

    var $detail_items = '字典值';

    public function detail_items($metadata_id)
    {
        $header = app::get('goldentra')->model('metadata')->dump($metadata_id, 'ext_field');
        $labels = array();
        if ($header) {
            $data = is_array($header['ext_field'])
                ? $header['ext_field']
                : json_decode($header['ext_field'], true);
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    if (strpos($k, 'ext_') === 0 && $v !== '' && $v !== null) {
                        $labels[$k] = is_scalar($v) ? (string) $v : '';
                    }
                }
            }
        }

        $items = app::get('goldentra')->model('metadata_item')->getList(
            '*',
            array('metadata_id' => $metadata_id),
            0,
            -1,
            'item_id ASC'
        );
        foreach ($items as $k => $item) {
            $rows = goldentra_metadata::decodeExtFieldRows($item['ext_field']);
            foreach ($rows as $i => $ext) {
                if (!empty($labels[$ext['key']])) {
                    $rows[$i]['key'] = $labels[$ext['key']];
                }
            }
            $items[$k]['ext_rows'] = $rows;
        }

        $render = app::get('goldentra')->render();
        $render->pagedata['items'] = $items;
        return $render->fetch('admin/metadata/detail_items.html');
    }
}
