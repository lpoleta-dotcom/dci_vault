(function () {
    var historyState = { dciAuthenticatedLanding: true };

    if (!window.history.state || window.history.state.dciAuthenticatedLanding !== true) {
        window.history.replaceState(historyState, '', window.location.href);
        window.history.pushState(historyState, '', window.location.href);
    }

    window.addEventListener('popstate', function () {
        window.history.pushState(historyState, '', window.location.href);
        window.location.reload();
    });

    window.addEventListener('pageshow', function (event) {
        var navigationEntries = window.performance && typeof window.performance.getEntriesByType === 'function'
            ? window.performance.getEntriesByType('navigation')
            : null;
        var isBackForward = (navigationEntries && navigationEntries[0] && navigationEntries[0].type === 'back_forward')
            || (window.performance && window.performance.navigation && window.performance.navigation.type === 2);

        if (event.persisted || isBackForward) {
            window.location.reload();
        }
    });
})();
