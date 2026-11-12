<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FinderImportTemplateTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testPurchaseModelProvidesSingleXlsxTemplateWithTenAndFiveColumnRows(): void
    {
        if (!class_exists('dbeav_model', false)) {
            eval('#[AllowDynamicProperties] class dbeav_model {}');
        }
        if (!class_exists('kernel', false)) {
            eval('class kernel { public static $service; public static function single($name) { return self::$service; } }');
        }

        require_once self::ROOT . '/app/purchase/model/po.php';
        $exporter = new class {
            public array $calls = [];
            public function newExportExcel($data, $fileName, $fileType, $title = null): bool
            {
                $this->calls[] = compact('data', 'fileName', 'fileType', 'title');
                echo $this->xlsxWithRows(array_merge([$title], array_values(array_filter($data))));
                return true;
            }
            private function xlsxWithRows(array $rows): string
            {
                $path = tempnam(sys_get_temp_dir(), 'finder-xlsx-');
                $zip = new ZipArchive();
                $zip->open($path, ZipArchive::OVERWRITE);
                $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
                foreach ($rows as $index => $row) {
                    $xml .= '<row r="' . ($index + 1) . '">';
                    foreach (array_values($row) as $column => $value) {
                        $xml .= '<c r="' . chr(65 + $column) . ($index + 1) . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $value, ENT_XML1) . '</t></is></c>';
                    }
                    $xml .= '</row>';
                }
                $xml .= '</sheetData></worksheet>';
                $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
                $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="sheet1" sheetId="1"/></sheets></workbook>');
                $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
                $zip->close();
                $contents = file_get_contents($path);
                unlink($path);
                return $contents;
            }
        };
        kernel::$service = $exporter;
        $model = new purchase_mdl_po();

        $this->assertTrue(method_exists($model, 'downloadImportTemplate'));
        ob_start();
        $this->assertTrue($model->downloadImportTemplate('xlsx'));
        $xlsx = ob_get_clean();
        $path = tempnam(sys_get_temp_dir(), 'finder-purchase-template-');
        file_put_contents($path, $xlsx);
        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($path) === true);
            $this->assertSame(1, preg_match_all('/<sheet\b/', (string) $zip->getFromName('xl/workbook.xml')), 'single-sheet XLSX');
            $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertSame(10, substr_count($this->rowXml($sheet, 1), '<c '));
            $this->assertSame(5, substr_count($this->rowXml($sheet, 2), '<c '));
            $zip->close();
        } finally {
            @unlink($path);
        }
        $this->assertSame('采购单模板', $exporter->calls[0]['fileName']);
        $this->assertSame('xlsx', $exporter->calls[0]['fileType']);
    }

    public function testFinderRequiresConfiguredPermissionOnlyForXlsxTemplateCapability(): void
    {
        $denied = $this->runFinderHarness('supported', 'xlsx', 'console_purchase_po', false);
        $this->assertSame('无权限下载导入模板', $denied);
        $this->assertStringNotContainsString("PK\x03\x04", $denied);
        $this->assertStringNotContainsString('CAPABILITY_CALLED', $denied);

        $authorized = $this->runFinderHarness('supported', 'xlsx', 'console_purchase_po', true);
        $this->assertStringStartsWith("PK\x03\x04", $authorized);
        $this->assertStringContainsString('CAPABILITY_CALLED', $authorized);
    }

    public function testFinderDelegatesOnlyExplicitXlsxAndKeepsCsvAndPostContracts(): void
    {
        $xlsx = $this->runFinderHarness('supported', 'xlsx');
        $this->assertStringStartsWith("PK\x03\x04", $xlsx);

        $csv = $this->runFinderHarness('csv', '', 'console_purchase_po', false);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('头一,头二', $csv);

        $unsupported = $this->runFinderHarness('unsupported', 'xlsx');
        $this->assertSame('无法获取模板定义', $unsupported);
        $this->assertStringNotContainsString("PK\x03\x04", $unsupported);

        $post = json_decode($this->runFinderHarness('post', '', 'console_purchase_po', false), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('fail', $post['rsp']);
        $this->assertSame('请选择要导入的 CSV 文件', $post['message']);
    }

    public function testPurchaseFinderConfiguresTheXlsxTemplatePermission(): void
    {
        $controller = (string) file_get_contents(self::ROOT . '/app/console/controller/api/purchase.php');
        $context = (string) file_get_contents(self::ROOT . '/app/desktop/lib/finder/vue/context.php');
        $this->assertStringContainsString("'import_template_permission' => 'console_purchase_po'", $controller);
        $this->assertStringContainsString("\$this->importTemplatePermission = (string) (\$params['import_template_permission'] ?? '');", $context);
        $this->assertStringContainsString('getImportTemplatePermission', $context);
    }

    private function rowXml(string $sheet, int $row): string
    {
        preg_match('/<row r="' . $row . '">(.*?)<\/row>/', $sheet, $matches);
        return $matches[1] ?? '';
    }

    private function runFinderHarness(string $modelType, string $ioType, string $permission = '', bool $authorized = false): string
    {
        $script = tempnam(sys_get_temp_dir(), 'finder-import-harness-');
        $source = <<<'PHP'
<?php
class desktop_finder_vue_context {
    private $model; private $name; private $permission;
    public function __construct($model, $name, $permission) { $this->model = $model; $this->name = $name; $this->permission = $permission; }
    public function getModel() { return $this->model; }
    public function getObjectName() { return $this->name; }
    public function getImportTemplatePermission() { return $this->permission; }
}
class kernel { public static function single($name) { return new HarnessUser(); } }
class HarnessUser { public function has_permission($permission) { return $GLOBALS['authorized']; } }
class SupportedModel { public function downloadImportTemplate($type) { echo "PK\x03\x04xlsxCAPABILITY_CALLED"; return true; } }
class CsvModel { public $oSchema = []; public function io_title($filter, $type) { $this->oSchema['csv'][$filter] = ['头一' => '', '头二' => '']; } }
class UnsupportedModel {}
require $argv[1] . '/app/desktop/lib/finder/vue/import.php';
$model = $argv[2] === 'supported' ? new SupportedModel() : ($argv[2] === 'csv' ? new CsvModel() : new UnsupportedModel());
$GLOBALS['authorized'] = $argv[5] === '1';
$_SERVER['REQUEST_METHOD'] = $argv[2] === 'post' ? 'POST' : 'GET';
$result = (new desktop_finder_vue_import(new desktop_finder_vue_context($model, 'purchase_mdl_po', $argv[4])))->handle($argv[2] === 'post' ? [] : array_filter(['do' => 'getTemplate', 'io_type' => $argv[3]], static fn($value) => $value !== ''));
if ($argv[2] === 'post') { echo json_encode($result, JSON_UNESCAPED_UNICODE); }
PHP;
        file_put_contents($script, $source);
        try {
            $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg(self::ROOT) . ' ' . escapeshellarg($modelType) . ' ' . escapeshellarg($ioType) . ' ' . escapeshellarg($permission) . ' ' . escapeshellarg($authorized ? '1' : '0');
            $output = shell_exec($command);
            $this->assertNotNull($output);
            return $output;
        } finally {
            @unlink($script);
        }
    }
}
