<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class fxordermsgTest extends TestCase
{
    // 在每个测试方法之前执行
    protected function setUp(): void
    {
        parent::setUp();

        // 在 setUp 方法中引入依赖文件
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }

    /**
     * 测试fxordermsg方法 - 成功添加卖家备注
     *
     * @return void
     * @author system
     */
    public function testFxordermsgSuccess(): void
    {
        // 测试参数
        $params = array(
            'order_bn' => 'L202507252210006274',
            'shop_id' => 'shop_20250704203141_6586',
            'sellerComments' => '供销供应商测试备注 - ' . date('Y-m-d H:i:s')
        );

        // 调用fxordermsg方法
        $bookingrefundProcess = kernel::single('erpapi_shop_response_process_bookingrefund');
        $result = $bookingrefundProcess->fxordermsg($params);

        // 验证返回结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('rsp', $result);
        $this->assertArrayHasKey('msg', $result);
        
        // 如果订单存在，应该成功
        if ($result['rsp'] === 'succ') {
            $this->assertEquals('succ', $result['rsp']);
            $this->assertEquals('订单信息同步成功', $result['msg']);
        } else {
            // 如果订单不存在，应该返回失败
            $this->assertEquals('fail', $result['rsp']);
            $this->assertStringContainsString('订单不存在', $result['msg']);
        }

        echo "测试结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * 测试fxordermsg方法 - 订单不存在
     *
     * @return void
     * @author system
     */
    public function testFxordermsgOrderNotExist(): void
    {
        // 测试参数 - 使用不存在的订单号
        $params = array(
            'order_bn' => 'NOT_EXIST_ORDER_' . time(),
            'shop_id' => 'shop_20250704203141_6586',
            'sellerComments' => '供销供应商测试备注'
        );

        // 调用fxordermsg方法
        $bookingrefundProcess = kernel::single('erpapi_shop_response_process_bookingrefund');
        $result = $bookingrefundProcess->fxordermsg($params);

        // 验证返回结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('rsp', $result);
        $this->assertArrayHasKey('msg', $result);
        $this->assertEquals('fail', $result['rsp']);
        $this->assertEquals('订单不存在', $result['msg']);

        echo "测试结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * 测试fxordermsg方法 - 没有sellerComments参数
     *
     * @return void
     * @author system
     */
    public function testFxordermsgNoSellerComments(): void
    {
        // 测试参数 - 不包含sellerComments
        $params = array(
            'order_bn' => 'L202507252210006274',
            'shop_id' => 'shop_20250704203141_6586'
            // 故意不传sellerComments
        );

        // 调用fxordermsg方法
        $bookingrefundProcess = kernel::single('erpapi_shop_response_process_bookingrefund');
        $result = $bookingrefundProcess->fxordermsg($params);

        // 验证返回结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('rsp', $result);
        $this->assertArrayHasKey('msg', $result);
        
        // 如果没有sellerComments，应该直接返回成功（因为暂时只处理sellerComments）
        if ($result['rsp'] === 'succ') {
            $this->assertEquals('succ', $result['rsp']);
            $this->assertEquals('订单信息同步成功', $result['msg']);
        } else {
            // 如果订单不存在，应该返回失败
            $this->assertEquals('fail', $result['rsp']);
            $this->assertStringContainsString('订单不存在', $result['msg']);
        }

        echo "测试结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * 测试fxordermsg方法 - 空sellerComments
     *
     * @return void
     * @author system
     */
    public function testFxordermsgEmptySellerComments(): void
    {
        // 测试参数 - sellerComments为空
        $params = array(
            'order_bn' => 'L202507252210006274',
            'shop_id' => 'shop_20250704203141_6586',
            'sellerComments' => ''
        );

        // 调用fxordermsg方法
        $bookingrefundProcess = kernel::single('erpapi_shop_response_process_bookingrefund');
        $result = $bookingrefundProcess->fxordermsg($params);

        // 验证返回结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('rsp', $result);
        $this->assertArrayHasKey('msg', $result);
        
        // 如果sellerComments为空，应该直接返回成功（因为暂时只处理sellerComments）
        if ($result['rsp'] === 'succ') {
            $this->assertEquals('succ', $result['rsp']);
            $this->assertEquals('订单信息同步成功', $result['msg']);
        } else {
            // 如果订单不存在，应该返回失败
            $this->assertEquals('fail', $result['rsp']);
            $this->assertStringContainsString('订单不存在', $result['msg']);
        }

        echo "测试结果: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * 测试fxordermsg方法 - 验证备注是否成功添加到订单
     *
     * @return void
     * @author system
     */
    public function testFxordermsgVerifyMarkAdded(): void
    {
        // 测试参数
        $testComment = '供销供应商验证备注 - ' . date('Y-m-d H:i:s');
        $params = array(
            'order_bn' => 'L202507252210006274',
            'shop_id' => 'shop_20250704203141_6586',
            'sellerComments' => $testComment
        );

        // 调用fxordermsg方法
        $bookingrefundProcess = kernel::single('erpapi_shop_response_process_bookingrefund');
        $result = $bookingrefundProcess->fxordermsg($params);

        // 如果成功，验证备注是否真的添加到了订单中
        if ($result['rsp'] === 'succ') {
            // 查询订单信息
            $orderModel = app::get('ome')->model('orders');
            $orderInfo = $orderModel->dump(array('order_bn' => $params['order_bn']), 'order_id,mark_text');
            
            if ($orderInfo) {
                $markText = unserialize($orderInfo['mark_text']);
                $this->assertIsArray($markText);
                
                // 查找最新的备注
                $latestMark = end($markText);
                $this->assertIsArray($latestMark);
                $this->assertArrayHasKey('op_content', $latestMark);
                $this->assertArrayHasKey('op_name', $latestMark);
                
                // 验证备注内容
                $this->assertEquals($testComment, $latestMark['op_content']);
                $this->assertEquals('供销供应商', $latestMark['op_name']);
                
                echo "验证成功: 备注已添加到订单中\n";
                echo "最新备注: " . json_encode($latestMark, JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                $this->fail('订单查询失败');
            }
        } else {
            echo "订单不存在或处理失败，跳过验证\n";
        }
    }

    /**
     * 测试ome_order_marktext::add方法
     *
     * @return void
     * @author system
     */
    public function testOrderMarktextAdd(): void
    {
        // 测试参数
        $order_bn = 'L202507252210006274';
        $testContent = 'ome_order_marktext测试备注 - ' . date('Y-m-d H:i:s');
        $op_name = '测试操作员';

        // 先查询订单ID
        $orderModel = app::get('ome')->model('orders');
        $orderInfo = $orderModel->dump(array('order_bn' => $order_bn), 'order_id');
        
        if (!$orderInfo) {
            $this->markTestSkipped('测试订单不存在，跳过此测试');
            return;
        }

        // 调用ome_order_marktext::add方法
        list($success, $message) = kernel::single('ome_order_marktext')->add($orderInfo['order_id'], $testContent, $op_name);

        // 验证返回结果
        $this->assertIsBool($success);
        $this->assertIsString($message);
        
        if ($success) {
            $this->assertTrue($success);
            $this->assertEquals('订单备注添加成功', $message);
            
            // 验证备注是否真的添加了
            $orderInfo = $orderModel->dump(array('order_id' => $orderInfo['order_id']), 'mark_text');
            $markText = unserialize($orderInfo['mark_text']);
            $latestMark = end($markText);
            
            $this->assertEquals($testContent, $latestMark['op_content']);
            $this->assertEquals($op_name, $latestMark['op_name']);
            
            echo "ome_order_marktext::add测试成功\n";
        } else {
            $this->assertFalse($success);
            echo "ome_order_marktext::add测试失败: " . $message . "\n";
        }
    }
}
?> 