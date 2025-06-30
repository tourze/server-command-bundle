<?php

namespace ServerCommandBundle\Controller\Admin;

use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Repository\NodeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TerminalHistoryController extends AbstractController
{
    public function __construct(
        private readonly RemoteCommandService $remoteCommandService,
        private readonly NodeRepository $nodeRepository,
    ) {
    }

    #[Route(path: '/admin/terminal/history/{nodeId}', name: 'admin_terminal_history', methods: ['GET'])]
    public function __invoke(int $nodeId): JsonResponse
    {
        $node = $this->nodeRepository->find($nodeId);
        if (null === $node) {
            return new JsonResponse([
                'success' => false,
                'error' => '节点不存在',
            ], 404);
        }

        // 获取该节点最近的终端命令
        $commands = $this->remoteCommandService->getRepository()->findTerminalCommandsByNode($node, 20);

        $history = [];
        foreach ($commands as $command) {
            $history[] = [
                'id' => $command->getId(),
                'command' => $command->getCommand(),
                'result' => $command->getResult() ?? '',
                'status' => $command->getStatus()->value,
                'executedAt' => $command->getExecutedAt()?->format('Y-m-d H:i:s'),
                'executionTime' => $command->getExecutionTime(),
                'workingDirectory' => $command->getWorkingDirectory(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'history' => $history,
        ]);
    }
}