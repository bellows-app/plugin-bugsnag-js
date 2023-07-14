import React from 'react';
import Bugsnag from '@bugsnag/js';
import BugsnagPluginReact from '@bugsnag/plugin-react';

if (import.meta.env.VITE_BUGSNAG_JS_API_KEY) {
    Bugsnag.start({
        apiKey: import.meta.env.VITE_BUGSNAG_JS_API_KEY,
        plugins: [new BugsnagPluginReact()],
        releaseStage: import.meta.env.VITE_APP_ENV,
        enabledReleaseStages: ['development', 'production'],
    });
}

export const ErrorBoundary =
    Bugsnag.getPlugin('react').createErrorBoundary(React);

// const ErrorView = () => (
//     <div>
//         <p>Inform users of an error in the component tree.</p>
//     </div>
// );

// export default () => (
//     <ErrorBoundary FallbackComponent={ErrorView}>
//         <App />
//     </ErrorBoundary>
// );
