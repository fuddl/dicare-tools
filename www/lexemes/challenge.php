<?php

require '../../inc/load.inc.php';

$challenge = null;
$error = null;

// specific challenge
if (!empty($_GET['id'])) {
    if (preg_match('/^[1-9][0-9]*$/', $_GET['id'])) {
        $challenge = LexemeChallenge::getChallenge($_GET['id']);
    }
    if (($challenge === null) || ($challenge->date_start === null)) {
        $error = 'Challenge not found!';
    }
}
// current challenge
else {
    $challenge = LexemeChallenge::getCurrentChallenge();
}

if ($challenge === null) {
    $error = 'No active challenge found!';
}

$title = (!empty($challenge->title) ? htmlentities($challenge->title).' — ' : '').'<a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php">Lexemes Challenge</a>';
define('PAGE_TITLE', $title);
page::setMenu('lexemes');

require '../../inc/header.inc.php';

if (!empty($error)) {
    echo '<h2>Error</h2><p>'.$error.'</p>';
}
else {
    
    // title and intro
    echo '<h2>Challenge started on '.$challenge->date_start.(!empty($challenge->date_end) ? ' and ended on '.$challenge->date_end : '').'</h2>';
    if (!empty($challenge->date_end)) {
        echo '<p><strong><a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php">&rarr; A new challenge is available!</a></strong></p>';
    }
    else {
        echo '<p>You can help by <a href="https://www.wikidata.org/wiki/Special:MyLanguage/Wikidata:Lexicographical_data">creating new lexemes</a> and linking senses to Wikidata items using the properties <a href="https://www.wikidata.org/wiki/Property:P5137"><em>item for this sense</em> (P5137)</a> or <a href="https://www.wikidata.org/wiki/Property:P9970"><em>predicate for</em> (P9970)</a>. Useful tool: <a href="https://lexeme-forms.toolforge.org/">Wikidata Lexeme Forms</a>.</p>';
    }
    
    $concepts = explode(' ', $challenge->concepts);
    
    // initial results
    $referenceParty = new LexemeParty();
    $referenceParty->setPath(LEXEMES_CHALLENGE_PATH);
    $referenceParty->setConcepts($concepts);
    $items = unserialize($challenge->results_start);
    $referenceParty->computeItems($items);
    echo '<div class="party_diff"><p>Start of the challenge:</p>'.LexemeParty::diff_party($referenceParty, null).'</div>';
    
    // final results
    if (!empty($challenge->date_end)) {
        $finalParty = new LexemeParty();
        $finalParty->setPath(LEXEMES_CHALLENGE_PATH);
        $finalParty->setConcepts($concepts);
        $items = unserialize($challenge->results_end);
        $finalParty->computeItems($items);
        echo '<div class="party_diff"><p>End of the challenge:</p>'.LexemeParty::diff_party($finalParty, $referenceParty).'</div>';
    }
    
    // current results
    $currentParty = new LexemeParty();
    $currentParty->setPath(LEXEMES_CHALLENGE_PATH);
    $currentParty->initLanguageDisplay();
    $currentParty->setConcepts($concepts);
    $items = $currentParty->queryItems();
    $currentParty->computeItems($items);
    
    // display
    echo '<div class="party_diff"><p>Current progress:</p>'.LexemeParty::diff_party($currentParty, $referenceParty).'</div>
    <form action="'.SITE_DIR.LEXEMES_SITE_DIR.'challenge.php" method="get" class="party_diff_clear">
    <p><input type="hidden" name="id" value="'.$challenge->id.'" /><label for="language_display">Display language:</label> <select name="language_display">
        <option value="auto">Automatic'.(($currentParty->language_display_form === 'auto') ? ' (detected: '.htmlentities($currentParty->language_display).')' : '').'</option>';
    $res = wdqs::query('SELECT DISTINCT ?code ?label WHERE { ?language wdt:P218 ?code ; rdfs:label ?label . FILTER (LANG(?label) = ?code) } ORDER BY ?code', 86400)->results->bindings;
    foreach ($res as $language) {
        echo '<option value="'.htmlentities($language->code->value).'"'.(($currentParty->language_display_form !== 'auto') && ($currentParty->language_display === $language->code->value) ? ' selected="selected"' : '').'>['.htmlentities($language->code->value).'] '.htmlentities($language->label->value).'</option>';
    }
    echo '</select> <input type="submit" value="Change" /></p>
</form>';
    $party = &$currentParty;
    if (!empty($_GET['table'])) {
        if ($_GET['table'] === 'reference') {
            $party = &$referenceParty;
        } elseif (($_GET['table'] === 'final') && (!empty($finalParty))) {
            $party = &$finalParty;
        }
    }
    $party->fetchConceptsMeta();
    $party->setDisplayMode('compact');
    $party->display();
    
    // rankings
    if (!empty($finalParty)) {
        $rankings = LexemeParty::generateRankings($referenceParty, $finalParty);
    }
    else {
        $rankings = LexemeParty::generateRankings($referenceParty, $currentParty);
    }
    $rankings = array_filter($rankings, function($var) { return ($var->added != 0) || ($var->removed != 0); });
    usort($rankings, function($a, $b) {
        $r = $b->removed + $b->added <=> $a->removed + $a->added;
        if ($r !== 0) {
            return $r;
        }
        return $b->completion <=> $a->completion;
    });
    if (count($rankings) >= 1) {
        echo '<h2 id="dashboard">Most improved languages during the challenge</h2>
<p>This table counts only lexemes for which the properties <a href="https://www.wikidata.org/wiki/Property:P5137"><em>item for this sense</em> (P5137)</a> or <a href="https://www.wikidata.org/wiki/Property:P9970"><em>predicate for</em> (P9970)</a> were added or removed during the challenge.</p>';
        LexemeParty::displayRankings($rankings, count($concepts));
    }
    echo '&rarr; <a href="'.SITE_DIR.LEXEMES_SITE_DIR.'challenges-dashboard.php">Challenges Dashboard</a>';
    
}

require '../../inc/footer.inc.php';

?>