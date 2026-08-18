<?php

declare(strict_types=1);

namespace AIEA\Core;

use AIEA\Admin\AdminPage;
use AIEA\Admin\EditorAssets;
use AIEA\Admin\Settings;
use AIEA\AI\AIManager;
use AIEA\AI\EndpointValidator;
use AIEA\AI\ProviderRegistry;
use AIEA\AI\SecretManager;
use AIEA\Agent\ContextBudget;
use AIEA\Agent\ContextService;
use AIEA\Agent\Redactor;
use AIEA\Agent\ConversationService;
use AIEA\Agent\PlanJsonDecoder;
use AIEA\Agent\PlanSchemaValidator;
use AIEA\Agent\PlanService;
use AIEA\Agent\PromptBuilder;
use AIEA\Database\ConversationRepository;
use AIEA\Database\Installer;
use AIEA\Database\PlanRepository;
use AIEA\Database\TableNames;
use AIEA\Audit\AuditLogger;
use AIEA\Audit\AuditRepository;
use AIEA\Audit\DiffService;
use AIEA\Audit\SnapshotRepository;
use AIEA\Elementor\CapabilityCatalog;
use AIEA\Elementor\ElementorContext;
use AIEA\Elementor\ElementorGuard;
use AIEA\Elementor\ElementorReader;
use AIEA\Elementor\DocumentRepository;
use AIEA\Elementor\DocumentValidator;
use AIEA\Elementor\ElementorWriter;
use AIEA\Rest\ChatController;
use AIEA\Rest\ContextController;
use AIEA\Rest\PlanController;
use AIEA\Rest\Permission;
use AIEA\Rest\ProviderController;
use AIEA\Rest\RestResponder;
use AIEA\Rest\Routes;
use AIEA\Rest\SessionController;
use AIEA\Rest\ExecutionController;
use AIEA\Jobs\DocumentTransaction;
use AIEA\Jobs\JobRepository;
use AIEA\Jobs\JobRunner;
use AIEA\Tools\ToolRegistry;
use AIEA\Support\PluginPermissions;

final class Plugin
{
    private static ?self $instance = null;

    private Container $container;

    private bool $booted = false;

    private function __construct()
    {
        $this->container = new Container();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        $this->registerServices();

        add_action('init', [$this->container->get(Installer::class), 'maybeMigrate']);
        add_action('init', [$this->container->get(AdminPage::class), 'registerCapabilities']);
        add_action('admin_menu', [$this->container->get(AdminPage::class), 'registerPage']);
        add_action('admin_init', [$this->container->get(AdminPage::class), 'registerSettings']);
        add_action('elementor/editor/footer', [$this->container->get(EditorAssets::class), 'renderEditorRoot'], 5);
        add_action('elementor/editor/after_enqueue_scripts', [$this->container->get(EditorAssets::class), 'enqueueEditorAssets']);
        add_action('admin_enqueue_scripts', [$this->container->get(EditorAssets::class), 'enqueueAdminAssets']);

        $guard = $this->container->get(CompatibilityGuard::class);
        if (!$guard->isCompatible()) {
            add_action('admin_notices', [$this, 'renderCompatibilityNotice']);
            return;
        }

        add_action('rest_api_init', [$this->container->get(Routes::class), 'register']);
    }

    public function renderCompatibilityNotice(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $issues = $this->container->get(CompatibilityGuard::class)->issues();
        echo '<div class="notice notice-warning"><p><strong>'
            . esc_html__('AI Elementor AG:', 'ai-elementor-ag')
            . '</strong> '
            . esc_html(implode(' ', $issues))
            . '</p></div>';
    }

    private function registerServices(): void
    {
        $this->container->singleton(CompatibilityGuard::class, static fn (): CompatibilityGuard => new CompatibilityGuard());
        $this->container->singleton(Installer::class, static fn (): Installer => new Installer());
        $this->container->singleton(Settings::class, static fn (): Settings => new Settings());
        $this->container->singleton(TableNames::class, static fn (): TableNames => new TableNames());
        $this->container->singleton(SecretManager::class, static fn (): SecretManager => new SecretManager());
        $this->container->singleton(EndpointValidator::class, static fn (): EndpointValidator => new EndpointValidator());
        $this->container->singleton(
            ProviderRegistry::class,
            fn (): ProviderRegistry => new ProviderRegistry(
                $this->container->get(Settings::class),
                $this->container->get(SecretManager::class),
                $this->container->get(EndpointValidator::class),
            ),
        );
        $this->container->singleton(
            AIManager::class,
            fn (): AIManager => new AIManager($this->container->get(ProviderRegistry::class)),
        );
        $this->container->singleton(ElementorGuard::class, static fn (): ElementorGuard => new ElementorGuard());
        $this->container->singleton(CapabilityCatalog::class, static fn (): CapabilityCatalog => new CapabilityCatalog());
        $this->container->singleton(
            ElementorReader::class,
            fn (): ElementorReader => new ElementorReader($this->container->get(ElementorGuard::class)),
        );
        $this->container->singleton(
            ElementorContext::class,
            fn (): ElementorContext => new ElementorContext(
                $this->container->get(ElementorReader::class),
                $this->container->get(CapabilityCatalog::class),
            ),
        );
        $this->container->singleton(Redactor::class, static fn (): Redactor => new Redactor());
        $this->container->singleton(ContextBudget::class, static fn (): ContextBudget => new ContextBudget());
        $this->container->singleton(
            ContextService::class,
            fn (): ContextService => new ContextService(
                $this->container->get(ElementorContext::class),
                $this->container->get(Redactor::class),
                $this->container->get(ContextBudget::class),
            ),
        );
        $this->container->singleton(
            ConversationRepository::class,
            fn (): ConversationRepository => new ConversationRepository($this->container->get(TableNames::class)),
        );
        $this->container->singleton(
            PlanRepository::class,
            fn (): PlanRepository => new PlanRepository($this->container->get(TableNames::class)),
        );
        $this->container->singleton(AuditLogger::class, fn (): AuditLogger => new AuditLogger($this->container->get(TableNames::class)));
        $this->container->singleton(AuditRepository::class, fn (): AuditRepository => new AuditRepository($this->container->get(TableNames::class)));
        $this->container->singleton(SnapshotRepository::class, fn (): SnapshotRepository => new SnapshotRepository($this->container->get(TableNames::class)));
        $this->container->singleton(DiffService::class, static fn (): DiffService => new DiffService());
        $this->container->singleton(
            DocumentRepository::class,
            fn (): DocumentRepository => new DocumentRepository($this->container->get(ElementorGuard::class)),
        );
        $this->container->singleton(
            ElementorWriter::class,
            fn (): ElementorWriter => new ElementorWriter($this->container->get(CapabilityCatalog::class)),
        );
        $this->container->singleton(
            DocumentValidator::class,
            fn (): DocumentValidator => new DocumentValidator($this->container->get(CapabilityCatalog::class)),
        );
        $this->container->singleton(ToolRegistry::class, static fn (): ToolRegistry => new ToolRegistry());
        $this->container->singleton(
            JobRepository::class,
            fn (): JobRepository => new JobRepository($this->container->get(TableNames::class)),
        );
        $this->container->singleton(
            DocumentTransaction::class,
            fn (): DocumentTransaction => new DocumentTransaction(
                $this->container->get(DocumentRepository::class),
                $this->container->get(ElementorWriter::class),
                $this->container->get(DocumentValidator::class),
                $this->container->get(SnapshotRepository::class),
                $this->container->get(DiffService::class),
            ),
        );
        $this->container->singleton(
            JobRunner::class,
            fn (): JobRunner => new JobRunner(
                $this->container->get(JobRepository::class),
                $this->container->get(DocumentTransaction::class),
                $this->container->get(ToolRegistry::class),
                $this->container->get(AuditLogger::class),
            ),
        );
        $this->container->singleton(PromptBuilder::class, static fn (): PromptBuilder => new PromptBuilder());
        $this->container->singleton(PlanJsonDecoder::class, static fn (): PlanJsonDecoder => new PlanJsonDecoder());
        $this->container->singleton(
            PlanSchemaValidator::class,
            fn (): PlanSchemaValidator => new PlanSchemaValidator($this->container->get(CapabilityCatalog::class)),
        );
        $this->container->singleton(
            ConversationService::class,
            fn (): ConversationService => new ConversationService(
                $this->container->get(AIManager::class),
                $this->container->get(Settings::class),
                $this->container->get(ContextService::class),
                $this->container->get(ConversationRepository::class),
                $this->container->get(PromptBuilder::class),
            ),
        );
        $this->container->singleton(
            PlanService::class,
            fn (): PlanService => new PlanService(
                $this->container->get(AIManager::class),
                $this->container->get(Settings::class),
                $this->container->get(ContextService::class),
                $this->container->get(ConversationRepository::class),
                $this->container->get(PlanRepository::class),
                $this->container->get(JobRepository::class),
                $this->container->get(DocumentRepository::class),
                $this->container->get(PromptBuilder::class),
                $this->container->get(PlanJsonDecoder::class),
                $this->container->get(PlanSchemaValidator::class),
            ),
        );
        $this->container->singleton(RestResponder::class, static fn (): RestResponder => new RestResponder());
        $this->container->singleton(PluginPermissions::class, static fn (): PluginPermissions => new PluginPermissions());
        $this->container->singleton(
            Permission::class,
            fn (): Permission => new Permission(
                $this->container->get(PluginPermissions::class),
                $this->container->get(Settings::class),
            ),
        );
        $this->container->singleton(
            ContextController::class,
            fn (): ContextController => new ContextController(
                $this->container->get(ContextService::class),
                $this->container->get(RestResponder::class),
            ),
        );
        $this->container->singleton(
            SessionController::class,
            fn (): SessionController => new SessionController(
                $this->container->get(ContextService::class),
                $this->container->get(Settings::class),
                $this->container->get(ConversationRepository::class),
                $this->container->get(RestResponder::class),
            ),
        );
        $this->container->singleton(
            ProviderController::class,
            fn (): ProviderController => new ProviderController(
                $this->container->get(AIManager::class),
                $this->container->get(AuditLogger::class),
                $this->container->get(AuditRepository::class),
                $this->container->get(RestResponder::class),
            ),
        );
        $this->container->singleton(
            ChatController::class,
            fn (): ChatController => new ChatController(
                $this->container->get(ConversationService::class),
                $this->container->get(RestResponder::class),
            ),
        );
        $this->container->singleton(
            PlanController::class,
            fn (): PlanController => new PlanController(
                $this->container->get(PlanService::class),
                $this->container->get(RestResponder::class),
            ),
        );
        $this->container->singleton(
            ExecutionController::class,
            fn (): ExecutionController => new ExecutionController(
                $this->container->get(JobRepository::class),
                $this->container->get(JobRunner::class),
                $this->container->get(DocumentTransaction::class),
                $this->container->get(RestResponder::class),
            ),
        );
        $this->container->singleton(
            AdminPage::class,
            fn (): AdminPage => new AdminPage(
                $this->container->get(CompatibilityGuard::class),
                $this->container->get(Settings::class),
            ),
        );
        $this->container->singleton(EditorAssets::class, static fn (): EditorAssets => new EditorAssets());
        $this->container->singleton(
            Routes::class,
            fn (): Routes => new Routes(
                $this->container->get(Permission::class),
                $this->container->get(ContextController::class),
                $this->container->get(SessionController::class),
                $this->container->get(ProviderController::class),
                $this->container->get(ChatController::class),
                $this->container->get(PlanController::class),
                $this->container->get(ExecutionController::class),
            ),
        );
    }
}
