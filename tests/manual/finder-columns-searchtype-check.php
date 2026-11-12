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
 *
 * Verification script for searchtype passthrough in buildColumnsMeta.
 * Tests that columns with searchtype in dbschema expose it in columns metadata.
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', realpath(__DIR__ . '/../..'));
}

include_once ROOT_DIR . '/app/base/defined.php';
include_once ROOT_DIR . '/app/base/kernel.php';
include_once ROOT_DIR . '/app/base/lib/kvstore.php';
include_once ROOT_DIR . '/app/desktop/lib/finder/vue/context.php';

$context = new desktop_finder_vue_context([
    'objectName' => 'ome_mdl_api_log',
    'finderAlias' => 'api-log',
]);
$columns = $context->buildColumnsMeta();

// Verify searchtype is present for original_bn (has searchtype: nequal in dbschema)
$target = array_values(array_filter($columns, fn($c) => $c['field'] === 'original_bn'))[0] ?? null;
if (!isset($target['searchtype'])) {
    fwrite(STDERR, "searchtype field missing from original_bn column metadata\n");
    exit(1);
}
if ($target['searchtype'] !== 'nequal') {
    fwrite(STDERR, "searchtype value incorrect for original_bn: got '" . $target['searchtype'] . "', expected 'nequal'\n");
    exit(1);
}

// Verify worker column (has searchtype: nequal in dbschema)
$worker = array_values(array_filter($columns, fn($c) => $c['field'] === 'worker'))[0] ?? null;
if (!isset($worker['searchtype']) || $worker['searchtype'] !== 'nequal') {
    fwrite(STDERR, "searchtype missing or incorrect for worker column\n");
    exit(1);
}

// Verify task_name column (has searchtype: has in dbschema)
$taskName = array_values(array_filter($columns, fn($c) => $c['field'] === 'task_name'))[0] ?? null;
if (!isset($taskName['searchtype']) || $taskName['searchtype'] !== 'has') {
    fwrite(STDERR, "searchtype missing or incorrect for task_name column\n");
    exit(1);
}

echo "ok\n";