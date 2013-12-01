<?php
# Credits
$wgExtensionCredits['other'][] = array(
    'name' => "Bibliography",
    'author' => "Franz Graf",
    'url' => "http://www.Locked.de",
    'description' => "Extension for displaying BibTex files as publication lists.",
    'version' => "1"
);

# setup hook depending on differnet mediawiki versions
# taken from the mediawiki book
if (defined('MW_SUPPORTS_PARSERFIRSTCALLINIT')){
    $wgHooks['ParserFirstCallInit'][] = 'Bibliography::setup';
} else {
    $wgExtensionFunctions[] = 'Bibliography::setup';
}

$wgAutoloadClasses['Bibliography'] = dirname(__FILE__)."/Bibliography_body.php";
$wgAutoloadClasses['PARSEENTRIES'] = dirname(__FILE__)."/bibtexParse/PARSEENTRIES.php";