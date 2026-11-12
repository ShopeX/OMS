<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class material_capability_sales_material
{
    private object $source;
    private object $templateSource;

    public function __construct(?object $source = null, ?object $templateSource = null)
    {
        $this->source = $source ?? app::get('material')->model('sales_material');
        $this->templateSource = $templateSource ?? app::get('material')->model('sales_material');
    }

    public function list(array $request): array
    {
        return $this->source->list($request);
    }

    public function detail(int $smId): ?array
    {
        if ($smId < 1) {
            throw new InvalidArgumentException('No sm_id provided');
        }

        return $this->source->detail($smId);
    }

    public function importTemplate(): array
    {
        return $this->templateSource->importTemplate();
    }
}
