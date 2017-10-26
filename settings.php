<?php 

	//dbsettings
	$hostname = "localhost"; //192.168.1.2
	$username = "root";
	$dbname = "lodsight2";
	$passwd = ""; //	

	//url of lodsight frontend app
	$visualizerLocation = "http://localhost/lodsight/index.html"; //"http://localhost/lodsight/index.html";
	
	$showFromDate = "2017-01-03";
	
	//"http://localhost/lodsight%20visualizer/index.html
  //"http://lod2-dev.vse.cz/lodsight/index.html"; 
	//"file:///C:/Users/user/Dropbox/LODSight/summaryApp/lodsight%20visualizer/index.html";
	
	$skipEntities = [
			["http://www.w3.org/1999/02/22-rdf-syntax-ns#", "type"]
	];
	
	$pathCountLimit = 200;
	$limitedDetailPercentage = 50;
	
	$time_limit = 300;

?>