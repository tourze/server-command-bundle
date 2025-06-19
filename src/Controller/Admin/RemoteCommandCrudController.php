<?php

namespace ServerCommandBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Repository\NodeRepository;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RemoteCommandCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RemoteCommandService $remoteCommandService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly NodeRepository $nodeRepository,
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return RemoteCommand::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('远程命令')
            ->setEntityLabelInPlural('远程命令')
            ->setPageTitle('index', '远程命令列表')
            ->setPageTitle('detail', fn(RemoteCommand $command) => sprintf('命令详情: %s', $command->getName()))
            ->setPageTitle('edit', fn(RemoteCommand $command) => sprintf('编辑命令: %s', $command->getName()))
            ->setPageTitle('new', '新建远程命令')
            ->setHelp('index', '管理远程服务器上的命令执行')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['name', 'command', 'workingDirectory', 'tags', 'node.name'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->setMaxLength(9999)
            ->hideOnForm();

        yield AssociationField::new('node', '服务器节点')
            ->setRequired(true)
            ->setFormTypeOption('choice_label', 'name');

        yield TextField::new('name', '命令名称')
            ->setRequired(true);

        yield TextareaField::new('command', '命令内容')
            ->setRequired(true)
            ->hideOnIndex();

        // 仅在详情页显示结果
        yield TextareaField::new('result', '执行结果')
            ->hideOnForm()
            ->hideOnIndex()
            ->setFormTypeOption('disabled', true);

        yield TextField::new('workingDirectory', '工作目录')
            ->hideOnIndex();

        yield BooleanField::new('useSudo', '使用sudo执行');

        yield BooleanField::new('enabled', '是否启用');

        yield NumberField::new('timeout', '超时时间(秒)')
            ->setHelp('命令执行超时时间，单位：秒')
            ->hideOnIndex();

        yield ChoiceField::new('status', '状态')
            ->setFormType(EnumType::class)
            ->setFormTypeOptions(['class' => CommandStatus::class])
            ->formatValue(function ($value) {
                if (!$value instanceof CommandStatus) {
                    return '未知';
                }

                return match ($value) {
                    CommandStatus::PENDING => '待执行',
                    CommandStatus::RUNNING => '执行中',
                    CommandStatus::COMPLETED => '已完成',
                    CommandStatus::FAILED => '失败',
                    CommandStatus::TIMEOUT => '超时',
                    CommandStatus::CANCELED => '已取消',
                };
            });

        yield DateTimeField::new('executedAt', '执行时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss');

        yield NumberField::new('executionTime', '执行耗时(秒)')
            ->hideOnForm()
            ->setNumDecimals(3);

        yield ArrayField::new('tags', '标签')
            ->hideOnIndex();

        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
            ->setFormat('yyyy-MM-dd HH:mm:ss');

        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
            ->hideOnIndex()
            ->setFormat('yyyy-MM-dd HH:mm:ss');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('node', '服务器节点'))
            ->add(TextFilter::new('name', '命令名称'))
            ->add(TextFilter::new('command', '命令内容'))
            ->add(BooleanFilter::new('useSudo', '使用sudo执行'))
            ->add(BooleanFilter::new('enabled', '是否启用'))
            ->add(ChoiceFilter::new('status', '状态')
                ->setChoices([
                    '待执行' => CommandStatus::PENDING->value,
                    '执行中' => CommandStatus::RUNNING->value,
                    '已完成' => CommandStatus::COMPLETED->value,
                    '失败' => CommandStatus::FAILED->value,
                    '超时' => CommandStatus::TIMEOUT->value,
                    '已取消' => CommandStatus::CANCELED->value,
                ]))
            ->add(DateTimeFilter::new('executedAt', '执行时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $executeAction = Action::new('execute', '执行命令')
            ->linkToCrudAction('executeCommand')
            ->setCssClass('btn btn-success')
            ->setIcon('fa fa-play');

        $cancelAction = Action::new('cancel', '取消命令')
            ->linkToCrudAction('cancelCommand')
            ->setCssClass('btn btn-danger')
            ->setIcon('fa fa-times');

        $sync = Action::new('terminal', '终端视图')
            ->linkToCrudAction('terminal')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-primary')
            ->setIcon('fa fa-terminal');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $sync)
            ->add(Crud::PAGE_INDEX, $executeAction)
            ->add(Crud::PAGE_DETAIL, $executeAction)
            ->add(Crud::PAGE_DETAIL, $cancelAction)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): \Doctrine\ORM\QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->select('entity')
            ->leftJoin('entity.node', 'node')
            ->orderBy('entity.createTime', 'DESC');
    }

    /**
     * 执行远程命令
     */
    #[AdminAction('{entityId}/execute', 'execute_remote_command')]
    public function executeCommand(AdminContext $context, Request $request): Response
    {
        /** @var RemoteCommand $command */
        $command = $context->getEntity()->getInstance();

        $this->remoteCommandService->executeCommand($command);
        $this->addFlash('success', sprintf('命令 %s 已开始执行', $command->getName()));

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($command->getId())
            ->generateUrl());
    }

    /**
     * 取消远程命令
     */
    #[AdminAction('{entityId}/cancel', 'cancel_remote_command')]
    public function cancelCommand(AdminContext $context, Request $request): Response
    {
        /** @var RemoteCommand $command */
        $command = $context->getEntity()->getInstance();

        if ($command->getStatus() === CommandStatus::PENDING) {
            $this->remoteCommandService->cancelCommand($command);
            $this->addFlash('success', sprintf('命令 %s 已取消', $command->getName()));
        } else {
            $this->addFlash('warning', sprintf('命令 %s 当前状态不允许取消', $command->getName()));
        }

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($command->getId())
            ->generateUrl());
    }

    #[AdminAction('terminal', 'terminal')]
    public function terminal(): Response
    {
        $nodes = $this->nodeRepository->findAll();

        return $this->render('@ServerCommand/terminal/index.html.twig', [
            'nodes' => $nodes,
        ]);
    }
}
