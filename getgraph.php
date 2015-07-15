<?php
	include 'settings.php';	
	
	class NS {
		public $str;
		public $id;
		public $selected;
		
		function __construct($s, $id, $selected) {
			$this->str = $s;
			$this->id = $id;
			$this->selected = $selected;
		}
	}

	class Entity {
		public $id;
		public $name;
		public $prefix;
		public $fromCSet;
		public $prefixcc;
		public $selected;
		
		function __construct($id, $name, $prefix, $fromCSet = false, $selected = false) {
			$this->id = $id;
			$this->name = $name;
			$this->prefix = $prefix;
			$this->fromCSet = $fromCSet;
			$this->selected = $selected;
		}
		
		function setPrefixcc($prefixcc) {
			$this->prefixcc = $prefixcc;
		}
		
	}
	
	class EntityStore {
		public $graph;
		public $namespaces;
		public $nsids;
		public $prefixes;
		private $prefixCount;
		
		public function getIndex($entity) {
			$ent_index = $this->getExistingEntityIndex($entity);
			if($ent_index<0) {
				/*$entity->setPrefixcc($this->resolvePrefix($entity->prefix));				
				$this->graph->entities[] = $entity;
				$ent_index = key($this->graph->entities);*/
				$ent_index = $this->addEntity($entity);
			}
			return $ent_index;
		}
		
		public function getExistingEntityIndex($entity) {
			foreach ($this->graph->entities as $index=>$store_entity) {
				if($store_entity->id == $entity->id) {
					return $index;
				}
			}
			return -1;
		}
		
		private function addEntity($entity) {
			$entity->setPrefixcc($this->resolvePrefix($entity->prefix));
			$this->graph->entities[] = $entity;
			return key($this->graph->entities);
		}
		
		public function addPredicate($predicate) {
			//$predExists = false;
			//foreach ($this->graph->predicates as $existingPred) if ($existingPred->id == $predicate->id) $predExists = true;
			//if(! $predExists) {
				$predicate->setPrefixcc($this->resolvePrefix($predicate->prefix));
				$this->graph->predicates[] = $predicate;
			//}
		}

		function resolvePrefix($namespaceUri, $id = null) {
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
					$this->nsids[] = $id;
					return $prefix;
				}
			}
			else {
				$this->prefixCount++;
				$autoPrefix = "ns$this->prefixCount";
				$this->prefixes[] = $autoPrefix;
				$this->namespaces[] = $namespaceUri;
				$this->nsids[] = $id;
				return $autoPrefix;
			}
		}
		
		function getPrefixMappings($selectedPrefixes) {
			$mappings = array();
			for($i=0; $i<count($this->prefixes); $i++) {
				//$mappings[] = $this->prefixes[$i].": ".$this->namespaces[$i];
				$selected = false;
				if($selectedPrefixes!=null) $selected = in_array($this->nsids[$i], $selectedPrefixes);
				$mappings[] = new NS($this->prefixes[$i].": ".$this->namespaces[$i], $this->nsids[$i], $selected);
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
		public $predicates;
		public $links;
		public $ent_store;
		private $skipEntities;
		
		function __construct($skipEntities) {
			$this->entities = array();
			$this->ent_store = new EntityStore($this);
			$this->links = array();		
			$this->skipEntities = $skipEntities;	
			$this->predicates = array();
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
		
		public function copyNeighborLinksTo($targetGraph) {
			$linksToAdd = array();
			foreach($this->links as $sourceLink) {
				$linkStartEnd = [$this->entities[$sourceLink->start], $this->entities[$sourceLink->end]];
				foreach($linkStartEnd as $sourceLinkEnt) {
					if($targetGraph->ent_store->getExistingEntityIndex($sourceLinkEnt)>=0) {
						$linksToAdd[] = $sourceLink;
						break;
					}
				}
			}
			foreach($linksToAdd as $linkToAdd)
						$targetGraph->addLink($this->entities[$linkToAdd->start], $linkToAdd->label, $this->entities[$linkToAdd->end]);				
		}
	}
	
	function sumIdsSqlComparison($sumids, $colName) {
		$sqlComp = "" ;
		$firstId = true;
		foreach($sumids as $sumid) {
			if($firstId) {
				$sqlComp .= "$colName = '$sumid'";
				$firstId = false;
			}
			else $sqlComp .= " OR  $colName = '$sumid'";
		}
		return $sqlComp;
	}
	
	function nsIdsSqlComparison($nsids, $colNames) {
		$sqlComp = "" ;
		$firstId = true;
		foreach($nsids as $nsid) {
			foreach($colNames as $colName)
			if($firstId) {
				$sqlComp .= "$colName = '$nsid'";
				$firstId = false;
			}
			else $sqlComp .= " OR  $colName = '$nsid'";
		}
		return $sqlComp;
	}
	
	set_time_limit($time_limit);
	
	$sumids = $_REQUEST["sumid"];
	foreach($sumids as $sumid) if(!is_numeric($sumid)) die("sumid param has to be a number");
	$minFreq = $_REQUEST["minfreq"];
	if(!is_numeric($minFreq)) die("minFreq param has to be a number");
	$maxFreq = PHP_INT_MAX;
	if(isset($_REQUEST["maxfreq"])) {
		$maxFreq = $_REQUEST["maxfreq"];
		if(!is_numeric($maxFreq)) die("maxFreq param has to be a number");
	}
	$sel_predicates = null;
	if(isset($_REQUEST["p"])) $sel_predicates = $_REQUEST["p"];
	$sel_namespaces = null;
	if(isset($_REQUEST["ns"])) $sel_namespaces = $_REQUEST["ns"];
	$neighborhoodSize = 1;
	
	$conn = mysqli_connect($hostname, $username, $passwd, $dbname);
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	

	$graph = new Graph($skipEntities);
	$complete_graph = new Graph($skipEntities);
	
	$getNamespacesSql = "SELECT DISTINCT SubjPref.URI AS SubjPrefURI, Subj.PrefixID AS SPrefId,
				PredPref.URI as PredPrefURI, Pred.PrefixID AS PPredId,
				ObjPref.URI AS ObjPrefURI, Obj.PrefixID AS OPredId
				FROM Path JOIN PathTriplet USING(PathID) 
			JOIN Entity AS Subj ON PathTriplet.Subject_EntityID = Subj.EntityID 
			JOIN Prefix AS SubjPref ON Subj.PrefixID = SubjPref.PrefixID
			JOIN Entity AS Pred ON PathTriplet.Predicate_EntityID = Pred.EntityID 
			JOIN Prefix AS PredPref ON Pred.PrefixID = PredPref.PrefixID
			JOIN Entity AS Obj ON PathTriplet.Object_EntityID = Obj.EntityID 
			JOIN Prefix AS ObjPref ON Obj.PrefixID = ObjPref.PrefixID
			WHERE 
			( ";
		$getNamespacesSql .= sumIdsSqlComparison($sumids, "Path.SumID");
		$getNamespacesSql .= " )";	
		$result = $conn->query($getNamespacesSql);
		
	while($row = $result->fetch_assoc()) {
		$namespaces = [$row['SubjPrefURI'], $row['PredPrefURI'], $row['ObjPrefURI']];
		$ids = [$row['SPrefId'], $row['PPredId'], $row['OPredId']];
		for($i=0;$i<3; $i++) $graph->ent_store->resolvePrefix($namespaces[$i], $ids[$i]);
	}
	
	$getPredicatesSql = "SELECT DISTINCT
	Predicate_EntityID,Pred.EntityName as PredEntityName, PredPref.URI as PredPrefURI
	FROM Path JOIN PathTriplet USING(PathID)
	JOIN Entity AS Pred ON PathTriplet.Predicate_EntityID = Pred.EntityID
	JOIN Prefix AS PredPref ON Pred.PrefixID = PredPref.PrefixID
	WHERE
	( ";
	$getPredicatesSql .= sumIdsSqlComparison($sumids, "Path.SumID");
	$getPredicatesSql .= " )";
	$result = $conn->query($getPredicatesSql);
	
	while($row = $result->fetch_assoc()) {
		$predicate = new Entity($row['Predicate_EntityID'], $row['PredEntityName'], $row['PredPrefURI']);
		$predicate->selected = false;
		if($sel_predicates != null) $predicate->selected = in_array($predicate->id, $sel_predicates);
		$graph->ent_store->addPredicate($predicate);
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
			Path.Frequency >= $minFreq AND ( ";
	$sql .= sumIdsSqlComparison($sumids, "Path.SumID");
	$sql .= " )";
	if($sel_namespaces!=null) {
		$sql.=" AND (";
		$sql.= nsIdsSqlComparison($sel_namespaces, ['Subj.PrefixID', 'Pred.PrefixID', 'Obj.PrefixID']);
		$sql.=")";
	}	
	$result = $conn->query($sql);
		
	$maxFrequency = 0;
	while($row = $result->fetch_assoc()) {
		if($row['Frequency'] < $maxFreq) {
			$subject = new Entity($row['Subject_EntityID'], $row['SubjEntityName'], $row['SubjPrefURI']);
			$predicate = new Entity($row['Predicate_EntityID'], $row['PredEntityName'], $row['PredPrefURI']);
			$object = new Entity($row['Object_EntityID'], $row['ObjEntityName'], $row['ObjPrefURI']);
			$shouldAddLink = true;
			if($sel_predicates != null) $shouldAddLink = in_array($predicate->id, $sel_predicates);
			if($shouldAddLink) $graph->addLink($subject, $predicate, $object, false, $row['Frequency'] );
			//if($sel_predicates != null) $complete_graph->addLink($subject, $predicate, $object, false, $row['Frequency'] );
		}				
		if($row['Frequency'] > $maxFrequency) $maxFrequency = $row['Frequency'];
	}
	//if($sel_predicates != null && $neighborhoodSize != null ) for($i=0; $i<$neighborhoodSize; $i++) $complete_graph->copyNeighborLinksTo($graph);
		
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
	WHERE ";
		//CSet.SumID = '$sumid'";
	$sql .= sumIdsSqlComparison($sumids, "CSet.SumID");
	
	$result = $conn->query($sql);
	
	while($row = $result->fetch_assoc()) {
		$graph->addLink(
				new Entity($row['Subject_EntityID'], $row['SubjEntityName'], $row['SubjPrefURI'], true),
				new Entity($row['Predicate_EntityID'], $row['PredEntityName'], $row['PredPrefURI'], true),
				new Entity($row['Object_EntityID'], $row['ObjEntityName'], $row['ObjPrefURI'], true), 
				true);
	}
	
	
	$dataset = "";
	$endpoint = "" ;
	$first = true;
	foreach($sumids as $sid){
		$sql= "SELECT Dataset, Endpoint
		FROM Summary WHERE SumID = '$sumids[0]'";
		$result = $conn->query($sql);
		while($row = $result->fetch_assoc()) {
			if(!$first) $dataset .= ", ";
			$dataset .= $row['Dataset'];
			$first = false;
			$endpoint = $row['Endpoint'];
		}
	}
	
	Class GraphResult {
		public $entities;
		public $links;
		public $prefixes;
		public $dataset;
		public $endpoint;
		public $predicates;
		public $maxFrequency;
		
		function __construct($ent, $links, $prefixes, $dataset, $endpoint, $predicates) {
			$this->entities = $ent;
			$this->links = $links;
			$this->prefixes = $prefixes;
			$this->dataset = $dataset;
			$this->endpoint = $endpoint;
			$this->predicates = $predicates;
		}
		
		function setMaxFrequency($freq) {
			$this->maxFrequency = $freq;
		}
	}
	
	$graph_result = new GraphResult($graph->entities, $graph->links, $graph->ent_store->getPrefixMappings($sel_namespaces), $dataset, $endpoint, $graph->predicates);
	$graph_result->setMaxFrequency($maxFrequency);
	
	echo json_encode($graph_result);
	
?>	
	