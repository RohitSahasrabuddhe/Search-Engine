<?php

// Surrounds all the query terms in the snippet with strong tags
function process_terms($text, $query) {
  preg_match_all('~\w+~', $query, $m);
  if(!$m)
      return $text;
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
    $pos = stripos($description, $query);
    if($pos !== false) { 
      $snippetHTML = process_terms($description, $query);
      return $snippetHTML;
    }
  }

  // try finding a sentence with all the terms together
  for($s_i = 0; $s_i < count($sentences); $s_i++) {    
    $current_sentence = $sentences[$s_i];
    $pos = stripos($current_sentence, $query);
    if($pos !== false) {
      $current_sentence = shorten($current_sentence, $query);  
      $current_sentence = ucfirst($current_sentence);      
      $snippetHTML = process_terms($current_sentence, $query);
      return $snippetHTML;
    }
  }

  // Try finding all the terms separately
  $highlighted_lines = [];
  $query_terms = explode(" ", $query);

  $query_terms_matched = []; 
  for($q_i = 0; $q_i < count($query_terms); $q_i++) { $query_terms_matched[] = false; }

  for($q_i = 0; $q_i < count($query_terms); $q_i++) {

    if(!$query_terms_matched[$q_i]) {
      
      $term = $query_terms[$q_i];

      for($s_i = 0; $s_i < count($sentences); $s_i++) {
      
        $current_sentence = $sentences[$s_i];
        $pos = stripos($current_sentence, $term);

        if($pos !== false) {
          $terms_matched = [ $term ];
          $query_terms_matched[$q_i] = true;

          // Check for further query terms being matched in the same sentence
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

function shorten($text, $phrase, $radius = 80, $ending = "&hellip;") { 
  $phraseLen = strlen($phrase);
  $radius = $radius - $phraseLen; 
  if ($radius < $phraseLen) { 
      $radius = $phraseLen;
  }
  $pos = stripos($text, $phrase);
  $startPos = 0;
  if ($pos > $radius) {
      $startPos = $pos - $radius;   
      $newwordPos = strpos($text, " ", $startPos) + 1;
      if($newwordPos - $startPos < $radius) {
        $startPos = $newwordPos;
      }
  }
  $textLen = strlen($text);
  $endPos = $pos + $phraseLen + $radius;
  if ($endPos >= $textLen) {
      $endPos = $textLen;
  }
  $excerpt = substr($text, $startPos, $endPos - $startPos);
  if ($endPos != $textLen) {
      $excerpt = substr_replace($excerpt, $ending, -$phraseLen);
  }
  return $excerpt;
}

?>
