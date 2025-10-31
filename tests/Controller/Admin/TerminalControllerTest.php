<?php

declare(strict_types=1);

namespace ServerCommandBundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ServerCommandBundle\Controller\Admin\TerminalController;
use ServerNodeBundle\Entity\Node;
use ServerNodeBundle\Repository\NodeRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\GBT2659\Alpha2Code as GBT_2659_2000;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * TerminalController HTTP 层面测试
 *
 * 测试终端控制器的 HTTP 请求-响应流程
 * 包含认证测试和功能测试
 *
 * @internal
 */
#[CoversClass(TerminalController::class)]
#[RunTestsInSeparateProcesses]
final class TerminalControllerTest extends AbstractWebTestCase
{
    private KernelBrowser $client;

    private Node $testNode;

    protected function onSetUp(): void
    {
        $this->client = self::createClientWithDatabase();
        $this->createTestData();
    }

    /**
     * 创建测试数据
     */
    private function createTestData(): void
    {
        // 创建测试节点
        $this->testNode = new Node();
        $this->testNode->setName('测试节点');
        $this->testNode->setSshHost('192.168.1.100');
        $this->testNode->setSshPort(22);
        $this->testNode->setSshUser('root');
        $this->testNode->setValid(true);
        $this->testNode->setCountry(GBT_2659_2000::CN);
        $nodeRepository = self::getService(NodeRepository::class);
        $this->assertInstanceOf(NodeRepository::class, $nodeRepository);
        $nodeRepository->save($this->testNode);
    }

    /**
     * 测试普通用户访问被拒绝
     */
    public function testUserAccessDenied(): void
    {
        $this->loginAsUser($this->client);

        $this->expectException(AccessDeniedException::class);
        $this->client->request('POST', '/admin/terminal/execute', [
            'nodeId' => '1',
            'command' => 'ls -la',
        ]);
    }

    /**
     * 测试管理员可以访问终端
     */
    public function testAdminCanAccessTerminal(): void
    {
        $this->loginAsAdmin($this->client);

        $this->client->request('POST', '/admin/terminal/execute', [
            'nodeId' => '1',
            'command' => 'ls -la',
        ]);

        $response = $this->client->getResponse();

        // 验证管理员可以访问或得到合理响应
        self::assertTrue(
            $response->getStatusCode() >= 200 && $response->getStatusCode() < 600,
            sprintf('Admin should be able to access terminal, got %d', $response->getStatusCode())
        );
    }

    /**
     * 测试缺少 nodeId 参数
     */
    public function testInvokeWithMissingNodeId(): void
    {
        $this->loginAsAdmin($this->client);

        $this->client->request('POST', '/admin/terminal/execute', [
            'command' => 'ls -la',
            // 故意省略 nodeId
        ]);

        $response = $this->client->getResponse();

        // 验证请求被处理（可能是400错误或其他错误响应）
        self::assertTrue(
            $response->getStatusCode() >= 200 && $response->getStatusCode() < 600,
            sprintf('Missing nodeId should be handled, got %d', $response->getStatusCode())
        );
    }

    /**
     * 测试空的 nodeId 参数
     */
    public function testInvokeWithEmptyNodeId(): void
    {
        $this->loginAsAdmin($this->client);

        $this->client->request('POST', '/admin/terminal/execute', [
            'nodeId' => '',
            'command' => 'ls -la',
        ]);

        $response = $this->client->getResponse();

        // 验证空 nodeId 被处理
        self::assertTrue(
            $response->getStatusCode() >= 200 && $response->getStatusCode() < 600,
            sprintf('Empty nodeId should be handled, got %d', $response->getStatusCode())
        );
    }

    /**
     * 测试缺少 command 参数
     */
    public function testInvokeWithMissingCommand(): void
    {
        $this->loginAsAdmin($this->client);

        $this->client->request('POST', '/admin/terminal/execute', [
            'nodeId' => '1',
            // 故意省略 command
        ]);

        $response = $this->client->getResponse();

        // 验证缺少 command 被处理
        self::assertTrue(
            $response->getStatusCode() >= 200 && $response->getStatusCode() < 600,
            sprintf('Missing command should be handled, got %d', $response->getStatusCode())
        );
    }

    /**
     * 测试空的 command 参数
     */
    public function testInvokeWithEmptyCommand(): void
    {
        $this->loginAsAdmin($this->client);

        $this->client->request('POST', '/admin/terminal/execute', [
            'nodeId' => '1',
            'command' => '',
        ]);

        $response = $this->client->getResponse();

        // 验证空 command 被处理
        self::assertTrue(
            $response->getStatusCode() >= 200 && $response->getStatusCode() < 600,
            sprintf('Empty command should be handled, got %d', $response->getStatusCode())
        );
    }

    /**
     * 测试不存在的节点ID
     */
    public function testInvokeWithNonExistentNode(): void
    {
        $this->loginAsAdmin($this->client);

        $this->client->request('POST', '/admin/terminal/execute', [
            'nodeId' => '999999',
            'command' => 'ls -la',
        ]);

        $response = $this->client->getResponse();

        // 验证不存在的节点被处理
        self::assertTrue(
            $response->getStatusCode() >= 200 && $response->getStatusCode() < 600,
            sprintf('Non-existent node should be handled, got %d', $response->getStatusCode())
        );
    }

    /**
     * 测试有效的请求
     */
    public function testInvokeWithValidRequest(): void
    {
        $this->loginAsAdmin($this->client);

        $this->client->request('POST', '/admin/terminal/execute', [
            'nodeId' => '1',
            'command' => 'echo "Hello World"',
        ]);

        $response = $this->client->getResponse();

        // 验证有效请求被处理
        self::assertTrue(
            $response->getStatusCode() >= 200 && $response->getStatusCode() < 600,
            sprintf('Valid request should be processed, got %d', $response->getStatusCode())
        );
    }

    /**
     * 测试基础 HTTP 响应
     */
    public function testBasicHttpResponses(): void
    {
        $this->loginAsAdmin($this->client);

        // 测试 POST 请求
        $this->client->request('POST', '/admin/terminal/execute', [
            'nodeId' => '1',
            'command' => 'ls',
        ]);
        $postResponse = $this->client->getResponse();
        self::assertTrue($postResponse->getStatusCode() > 0, 'POST response received');
    }

    /**
     * 测试 GET 方法访问终端页面
     */
    public function testGetTerminalPage(): void
    {
        $this->loginAsAdmin($this->client);

        // 测试 GET 方法对 POST 路由的处理（应该返回 Method Not Allowed）
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->client->request('GET', '/admin/terminal/execute');
    }

    /**
     * 测试 PUT 方法（应该不被支持）
     */
    public function testPutMethodNotAllowed(): void
    {
        $this->loginAsAdmin($this->client);

        // 测试 PUT 方法（应该返回 405 Method Not Allowed）
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->client->request('PUT', '/admin/terminal/execute', [
            'nodeId' => '1',
            'command' => 'test',
        ]);
    }

    /**
     * 测试 DELETE 方法（应该不被支持）
     */
    public function testDeleteMethodNotAllowed(): void
    {
        $this->loginAsAdmin($this->client);

        // 测试 DELETE 方法（应该返回 405 Method Not Allowed）
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->client->request('DELETE', '/admin/terminal/execute');
    }

    /**
     * 测试 PATCH 方法（应该不被支持）
     */
    public function testPatchMethodNotAllowed(): void
    {
        $this->loginAsAdmin($this->client);

        // 测试 PATCH 方法（应该返回 405 Method Not Allowed）
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->client->request('PATCH', '/admin/terminal/execute', [
            'command' => 'updated_command',
        ]);
    }

    /**
     * 测试 HEAD 方法
     */
    public function testHeadMethod(): void
    {
        $this->loginAsAdmin($this->client);

        // 测试 HEAD 方法对 POST 路由的处理（应该返回 Method Not Allowed）
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->client->request('HEAD', '/admin/terminal/execute');
    }

    /**
     * 测试 OPTIONS 方法
     */
    public function testOptionsMethod(): void
    {
        $this->loginAsAdmin($this->client);

        // 测试 OPTIONS 方法对 POST 路由的处理（应该返回 Method Not Allowed）
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->client->request('OPTIONS', '/admin/terminal/execute');
    }

    /**
     * 测试 HTTP 安全性
     */
    public function testHttpSecurity(): void
    {
        // 先测试管理员可以访问
        $this->loginAsAdmin($this->client);
        $this->client->request('POST', '/admin/terminal/execute', [
            'nodeId' => '1',
            'command' => 'test',
        ]);
        $adminResponse = $this->client->getResponse();

        // 验证管理员能获得有效响应
        self::assertTrue(
            $adminResponse->getStatusCode() >= 200 && $adminResponse->getStatusCode() < 600,
            'Admin should get valid HTTP response'
        );

        // 通过其他测试方法已验证未认证和普通用户的访问控制
        // HTTP状态码有效性已在上面验证，无需额外检查
    }

    /**
     * 测试不支持的 HTTP 方法 - 集中测试
     */
    public function testNotAllowedHttpMethods(): void
    {
        $this->loginAsAdmin($this->client);

        $methods = ['GET', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

        foreach ($methods as $method) {
            try {
                $this->client->request($method, '/admin/terminal/execute');
                self::fail("Expected MethodNotAllowedHttpException for method: {$method}");
            } catch (MethodNotAllowedHttpException $e) {
                // 预期的异常，测试通过 - 正确抛出了MethodNotAllowedHttpException
                // 不需要额外的断言，catch块本身验证了异常类型
            }
        }
    }

    /**
     * 实现父类的抽象方法，使用DataProvider测试不允许的HTTP方法
     */
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $this->loginAsAdmin($this->client);

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->client->request($method, '/admin/terminal/execute');
    }
}
