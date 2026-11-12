<?php
/**
 * Shopex licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

class desktop_finder_vue_tabs
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private desktop_finder_vue_context $context;

    public function __construct(desktop_finder_vue_context $context)
    {
        $this->context = $context;
    }

    public function handle(array $request): array
    {
        $views = $this->context->buildViewDefinitions();
        $tabs = [];

        foreach ($views as $view) {
            $filter = $view['filter'] ?? [];
            $count = $this->getCount($filter, $view['needCache'] ?? true);
            $tabs[] = [
                'id' => $view['id'] ?? $view['label'],
                'label' => $view['label'],
                'filter' => $filter,
                'addon' => $count,
                'optional' => $view['optional'] ?? false,
            ];
        }

        // 追加用户保存的筛选条件（来自 desktop_filter 表）
        $savedFilters = $this->getSavedFilters();

        return [
            'tabs' => $tabs,
            'savedFilters' => $savedFilters,
            'filterContext' => [
                'model' => $this->context->getObjectName(),
            ],
        ];
    }

    /**
     * 获取当前用户保存的自定义筛选条件
     * 兼容 legacy desktop_finder_builder_prototype::get_views 的逻辑
     */
    private function getSavedFilters(): array
    {
        $userId = $this->context->getCurrentUserId();
        if (!$userId) {
            return [];
        }

        $objectName = $this->context->getObjectName();
        $filter = app::get('desktop')->model('filter');
        $_filter = [
            'model'   => $objectName,
            'user_id' => $userId,
        ];

        $rows = $filter->getList('*', $_filter, 0, -1, 'create_time asc');
        $result = [];
        foreach ($rows as $row) {
            // Legacy 存的是 query string 格式，parse 为数组
            $filterArray = [];
            if ($row['filter_query']) {
                parse_str($row['filter_query'], $filterArray);
            }
            $result[] = [
                'filter_id' => (int)$row['filter_id'],
                'filter_name' => $row['filter_name'],
                'filter' => $filterArray,
                'filter_query' => $row['filter_query'],
            ];
        }
        return $result;
    }

    private function getCount(array $filter, bool $needCache = true): ?int
    {
        $tabStyle = app::get('ome')->getConf('desktop.finder.tab');
        if ($tabStyle == '1') {
            return null;
        }

        try {
            $model = $this->context->getModel();
            $baseFilter = $this->context->getBaseFilter();
            $mergedFilter = array_merge($baseFilter, $filter);
            // viewcount() 在 legacy 语义下返回的是「懒加载 HTML 占位串」（真正的数字由前端异步请求
            // desktop&ctl=default&act=getviewcount 填入），并不是计数数字；而本方法声明为 ?int
            // ⇒ 直接返回该字符串会触发 TypeError，并被下面的 catch 吞成 0（表现为所有 Vue finder 页
            // 的 Tab 计数恒为 0）。故：只在确实拿到数字时用缓存值，否则回退到 $model->count() 取真实计数。
            $cached = $needCache ? $model->viewcount($mergedFilter) : null;

            return is_numeric($cached) ? (int) $cached : $model->count($mergedFilter);
        } catch (Throwable $e) {
            return 0;
        }
    }
}
