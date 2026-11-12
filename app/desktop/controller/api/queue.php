<?php

use app\desktop\lib\annotation\RequirePermission;

class desktop_ctl_api_queue extends desktop_ctl_api_base
{
    protected static $objectName = 'base_mdl_queue';

    private const FINDER_ALIAS = 'desktop-queue-vue';

    private const FINDER_COLS = 'queue_id,queue_name,status,worker_name,create_time,begin_time,end_time,message,error_count';

    #[RequirePermission('performance')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName' => static::$objectName,
            'finderAlias' => self::FINDER_ALIAS,
            'title' => app::get('desktop')->_('队列列表'),
            'finderCols' => self::FINDER_COLS,
            'use_buildin_filter' => true,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'orderBy' => 'queue_id desc',
        
            'actions' => [],
        ]);
    }

    /**
     * POST - Start queue workers (JSON API)
     * ?app=desktop&ctl=api_queue&act=run
     * Body: queue_id[]=1&queue_id[]=2 (form-urlencoded) or JSON { queue_id: [1,2] }
     */
    #[RequirePermission('performance')]
    public function run(): void
    {
        try {
            $input = $this->getRequestInput();
            $queueIds = $input['queue_id'] ?? $_POST['queue_id'] ?? [];
            if (!is_array($queueIds)) {
                $queueIds = [$queueIds];
            }
            $queueIds = array_values(array_filter(array_map('intval', $queueIds)));

            if (empty($queueIds)) {
                $this->fail(app::get('desktop')->_('请选择要启动的队列'));
                return;
            }

            $queueModel = app::get('base')->model('queue');
            foreach ($queueIds as $id) {
                $queueModel->save(['queue_id' => $id, 'status' => 'hibernate']);
            }
            $queueModel->flush();

            $this->succ([
                'message' => app::get('desktop')->_('启动成功'),
                'queue_ids' => $queueIds,
            ]);
        } catch (Throwable $e) {
            error_log('[api_queue::run] ' . $e->getMessage());
            $this->fail('启动失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST - Pause queue workers (JSON API)
     * ?app=desktop&ctl=api_queue&act=pause
     */
    #[RequirePermission('performance')]
    public function pause(): void
    {
        try {
            $input = $this->getRequestInput();
            $queueIds = $input['queue_id'] ?? $_POST['queue_id'] ?? [];
            if (!is_array($queueIds)) {
                $queueIds = [$queueIds];
            }
            $queueIds = array_values(array_filter(array_map('intval', $queueIds)));

            if (empty($queueIds)) {
                $this->fail(app::get('desktop')->_('请选择要暂停的队列'));
                return;
            }

            $queueModel = app::get('base')->model('queue');
            foreach ($queueIds as $id) {
                $queueModel->save(['queue_id' => $id, 'status' => 'paused']);
            }
            $queueModel->flush();

            $this->succ([
                'message' => app::get('desktop')->_('暂停成功'),
                'queue_ids' => $queueIds,
            ]);
        } catch (Throwable $e) {
            error_log('[api_queue::pause] ' . $e->getMessage());
            $this->fail('暂停失败: ' . $e->getMessage(), 500);
        }
    }
}
