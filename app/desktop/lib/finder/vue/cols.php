<?php
class desktop_finder_vue_cols
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    private $context;

    public function __construct($context)
    {
        $this->context = $context;
    }

    public function handle(array $request): array
    {
        $userId = $this->context->getCurrentUserId();
        $key = 'view.' . $this->context->getObjectName() . '.' . $this->context->getFinderAlias() . '.' . $userId;

        $action = $this->resolveAction($request);

        switch ($action) {
            case 'save':
                return $this->handleSave($key, $request);
            case 'reset':
                return $this->handleReset($key);
            case 'list':
            default:
                return $this->handleList($key);
        }
    }

    private function resolveAction(array $request): string
    {
        $action = strtolower($request['do'] ?? '');
        if (in_array($action, ['list', 'save', 'reset'], true)) {
            return $action;
        }

        if (!empty($request['reset'])) {
            return 'reset';
        }
        if (array_key_exists('cols', $request)) {
            return 'save';
        }

        return 'list';
    }

    private function handleList(string $key): array
    {
        return ['success' => true, 'cols' => app::get('desktop')->getConf($key)];
    }

    private function handleReset(string $key): array
    {
        app::get('desktop')->setConf($key, null);
        return ['success' => true, 'cols' => null, 'message' => 'Columns reset'];
    }

    private function handleSave(string $key, array $request): array
    {
        $cols = $request['cols'] ?? null;
        if (!is_array($cols)) {
            return ['success' => false, 'message' => 'cols must be an array'];
        }

        $normalized = [];
        foreach ($cols as $col) {
            $col = trim((string) $col);
            if ($col !== '' && !in_array($col, $normalized, true)) {
                $normalized[] = $col;
            }
        }

        if (empty($normalized)) {
            return $this->handleReset($key);
        }

        $colsStr = implode(',', $normalized);
        app::get('desktop')->setConf($key, $colsStr);

        return ['success' => true, 'cols' => $colsStr];
    }
}
