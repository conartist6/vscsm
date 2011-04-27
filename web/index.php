<?php
/*define('QUADODO_IN_SYSTEM', true);
require_once('qls/includes/header.php');
$qls->Security->check_auth_page('members.php');*/ ?>
<?php
// Look in the USERGUIDE.html for more info
//if ($qls->user_info['username'] != '') {
?>

<!--You are logged in as <?php //echo $qls->user_info['username']; ?><br />
Your email address is set to <?php //echo $qls->user_info['email']; ?><br />
There have been <b><?php //echo $qls->hits('qls/members.php'); ?></b> visits to this page.<br />
<br />
Currently online users (<?php //echo count($qls->online_users()); ?>): <?php //$qls->output_online_users(); ?>-->

<?php
//}
//else {   
//    header('Location: qls/login.php');}
?>
<?php
    //Types of permissions for this page:
    //Start/stop server
    //Send server command.
    //View servers
    //View server output
    //Server shutdown/restart/etc
    //Create server
    //Delete server
    //Allowing on-the-fly server creation would be BAD for unprivs. User could start a shell!


?>



<html>
    <head>
<link rel="stylesheet" type="text/css" href="newlayout.css" />
<script type="text/javascript" src="flot/jquery.min.js"></script>
<script type="text/javascript" src="flot/jquery.flot.min.js"></script>
<script type="text/javascript">
    window.onresize=resizeLayout;

    var serverList;
    var host;
    var activeServer;
    var tc;
    var lens;
    var anyActive = false;
    lens = new Array(); 

    function Server(fullname, tabname){
	this.fullName=fullname;
	/* Should sound good with "The ______ Server" */
	this.tabName=tabname;
	/* Should look good on a tab */
	this.shortN=this.tabName.replace(/[ :;.,]/g,"").toLowerCase(); 
	//SHORTN MUST NOT BE A LONE DIGIT PLZTHX. Don't use, say '1'.
	this.running=false;
	//this.buffer=null;
	this.serverNo=0;
	this.wasRunning=false;
	/* Do we need to update it? */
	
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

    addServer = function(s){
	    serverList[s.shortN] = s;
    }	

    function pageLoad(){
	resizeLayout();

	serverList = new Array(); 
	
	/*addServer(new Server("Counter-Strike: Source", "cs:s"));
	addServer(new Server("Call of Duty II", "cod2"));
	addServer(new Server("Minecraft", "mcraft"));
	addServer(new Server("Ventrillo", "vent"));
	addServer(new Server("test", "onetwo"));*/
        var xmlhttp, response; 
	if(window.XMLHttpRequest){
	    xmlhttp = new XMLHttpRequest();
	}
	else{
	    xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.open("GET",
	    "srvcommand.php?cmd=f", false);
	/*xmlhttp.onreadystatechange=function(){

	}*/
	xmlhttp.send();
	response = xmlhttp.responseText;
	//alert("ready? " + readyState);
	    //if(xmlhttp.readyState == 4){ 
		//alert("ready!");
	if(xmlhttp.status == 200){
	    var srvs; 
	    if(response.length > 3){
		srvs = response.substring(3).split("\n");
		srvs.pop();	
	    }
	    for(var i=0; i<srvs.length; i+=2){
		addServer(new Server(srvs[i+1], srvs[i]));
	    }
	    //alert(srvs.toString());	    
	}
	else if(xmlhttp.status == 503){
	    alert("Backend was not found running.");
	}
	else{
	    alert("For some reason we couldn't get in touch with PHP.");
	}
	//We should direct users to some sort of page that is less broken here
	    //}
	//loadCfg(serverList[0]); 
	//loadCfg(serverList[1]);
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

    //config files should definitely be JSON instead of this crap, or at least parsed into JSON
    //by the backend.
    /*function loadCfg(server){
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
    }*/

    function appendNewLines(){
        setTimeout("appendNewLines()", 1000);
	doAppendNewLines();
    }

    function doAppendNewLines(){
	var o, sp0, s, gss;

	s = document.getElementById('serverStatus');

	var xmlhttp, response; 
	if(window.XMLHttpRequest){
	    xmlhttp=new XMLHttpRequest();
	}
	else{
	    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.open("GET","srvcommand.php?cmd=p", false);
	xmlhttp.send();                                                                     

        response=xmlhttp.responseText;

	//completely empty response:
	// p@
	//lineless response would be:
	// p@css 0 mcraft 0
	// normal response would be:
	// p@css 5 mcraft 1 vent 0


	//Get portion of string after p@
	var endl = response.indexOf('\n');
	//alert(response);
	//alert("endl: "+ endl);
	//alert(response.substring(2, endl));
	var runningSrvs, runningSrvsOrd; //We have two copies of the same data!
	runningSrvs = new Object();
	//alert(response.length); 
	if(endl != -1 && response.length > 2){ // 2?
	    runningSrvsOrd = response.substring(2, endl).split(" ");	    
	}
	else{
	    //if it is empty stop running servers and then return
	    stopAllRunning();
	    return;
	}
	
	//alert(runningSrvs);
	//otherwise for each word, get next word as number of lines, append that number of lines.

	for (var i = 0; i<runningSrvsOrd.length; i+= 2){
	    //converts an array of string pairs to an array of strings indexed by shortN's.
	    runningSrvs[runningSrvsOrd[i]] = true;
	    //alert(runningSrvs[i]);
	}
	//alert(runningSrvs.toString());

        /*for(var rs in runningSrvs){
	    alert("runningSrvs["+rs+"] = " + runningSrvs[rs]);
	}*/ 

	linestart = endl + 1;
	response = response.substring(linestart);
	//alert(response.substring(0, 20));
	//we should now have only lines of server reponse data and info on whose they are.

	checkIfStartStop(runningSrvs);

        //runningSrvs.sort(function(sa, sb){if(sa.i < sb.i)return -1; else return 1;});
               /*
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
		*/ 

	for(var i = 0; i<runningSrvsOrd.length; i+=2){ //for each running server
	    var o, server;
	    if(runningSrvsOrd[i+1] == 0){
		continue;
	    } 
	    linestart = 0;
	    //alert(runningSrvsOrd.toString());//.length + ", " + runningSrvsOrd[i]);
	    server = serverList[runningSrvsOrd[i]]; //Is this ok? It SHOULD be.
	    o = document.getElementById(server.shortN + 'Output');
	    //alert(
	    for(var j=0; j<runningSrvsOrd[i+1]; j++){ //for each line in that server
		//find the j'th newline
		var ind = response.indexOf('\n', linestart + 1);
		if(ind != -1){
		    linestart = ind + 6;
		}
		else break;
	    }
	    //alert(runningSrvsOrd[i+1]);
	    //linestart += 6; //Get the br that is on the next line.
	    //alert("ls: " + linestart + " rso[+1]: " + runningSrvsOrd[i+1]); 
	    //alert(response);
	    //alert(response.substring(0,linestart));
	    o.innerHTML += "<span>\n" + response.substring(0, linestart) +"</span>\n";
	    o.scrollTop = o.scrollHeight;
	    response = response.substring(linestart + 1);//Reponse is everything after what we just printed
	}    
    }

    function checkIfStartStop(runningSrvs){
 	for(serverN in serverList){
	    var server = serverList[serverN];            //Loop thru ALL servers
	    /*if(server.shortN == "onetwo"){
		alert(runningSrvs[server.shortN]);
	}*/     
	    if(runningSrvs[server.shortN] != undefined){ //If this server's name was part of the running list...
		//alert(server.shortN + "was in the running list...");
		if(server.wasRunning == false){
		    //alert(server.shortN + " is now running"); 
		    server.running = true;			 //This should be the only looped code checking
		    setTabRunning(server);  	 //if a server is or is not really running	
		    server.wasRunning = true;
		}
	    }	    
	    else{
		/*if(server.shortN == "onetwo"){
		    alert(server.wasRunning);
	    }*/
		if(server.wasRunning == true){
		    //alert(server.shortN + " was stopped.");
		    server.running = false;
		    setTabRunning(server);
		    server.wasRunning = false;
		}	    
	    }
	}
    }

    function stopAllRunning(){
	for(serverN in serverList){
	    var server = serverList[serverN];
	    if(server.wasRunning == true){
		//alert(server.shortN + " was stopped.");
		server.running = false;
		setTabRunning(server);
		server.wasRunning = false;
	    }
	}
    }

    function sendCommand(){
	var command, cbox;
	//alert(activeServer);
	cbox = document.getElementById('serverCommand');
	command = cbox.value;
	cbox.value = "";
	//alert(activeServer);
	sendThisCommand(activeServer, command);
	return false;
    }

    function sendThisCommand(server, command){
	command = command.replace(" ", "%20");
  	/*alert("srvcommand.php?" +
	    "cmd=c&game=" + server.shortN +
	    "&gcmd=" + command);*/
	var xmlhttp, response; 
	if(window.XMLHttpRequest){
	    xmlhttp=new XMLHttpRequest();
	}
	else{
	    xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.open("GET", "srvcommand.php?" +
	    "cmd=c&game=" + server.shortN +
	    "&gcmd=" + command
	    , false); 
	xmlhttp.send();

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

    function modServer(startStr, server){
	var start, server, cmd;
	if(startStr == "start"){
	    //Don't start a server which is already started, etc.
	    start = true;
	    if(server.running == true){
		return;
	    }
	    server.running = true;
	    cmd = "s";
	}
	else if(startStr == "stop"){
	    start = false;
	    if(server.running == false){
		return;
	    }
	    cmd = "o";
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
	
	var url =  "srvcommand.php?cmd=" + cmd + "&game=" + server.shortN;	
	//alert(url);
	xmlhttp.open("GET", url, false);
	xmlhttp.send();
	//alert(xmlhttp.responseText);
	
	//appendNewLines(serverList[serverNo], start);

 	//alert("Starting server! \n Server says: " + xmlhttp.responseText);	
    }   

    function populateTabs(){
	var stabs = document.getElementById('serverTabs');
	stabs.innerHTML="";
	var count = 0;

	for(var i in serverList){
	    var server = serverList[i];
	    var newDiv = document.createElement('div');
	    newDiv.setAttribute('class', 'tab');
	    newDiv.setAttribute('onClick',
		    'changeActiveServer("' + i + '")');

	    var newTab = document.createElement('div');
	    newDiv.appendChild(newTab);

	    newTab = document.createElement('div');
	    newTab.innerHTML= serverList[i].tabName;
	    newDiv.firstChild.appendChild(newTab);

	    stabs.appendChild(newDiv);

	    var op = document.getElementById('serverOutput');
	    newDiv = document.createElement('div');
	    newDiv.setAttribute('id', i + 'Output');
	    newDiv.setAttribute('style', 'z-index: 0;');
	    
	    server.serverNo = count;
            op.appendChild(newDiv);

            var ss = document.getElementById('serverStatus');
	    newDiv = document.createElement('div');
	    newDiv.setAttribute('id', i + 'Status');
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

	    /*if(server.maplist != null){
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
	    }*/
            count++;
	}
	
	var xmlhttp; 
	if(window.XMLHttpRequest){
	    xmlhttp = new XMLHttpRequest();
	}
	else{
	    xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.open("GET",
	    "srvcommand.php?cmd=l", true);
	xmlhttp.onreadystatechange=function(){
	    if(xmlhttp.readyState == 4){
		if(xmlhttp.status == 200){
		    var response = xmlhttp.responseText;
		    if(response.length > 2){
			var runningSrvs = response.substring(2).split();

			while(runningSrvs[0] != undefined){
			    //converts an array of string pairs to an array of strings indexed by shortN's.
			    runningSrvs[runningSrvs[0]] = true;
			    runningSrvs.shift();
			}
			//alert(runningSrvs);
			var server;
			for(i in serverList){
			    server = serverList[i];
			    if(runningSrvs[i] != undefined){
				server.running = true;
				//setTabRunning(server);
			    }
			    /*else{
				setTabRunning(server);
			    }*/
			    setTabRunning(server);
			}    
		    }
		    else{
			for(i in serverList){
			    setTabRunning(serverList[i]);
			}
		    }	
		}
		else if(xmlhttp.status == 503){
		    //no line server is running
		    //alert("lineserver not running.");
		    for(i in serverList){
			//serverList[i].running = false; (should be set by default!)
			setTabRunning(serverList[i]);
		    }
		}
	    }
	}
	xmlhttp.send();
	
	var count = false;
	for(i in serverList){
	    if(serverList[i].serverNo == 0){ //Grab the first index out of the iterator.
		changeActiveServer(serverList[i]);
		break;
	    }
	} 
    }

    function changeActiveServer(server){
	//tabNumber = server.serverNo;
	//we gotta take a string or a server and get a server either way.
	if(server.split){
	    server = serverList[server];
	}
	var oldActive = activeServer;
	activeServer = server;
	setTabRunning(activeServer);
	if(oldActive){
	    setTabRunning(oldActive);

	    var ssold = document.getElementById(oldActive.shortN + 'Status');
	    ssold.setAttribute('style', 'z-index: 0;');
	    var soold = document.getElementById(oldActive.shortN + 'Output');
	    //soold.style.zIndex = '0';
	    soold.setAttribute('style', 'z-index: 0;'); 
	}
	var ssnew = document.getElementById(activeServer.shortN + 'Status');
	ssnew.setAttribute('style', 'z-index: 10;');
	var sonew = document.getElementById(activeServer.shortN + 'Output');
	//alert('server' + tabNumber + 'Output');
	//soold.style.zIndex = '10';
	sonew.setAttribute('style', 'z-index: 10;');
    }

    function setTabRunning(server){
	//alert(server);
	//alert(server.serverNo);
	var tab = server.serverNo;
	var clas, tabref;
	clas = "tab clear tab";
	if(server.running) clas += "running";
	else clas += "stopped";
	if(server == activeServer){
	    clas  += "active";
	    setBorderColors(server.running);
	}
	else clas += "inactive";

	//alert("Server: " + server);
	//alert(server.serverNo);
	tabref = document.getElementById('serverTabs').childNodes[tab];
	//alert(tabref);
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
			<!--<input type="button" value=" Restart " class="daemonbutton">-->
			<input type="button" value=" Print " class="daemonbutton"
				    onclick='doAppendNewLines()'>
		    </div>
		</div>
	    </div>
	    <div style="clear:both"></div>
	</div>
	<div class="pagebottom">
	    <div class="inputbox">
		<form method="post" action="javascript:" onSubmit="return sendCommand()">
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
