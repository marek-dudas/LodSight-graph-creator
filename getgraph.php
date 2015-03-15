<?php
	include 'settings.php';	

	class Entity {
		public $id;
		public $name;
		public $prefix;
		public $fromCSet;
		public $prefixcc;
		
		function __construct($id, $name, $prefix, $fromCSet = false) {
			$this->id = $id;
			$this->name = $name;
			$this->prefix = $prefix;
			$this->fromCSet = $fromCSet;
		}
		
		function setPrefixcc($prefixcc) {
			$this->prefixcc = $prefixcc;
		}
		
	}
	
	class EntityStore {
		public $graph;
		public $namespaces;
		public $prefixes;
		private $prefixCount;
		
		public function getIndex($entity) {
			$ent_index = -1;
			foreach ($this->graph->entities as $index=>$store_entity) {
				if($store_entity->id == $entity->id) {
					$ent_index = $index;
				}
			}
			if($ent_index<0) {
				$entity->setPrefixcc($this->resolvePrefix($entity->prefix));				
				$this->graph->entities[] = $entity;
				$ent_index = key($this->graph->entities);
			}
			return $ent_index;
		}		

		function resolvePrefix($namespaceUri) {
			$prefixIndex = array_search($namespaceUri, $this->namespaces);
			if(!($prefixIndex === false)) return $this->prefixes[$prefixIndex];
			
			$url = "http://prefix.cc/reverse?uri=".urlencode($namespaceUri)."&format=json";
			
			/*
			$req = new \http\Client\Request('GET', $url);
			$req->setOptions(["timeout"=>10, "redirect" => 10]);
			$client = (new \http\Client())
				->enqueue($req)
				->send();
			$resp = $client->getResponse();
			$code = $resp->getResponseCode();
			$content = $resp->getBody();*/
			
			$ch = curl_init();			
			// set url
			curl_setopt($ch, CURLOPT_URL, $url);			
			//return the transfer as a string
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			// $output contains the output string
			$content = curl_exec($ch);			
			// close curl resource to free up system resources
			curl_close($ch);
			
			$ccprefixes = json_decode($content);
			if($ccprefixes!=null) {
				foreach($ccprefixes as $prefix => $namespace) {
					$this->prefixes[] = $prefix;
					$this->namespaces[] = $namespaceUri;
					return $prefix;
				}
			}
			else {
				$this->prefixCount++;
				$autoPrefix = "ns$this->prefixCount";
				$this->prefixes[] = $autoPrefix;
				$this->namespaces[] = $namespaceUri;
				return $autoPrefix;
			}
		}
		
		function getPrefixMappings() {
			$mappings = array();
			for($i=0; $i<count($this->prefixes); $i++) {
				$mappings[] = $this->prefixes[$i].": ".$this->namespaces[$i];
			}
			return $mappings;
		}
		
		function __construct($graph) {
			$this->namespaces = array();
			$this->prefixes = array();
			$this->prefixCount = 0;
			$this->graph = $graph;
		}
	}
	
	class Link {
		public $start;
		public $end;
		public $label;
		public $fromCSet;
		public $frequency;
		
		function __construct($ent_store, $start, $label, $end, $fromCSet = false, $frequency = 1) {
			$this->start = $ent_store->getIndex($start);
			$this->end = $ent_store->getIndex($end);
			
			$label->setPrefixcc($ent_store->resolvePrefix($label->prefix));
			
			$this->label = $label;
			$this->fromCSet = $fromCSet;
			$this->frequency = $frequency;
		}
		
		function equals($ent_store, $start, $label, $end) {
			if($start->id == $ent_store->graph->entities[$this->start]->id 
					&& $label->id == $this->label->id 
					&& $end->id == $ent_store->graph->entities[$this->end]->id )
				return true;
			else return false;
		}
	}
	
	class Graph {
		public $entities;
		public $links;
		public $ent_store;
		private $skipEntities;
		
		function __construct($skipEntities) {
			$this->entities = array();
			$this->ent_store = new EntityStore($this);
			$this->links = array();		
			$this->skipEntities = $skipEntities;	
		}
		
		function addLink($start, $label, $end, $csetlink = false, $frequency = 1) {
			$existing = false;
			foreach($this->skipEntities as $entity) {
				if($label->name == $entity[1] && $label->prefix == $entity[0]) $existing = true;
			}
			if(! $existing) {
				foreach($this->links as $link) {
					if($link->equals($this->ent_store, $start, $label, $end)) $existing = true;
				}
			}
			if($csetlink) {
				$startsInPath = false;
				foreach($this->entities as $entity) {
					if(!$entity->fromCSet && $entity->id == $start->id) $startsInPath = true;
				}
				if(!$startsInPath) $existing = true;
			}
			if(!$existing) $this->links[] = new Link($this->ent_store, $start, $label, $end, $csetlink, $frequency);
		}
	}
	
	$sumid = $_REQUEST["sumid"];
	if(!is_numeric($sumid)) die("sumid param has to be a number");
	$minFreq = $_REQUEST["minfreq"];
	if(!is_numeric($minFreq)) die("minFreq param has to be a number");
	
	$conn = mysqli_connect($hostname, $username, $passwd, $dbname);
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	
	$sql = "SELECT Subject_EntityID, Subj.EntityName AS SubjEntityName, SubjPref.URI AS SubjPrefURI, 
				Predicate_EntityID,Pred.EntityName as PredEntityName, PredPref.URI as PredPrefURI, 
				Object_EntityID, Obj.EntityName AS ObjEntityName, ObjPref.URI AS ObjPrefURI,
				Frequency
				FROM Path JOIN PathTriplet USING(PathID) 
			JOIN Entity AS Subj ON PathTriplet.Subject_EntityID = Subj.EntityID 
			JOIN Prefix AS SubjPref ON Subj.PrefixID = SubjPref.PrefixID
			JOIN Entity AS Pred ON PathTriplet.Predicate_EntityID = Pred.EntityID 
			JOIN Prefix AS PredPref ON Pred.PrefixID = PredPref.PrefixID
			JOIN Entity AS Obj ON PathTriplet.Object_EntityID = Obj.EntityID 
			JOIN Prefix AS ObjPref ON Obj.PrefixID = ObjPref.PrefixID
			WHERE 
			Path.Frequency > $minFreq AND Path.SumID = '$sumid'";
	
	$result = $conn->query($sql);
	
	$graph = new Graph($skipEntities);
	
	$maxFrequency = 0;
	while($row = $result->fetch_assoc()) {
		$graph->addLink(
				new Entity($row['Subject_EntityID'], $row['SubjEntityName'], $row['SubjPrefURI']), 
				new Entity($row['Predicate_EntityID'], $row['PredEntityName'], $row['PredPrefURI']), 
				new Entity($row['Object_EntityID'], $row['ObjEntityName'], $row['ObjPrefURI']),
				false,
				$row['Frequency'] );
		if($row['Frequency'] > $maxFrequency) $maxFrequency = $row['Frequency'];
	}
		
	//adding characteristic sets results:
	
	$sql = "SELECT Subject_EntityID, Subj.EntityName AS SubjEntityName, SubjPref.URI AS SubjPrefURI,
	Predicate_EntityID,Pred.EntityName as PredEntityName, PredPref.URI as PredPrefURI,
	Object_EntityID, Obj.EntityName AS ObjEntityName, ObjPref.URI AS ObjPrefURI
	FROM CSet JOIN SetTriplet USING(SetID)
	JOIN Entity AS Subj ON SetTriplet.Subject_EntityID = Subj.EntityID
	JOIN Prefix AS SubjPref ON Subj.PrefixID = SubjPref.PrefixID
	JOIN Entity AS Pred ON SetTriplet.Predicate_EntityID = Pred.EntityID
	JOIN Prefix AS PredPref ON Pred.PrefixID = PredPref.PrefixID
	JOIN Entity AS Obj ON SetTriplet.Object_EntityID = Obj.EntityID
	JOIN Prefix AS ObjPref ON Obj.PrefixID = ObjPref.PrefixID
	WHERE
	CSet.SumID = '$sumid'";
	
	$result = $conn->query($sql);
	
	while($row = $result->fetch_assoc()) {
		$graph->addLink(
				new Entity($row['Subject_EntityID'], $row['SubjEntityName'], $row['SubjPrefURI'], true),
				new Entity($row['Predicate_EntityID'], $row['PredEntityName'], $row['PredPrefURI'], true),
				new Entity($row['Object_EntityID'], $row['ObjEntityName'], $row['ObjPrefURI'], true), 
				true);
	}
	
	
	$dataset = "";
	$endpoint = "";
	$sql= "SELECT Dataset, Endpoint
	FROM Summary WHERE SumID = '$sumid'";
	$result = $conn->query($sql);
	while($row = $result->fetch_assoc()) {
		$dataset = $row['Dataset'];
		$endpoint = $row['Endpoint'];
	}
	
	Class GraphResult {
		public $entities;
		public $links;
		public $prefixes;
		public $dataset;
		public $endpoint;
		public $maxFrequency;
		
		function __construct($ent, $links, $prefixes, $dataset, $endpoint) {
			$this->entities = $ent;
			$this->links = $links;
			$this->prefixes = $prefixes;
			$this->dataset = $dataset;
			$this->endpoint = $endpoint;
		}
		
		function setMaxFrequency($freq) {
			$this->maxFrequency = $freq;
		}
	}
	
	$graph_result = new GraphResult($graph->entities, $graph->links, $graph->ent_store->getPrefixMappings(), $dataset, $endpoint);
	$graph_result->setMaxFrequency($maxFrequency);
	
	echo json_encode($graph_result);
	
?>	
	