<?php

class desktop_ctl_api_table_refs extends desktop_ctl_api_base
{
    public function index(): void
    {
        $this->actionRefs();
    }

    private function buildObjectName(): string
    {
        $model = $_GET['model'] ?? '';
        $appId = $_GET['app_id'] ?? '';
        
        if (!$model) {
            throw new Exception('model is required');
        }
        
        return $appId . '_mdl_' . $model;
    }

    protected function actionRefs(): void
    {
        $model = $_GET['model'] ?? '';
        $appId = $_GET['app_id'] ?? '';
        
        // Set type so that refs handler can parse it correctly
        $_GET['type'] = 'table:' . $model . '@' . $appId;
        
        $handler = new desktop_finder_vue_refs(
            new desktop_finder_vue_context([
                'objectName' => $this->buildObjectName(),
                'sensitiveFields' => [],
            ])
        );
        $result = $handler->handle($_GET);
        $this->succ($result);
    }
}
