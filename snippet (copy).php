<?php

// Surrounds all the query terms in the snippet with strong tags
function highlight_query_terms($text, $query) {
  preg_match_all('~\w+~', $query, $m);
  if(!$m)
      return $text;
  $re = '~\\b(' . implode('|', $m[0]) . ')\\b~i';
  return preg_replace($re, '<b>$0</b>', $text);
}

// Given a filename, extracts its HTML and tries to prepare a snippet
function generate_snippets($filename, $description, $query) {

  // Get the HTML from the files
  $html_text_raw = file_get_contents($filename);
  
  include_once "Html2Text.php";
  $html = new \Html2Text\Html2Text($html_text_raw);
  $html_text = $html->getText();

  // $dom = new DOMDocument;
  // @$dom->loadHTMLFile($filename);
  // $articles = $dom->getElementsByTagName('article');
  // echo "<br>";
  // var_dump($articles);
  // echo "<br>";

  $html_text = preg_replace('/\[.*?\]/i', "", $html_text); // Remove tags surrounded by square brackets
  $html_text = preg_replace('/  /i', " ", $html_text); // Replace double-spaces by single spaces
  // $html_text = str_replace(" advertisement", "", $html_text);
  $html_text = str_replace(" __", "", $html_text);
  // $html_text = str_replace(" Feedback", "", $html_text);
  // $html_text = str_replace("News works best with JavaScript turned on", "", $html_text);
  $sentences = preg_split('/(?<!\.\.\.)(?<!Dr\.)(?<=[.?!]|\.\)|\.")\s+(?=[a-zA-Z"\(])/i', $html_text); // Split block of text into sentences
  // var_dump($sentences);

  // try finding the query terms in the description
  if($description) {
    
    $pos = stripos($description, $query);

    if($pos !== false) { 
      $snippetHTML = highlight_query_terms($description, $query);
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
      $snippetHTML = highlight_query_terms($current_sentence, $query);
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
          $snippetHTML = highlight_query_terms($current_sentence, implode(" ", $terms_matched));
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

// Truncates a large passage with surrounding ellipses
function shorten($text, $phrase, $radius = 80, $ending = "&hellip;") { 
  $phraseLen = strlen($phrase);
  $radius = $radius - $phraseLen; // To allow the whole thing to come to approx 160 chars.
  if ($radius < $phraseLen) { // check if query length is more than radius itself
      $radius = $phraseLen;
  }

  $pos = stripos($text, $phrase);

  $startPos = 0;
  if ($pos > $radius) {
      $startPos = $pos - $radius;

      // adjust it to the next word
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

  // if ($startPos != 0) {
  //     $excerpt = substr_replace($excerpt, $ending, 0, $phraseLen);
  // }

  if ($endPos != $textLen) {
      $excerpt = substr_replace($excerpt, $ending, -$phraseLen);
  }

  return $excerpt;
}

?>