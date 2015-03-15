<?php 

	$hostname = "localhost"; //192.168.1.2
	$username = "";
	$dbname = "lodsight2";
	$passwd = "";
	
	$showFromDate = "2014-02-03";
	
	$visualizerLocation = "http://lod2-dev.vse.cz/lodsight/index.html"; 
	//"http://localhost/lodsight%20visualizer/index.html"; 
	//"file:///C:/Users/user/Dropbox/LODSight/summaryApp/lodsight%20visualizer/index.html";
	
	$skipEntities = [
			["http://www.w3.org/1999/02/22-rdf-syntax-ns#", "type"]
	];
	
	$pathCountLimit = 200;
	$limitedDetailPercentage = 50;

?>