<?php

include_once "SpellCorrector.php";

function spellfix($query) {

  $keywords = explode(" ", $query);
  $n = count($keywords);

  // Add output of spelling checker
  $spellsuggestion = "";
  for($i = 0; $i < $n; $i++) {
    $spellsuggestion = $spellsuggestion.SpellCorrector::correct($keywords[$i]);

    if($i < ($n-1)) {
      $spellsuggestion = $spellsuggestion." ";
    }
  }

  return $spellsuggestion;
}

?>