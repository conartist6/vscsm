<?php
    //How about if we make this a complete backend for server communication. We can
    //agnosticize the fronted to the mechanics of the backend by using PHP to output
    //JSON for the frontend. Figure out how much less compact JSON would be for large
    //amounts of stuff. Seems like it should be ok, esp w/ native JSON.
    //
    //
    function fwrite_stream($fp, $string) {
	for ($written = 0; $written < strlen($string); $written += $fwrite){
	    //echo strlen($string);
	    $fwrite = fwrite($fp, substr($string, $written));
	    if ($fwrite === false) {
		//echo "Error: " . posix_strerror(posix_get_last_error()); 
		return $written;
	    }
	}
	return $written;
    } 


    if(file_exists("/mnt/memory/vscsm_lineserver.lock")){
	if(isset($_GET['cmd'])){
	    switch($_GET['cmd']){
		case "s":
		case "o":
		    if(!isset($_GET['game'])){
			echo "invalid command";
			exit(2);
		    }
		    $command = $_GET['cmd'] . "&" . $_GET['game'] . "@\0";
		    break;
		case "p":
		case "l":
	 	    $command = $_GET['cmd'] . "@\0";
		    break;
		default:
		    echo "invalid command";
		    exit(2);
	    }
	}
	else{
	    echo "invalid command";
	    exit(2); //invalid command
	}

	//echo $pipepath . "<br />";
	$serversock = fopen("/tmp/vscsm_fifo", "wb");

	fwrite_stream($serversock, $command);
	//echo "<br />";
	fclose($serversock);       

	//get output from server
	$serversock = fopen("/tmp/vscsm_fifo", "rb");
	
	$r = Array($serversock); 
	while(($ss = stream_select($r, $w = null, $e = null, 0, 5000)) !=0){
	//While input would not block and is not on eof, get lines.
	    $chars = fread($serversock, 20);//)){
	    echo $chars;
	    if(feof($serversock)){
		break;
	    }
	}
	if ($ss === FALSE){
	    echo "grrrrr";
	}
	else{
	    //echo "no output!";
	}
	fclose($serversock);
    }
    else{
	//Return status 503?
	header("HTTP/1.1 503 Service Unavailable");
    }
?>
