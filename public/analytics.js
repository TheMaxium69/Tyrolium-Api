(function () {
    var script = document.currentScript;
    var tag = script && script.dataset.tyroTag;
    if (!tag) {
        return;
    }

    var endpoint = new URL('/tyrolium/analytics/post-create-input', script.src).href;

    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        keepalive: true,
        body: JSON.stringify({
            projectTag: tag,
            pageName: document.title,
            uri: location.pathname,
            isLogin: script.dataset.tyroLoggedIn === 'true'
        })
    });
})();
