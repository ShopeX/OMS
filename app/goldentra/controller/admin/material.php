<?php

class goldentra_ctl_admin_material extends desktop_controller
{
    public $workground = 'goldentra_center';

    /** 同步转换上限，超出则分批投递异步任务 */
    const MANUAL_CONVERT_SYNC_LIMIT = 100;

    /** 每批异步任务携带的 material_id 数量 */
    const MANUAL_CONVERT_ASYNC_CHUNK = 100;

    /**
     * 主档列表 Tab
     */
    public function _views()
    {
        $mdl = app::get('goldentra')->model('material');
        $sub_menu = array(
            0 => array('label' => app::get('base')->_('全部'), 'filter' => array(), 'optional' => false),
            1 => array('label' => app::get('base')->_('未匹配'), 'filter' => array('match_status' => '0'), 'optional' => false),
            2 => array('label' => app::get('base')->_('未处理'), 'filter' => array('process_status' => 'pending'), 'optional' => false),
            3 => array('label' => app::get('base')->_('已匹配'), 'filter' => array('match_status' => '1'), 'optional' => false),
            4 => array('label' => app::get('base')->_('处理成功'), 'filter' => array('process_status' => 'succ'), 'optional' => false),
            5 => array('label' => app::get('base')->_('处理失败'), 'filter' => array('process_status' => 'fail'), 'optional' => false),
            6 => array('label' => app::get('base')->_('异常'), 'filter' => array('process_status' => 'none'), 'optional' => false),
        );

        $i = 0;
        foreach ($sub_menu as $k => $v) {
            $filter = $v['filter'] ? $v['filter'] : array();
            $sub_menu[$k]['filter'] = $filter ?: null;
            if (empty($this->noViewCount)) {
                $sub_menu[$k]['addon'] = $mdl->viewcount($filter);
            }
            $sub_menu[$k]['href'] = 'index.php?app=goldentra&ctl=admin_material&act=index&view=' . $i++;
        }

        return $sub_menu;
    }

    public function index()
    {
        $params = array(
            'title' => 'SAP主档信息',
            'use_view_tab' => true,
            'actions' => array(
                array(
                    'label' => '人工转换基础物料',
                    'submit' => 'index.php?app=goldentra&ctl=admin_material&act=manualConvert',
                    'target' => 'refresh',
                    'confirm' => '确认将选中的主档转换为/关联基础物料？全选时将按当前筛选条件异步批量转换。',
                ),
            ),
            'base_filter' => array(),
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'use_buildin_import' => false,
            'orderBy' => 'material_id DESC',
        );

        $this->finder('goldentra_mdl_material', $params);
    }

    public function manualConvert()
    {
        // 全选只分页投递，不再攒全量 ID；2G 仅作兜底
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $view = isset($_GET['view']) ? intval($_GET['view']) : 0;
        if (isset($_POST['view'])) {
            $view = intval($_POST['view']);
        }
        $this->begin('index.php?app=goldentra&ctl=admin_material&act=index&view=' . $view);

        // 全选：按筛选条件边查边入队，避免百万 ID 撑爆内存
        if (!empty($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_') {
            $filter = $this->buildManualConvertFilter($view);
            $result = $this->pushManualConvertByFilter($filter);
            $msg = sprintf(
                '已投递异步转换：共扫描 %d 条，成功投递 %d 批（每批最多 %d 条）',
                $result['total'],
                $result['pushed'],
                self::MANUAL_CONVERT_ASYNC_CHUNK
            );
            if ($result['failed'] > 0) {
                $msg .= sprintf('，失败 %d 批（请检查 taskmgr）', $result['failed']);
            }
            if ($result['total'] <= 0) {
                $this->end(false, '当前筛选条件下没有可转换的主档记录');
            }
            $this->end($result['pushed'] > 0, $msg);
        }

        $ids = $this->resolveManualConvertIds($view);
        if (!$ids) {
            $this->end(false, '请勾选需要转换的主档记录');
        }

        $count = count($ids);
        if ($count > self::MANUAL_CONVERT_SYNC_LIMIT) {
            $chunks = array_chunk($ids, self::MANUAL_CONVERT_ASYNC_CHUNK);
            $pushed = 0;
            $failed = 0;
            foreach ($chunks as $chunk) {
                $taskData = array('material_ids' => $chunk);
                if (goldentra_autotask::push('goldentramaterial', $taskData)) {
                    $pushed++;
                } else {
                    $failed++;
                }
            }
            $msg = sprintf(
                '已投递异步转换：共 %d 条，分 %d 批（每批最多 %d 条），成功投递 %d 批',
                $count,
                count($chunks),
                self::MANUAL_CONVERT_ASYNC_CHUNK,
                $pushed
            );
            if ($failed > 0) {
                $msg .= sprintf('，失败 %d 批（请检查 taskmgr）', $failed);
            }
            $this->end($pushed > 0, $msg);
        }

        $result = kernel::single('goldentra_material_processor')->processByIds($ids);
        $msg = sprintf('转换完成：成功 %d 条，失败 %d 条', $result['ok'], $result['fail']);
        if (!empty($result['fail_msgs'])) {
            $preview = array_slice($result['fail_msgs'], 0, 5);
            $msg .= '。' . implode('；', $preview);
            if (count($result['fail_msgs']) > 5) {
                $msg .= '…';
            }
        }

        $this->end($result['ok'] > 0, $msg);
    }

    /**
     * 全选：按筛选条件游标翻页取出 material_id，立即投递，不在内存中堆积全量 ID
     *
     * 不用 OFFSET：转换过程中记录离开筛选结果集会漏数；用 material_id > lastId 游标推进。
     *
     * @param array $filter
     * @return array{total:int,pushed:int,failed:int}
     */
    protected function pushManualConvertByFilter(array $filter)
    {
        $mdl = app::get('goldentra')->model('material');
        $mdl->filter_use_like = true;

        $total = 0;
        $pushed = 0;
        $failed = 0;
        $lastId = 0;
        $pageSize = 1000;
        $chunkSize = self::MANUAL_CONVERT_ASYNC_CHUNK;
        $buffer = array();

        do {
            $pageFilter = $filter;
            // 游标：始终取比上一批最大 id 更大的记录，避免 OFFSET 漏数/重扫
            if ($lastId > 0) {
                $pageFilter['material_id|than'] = $lastId;
            }
            $rows = $mdl->getList('material_id', $pageFilter, 0, $pageSize, 'material_id ASC');
            if (!is_array($rows) || !$rows) {
                break;
            }
            $got = 0;
            foreach ($rows as $row) {
                $id = intval($row['material_id']);
                if ($id <= 0) {
                    continue;
                }
                if ($id > $lastId) {
                    $lastId = $id;
                }
                $buffer[] = $id;
                $total++;
                $got++;
                if (count($buffer) >= $chunkSize) {
                    $taskData = array('material_ids' => $buffer);
                    if (goldentra_autotask::push('goldentramaterial', $taskData)) {
                        $pushed++;
                    } else {
                        $failed++;
                    }
                    $buffer = array();
                }
            }
            unset($rows);
        } while ($got >= $pageSize);

        if ($buffer) {
            $taskData = array('material_ids' => $buffer);
            if (goldentra_autotask::push('goldentramaterial', $taskData)) {
                $pushed++;
            } else {
                $failed++;
            }
        }

        return array(
            'total' => $total,
            'pushed' => $pushed,
            'failed' => $failed,
        );
    }

    /**
     * 组装人工转换筛选条件（Tab + 快捷/高级筛选）
     *
     * @param int $view
     * @return array
     */
    protected function buildManualConvertFilter($view)
    {
        $baseFilter = array();
        $this->noViewCount = true;
        $subMenu = $this->_views();
        $this->noViewCount = false;
        if (!empty($subMenu[$view]['filter']) && is_array($subMenu[$view]['filter'])) {
            $baseFilter = $subMenu[$view]['filter'];
        }

        $filter = array_merge($baseFilter, $_POST);
        foreach (array(
            'isSelectedAll',
            'material_id',
            '_finder',
            'finder_id',
            'finder_vid',
            'app',
            'ctl',
            'act',
            'view',
            'offset',
            'limit',
            '_export',
            '_io_type',
            '_export_type',
        ) as $dropKey) {
            unset($filter[$dropKey]);
        }
        foreach ($filter as $k => $v) {
            if ($v === '' || $v === null || $v === array()) {
                unset($filter[$k]);
            }
        }
        return $filter;
    }

    /**
     * 解析人工转换目标 ID：仅勾选场景（非全选）
     *
     * @param int $view
     * @return int[]
     */
    protected function resolveManualConvertIds($view)
    {
        $ids = isset($_POST['material_id']) ? (array) $_POST['material_id'] : array();
        return array_values(array_filter(array_map('intval', $ids)));
    }
}
