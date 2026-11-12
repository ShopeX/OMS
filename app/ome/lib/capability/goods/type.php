<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_goods_type
{
    private object $source;

    public function __construct(?object $source = null)
    {
        $this->source = $source ?? app::get('ome')->model('goods_type');
    }

    public function list(array $request): array
    {
        return $this->source->list($request);
    }

    public function detail(int $typeId): ?array
    {
        if ($typeId < 1) {
            throw new InvalidArgumentException('type_id is required');
        }

        return $this->source->detail($typeId);
    }
}
