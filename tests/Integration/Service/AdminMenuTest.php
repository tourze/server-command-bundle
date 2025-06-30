<?php

namespace ServerCommandBundle\Tests\Integration\Service;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Service\AdminMenu;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;

class AdminMenuTest extends TestCase
{
    private AdminMenu $adminMenu;
    private LinkGeneratorInterface $linkGenerator;

    protected function setUp(): void
    {
        $this->linkGenerator = $this->createMock(LinkGeneratorInterface::class);
        $this->adminMenu = new AdminMenu($this->linkGenerator);
    }

    public function testInvokeCreatesServerMenu(): void
    {
        $rootMenu = $this->createMock(ItemInterface::class);
        $serverMenu = $this->createMock(ItemInterface::class);
        
        // 设置第一次调用getChild返回null，第二次返回serverMenu
        $rootMenu->expects($this->exactly(2))
            ->method('getChild')
            ->with('服务器管理')
            ->willReturnOnConsecutiveCalls(null, $serverMenu);
            
        $rootMenu->expects($this->once())
            ->method('addChild')
            ->with('服务器管理')
            ->willReturn($serverMenu);

        $this->linkGenerator->expects($this->exactly(2))
            ->method('getCurdListPage')
            ->willReturn('/admin/test');

        $serverMenu->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnSelf();
            
        $serverMenu->expects($this->exactly(2))
            ->method('setUri')
            ->willReturnSelf();
            
        $serverMenu->expects($this->exactly(2))
            ->method('setAttribute')
            ->willReturnSelf();

        ($this->adminMenu)($rootMenu);
    }
}