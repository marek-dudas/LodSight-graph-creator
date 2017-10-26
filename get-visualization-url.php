<?php 
	include 'settings.php';	
	
	class UrlResult {
		public $url;
		function __construct($url) {
			$this->url = $url;
			$this->valid = ($url!=null);
		}
	}

	$conn = mysqli_connect($hostname, $username, $passwd, $dbname);
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	
	$uri = $_REQUEST['uri'];
	
	if (filter_var($uri, FILTER_VALIDATE_URL) === FALSE) {
		die('Not a valid URL');
	}
	
	$sql = "SELECT DISTINCT SumID, PrefixID FROM prefix JOIN entity USING (PrefixID) ".
		   "JOIN pathtriplet ".
		   "ON entity.EntityID = pathtriplet.Subject_EntityID OR entity.EntityID = pathtriplet.Object_EntityID OR entity.EntityID = pathtriplet.Predicate_EntityID ".
		   "JOIN path USING (PathID) JOIN summary USING (SumID) WHERE prefix.URI LIKE '$uri%' AND DATE(summary.StartedAt) > '$showFromDate'".
		   "UNION ".
		   "SELECT DISTINCT SumID, PrefixID FROM prefix JOIN entity USING (PrefixID) ".
		   "JOIN setpredicate USING (EntityID) JOIN cset USING (SetID) JOIN summary USING (SumID)".
		   "WHERE prefix.URI LIKE '$uri%' AND DATE(summary.StartedAt) > '$showFromDate'";
	
	$result = $conn->query($sql);
	
	$sumIdString = "";
	$prefixId = null;
	while($row = $result->fetch_assoc()) {
		$sumIdString .= "sumid=".$row['SumID']."&";	
		if($prefixId==null) $prefixId = $row['PrefixID'];
	}	
	
	$urlObject = null;
	if($prefixId != null) $urlObject = new UrlResult("$visualizerLocation?".$sumIdString."minfreq=1&maxfreq=9223372036854775807&ns=$prefixId");
	else $urlObject = new UrlResult(null);
	
	echo json_encode($urlObject);
?>