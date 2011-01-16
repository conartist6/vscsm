<html>
    <head>
<link rel="stylesheet" type="text/css" href="newlayout.css" />
<script type="text/javascript" src="flot/jquery.min.js"></script>
<script type="text/javascript" src="flot/jquery.flot.js"></script>
<script type="text/javascript">
    window.onresize=resizeLayout;

    var serverList;
    var host;
    var activeServer = 0;
    var tc;
    var lens;
    var anyActive = false;
    lens = new Array(); 

    function Server(fullname, tabname, spath){
	this.fullName=fullname;
	/* Should sound good with "The ______ Server" */
	this.tabName=tabname;
	/* Should look good on a tab */
	this.shortN=this.tabName.replace(/[ :;.,]/g,"").toLowerCase(); 
	this.running=false;
	//this.buffer=null;
	this.serverNo=0;
	this.wasRunning=false;
	/* Do we need to update it? */
	this.serverPath=spath;
	
	this.maplist=null;
	this.mapselectable=false;
	this.mapChangeCmd = null;
    }
    Server.prototype.selectmap = function(){
	    var mapsel = document.getElementById('mapSelector' + this.serverNo);
	    var seltxt = document.getElementById('changeS' + this.serverNo).firstChild;
	    if(this.mapselectable == false){ 
		mapsel.disabled = false;
		this.mapselectable = true;
		seltxt.innerHTML = "cancel";
	    }
	    else{
		mapsel.disabled = true;
		this.mapselectable = false;
		seltxt.innerHTML = "change";
	    }		
    }
    Server.prototype.changemap = function(mapname){
	    //alert("changing map");
	    if(this.mapChangeCmd == null) return;
	    for(i in this.mapChangeCmd){
		var line = this.mapChangeCmd[i].replace("%m", mapname);
		//alert(line);
		sendThisCommand(line);
	    }
	    this.selectmap();
    } 
    function Host(){
	this.ipaddr = <?php
exec("ifconfig eth0 | grep 'inet addr:'|" .
"grep -v '127.0.0.1' | cut -d: -f2 | awk '{ print $1}'", $ip);
echo json_encode($ip);
?>;
	this.hostname = <?php
exec("hostname --fqdn", $fqdn);
echo json_encode($fqdn);
?>;
	this.nprocs = <?php
exec("cat /proc/cpuinfo | grep processor | wc -l", $np);
echo json_encode($np);
?>;
	this.usageGraph = new usageGraph(this.nprocs); 	
    }

    function usageGraph(np){
	this.graphObj = new Array(np);
	this.tOffs = -1;
	for(var i=0; i<np; i++){
	    this.graphObj[i] = new Object();
	    this.graphObj[i].data = new Array(40);
	    this.graphObj[i].color = "rgb(255,215,0)";
	}
    }
    usageGraph.prototype.addLoad = function(load){
	for(var i=0; i<host.nprocs; i++){
	    if(this.graphObj[i].data.length >= 40){
		this.graphObj[i].data.shift();
	    }
	    this.graphObj[i].data.push(
		[this.tOffs + this.graphObj[i].data.length + 1,
		load[i]]
	    );
	    this.tOffs++;
	    if(this.graphObj[i].data.length + this.tOffs == 1073741824){
		for(var j=0; j<graphObj[i].data.length; j++){
		    graphObj[i].data[j][0] = j;
		    this.tOffs = -1;
		}	
	    }
	}
	this.tOffs++;	
    }
    usageGraph.prototype.renderGraph = function(){
	var ug = host.usageGraph;
	var go = ug.graphObj;
 			
	$.plot($(".loadgraphcanvas"), go,
	    {
		series: {
		    lines: { show: true, fill: true, color: "rgb(255,255,0)" },
		},    
		xaxis: { min: ug.tOffs, max: ug.tOffs + go[0].data.length,
		ticks: new Array(1)},
	    yaxis: { min: 0, max: 100, ticks: 5 }
	    });

    } 

    function pageLoad(){
	resizeLayout();

	serverList = new Array();

	serverList.push(new Server("Counter-Strike: Source",
	    "cs:s", "/usr/local/srcds_l/orangebox/"));
	serverList.push(new Server("Call of Duty II", "cod2", "/usr/local/cod2_ds/"));
	serverList.push(new Server("Minecraft", "mcraft", "/usr/local/minecraft_ds/"));
	serverList.push(new Server("Ventrillo", "vent", "/usr/local/ventsrv/"));
        loadCfg(serverList[0]); 
	loadCfg(serverList[1]);
	populateTabs();
	appendNewLines();

	host = new Host(); 

	updateHostGraph();
    }
    function updateHostGraph(){
	var xmlhttp; 
	if(window.XMLHttpRequest){
	    xmlhttp=new XMLHttpRequest();
	}
	else{
	    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.open("GET", "load.php", true);
	xmlhttp.onreadystatechange = function(){
	    if(xmlhttp.readyState==4 && xmlhttp.status == 200){
		var splits = xmlhttp.responseText.split("\n");
		
		var loads = new Array(splits.length);
		for(var i=0; i<splits.length; i++){
		    loads[i] = 100 - parseFloat(splits[i]);
		}

		host.usageGraph.addLoad(loads);                                      
		host.usageGraph.renderGraph();

		setTimeout("updateHostGraph()", 4000);     
	    }
	}
	xmlhttp.send();
	//alert(xmlhttp.responseText);

    }                                                              
    function loadCfg(server){
        var xmlhttp, responses; 
	if(window.XMLHttpRequest){
	    xmlhttp=new XMLHttpRequest();
	}
	else{
	    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.open("GET", server.shortN + ".cfg"
	    , false);
	xmlhttp.send();
	responses = xmlhttp.responseText.split("\n");
	var response, section;
	for(line in responses){
	    response = responses[line];
	    var linebegin = response.search(/[^ ]/);
	    if (linebegin != -1){
		response = response.substr(linebegin);
		//alert (response);
		if (response.charAt(0) == ";"){
		}
		else if (response.charAt(0) == "["){
		    section = response.substr(1, response.indexOf("]") - 1);
		}
		else{
		    switch(section){
		    case "maps":
			if(server.maplist == null){
			    server.maplist = new Array();
			}
			server.maplist.push(response);
			break;
		    case "tochange":
			server.mapChangeCmd = new Array();
			server.mapChangeCmd.push(response);
			break;
		    }
		}
	    }
	}
    } 
    function appendNewLines(){
	var server;
	for(var i in serverList){
	    server = serverList[i];
	    if(server.running){
		doAppendNewLines(server);
	    }
	}
	setTimeout("appendNewLines()", 1000);
    }
    function doAppendNewLines(server){
	var o, sp0, s, gss;

	o = document.getElementById('server' + server.serverNo + 'Output');
	s = document.getElementById('serverStatus');

	var xmlhttp, response; 
	if(window.XMLHttpRequest){
	    xmlhttp=new XMLHttpRequest();
	}
	else{
	    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	//alert(server.tabName);
	xmlhttp.open("GET","getlines.php?game=" + server.shortN, false);
	xmlhttp.send();
	//alert(server.wasRunning);                                                                     
	if((gss = xmlhttp.getResponseHeader('Game-Server-Status')) == "Running"){
	    if(server.wasRunning == false){		//Should maybe be moved somewhere else?
		server.running = true;			//This should be the only looped code checking
		setTabRunning(server.serverNo, true);  	//if a server is or is not really running	
		server.wasRunning = true;
	    }
	    //alert("\"" + gss + "\"");	    
	    response = xmlhttp.responseText;
            //alert(response);

	    var cl = parseInt(response.substring(0, 8), 10);
	    //alert("cl: " + response.substring(0,8));
	    if(cl > 0){	
		lens.push(cl);
		//alert("countlines: " + countLines());	
		while(countLines() > 400){
		    //alert("countl: " + countLines());
		    sp0 = o.firstChild;
		    sp0.parentNode.removeChild(sp0);
		    //alert("Tried to remove a child. Did it work?");
		    lens.shift(); 
		}
		//alert(response);
		o.innerHTML += "<span>\n" + response.substring(8) + "</span>";
		o.scrollTop = o.scrollHeight;
	    }	
	}
	else{
	    if(server.wasRunning == true){
		server.running = false;
		setTabRunning(server.serverNo, false);
		server.wasRunning = false;
	    }
	    //alert("\"" + gss + "\"");	    
	}
    }
    function sendCommand(){
	var command, cbox;
	//alert(activeServer);
	cbox = document.getElementById('serverCommand');
	command = cbox.value;
	cbox.value = "";
        sendThisCommand(command);
    }
    function sendThisCommand(command){
	var server = serverList[activeServer]; 
      	command = command.replace(" ", "%20");
	var xmlhttp, response; 
	if(window.XMLHttpRequest){
	    xmlhttp=new XMLHttpRequest();
	}
	else{
	    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.open("GET", "sendcommand.php?" +
	    "game=" + server.shortN +
	    "&dir=" + server.serverPath +
	    "&command=" + command
	    , false);
	xmlhttp.send();
	/*alert("sendcommand.php?" +
	    "game=" + server.shortN +
	    "&dir=" + server.serverPath +
	    "&command=" + command);*/
	/*alert(xmlhttp.responseText);*/
	return false; 
    }
    function clearBuffer(){
	var o = document.getElementById('serverOutput');
	o.innerHTML = "";
	lens.splice(0,lens.length);
    }
    function countLines(){
	var count, i;
	count = 0;
	//alert("lens.len: " + lens.length);
	//alert("lens0: " + lens[0]);
	for(i=0; i<lens.length; i++){
	    if (lens[i] > 0){
		count += lens[i];
	    }
	}
	//alert("count: " + count);
	return count;
    }
    function showStats(){
	alert("countLines(): " + countLines());
    }
    function scrollToDivBottom(){
	var scrollHeight = Math.max(this.scrollHeight, this.clentHeight);
       	this.scrollTop = scrollHeight - this.clientHeight;
    }
    function modServer(startStr, serverNo){
	var start, server;
	server = serverList[serverNo];
	if(startStr == "start"){
	    //Don't start a server which is already started, etc.
	    start = true;
	    if(server.running == true){
		return;
	    }
	    server.running = true;
	}
	else if(startStr == "stop"){
	    start = false;
	    if(server.running == false){
		return;
	    }
	}
	else{
	    alert("Not start or stop");
	    return;
	}
	var xmlhttp; 
	if(window.XMLHttpRequest){
	    xmlhttp = new XMLHttpRequest();
	}
	else{
	    xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	//alert(serverNo + " " + server.tabName);
	if(start){
	    var url =  "srvcommand.php?" +
		"game=" + server.tabName + "&dir=" +
	        server.serverPath;	
	    xmlhttp.open("GET", url, false);
	    //alert(url);
	    xmlhttp.send();
	    //alert(xmlhttp.responseText);
	}
	else{
	    xmlhttp.open("GET", "stop" + server.shortN + ".php", false);
	    xmlhttp.send(); 
	}
	//appendNewLines(serverList[serverNo], start);

 	//alert("Starting server! \n Server says: " + xmlhttp.responseText);	
    }   

    function populateTabs(){
	var stabs = document.getElementById('serverTabs');
	stabs.innerHTML="";
	for(var i in serverList){
	    var server = serverList[i];
	    var newDiv = document.createElement('div');
	    newDiv.setAttribute('class', 'tab');
	    newDiv.setAttribute('onClick',
		    'changeActiveServer(' + i + ')');

	    var newTab = document.createElement('div');
	    newDiv.appendChild(newTab);

	    newTab = document.createElement('div');
	    newTab.innerHTML= serverList[i].tabName;
	    newDiv.firstChild.appendChild(newTab);

	    stabs.appendChild(newDiv);

	    var op = document.getElementById('serverOutput');
	    newDiv = document.createElement('div');
	    newDiv.setAttribute('id', 'server' + i + 'Output');
	    newDiv.setAttribute('style', 'z-index: 0;');
	    
	    server.serverNo = i;
            op.appendChild(newDiv);

            var ss = document.getElementById('serverStatus');
	    newDiv = document.createElement('div');
	    newDiv.setAttribute('id', 'server' + i + 'Status');
	    newDiv.setAttribute('style', 'z-index: 0;');
	    newDiv.setAttribute('class', 'singleserverstat');
	    var tmp = document.createElement('div');
	    tmp.setAttribute('class', 'servername');
	    tmp.innerHTML = server.fullName;
	    newDiv.appendChild(tmp);
	    tmp = document.createElement('hr');
	    tmp.setAttribute('width', '60%');
	    newDiv.appendChild(tmp);
	    //newDiv.innerHTML =
		//"<hr width=\"60%\" />\n";// +
		//"<div class=\"servercontrols\">\n" +
		//"</div>";

	    ss.appendChild(newDiv);

	    if(server.maplist != null){
		var tmp2, tmp3;
		tmp = document.createElement('form');
		tmp.setAttribute('onsubmit', 'return false');
		tmp.innerHTML += "<b> map:</b> ";
		tmp2 = document.createElement('select');
		tmp2.setAttribute('id', 'mapSelector' + i);
		tmp2.setAttribute('onchange',
		    'serverList[' + i + '].changemap(this.value)');
		for(j in server.maplist){
		    var map = server.maplist[j]; 
		    tmp3 = document.createElement('option');
		    tmp3.setAttribute('value', map);
		    tmp3.innerHTML = map;
		    tmp2.appendChild(tmp3);
		}
		tmp2.disabled = true;
		tmp.appendChild(tmp2);
		tmp3 = document.createElement('div');
		tmp3.setAttribute('id', 'changeS' + i);
		tmp3.setAttribute('style', 'float: right; padding-right: 5px;');
		tmp2 = document.createElement('a');
		tmp2.setAttribute('href', '#');
		tmp2.setAttribute('onMouseUp',
		    'serverList[activeServer].selectmap()');
		tmp2.innerHTML = "change";
		tmp3.appendChild(tmp2);
		tmp.appendChild(tmp3);
		document.getElementById('server' + i + 'Status').appendChild(tmp);
	    }



	    var xmlhttp; 
	    if(window.XMLHttpRequest){
		xmlhttp = new XMLHttpRequest();
	    }
	    else{
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	    }
	    xmlhttp.open("GET",
		"getlines.php?game=" +
		serverList[i].shortN
		+ "&onlyHeaders=true", false);
	    xmlhttp.send();
	    switch(xmlhttp.getResponseHeader('Game-Server-Status')){
	    case "Running":
		serverList[i].running = true;
		setTabRunning(i, true);
		break;
	    case "Stopped":
		setTabRunning(i, false);
		break;
	    }
	}
	changeActiveServer(0); 
    }
    function changeActiveServer(tabNumber){
	var oldActive = activeServer;
	activeServer = tabNumber;
	if(serverList[activeServer].running){
	    setTabRunning(tabNumber, true);
	}
	else{
	    setTabRunning(tabNumber, false);
	}
	setTabRunning(oldActive, serverList[oldActive].running);
	//var sn = document.getElementById('serverName');
	//sn.innerHTML=serverList[tabNumber].fullName;

	var ssold = document.getElementById('server' + oldActive + 'Status');
	ssold.setAttribute('style', 'z-index: 0;');
 	var ssnew = document.getElementById('server' + tabNumber + 'Status'); 
	ssnew.setAttribute('style', 'z-index: 10;');

	var soold = document.getElementById('server' + oldActive + 'Output');
	//soold.style.zIndex = '0';
	soold.setAttribute('style', 'z-index: 0;');
	var sonew = document.getElementById('server' + tabNumber + 'Output');
	//alert('server' + tabNumber + 'Output');
	//soold.style.zIndex = '10';
	sonew.setAttribute('style', 'z-index: 10;');

    }
    function setTabRunning(tab, running){
    //alert(tab);
	var clas, tabref;
	clas = "tab clear tab";
	if(running) clas += "running";
	else clas += "stopped";
	if(tab == activeServer){
	    clas  += "active";
	    setBorderColors(running);
	}
	else clas += "inactive";

	tabref = document.getElementById('serverTabs').childNodes[tab];
	tabref.setAttribute('class', clas);

    }
    function setBorderColors(isRunning){
	var cs = document.getElementById('colorStatus');
	var ssc = document.getElementById('serverStatusColor');
	var pmc = document.getElementById('pageMidColor');
	if(isRunning){
	    cs.setAttribute('class', 'colorstatus brightgreen');
	    ssc.setAttribute('class', 'serverstatuscolor brightgreen');
	    pmc.setAttribute('class', 'pagemidcolor brightgreen');
	}
	else{
	    cs.setAttribute('class', 'colorstatus brightred');
	    ssc.setAttribute('class', 'serverstatuscolor brightred');
	    pmc.setAttribute('class', 'pagemidcolor brightred');
	}
    }
    function resizeLayout(){
	setTopHalfHeight();
	setRunningServersWidth();
    }
    function getWindowHeight(){
        return window.innerHeight;
    }
    function getWindowWidth(){
        return window.innerWidth;
    }
    function setTopHalfHeight(){
	var sop = document.getElementById('serverOutputPane');
	var ptr = document.getElementById('pageTopRight');
	var so = document.getElementById('serverOutput');
	var wh = getWindowHeight();
	sop.style.height = wh - 12 /*- 16*/ - 38 - 40;
	so.style.height = wh - 12 - 38 - 40 - 6 - 4;
	ptr.style.height = wh - 12 - 40;
    }
    function setRunningServersWidth(){
	var rs = document.getElementById('runningServers');
	var so = document.getElementById('serverOutput');
	var ww = getWindowWidth();
	rs.style.width = ww - 255 - 10 - 12 ;
	so.style.width = ww - 255 - 10 - 12 - 6 - 8;
	setInputBoxWidth(ww - 12);
    }
    function setInputBoxWidth(pageWidth){
	var sc = document.getElementById('serverCommand');
	var boxpx = pageWidth - 72;
	sc.size = parseInt(-5.5 + boxpx * .164);
    }
</script>
<title>Kraid Server Management</title>
</head>
<body onload="pageLoad()">
    <div class="bodyframe">
	<div class="pagetop" id="pageTop">
	    <div class="runningservers" id="runningServers" style="width: 1px">
		<div class="servertabs" id="serverTabs">
		</div>
		<div class="serveroutputpane" id="serverOutputPane" style="height: 1px">
		    <div class="colorstatus" id="colorStatus">
		    </div>
		    <div class="serveroutput" id="serverOutput" style="width: 1px">
		    </div>
		    <div class="pagemidcolor" id="pageMidColor">
		    </div>
		</div>
	    </div>
	    <div class="pagetopright" id="pageTopRight" style="height: 1px">
		<div class="hoststatus" id="hostStatus">
		    <b>Hostname:</b> <?php system("hostname --fqdn"); ?>
		    <br />
		    <b>IP:</b> <?php system("ifconfig eth0 | grep 'inet addr:'|" .
	"grep -v '127.0.0.1' | cut -d: -f2 | awk '{ print $1}'"); ?>
		    <div class="loadgraphcanvas">
		    </div>
		</div>
		<div class="serverstatusbox" id="serverStatusBox">
		    <div class="serverstatuscolor" id="serverStatusColor">
		    </div>
		    <div class="serverstatus" id="serverStatus">

		    </div>
		    <div style="clear:both"></div> 
		    <div class="daemoncontrol">
			<input type="button" value=" Start "
			    class="daemonbutton"
				    onclick='modServer("start", activeServer)'>
			<input type="button" value=" Stop "
			    class="daemonbutton"
				    onclick='modServer("stop", activeServer)'>
			<input type="button" value=" Restart " class="daemonbutton">
		    </div>
		</div>
	    </div>
	    <div style="clear:both"></div>
	</div>
	<div class="pagebottom">
	    <div class="inputbox">
		<form method="GET" action="" onSubmit="sendCommand(); return false;">
		    <input type="text" name="servercommand" class="servercommand"
			id="serverCommand" size="10" autocomplete="off" />
		    <div class="submitbuttonbox">
			<input class="submitbutton" type="submit" value="  Send  "</input>
		    </div>
		    <div style="clear: both"></div>
		</form>       
	    </div>
	</div>
</body>
</html>



<?php
/*
 * WEB INTERFACE MULTI-SERVER BEHAVIOUR
 *	Tick based. Tick occurs every 1000 ms by defualt.
 *	On each tick: append output for each running server to its div
 *
 * POSSIBLE CHANGES
 *	DONE: Call interface update functions only on backend update triggers
 *
 * DONE: ON TAB INITIALIZATION ...
 *
 * PROBABLY REALLY IMPORTANT
 *	It takes a good bit longer for the cod2 start/stop to be recognized than
 *	it does for the source one... :?
 *
 */
?>