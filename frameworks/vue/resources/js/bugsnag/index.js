import Bugsnag from '@bugsnag/js';
import BugsnagPluginVue from '@bugsnag/plugin-vue';

if (import.meta.env.VITE_BUGSNAG_JS_API_KEY) {
    Bugsnag.start({
        apiKey: import.meta.env.VITE_BUGSNAG_JS_API_KEY,
        plugins: [new BugsnagPluginVue()],
        releaseStage: import.meta.env.VITE_APP_ENV,
        enabledReleaseStages: ['development', 'production'],
    });
}

export const bugsnagVue = import.meta.env.VITE_BUGSNAG_JS_API_KEY
    ? Bugsnag.getPlugin('vue')
    : undefined;
