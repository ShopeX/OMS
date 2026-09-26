<?php

class goldentra_ctl_admin_stock_sync extends desktop_controller
{
    public $workground = 'goldentra_center';

    /** 增量比对单次任务处理条数（与 worker 默认一致） */
    const COMPARE_INCREMENT_LIMIT = 1000;

    /**
     * 库存同步列表 Tab（按处理状态）
     */
    public function _views()
    {
        $mdl = app::get('goldentra')->model('stock');
        $sub_menu = array(
            0 => array('label' => app::get('base')->_('全部'), 'filter' => array(), 'optional' => false),
            1 => array('label' => app::get('base')->_('待处理'), 'filter' => array('process_status' => 'pending'), 'optional' => false),
            2 => array('label' => app::get('base')->_('处理中'), 'filter' => array('process_status' => 'running'), 'optional' => true),
            3 => array('label' => app::get('base')->_('处理成功'), 'filter' => array('process_status' => 'succ'), 'optional' => true),
            4 => array('label' => app::get('base')->_('处理失败'), 'filter' => array('process_status' => 'fail'), 'optional' => true),
            5 => array('label' => app::get('base')->_('已跳过'), 'filter' => array('process_status' => 'skip'), 'optional' => true),
        );

        $i = 0;
        foreach ($sub_menu as $k => $v) {
            $filter = $v['filter'] ? $v['filter'] : array();
            $sub_menu[$k]['filter'] = $filter ?: null;
            if (empty($this->noViewCount)) {
                $sub_menu[$k]['addon'] = $mdl->viewcount($filter);
            }
            $sub_menu[$k]['href'] = 'index.php?app=goldentra&ctl=admin_stock_sync&act=index&view=' . $i++;
        }

        return $sub_menu;
    }

    public function index()
    {
        $actions = array(
            array(
                'label' => 'FTP全量导入',
                'href' => 'index.php?app=goldentra&ctl=admin_stock_sync&act=ftpImportDialog',
                'target' => 'dialog::{width:520,height:260,title:\'FTP全量导入\'}',
            ),
        );

        $view = isset($_GET['view']) ? (string) $_GET['view'] : '0';
        if (in_array($view, array('1', '4'), true)) {
            $actions[] = array(
                'label' => '处理待比对',
                'submit' => 'index.php?app=goldentra&ctl=admin_stock_sync&act=triggerProcess',
                'target' => 'refresh',
                'confirm' => '确认将选中的库存同步记录加入比对队列？全选时按当前筛选条件、按同步批次分批入队（全量每批次 1 个任务，增量每任务最多 '
                    . self::COMPARE_INCREMENT_LIMIT . ' 条）。',
            );
        }

        $params = array(
            'title' => '库存同步',
            'use_view_tab' => true,
            'actions' => $actions,
            'base_filter' => array(),
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'use_buildin_import' => false,
            'orderBy' => 'stock_id DESC',
        );

        $this->finder('goldentra_mdl_stock', $params);
    }

    /**
     * 处理待比对：支持勾选 / 全选，按 sync_batch_no 分批入队 goldentrastock
     */
    public function triggerProcess()
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $view = isset($_GET['view']) ? intval($_GET['view']) : 0;
        if (isset($_POST['view'])) {
            $view = intval($_POST['view']);
        }
        $this->begin('index.php?app=goldentra&ctl=admin_stock_sync&act=index&view=' . $view);

        if (!empty($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_') {
            $filter = $this->buildStockCompareFilter($view);
            $batches = $this->collectBatchesByFilter($filter);
            if (!$batches) {
                $this->end(false, '当前筛选条件下没有可比对的库存同步记录');
            }
            $result = $this->pushStockCompareBatches($batches);
            $this->end($result['pushed'] > 0, $this->formatStockComparePushMsg($result));
        }

        $ids = $this->resolveStockCompareIds();
        if (!$ids) {
            $this->end(false, '请勾选需要比对的库存同步记录，或使用全选');
        }

        $batches = $this->collectBatchesByIds($ids);
        if (!$batches) {
            $this->end(false, '勾选记录没有有效的同步批次号');
        }
        $result = $this->pushStockCompareBatches($batches);
        $this->end($result['pushed'] > 0, $this->formatStockComparePushMsg($result));
    }

    /**
     * FTP 全量导入弹框（无需勾选列表）
     */
    public function ftpImportDialog()
    {
        $this->pagedata['finder_id'] = isset($_GET['finder_id']) ? $_GET['finder_id'] : '';
        $this->pagedata['default_file_date'] = date('Y-m-d');
        $this->page('admin/stock/ftp_import_dialog.html');
    }

    /**
     * 将 FTP 全量导入加入异步队列（可指定日期）
     */
    public function doFtpImport()
    {
        $file_date = $this->normalizeFtpFileDate(
            isset($_POST['file_date']) ? $_POST['file_date'] : (isset($_GET['file_date']) ? $_GET['file_date'] : '')
        );
        if ($file_date === '') {
            echo json_encode(array(
                'success' => false,
                'msg' => '请填写有效的库存文件日期（YYYY-MM-DD 或 YYYYMMDD）',
            ));
            return;
        }

        $ok = goldentra_autotask::push('goldentrastockftp', array(
            'file_date' => $file_date,
        ));
        if ($ok) {
            echo json_encode(array(
                'success' => true,
                'msg' => sprintf(
                    '已加入 FTP 全量导入队列（日期 %s），后台处理后会自动排队比对，请稍后刷新列表查看',
                    $file_date
                ),
            ));
            return;
        }
        echo json_encode(array(
            'success' => false,
            'msg' => '加入 FTP 全量导入队列失败，请检查 taskmgr 是否已配置 goldentrastockftp 白名单',
        ));
    }

    public function triggerFtpImport()
    {
        $view = isset($_GET['view']) ? intval($_GET['view']) : 0;
        $this->begin('index.php?app=goldentra&ctl=admin_stock_sync&act=index&view=' . $view);

        $file_date = $this->normalizeFtpFileDate(
            isset($_POST['file_date']) ? $_POST['file_date'] : (isset($_GET['file_date']) ? $_GET['file_date'] : date('Ymd'))
        );
        if ($file_date === '') {
            $this->end(false, '请填写有效的库存文件日期（YYYY-MM-DD 或 YYYYMMDD）');
        }

        $ok = goldentra_autotask::push('goldentrastockftp', array(
            'file_date' => $file_date,
        ));
        if (!$ok) {
            $this->end(false, '加入 FTP 全量导入队列失败，请检查 taskmgr 是否已配置 goldentrastockftp 白名单');
        }
        $this->end(true, sprintf('已加入 FTP 全量导入队列（日期 %s），后台处理后会自动排队比对', $file_date));
    }

    /**
     * Tab + 快捷/高级筛选
     *
     * @param int $view
     * @return array
     */
    protected function buildStockCompareFilter($view)
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
            'stock_id',
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

        // 仅待处理/失败可投比对；「全部」Tab 全选时收窄到可处理状态
        if (empty($filter['process_status']) && empty($filter['process_status|in'])) {
            $filter['process_status|in'] = array('pending', 'fail');
        }

        return $filter;
    }

    /**
     * @return int[]
     */
    protected function resolveStockCompareIds()
    {
        $ids = isset($_POST['stock_id']) ? (array) $_POST['stock_id'] : array();
        return array_values(array_filter(array_map('intval', $ids)));
    }

    /**
     * 全选：游标扫描，按 sync_batch_no 聚合（不攒全量 ID）
     *
     * @param array $filter
     * @return array sync_batch_no => array(stock_type, cnt)
     */
    protected function collectBatchesByFilter(array $filter)
    {
        $mdl = app::get('goldentra')->model('stock');
        $mdl->filter_use_like = true;

        $batches = array();
        $lastId = 0;
        $pageSize = 2000;

        do {
            $pageFilter = $filter;
            if ($lastId > 0) {
                $pageFilter['stock_id|than'] = $lastId;
            }
            $rows = $mdl->getList(
                'stock_id,sync_batch_no,stock_type,process_status',
                $pageFilter,
                0,
                $pageSize,
                'stock_id ASC'
            );
            if (!is_array($rows) || !$rows) {
                break;
            }
            $got = 0;
            foreach ($rows as $row) {
                $id = intval($row['stock_id']);
                if ($id > $lastId) {
                    $lastId = $id;
                }
                $got++;
                $bn = isset($row['sync_batch_no']) ? trim((string) $row['sync_batch_no']) : '';
                if ($bn === '') {
                    continue;
                }
                if (!isset($batches[$bn])) {
                    $batches[$bn] = array(
                        'stock_type' => !empty($row['stock_type']) ? $row['stock_type'] : 'increment',
                        'cnt' => 0,
                    );
                }
                $batches[$bn]['cnt']++;
                // 同批次出现 full 时优先按全量处理
                if (!empty($row['stock_type']) && $row['stock_type'] === 'full') {
                    $batches[$bn]['stock_type'] = 'full';
                }
            }
            unset($rows);
        } while ($got >= $pageSize);

        return $batches;
    }

    /**
     * 勾选：按 stock_id 解析批次
     *
     * @param int[] $ids
     * @return array
     */
    protected function collectBatchesByIds(array $ids)
    {
        $batches = array();
        $mdl = app::get('goldentra')->model('stock');
        foreach (array_chunk($ids, 500) as $chunk) {
            $rows = $mdl->getList(
                'stock_id,sync_batch_no,stock_type,process_status',
                array('stock_id|in' => $chunk),
                0,
                -1
            );
            foreach ((array) $rows as $row) {
                if (!in_array($row['process_status'], array('pending', 'fail'), true)) {
                    continue;
                }
                $bn = isset($row['sync_batch_no']) ? trim((string) $row['sync_batch_no']) : '';
                if ($bn === '') {
                    continue;
                }
                if (!isset($batches[$bn])) {
                    $batches[$bn] = array(
                        'stock_type' => !empty($row['stock_type']) ? $row['stock_type'] : 'increment',
                        'cnt' => 0,
                    );
                }
                $batches[$bn]['cnt']++;
                if (!empty($row['stock_type']) && $row['stock_type'] === 'full') {
                    $batches[$bn]['stock_type'] = 'full';
                }
            }
        }
        return $batches;
    }

    /**
     * 全量：每批次 1 个任务；增量：按条数拆成多个任务（每任务最多 COMPARE_INCREMENT_LIMIT）
     *
     * @param array $batches
     * @return array{rows:int,batches:int,pushed:int,failed:int}
     */
    protected function pushStockCompareBatches(array $batches)
    {
        $rows = 0;
        $pushed = 0;
        $failed = 0;
        $batchCnt = 0;

        foreach ($batches as $sync_batch_no => $info) {
            $batchCnt++;
            $cnt = isset($info['cnt']) ? intval($info['cnt']) : 0;
            $rows += $cnt;
            $stockType = isset($info['stock_type']) ? $info['stock_type'] : 'increment';

            if ($stockType === 'full') {
                $jobs = 1;
                $taskData = array('sync_batch_no' => $sync_batch_no);
            } else {
                $jobs = max(1, (int) ceil($cnt / self::COMPARE_INCREMENT_LIMIT));
                $taskData = array(
                    'sync_batch_no' => $sync_batch_no,
                    'limit' => self::COMPARE_INCREMENT_LIMIT,
                );
            }

            for ($i = 0; $i < $jobs; $i++) {
                if (goldentra_autotask::push('goldentrastock', $taskData)) {
                    $pushed++;
                } else {
                    $failed++;
                }
            }
        }

        return array(
            'rows' => $rows,
            'batches' => $batchCnt,
            'pushed' => $pushed,
            'failed' => $failed,
        );
    }

    /**
     * @param array $result
     * @return string
     */
    protected function formatStockComparePushMsg(array $result)
    {
        $msg = sprintf(
            '已投递库存比对：扫描 %d 条，涉及 %d 个同步批次，成功入队 %d 个任务',
            isset($result['rows']) ? $result['rows'] : 0,
            isset($result['batches']) ? $result['batches'] : 0,
            isset($result['pushed']) ? $result['pushed'] : 0
        );
        if (!empty($result['failed'])) {
            $msg .= sprintf('，失败 %d 个（请检查 taskmgr / goldentrastock 白名单）', $result['failed']);
        }
        return $msg;
    }

    /**
     * @param string $raw
     * @return string Ymd 或空串
     */
    private function normalizeFtpFileDate($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return date('Ymd');
        }
        if (preg_match('/^\d{8}$/', $raw)) {
            $ts = strtotime(substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2));
            return $ts ? date('Ymd', $ts) : '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $ts = strtotime($raw);
            return $ts ? date('Ymd', $ts) : '';
        }
        $digits = preg_replace('/\D/', '', $raw);
        if (preg_match('/^\d{8}$/', $digits)) {
            $ts = strtotime(substr($digits, 0, 4) . '-' . substr($digits, 4, 2) . '-' . substr($digits, 6, 2));
            return $ts ? date('Ymd', $ts) : '';
        }
        return '';
    }
}
