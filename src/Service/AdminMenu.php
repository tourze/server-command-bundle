<?php

namespace ServerCommandBundle\Service;

use Knp\Menu\ItemInterface;
use ServerCommandBundle\Entity\RemoteCommand;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private readonly LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (!$item->getChild('服务器管理')) {
            $item->addChild('服务器管理');
        }

        $serverMenu = $item->getChild('服务器管理');

        // 现有的远程命令菜单
        $serverMenu->addChild('远程命令')
            ->setUri($this->linkGenerator->getCurdListPage(RemoteCommand::class))
            ->setAttribute('icon', 'fas fa-cogs');
    }
}
