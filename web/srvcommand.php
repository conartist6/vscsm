<?php
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
    //Open lock file, get pid
    //send KILL SIGIO to pid
    //return all results
    if(isset($_GET['game'])){
	$command = $_GET['cmd'] . "&" . $_GET['game']. "\0"; 
    }
    else{
	$command = $_GET['cmd'] . "@\0";
    }
    if(file_exists("/mnt/memory/vscsm_lineserver.lock")){
	//echo $pipepath . "<br />";
	$serversock = fopen("/usr/local/srcds_l/orangebox/fifo", "wb");

	fwrite_stream($serversock, $command);
	//echo "<br />";
	fclose($serversock);       
    }

    //get output from server
    $serversock = fopen("/usr/local/srcds_l/orangebox/fifo", "rb");
    
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
?>
