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


declare(strict_types=1);

final class console_purchase_import_file_adapter
{
    public function toLegacyCsv(string $utf8Csv): string
    {
        if ($utf8Csv === '' || !mb_check_encoding($utf8Csv, 'UTF-8')) {
            throw new InvalidArgumentException('采购单 CSV 必须为有效 UTF-8');
        }

        $legacyCsv = mb_convert_encoding($utf8Csv, 'GBK', 'UTF-8');
        if ($legacyCsv === '') {
            throw new InvalidArgumentException('采购单 CSV 编码转换失败');
        }

        return $legacyCsv;
    }
}
