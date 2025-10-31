<?php

namespace ServerCommandBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerCommandBundle\Service\RemoteFileService;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<RemoteFileTransfer>
 */
#[AdminCrud(routePath: '/server-command/remote-file-transfer', routeName: 'server_command_remote_file_transfer')]
final class RemoteFileTransferCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RemoteFileService $remoteFileService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return RemoteFileTransfer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('文件传输')
            ->setEntityLabelInPlural('文件传输')
            ->setPageTitle('index', '文件传输列表')
            ->setPageTitle('detail', '文件传输详情')
            ->setPageTitle('new', '新建文件传输')
            ->setPageTitle('edit', '编辑文件传输')
            ->setHelp('index', '管理远程文件传输任务，支持查看传输状态、执行结果等')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'name', 'localPath', 'remotePath']) // 确保不包含tags
            ->setPaginatorPageSize(20)
            // 强制禁用自动字段发现中可能的tags字段
            ->overrideTemplate('crud/field/array', '@EasyAdmin/crud/field/text.html.twig')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        // ID字段
        yield IntegerField::new('id', 'ID')
            ->setColumns(6)
            ->hideOnForm()
        ;

        // 基本信息字段
        yield TextField::new('name', '传输名称')
            ->setMaxLength(50)
        ;

        yield AssociationField::new('node', '目标节点')
            ->setRequired(true)
            ->autocomplete()
        ;

        yield TextField::new('localPath', '本地文件路径')
            ->setMaxLength(80)
            ->hideOnIndex()
        ;

        yield TextField::new('remotePath', '远程目标路径')
            ->setMaxLength(80)
        ;

        yield TextField::new('tempPath', '临时路径')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        // 文件信息
        yield IntegerField::new('fileSize', '文件大小')
            ->hideOnForm()
            ->formatValue(function ($value) {
                assert(is_int($value) || null === $value);

                return $this->formatFileSize($value);
            })
        ;

        yield IntegerField::new('timeout', '超时时间(秒)')
            ->setHelp('传输超时时间，默认300秒')
        ;

        // 状态和配置
        yield ChoiceField::new('status', '状态')
            ->setFormType(EnumType::class)
            ->setFormTypeOptions(['class' => FileTransferStatus::class])
            ->formatValue(function ($value) {
                return $value instanceof FileTransferStatus ? $value->getLabel() : '';
            })
            ->renderAsBadges([
                FileTransferStatus::PENDING->value => 'warning',
                FileTransferStatus::UPLOADING->value => 'info',
                FileTransferStatus::MOVING->value => 'info',
                FileTransferStatus::COMPLETED->value => 'success',
                FileTransferStatus::FAILED->value => 'danger',
                FileTransferStatus::CANCELED->value => 'secondary',
            ])
        ;

        yield BooleanField::new('useSudo', '使用sudo')
            ->setHelp('移动文件到目标位置时是否使用sudo权限')
        ;

        yield BooleanField::new('enabled', '启用')
            ->setHelp('是否启用此传输任务')
        ;

        // 时间信息
        yield DateTimeField::new('startedAt', '开始时间')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield DateTimeField::new('completedAt', '完成时间')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield NumberField::new('transferTime', '传输耗时(秒)')
            ->hideOnForm()
            ->setNumDecimals(3)
            ->hideOnIndex()
        ;

        // 结果信息
        yield TextareaField::new('result', '传输结果')
            ->hideOnForm()
            ->hideOnIndex()
            ->setMaxLength(200)
        ;

        // 标签 - 完全避免直接引用tags字段，使用虚拟字段
        if (Crud::PAGE_DETAIL === $pageName) {
            yield TextField::new('tagsDisplay', '标签')
                ->hideOnForm()
            ;
        }

        // 审计字段
        yield TextField::new('createdBy', '创建人')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield TextField::new('updatedBy', '更新人')
            ->hideOnForm()
            ->hideOnIndex()
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->hideOnIndex()
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        // 状态过滤器
        $statusChoices = [];
        foreach (FileTransferStatus::cases() as $case) {
            $statusChoices[$case->getLabel()] = $case->value;
        }

        return $filters
            ->add(TextFilter::new('name', '传输名称'))
            ->add(EntityFilter::new('node', '目标节点'))
            ->add(ChoiceFilter::new('status', '状态')->setChoices($statusChoices))
            ->add(BooleanFilter::new('useSudo', '使用sudo'))
            ->add(BooleanFilter::new('enabled', '启用状态'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('startedAt', '开始时间'))
            ->add(DateTimeFilter::new('completedAt', '完成时间'))
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // 重新执行传输操作
        $retryAction = Action::new('retry', '重新执行', 'fa fa-redo')
            ->linkToCrudAction('retryTransfer')
            ->setCssClass('btn btn-warning')
            ->displayIf(function (RemoteFileTransfer $transfer) {
                return in_array($transfer->getStatus(), [
                    FileTransferStatus::FAILED,
                    FileTransferStatus::CANCELED,
                ], true);
            })
        ;

        // 取消传输操作
        $cancelAction = Action::new('cancel', '取消传输', 'fa fa-times')
            ->linkToCrudAction('cancelTransfer')
            ->setCssClass('btn btn-danger')
            ->displayIf(function (RemoteFileTransfer $transfer) {
                return FileTransferStatus::PENDING === $transfer->getStatus();
            })
        ;

        // 查看日志操作
        $viewLogsAction = Action::new('viewLogs', '查看日志', 'fa fa-file-text')
            ->linkToCrudAction('viewLogs')
            ->setCssClass('btn btn-info')
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $retryAction)
            ->add(Crud::PAGE_INDEX, $cancelAction)
            ->add(Crud::PAGE_INDEX, $viewLogsAction)
            ->add(Crud::PAGE_DETAIL, $retryAction)
            ->add(Crud::PAGE_DETAIL, $cancelAction)
            ->add(Crud::PAGE_DETAIL, $viewLogsAction)
            // 移除reorder调用和不必要的remove/update调用，避免引用不存在的actions
        ;
    }

    /**
     * 重新执行传输
     */
    #[AdminAction(routePath: '{entityId}/retry', routeName: 'retry_transfer')]
    public function retryTransfer(AdminContext $context, Request $request): Response
    {
        $transfer = $context->getEntity()->getInstance();
        assert($transfer instanceof RemoteFileTransfer);

        try {
            // 重置状态为PENDING
            $transfer->setStatus(FileTransferStatus::PENDING);
            $transfer->setResult(null);
            $transfer->setStartedAt(null);
            $transfer->setCompletedAt(null);
            $transfer->setTransferTime(null);

            // 执行传输
            $result = $this->remoteFileService->executeTransfer($transfer);

            if (FileTransferStatus::COMPLETED === $result->getStatus()) {
                $this->addFlash('success', sprintf('文件传输 "%s" 重新执行成功', $transfer->getName()));
            } else {
                $this->addFlash('warning', sprintf('文件传输 "%s" 重新执行失败: %s', $transfer->getName(), $result->getResult()));
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('重新执行传输时出错: %s', $e->getMessage()));
        }

        return $this->redirect($context->getRequest()->headers->get('referer') ?? '/');
    }

    /**
     * 取消传输
     */
    #[AdminAction(routePath: '{entityId}/cancel', routeName: 'cancel_transfer')]
    public function cancelTransfer(AdminContext $context, Request $request): Response
    {
        $transfer = $context->getEntity()->getInstance();
        assert($transfer instanceof RemoteFileTransfer);

        try {
            $this->remoteFileService->cancelTransfer($transfer);
            $this->addFlash('success', sprintf('文件传输 "%s" 已取消', $transfer->getName()));
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('取消传输时出错: %s', $e->getMessage()));
        }

        return $this->redirect($context->getRequest()->headers->get('referer') ?? '/');
    }

    /**
     * 查看传输日志
     */
    #[AdminAction(routePath: '{entityId}/logs', routeName: 'view_transfer_logs')]
    public function viewLogs(AdminContext $context, Request $request): Response
    {
        $transfer = $context->getEntity()->getInstance();
        assert($transfer instanceof RemoteFileTransfer);

        // 构建日志信息
        $logInfo = [
            '传输ID' => $transfer->getId(),
            '传输名称' => $transfer->getName(),
            '目标节点' => $transfer->getNode()->getName(),
            '本地路径' => $transfer->getLocalPath(),
            '远程路径' => $transfer->getRemotePath(),
            '临时路径' => $transfer->getTempPath(),
            '文件大小' => $this->formatFileSize($transfer->getFileSize()),
            '状态' => $transfer->getStatus()?->getLabel(),
            '使用sudo' => (true === $transfer->isUseSudo()) ? '是' : '否',
            '超时时间' => $transfer->getTimeout() . '秒',
            '开始时间' => $transfer->getStartedAt()?->format('Y-m-d H:i:s'),
            '完成时间' => $transfer->getCompletedAt()?->format('Y-m-d H:i:s'),
            '传输耗时' => null !== $transfer->getTransferTime() ? round($transfer->getTransferTime(), 3) . '秒' : null,
            '传输结果' => $transfer->getResult(),
            '标签' => $transfer->getTagsDisplay(),
            '创建人' => $transfer->getCreatedBy(),
            '创建时间' => $transfer->getCreateTime()?->format('Y-m-d H:i:s'),
        ];

        // 生成简单的HTML页面显示日志
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>传输日志 - ' . htmlspecialchars($transfer->getName()) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .back-btn { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="back-btn">
        <button onclick="window.history.back()">← 返回</button>
    </div>
    <h1>文件传输日志详情</h1>
    <table>';

        foreach ($logInfo as $key => $value) {
            if (null !== $value && '' !== $value) {
                $html .= sprintf(
                    '<tr><th>%s</th><td>%s</td></tr>',
                    htmlspecialchars($key),
                    htmlspecialchars((string) $value)
                );
            }
        }

        $html .= '</table></body></html>';

        return new Response($html);
    }

    /**
     * 格式化文件大小显示
     */
    private function formatFileSize(?int $bytes): string
    {
        if (null === $bytes) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes > 0 ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[(int) $pow];
    }
}
