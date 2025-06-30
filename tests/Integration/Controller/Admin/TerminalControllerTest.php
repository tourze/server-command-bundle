<?php

namespace ServerCommandBundle\Tests\Integration\Controller\Admin;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Controller\Admin\TerminalController;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Repository\NodeRepository;
use Symfony\Component\HttpFoundation\Request;

class TerminalControllerTest extends TestCase
{
    private TerminalController $controller;
    private NodeRepository $nodeRepository;
    private RemoteCommandService $remoteCommandService;

    protected function setUp(): void
    {
        $this->nodeRepository = $this->createMock(NodeRepository::class);
        $this->remoteCommandService = $this->createMock(RemoteCommandService::class);
        
        $this->controller = new TerminalController(
            $this->remoteCommandService,
            $this->nodeRepository
        );
    }

    public function testExecuteAction(): void
    {
        $request = new Request();
        $request->request->set('nodeId', '1');
        $request->request->set('command', 'ls -la');

        $response = $this->controller->__invoke($request);

        $this->assertEquals(404, $response->getStatusCode()); // Node not found
    }

    public function testExecuteActionWithMissingParameters(): void
    {
        $request = new Request();
        
        $response = $this->controller->__invoke($request);

        $this->assertEquals(400, $response->getStatusCode());
    }
}