<?php

namespace Bellows\Plugins;

use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Contracts\HttpClient;
use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\Data\AddApiCredentialsPrompt;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\Entity;
use Bellows\PluginSdk\Facades\Npm;
use Bellows\PluginSdk\Facades\Project;
use Bellows\PluginSdk\Plugin;
use Bellows\PluginSdk\PluginResults\CanBeDeployed;
use Bellows\PluginSdk\PluginResults\CanBeInstalled;
use Bellows\PluginSdk\PluginResults\DeploymentResult;
use Bellows\PluginSdk\PluginResults\InstallationResult;
use Illuminate\Http\Client\PendingRequest;

class BugsnagJS extends Plugin implements Deployable, Installable
{
    use CanBeDeployed, CanBeInstalled;

    protected string $jsFramework = 'js';

    protected ?string $bugsnagKey;

    protected string $organizationId;

    public function __construct(
        protected HttpClient $http,
    ) {
    }

    public function install(): ?InstallationResult
    {
        $jsFramework = Console::choice('Which JS framework are you using?', ['Vue', 'React', 'Neither']);

        $this->jsFramework = match ($jsFramework) {
            'Vue'   => 'vue',
            'React' => 'react',
            default => 'js',
        };

        $result = InstallationResult::create();

        if (Console::confirm('Setup Bugsnag JS project now?', false)) {
            $this->setupProject();
            $result->environmentVariables($this->environmentVariables());
        }

        return $result->npmPackages(
            match ($this->jsFramework) {
                'vue'   => ['@bugsnag/plugin-vue'],
                'react' => ['@bugsnag/plugin-react'],
                default => [],
            }
        )
            ->copyDirectory(__DIR__ . '/../frameworks/' . $this->jsFramework)
            ->wrapUp($this->installationWrapUp(...));
    }

    public function deploy(): ?DeploymentResult
    {
        $this->setupProject();

        return DeploymentResult::create()->environmentVariables($this->environmentVariables());
    }

    public function requiredNpmPackages(): array
    {
        return [
            '@bugsnag/js',
        ];
    }

    public function shouldDeploy(): bool
    {
        return !Deployment::site()->env()->hasAll('BUGSNAG_JS_API_KEY', 'VITE_BUGSNAG_JS_API_KEY');
    }

    protected function getProjectKey(string $type)
    {
        $this->setupClient();

        $projects = $this->http->client()->get("organizations/{$this->organizationId}/projects", [
            'per_page' => 100,
        ])->json();

        $projectsOfType = collect($projects)->where('type', $type);

        return Entity::from($projectsOfType)
            ->selectFromExisting(
                'Select a Bugsnag project',
                'name',
                Project::appName(),
                'Create new project',
            )
            ->createNew(
                'Create new Bugsnag project?',
                fn () => $this->createProject($type),
            )
            ->prompt()['api_key'];
    }

    protected function setupClient(): void
    {
        $this->http->createJsonClient(
            'https://api.bugsnag.com/',
            fn (PendingRequest $request, array $credentials) => $request->withToken($credentials['token'], 'token'),
            new AddApiCredentialsPrompt(
                url: 'https://app.bugsnag.com/settings/my-account',
                credentials: ['token'],
                displayName: 'Bugsnag',
            ),
            fn (PendingRequest $request) => $request->get('user/organizations', ['per_page' => 1]),
            true,
        );

        $this->organizationId = $this->http->client()->get('user/organizations')->json()[0]['id'];
    }

    protected function createProject(string $type): array
    {
        return $this->http->client()->post("organizations/{$this->organizationId}/projects", [
            'name' => Console::ask('Project name', Project::appName()),
            'type' => $type,
        ])->json();
    }

    protected function setupProject()
    {
        $this->bugsnagKey = Project::env()->get('BUGSNAG_JS_API_KEY');

        if ($this->bugsnagKey) {
            Console::miniTask('Using existing Bugsnag JS key from', '.env');

            return;
        }

        $type = collect(['vue', 'react'])->first(fn ($p) => Npm::packageIsInstalled($p)) ?: 'js';

        $this->bugsnagKey = $this->getProjectKey($type);
    }

    protected function environmentVariables(): array
    {
        return [
            'BUGSNAG_JS_API_KEY'      => $this->bugsnagKey,
            'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
        ];
    }

    protected function installationWrapUp(): void
    {
        if ($this->jsFramework === 'js') {
            return;
        }

        if ($this->jsFramework === 'vue') {
            Project::file('resources/js/app.js')
                ->addJsImport("import { bugsnagVue } from './bugsnag'")
                ->replace('.use(plugin)', ".use(plugin)\n.use(bugsnagVue)");
        }
    }
}
