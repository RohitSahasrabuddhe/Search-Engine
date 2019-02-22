<?php 

// Author: Rohit Sahasrabuddhe
// Date: 11/10/2018
// USC ID: 6377842822

// PHP client-server code for retrieving and displaying solr results
 

// make sure browsers see this page as utf-8 encoded HTML 
header('Content-Type: text/html; charset=utf-8');  

$limit = 10; 
$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false; 
$results = false;  

// Declaring base directory of our crawl data
$dir = "/home/rohit/HW4/latimes/latimes/";


// code for snippet genereation
function process_terms($text, $query) {
  preg_match_all('~\w+~', $query, $m);
  if(!$m){
      return $text;
  }
  $re = '~\\b(' . implode('|', $m[0]) . ')\\b~i';
  return preg_replace($re, '<b>$0</b>', $text);
}

// Given a filename, extracts its HTML and tries to prepare a snippet
function get_snippet($filename, $description, $query) {
  // Get the HTML from the files
  $html_text_unprocessed = file_get_contents($filename);
  
  // Using Html2Text.php developed by Jon Abernathy
  include_once "Html2Text.php";

  $html = new \Html2Text\Html2Text($html_text_unprocessed);
  $html_text_processed = $html->getText();
  $html_text_processed = preg_replace('/\[.*?\]/i', "", $html_text_processed); 
  $html_text_processed = preg_replace('/  /i', " ", $html_text_processed); 
  $html_text_processed = str_replace(" __", "", $html_text_processed);
  $sentences = preg_split('/(?<!\.\.\.)(?<!Dr\.)(?<=[.?!]|\.\)|\.")\s+(?=[a-zA-Z"\(])/i', $html_text_processed); 

  // try finding the query terms in the description
  if($description) {
    $position = stripos($description, $query);
    if($position !== false) {
      $snippetHTML = process_terms($description, $query);
      return $snippetHTML;
    }
  }

  for($s_i = 0; $s_i < count($sentences); $s_i++) {
    $current_sentence = $sentences[$s_i];
    $position = stripos($current_sentence, $query);
    if($position !== false) {
      $current_sentence = shorten($current_sentence, $query);
      $current_sentence = ucfirst($current_sentence);
      $snippetHTML = process_terms($current_sentence, $query);
      return $snippetHTML;
    }
  }

  $highlighted_lines = [];
  $query_terms = explode(" ", $query);
  $query_terms_matched = []; 
  for($q_i = 0; $q_i < count($query_terms); $q_i++) { 
	$query_terms_matched[] = false; 
  }
  for($q_i = 0; $q_i < count($query_terms); $q_i++) {
    if(!$query_terms_matched[$q_i]) {
      $term = $query_terms[$q_i];
      for($s_i = 0; $s_i < count($sentences); $s_i++) {
        $current_sentence = $sentences[$s_i];
        $position = stripos($current_sentence, $term);
        if($position !== false) {
          $terms_matched = [ $term ];
          $query_terms_matched[$q_i] = true;
          for($p_i = $q_i; $p_i < count($query_terms); $p_i++) {
            $otherTerm = $query_terms[$p_i];
            $otherPos = stripos($current_sentence, $otherTerm);
            if($otherPos !== false) {
              $terms_matched[] = $otherTerm;
              $query_terms_matched[$p_i] = true;
            }
          }
          $current_sentence = shorten($current_sentence, $term, (80*count($terms_matched)/count($query_terms)));
          $current_sentence = ucfirst($current_sentence);      
          $snippetHTML = process_terms($current_sentence, implode(" ", $terms_matched));
          $highlighted_lines[] = $snippetHTML;
          break;
        }
      }
    }
  }

  if(count($highlighted_lines) > 0) {
    return implode(" ",$highlighted_lines);
  } else {
    return false;
  }
}

function shorten($incomingText, $incomingPhrase, $r = 80, $e = "&hellip;") { 
  $phraseLength = strlen($incomingPhrase);
  $r = $r - $phraseLength; 
  if ($r < $phraseLength) { 
      $r = $phraseLength;
  }
  $position = stripos($incomingText, $incomingPhrase);
  $startPos = 0;
  if ($position > $r) {
      $startPos = $position - $r;
      $newwordPos = strpos($incomingText, " ", $startPos) + 1;
      if($newwordPos - $startPos < $r) {
        $startPos = $newwordPos;
      }
  }
  $textLen = strlen($incomingText);
  $endPos = $position + $phraseLength + $r;
  if ($endPos >= $textLen) {
      $endPos = $textLen;
  }
  $excerpt = substr($incomingText, $startPos, $endPos - $startPos);
  if ($endPos != $textLen) {
      $excerpt = substr_replace($excerpt, $e, -$phraseLength);
  }
  return $excerpt;
}


















if ($query) 
{ 
  // The Apache Solr Client library should be on the include path 
  // which is usually most easily accomplished by placing in the 
  // same directory as this script ( . or current directory is a default 
  // php include path entry in the php.ini) 
  require_once('Apache/Solr/Service.php'); 

  if (!isset($urlFileMap)) {
    // Parse the list of URLs into an associative array
    $urlFileMap = array();
    if (($urlToHTMLFile = fopen("/home/rohit/HW4/URLtoHTML_latimes.csv", "r")) !== FALSE) {
        while (($data = fgetcsv($urlToHTMLFile, 1000, ",")) !== FALSE ) {  
            $fileName = $dir.$data[0];
            $url = $data[1];  
            $urlFileMap[$fileName] = $url;
        }
        fclose($urlToHTMLFile);
    }
  }

  // create a new solr service instance - host, port, and corename 
  // path (all defaults in this example) 
  $solr = new Apache_Solr_Service('localhost', 8983, '/solr/hw4/'); 

 

  // if magic quotes is enabled then stripslashes will be needed 
  if (get_magic_quotes_gpc() == 1) 
  { 
    $query = stripslashes($query); 
  } 



  // automatically perform spell-checking by using SpellCorrector.php developed by Felipe Ribeiro. Copyright (C) 2008

  $originalQuery = $query;

  if(isset($_REQUEST["forcespelling"]) && $_REQUEST["forcespelling"]) {
    // ignore spellcheck
  }
  else {
  //calling spellcorrector service to get the correct spelling of the query term
  include_once "SpellCorrector.php";
  $keywordsGenerated = explode(" ", $query);
  $wordCount = count($keywordsGenerated);

  // Add output of spelling checker
  $s = "";
  for($i = 0; $i < $wordCount; $i++) {
    $s = $s.SpellCorrector::correct($keywordsGenerated[$i]);

    if($i < ($wordCount-1)) {
      $s = $s." ";
    }
  }
  // Get the correct query after the spelling correction
  $query = $s;

  }




  // in production code you'll always want to use a try /catch for any 
  // possible exceptions emitted  by searching (i.e. connection 
  // problems or a query parsing error) 
  try 
  { 
    // Setting algorithm type obtained from submit action on the form
    // If it depends on user selection i.e. lucene or PageRank 
    if(isset($_REQUEST['algorithm'])) {
      $additionalParameters = array( 
        'sort' => $_REQUEST['algorithm'], 
      ); 
      $results = $solr->search($query, 0, $limit, $additionalParameters); 
    }       
    else {
      $results = $solr->search($query, 0, $limit); 
    }        
  } 
  catch (Exception $e) 
  { 
    // in production you'd probably log or email this error to an admin 
    // and then show a special message to the user but for this example 
    // we're going to show the full exception 
    die("<html><head><title>Someting bad happened :(</title><body><pre>{$e->__toString()}</pre></body></html>"); 
  } 
} 

?> 
<html> 
<head> 
  <title>CSCI572 HW4</title> 
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

  <!-- devBridge jQuery-Autocomplete -->
  <script src="lib/jquery.autocomplete.js"></script>
  <link rel="stylesheet" href="lib/jquery.autocomplete.css">

  <!-- Additional styles -->
  <link href="https://fonts.googleapis.com/css?family=Roboto|Capriola" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head> 
<style>
	input[type=text], select {
		width: 100%;
		padding: 12px 20px;
		margin: 8px 0;
		display: inline-block;
		border: 1px solid #ccc;
		border-radius: 4px;
		box-sizing: border-box;
	}

	input[type=submit] {
		width: 100%;
		background-color: #4CAF50;
		color: white;
		padding: 14px 20px;
		margin: 8px 0;
		border: none;
		border-radius: 4px;
		cursor: pointer;
	}

	input[type=submit]:hover {
		background-color: #45a049;
	}

	div {
		border-radius: 5px;
		background-color: #f2f2f2;
		padding: 20px;
	}
</style>
<body> 
  <div class="container">
		<div class="text-center mt-5 mb-3">
		  <h1>
			CSCI572 Search Engine
		  </h1>
		</div>

		<div class="row">
			<form  class="searchform" accept-charset="utf-8" method="get">          
				<div class="col-sm-12">
					<input id="autocomplete" class="form-control mb-3" id="q" name="q" type="text" placeholder="Enter search term here" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>"/>
				</div>

				<div class="col-sm-3"></div>
					<div class="col-sm-3">
						<select class="form-control" name="algorithm" id="algorithm">
							<option value="score desc">Lucene</option>
							<option value="PageRankFile desc" <?php if(isset($_REQUEST['algorithm']) && $_REQUEST['algorithm']=="PageRankFile desc") { echo "selected"; } ?> >PageRank</option>
						</select>
					</div>

					<div class="col-sm-3">
						<input type="submit"/> 
					</div> 
			
				<div class="col-sm-3"></div>          
			</form> 
		</div>

		

		  <?php 
		  // display results 
		  $array = array(10);

		  if ($results) 
		  { 
				$total = (int) $results->response->numFound; 
				$start = min(1, $total); 
				$end = min($limit, $total); 
		  ?> 
		  <?php
				if($query != $originalQuery) {
					$url = "./";
					$params = array('q' => $originalQuery, 'forcespelling' => true);
					if(isset($_REQUEST['algorithm'])) {
						$params["algorithm"] = $_REQUEST['algorithm'];
					}
					$forceUrl = $url . "?" . http_build_query($params);

      	  ?>
      		<div>Showing results for <strong class="text-primary"><?php echo $query ?></strong>
      		<br />Search instead for <a href="<?php echo $forceUrl ?>"><span class="text-primary"><?php echo $originalQuery ?></span></a></div>
		
		  <?php
				}
    	  ?>
			<hr size="5" color="black">
			<div>Showing Results <?php echo $start; ?> - <?php print $end;?> of <?php print $total; ?></div> 
			<table> 
		  <?php 
				// iterate result documents 
				$i = 0;
				foreach ($results->response->docs as $doc) 
				{ 
		  ?> 
		  <tr><td>


		  <?php
					if($doc->og_url != "http://www.latimes.com") {
						$url = $doc->og_url;
						if(is_array($url)){
							$url = $url[0];
						} 
					}
					else {
						$url = $urlFileMap[$doc->id];
						if(is_array($url)){
							$url = $url[0];
						}
					}			
					if(is_array($doc->title)){
						$doc->title = $doc->title[0];
					}
					$array[$i] = $url;
		  ?>
		  
		  <br /> 
		  <br /><span style="color:blue"><strong>Title: </strong><a href="<?php print( $url )?>" target="_blank"><?php print( $doc->title) ?></a></span>          
      		  <br /><strong>URL:</strong><a href="<?php print( $url) ?>" target="_blank" class="text-success"><?php print( $url) ?></a></span>
		  
		  <?php 
					//include_once "snippet.php";
					$snippetHTML = get_snippet($doc->id, $doc->og_description, $query);
					// Don't display snippet if it fails
					if($snippetHTML !== false) { 
		  ?> 
          <br /> 
		  <strong>Snippet:</strong>
          <?php 
						echo $snippetHTML ;
		 			}
          ?>
		  
		  <?php 
					if($doc->og_description) { 
		  ?> 
		  
		  <br /><span style="color:brown"><strong>Description: </strong><?php print $doc->og_description ?></span>        
				 
		  <?php 
					} 
		  ?> 
		  
		  <br /><span style="color:red"><strong>ID: </strong><?php print $doc->id ?> </span>
		  </td></tr>


          
		  
		  <?php 
					$i = $i + 1;
					} 
		  ?> 
			
		  </table> 
		  <hr size="5" color="black">
		  
		  
		  <?php 
					foreach ($array as $ar){
						//echo $ar . '<br/>';
					}
				} 
		  ?> 
  
  </div>
  <script src="js/auto_suggest_script.js"></script>
</body> 
</html> 

