<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class desktop_finder_vue_detail
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
        $id = $request['id'] ?? null;
        $finderview = $request['finderview'] ?? null;

        if (!$id || !$finderview) {
            return ['error' => 'id and finderview are required'];
        }

        $detailPages = $this->context->getDetailPages();
        $methodName = 'detail_' . $finderview;

        if (!isset($detailPages[$methodName])) {
            return ['error' => 'detail view not found: ' . $methodName];
        }

        $detailDef = $detailPages[$methodName];
        $object = $detailDef['object'];
        $method = $detailDef['method'];

        // Ensure vue_mode is set on the finder object so detail_* returns structured data
        if (method_exists($object, 'setVueMode')) {
            $object->setVueMode(true);
        }

        $result = $object->$method($id);

        $data = $this->normalizeResult($result);

        // 金额列币种展示（判据 = payload header 里的 dbschema type='money'；多币种前瞻）
        $data = $this->formatMoneyColumns($data);

        $tabs = $this->buildTabs($finderview);

        return array_merge($data, [
            'tabs' => $tabs,
            'current' => $finderview,
        ]);
    }

    /**
     * Normalize detail_* result for Vue rendering.
     *
     * Finder detail_* methods may return:
     *   - Structured array (vue_mode returns pagedata): ['order' => ..., 'member' => ...] → 直接返回
     *   - HTML string (legacy): '<table>...</table>' → wrap as {html: ...}
     *   - null / other
     */
    private function normalizeResult($result): array
    {
        // Structured array (vue_mode returns pagedata directly)
        if (is_array($result) && !empty($result)) {
            return $result;
        }

        // HTML string (legacy finder not yet migrated)
        if (is_string($result)) {
            return ['html' => $result];
        }

        return [];
    }

    /**
     * 金额列币种展示：detail_* 返回的 header 即模型 _columns()（带 dbschema type），
     * 据此把 body / lines.body 里 type='money' 的列格式化为带币种符号的展示值。
     *
     * 列表通道同判据见 desktop_finder_vue_context::applyMoneyModifiersToAllMoneyColumns()。
     */
    private function formatMoneyColumns(array $data): array
    {
        if (isset($data['header'], $data['body']) && is_array($data['header']) && is_array($data['body'])) {
            $data['body'] = $this->context->applyMoneyFormatter($data['body'], $data['header']);
        }

        if (isset($data['lines']['header'], $data['lines']['body'])
            && is_array($data['lines']['header']) && is_array($data['lines']['body'])) {
            $data['lines']['body'] = $this->context->applyMoneyFormatter($data['lines']['body'], $data['lines']['header']);
        }

        return $data;
    }

    private function buildTabs(string $currentView): array
    {
        $detailPages = $this->context->getDetailPages();
        $tabs = [];

        foreach ($detailPages as $methodName => $detailDef) {
            $labelMethod = $detailDef['method'];
            $label = $detailDef['object']->$labelMethod ?: $labelMethod;
            $viewKey = substr($labelMethod, 7);

            $tabs[] = [
                'key'   => $viewKey,
                'label' => $label,
                'url'   => '?finderview=' . $viewKey,
            ];
        }

        return $tabs;
    }
}
