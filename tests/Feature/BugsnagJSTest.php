<?php

use Bellows\Plugins\BugsnagJS;
use Bellows\PluginSdk\Facades\Npm;
use Bellows\PluginSdk\Facades\Project;
use Illuminate\Support\Facades\Http;

it('can choose an app from the list', function () {
    Npm::install('vue');

    Http::fake([
        'user/organizations' => Http::response([
            [
                'id'   => '123',
                'name' => 'Bellows',
            ],
        ]),
        'organizations/123/projects?per_page=100' => Http::response([
            [
                'id'      => '456',
                'name'    => Project::appName(),
                'type'    => 'vue',
                'api_key' => 'test-api-key',
            ],
        ]),
    ]);

    $result = $this->plugin(BugsnagJS::class)
        ->expectsQuestion('Select a Bugsnag project', Project::appName())
        ->deploy();

    expect($result->getEnvironmentVariables())->toBe([
        'BUGSNAG_JS_API_KEY'      => 'test-api-key',
        'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
    ]);
});

it('can create a new app', function ($package, $projectType) {
    if ($package) {
        Npm::install($package);
    }

    Http::fake([
        'user/organizations' => Http::response([
            [
                'id'   => '123',
                'name' => 'Bellows',
            ],
        ]),
        'organizations/123/projects?per_page=100' => Http::response([
            [
                'id'      => '456',
                'name'    => 'Random Project',
                'type'    => $projectType,
                'api_key' => 'test-api-key',
            ],
        ]),
        'projects' => Http::response([
            'id'      => '789',
            'name'    => 'Test App',
            'api_key' => 'test-api-key',
        ]),
    ]);

    $result = $this->plugin(BugsnagJS::class)
        ->expectsConfirmation('Create new Bugsnag project?', 'yes')
        ->expectsQuestion('Project name', 'Test App')
        ->deploy();

    $this->assertRequestWasSent('POST', 'organizations/123/projects', [
        'name' => 'Test App',
        'type' => $projectType,
    ]);

    expect($result->getEnvironmentVariables())->toBe([
        'BUGSNAG_JS_API_KEY'      => 'test-api-key',
        'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
    ]);
})->with([
    ['vue', 'vue'],
    ['react', 'react'],
    [null, 'js'],
]);

it('will use the .env variable if there is one', function () {
    $this->setEnv(['BUGSNAG_JS_API_KEY' => 'test-api-key']);

    $result = $this->plugin(BugsnagJS::class)
        ->expectsOutputToContain('Using existing Bugsnag JS key from')
        ->deploy();

    expect($result->getEnvironmentVariables())->toBe([
        'BUGSNAG_JS_API_KEY'      => 'test-api-key',
        'VITE_BUGSNAG_JS_API_KEY' => '${BUGSNAG_JS_API_KEY}',
    ]);
});
