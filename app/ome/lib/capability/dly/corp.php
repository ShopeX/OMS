<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_dly_corp
{
    private object $model;

    public function __construct(?object $model = null)
    {
        $this->model = $model ?? app::get('ome')->model('dly_corp');
    }

    public function list(): array
    {
        return ['list' => $this->model->getList('*', ['disabled' => 'false'])];
    }

    public function detail(int $corpId): ?array
    {
        if ($corpId < 1) {
            throw new InvalidArgumentException('缺少 corp_id 参数');
        }

        $detail = $this->model->dump($corpId);
        if (!is_array($detail) || $detail === []) {
            return null;
        }

        $detail['area_fee_conf'] = !empty($detail['area_fee_conf']) ? (unserialize($detail['area_fee_conf']) ?: []) : [];
        $detail['protect_rate'] = floatval($detail['protect_rate'] ?? 0) * 100;

        return [
            'detail' => $detail,
            'dlytype_readonly' => 0,
        ];
    }
}
