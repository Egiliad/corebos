    var sentForm = event.data;
    if (sentForm['module']!=undefined) {
        var thisp = this, syncPBar = new EventSource('index.php?module='+sentForm['module']+'&action='+sentForm['module']+'Ajax&file=syncrecods&params='+encodeURIComponent(JSON.stringify(sentForm)));
        syncPBar.addEventListener('message', function (event) {
            var result = JSON.parse(event.data);
            thisp.postMessage(result);
            if (event.lastEventId == 'CLOSE') {
                thisp.postMessage('CLOSE');
                syncPBar.close();
            }
        }, false);

        syncPBar.addEventListener('error', function (e) {
            syncPBar.close();
        });
    }