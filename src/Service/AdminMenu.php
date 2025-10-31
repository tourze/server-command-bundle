<?php

namespace ServerCommandBundle\Service;

use Knp\Menu\ItemInterface;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('服务器管理')) {
            $item->addChild('服务器管理');
        }

        $serverMenu = $item->getChild('服务器管理');
        if (null === $serverMenu) {
            return;
        }

        // 现有的远程命令菜单
        $serverMenu
            ->addChild('远程命令')
            ->setUri($this->linkGenerator->getCurdListPage(RemoteCommand::class))
            ->setAttribute('icon', 'fas fa-cogs')
        ;

        // 新增的文件传输菜单
        $serverMenu
            ->addChild('文件传输')
            ->setUri($this->linkGenerator->getCurdListPage(RemoteFileTransfer::class))
            ->setAttribute('icon', 'fas fa-upload')
        ;
    }
}
