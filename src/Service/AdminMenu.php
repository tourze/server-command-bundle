<?php

namespace ServerCommandBundle\Service;

use Knp\Menu\ItemInterface;
use ServerCommandBundle\Entity\RemoteCommand;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private readonly LinkGeneratorInterface $linkGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (!$item->getChild('服务器管理')) {
            $item->addChild('服务器管理');
        }

        $serverMenu = $item->getChild('服务器管理');

        // 添加服务器终端菜单
        $serverMenu->addChild('服务器终端')
            ->setUri($this->urlGenerator->generate('admin_terminal_index'))
            ->setAttribute('icon', 'fas fa-terminal');

        // 现有的远程命令菜单
        $serverMenu->addChild('远程命令')
            ->setUri($this->linkGenerator->getCurdListPage(RemoteCommand::class))
            ->setAttribute('icon', 'fas fa-cogs');
    }
}
