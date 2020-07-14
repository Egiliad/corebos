function run_customsse(e) {
	var message = e.data;
	if (e.data == 'CLOSE') {
		__addLog('<br><b>' + alert_arr.ProcessFINISHED + '!</b>');
		var pBar = document.getElementById('progressor');
		pBar.value = pBar.max; //max out the progress bar
	} else {
		__addLog(message.message);
		var pBar = document.getElementById('progressor');
		pBar.value = message.progress;
		var perc = document.getElementById('percentage');
		perc.innerHTML   = message.progress + '% &nbsp;&nbsp;' + message.processed + '/' + message.total;
		perc.style.width = (Math.floor(pBar.clientWidth * (message.progress/100)) + 15) + 'px';
	}
}