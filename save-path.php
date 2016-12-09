<?php 
	$purom_filename = uniqid().".jsonld";
	$file_path = "paths/".$purom_filename;
	$rdfInput = file_get_contents('php://input');
	$jsonObject = json_decode($rdfInput);
	if($jsonObject != null) {
		$puredJson = json_encode($jsonObject);
		file_put_contents($file_path,$puredJson);
		$loadedJson = file_get_contents($file_path);
		$loadedJsonObject = json_decode($loadedJson);
		if($loadedJsonObject === null) unlink($filename);
	}
?>