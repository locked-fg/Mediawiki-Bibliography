<?php
# implementation of the class

class Bibliography {
    const DISABLE_PARSER_CACHE = false;

    const NAME = "Bibliography";

    const DEFAULT_PRE = '<table class="bibtexTable">';
    const DEFAULT_HEAD = '<tr><td><b>{year}</b></td> </tr>';
    const DEFAULT_POST = '</table>';

    const BODY_DEFAULT = "{author}<br/> <b>{title}</b><br/> {journal}{booktitle}, {year} [<br>{dbslinks}]";
    const BODY_ARTICLE = "{author}<br/> <b>{title}</b><br/> {journal}[, {volume}][({number})][: {pages}], {year}. [<br>{dbslinks}]";
    const BODY_BOOK = "{author}<br/> <b>{title}</b><br/> {publisher}[, ISBN: {isbn}], {year}. [<br>{dbslinks}]";
    const BODY_INCOLLECTION = "{author}<br/> <b>{title}</b><br/> In[ {editor} (ed.)]: {booktitle}, {publisher}[, {volume}][: {pages}], {year}. [<br>{dbslinks}]";
    const BODY_INPROCEEDINGS = "{author}<br/> <b>{title}</b><br/> In {booktitle}, {year}. [<br>{dbslinks}]";
    const BODY_PROCEEDINGS = "[{author}<br/> ]<b>{title}</b><br/>[ In {booktitle}, ]{year}. [<br>{dbslinks}]";
    const BODY_MASTERSTHESIS = "{author}<br/> <b>{title}</b><br/> [{note},] {school}, {year}. [<br>{dbslinks}]";
    const BODY_MISC = "[{author}<br/>][ <b>{title}</b><br/>][{howpublished}][, {note}][, {year}]. [<br>{dbslinks}]";
    const BODY_PHDTHESIS = "{author}<br/> <b>{title}</b><br/> PhD Thesis, {school}, {year}. [<br>{dbslinks}]";
    const BODY_TECHREPORT = "{author}<br/> <b>{title}</b><br/> Technical Report, [No. {number}, ]{institution}, {year}. [<br>{dbslinks}]";
    const BODY_UNPUBLISHED = "{author}<br/> <b>{title}</b><br/> {note}. [<br>{dbslinks}]";

    static function setup() {
        global $wgParser;
        $wgParser->setHook(self::NAME, array('Bibliography', 'render'));
        return true;
    }

    /**
     *
     * @param String $input the data between the bibliography-tags or null
     * @param array $argv array of html-attributes (might be an empty array)
     * @param Objct $parser
     * @return html string
     */
    static function render($input, $argv, &$parser) {
        if (self::DISABLE_PARSER_CACHE) {
            $parser->disableCache();
        }

        if (!isset($argv['src']) || empty($argv['src'])) {
            error_log("no src attribute defined or src is empty");
            return "";
        }

        // do a bit cleaning on the arguments
        foreach ($argv as $k => $v) {
            $argv[strtolower($k)] = trim($v);
        }

        if (!isset($argv['authormap'])) {
            $argv['authormap'] = $GLOBALS['wgBibliographyAuthorMap'];
        }
        $authorMap = self::loadSource($argv['authormap']);
        $authorMap = self::parseAuthorMap($authorMap);

        $entryList = self::loadSource($argv['src']);
        $entryList = self::umlauts($entryList);
        $entryList = self::parseBibliography($entryList);
        $entryList = self::filterBibliography($entryList, $argv);

        usort($entryList, array("Bibliography", "sortEntries"));
        $wikiText = self::parseEntryList($entryList, $authorMap, $parser);

        return $wikiText;
    }

    /**
     * Parses the author map from "article-name|keyword-in-bibtex" to an array
     * of keywords => article-name
     *
     * @param String $authorMap
     * @return associative array
     */
    static function parseAuthorMap($authorMap) {
        if (!isset($authorMap) || empty($authorMap)) {
            return array();
        }
        preg_match_all("/\W*([^|]+)\|(.+)\s*/i", $authorMap, $matches);
        $authors = array();
        for ($i = 0; $i < count($matches[0]); $i++) {
            $authors[trim($matches[2][$i])] = trim($matches[1][$i]);
        }
        return $authors;
    }

    /**
     * Load Text from article using the preloader extension OR load text from file,
     * if $articleName begins with 'file:'
     */
    static function loadSource($articleName) {
        $allowFileRead = true;
        if (isset($GLOBALS['wgBibliographyAllowFileRead'])) {
            $allowFileRead = $GLOBALS['wgBibliographyAllowFileRead'];
        }
        if (preg_match("/^file:/i", $articleName)) {
            if (!$allowFileRead) {
                return "";
            }
            // load text from file
            $articleName = substr($articleName, 5); // remove "file:"
            $text = @file_get_contents($articleName);
            if (!$text) {
                error_log("couldn't read file $articleName");
                return "";
            }
            return $text;
        } else {
            // load text from related article
            $text = "";
            $title = Title::newFromText($articleName);
            if ($title && $title->exists()) {
                // get Revision object
                $revision = Revision::newFromTitle($title);
                if ($revision) {
                    $text = $revision->getText();
                }
            }
            return $text;
        }
    }

    /**
     * parse bibtex string to arrays
     * @param String $text the bibtex entries including template text strings
     * @return array of entries
     */
    static function parseBibliography($text) {
        $parse = NEW PARSEENTRIES();
        $parse->expandMacro = true;
        $parse->fieldExtract = true;
        $parse->loadBibtexString($text);
        $parse->extractEntries();
        $entries = $parse->returnArrays();
        return $entries[2];
    }

    /**
     * Apply the filter given in $args to the list of entries.
     *
     * @param array $entryList list of entries (sub arrays)
     * @param array $args associative array of filters
     */
    static function filterBibliography($entryList, $args) {
//        var_dump($entryList);
        // build filter array from args
        $filter = array();
        foreach ($args as $k => $v) {
            $k = trim(strtolower($k));
            $v = trim(strtolower($v));
            // src and authormap are no filters but sources for the bibtex and
            // the author links
            if ($k == "src" || $k == "authormap") {
                continue;
            }

            $filter[$k] = preg_split("/ *, */", $v);
        }

        // filter entries
        $newEntries = array();

        // check each entry
        foreach ($entryList as $entry) {
            // unpublished links will not be listed
            if ($entry["bibtexEntryType"] == "unpublished") {
                continue;
            }

            $found = true;
            foreach ($filter as $key => $filterList) {
                if ($key == "key") { // selection by bibtex key
                    $found = in_array(strtolower($entry["bibtexCitation"]), $filterList);
                } else if ($key == "author") {
                    // authors are filtered in a special way as a comma might not
                    // be the best split option AND:
                    // ALL named authors must be listed in the entry!
                    $found &= self::filterAuthors($entry, $filterList);
                } else {
                    // other fields are splitted by comma and checked
                    // One of the keywords is enough
                    $entryValues = preg_split("/ *, */", strtolower(trim($entry[$key])));
                    foreach ($filterList as $aFilterValue) {
                        $found &= in_array($aFilterValue, $entryValues);
                    }
                }
            }

            if ($found) {
                $newEntries[] = $entry;
            }
        }

        return $newEntries;
    }

    /**
     * Checks, if the bibtex entry contains ALL requested authors
     *
     * @param array $entry a single bibtex entry
     * @param array $authorsFilter list of author names that must appear as authors
     * @return boolean true if all authors were found, false otherwise
     */
    static function filterAuthors($entry, $authorsFilter) {
        $authors = preg_split("/ and /", $entry['author']);

        $foundAll = true;
        foreach ($authorsFilter as $search) {
            $foundThis = false;
            foreach ($authors as $anAuthor) {
                if (stristr($anAuthor, $search)) {
                    $foundThis = true;
                }
            }
            $foundAll &= $foundThis;
        }
        return $foundAll;
    }

    /**
     * user defined sorting function that orders the entries according to
     * year, proceeding type (artice, proceeding, ..), booktitle, title
     */
    static function sortEntries($a, $b) {
        if (!isset($a['year']) || !isset($b['year'])) {
            return 0;
        }
        // sort by year
        $diff = -1 * ($a['year'] - $b['year']);
        if ($diff != 0) {
            return $diff;
        }
        // within a year, sort by proceeding type
        $diff = strcmp($a['bibtexEntryType'], $b['bibtexEntryType']);
        if ($diff != 0) {
            return $diff;
        }
        // within proceeding type, sort by conf
        if (isset($a['booktitle']) && isset($b['booktitle'])) {
            $diff = strcmp($a['booktitle'], $b['booktitle']);
            if ($diff != 0) {
                return $diff;
            }
        }

        // within a proceeding type, sort by title
        return strcmp($a['title'], $b['title']);
    }

    /**
     * Return the body string depending on the type of the bibtex entry
     *
     * @param String $type inproceedings, book, article, ....
     * @return the string that should be parsed
     */
    static function buildBody($type) {
        $type = strtolower($type);
        $pre = '<tr><td>{counter}</td><td>';
        $post = '</td></tr>';
        if ($type == "article")
            return $pre . self::BODY_ARTICLE . $post;
        elseif ($type == "book")
            return $pre . self::BODY_BOOK . $post;
        elseif ($type == "incollection")
            return $pre . self::BODY_INCOLLECTION . $post;
        elseif ($type == "proceedings")
            return $pre . self::BODY_PROCEEDINGS . $post;
        elseif ($type == "inproceedings")
            return $pre . self::BODY_INPROCEEDINGS . $post;
        elseif ($type == "mastersthesis")
            return $pre . self::BODY_MASTERSTHESIS . $post;
        elseif ($type == "misc")
            return $pre . self::BODY_MISC . $post;
        elseif ($type == "phdthesis")
            return $pre . self::BODY_PHDTHESIS . $post;
        elseif ($type == "techreport")
            return $pre . self::BODY_TECHREPORT . $post;
        elseif ($type == "unpublished")
            return $pre . self::BODY_UNPUBLISHED . $post;
        else
            return $pre . self::BODY_DEFAULT . $post;
    }

    /**
     * parses the filtered and ordered list of entries into nice HTML
     *
     * @param array $entryList filtered & ordered list of entries
     * @param array $authorMap name shortcut as it is in teh paper => Article name
     * @param Parser $parser the parser object given from outside
     * @return String completely parsed text
     */
    static function parseEntryList($entryList, $authorMap, &$parser) {
        $text = "";
        $i = count($entryList);
        $lastYear = -1;
        foreach ($entryList as $entry) {
            $parsedEntry = "";
            // prepend a new Head if the year changed
            if (isset($entry['year']) && $lastYear != $entry['year']) {
                $parsedEntry .= self::DEFAULT_HEAD . "\n";
            }
            // the template string for this type of bibtex entry
            $parsedEntry .= self::buildBody($entry['bibtexEntryType']) . "\n";


            // parse the author string from bibtex format into the desired format
            // also the authors will be linked to articles according to the authorMap
            $entry['author'] = self::formatAuthors($entry['author'], $parser, $authorMap);

            // convert links in dbslinks-entry. $str = "[foo|bar], [foo1|bar1], [foo2|bar2], [foo3]";
            $entry['dbslinks'] = self::parseDbsLinks($entry['dbslinks']);

            // find and replace template strings: {template}
            preg_match_all("/{(\w+)}/", $parsedEntry, $placeholder);
            for ($j = 0; $j < count($placeholder[0]); $j++) {
                $key = strtolower($placeholder[1][$j]);
                if (!empty($entry[$key])) { // do NOT yet remove unmatched tags
                    $parsedEntry = str_ireplace($placeholder[0][$j], $entry[$key], $parsedEntry);
                }
            }
            $parsedEntry = str_ireplace("{counter}", $i--, $parsedEntry);

            // remove optional fields
            $parsedEntry = self::removeOptionalFields($parsedEntry);

            $text .= $parsedEntry;
            $lastYear = $entry['year'];
        }

        // replace URLS and orphaned bibtex braces
        $pattern = array('=\\\\url{([^}]+)}=', "={|}=");
        $replace = array('<a href="\\1">link</a>', '');
//        $pattern = array(
//            '=\\\\url{([^}]+)}=', // \url{link -> a href ...
//            '=(\\\\\w+)?{=', // \bibtexStuff{ -> "",
//            '=}=' // closing braces (end of \bibtexStuff) -> ""
//        );
//        $replace = array(
//            '<a href="\\1">link</a>',
//            "", "");
        $text = preg_replace($pattern, $replace, $text);

        return self::DEFAULT_PRE . $text . self::DEFAULT_POST;
    }

    /**
     * convert links in dbslinks-entry. $text = "[foo|bar], [foo1|bar1], [foo2|bar2], [foo3]";
     * @param <type> $text
     * @return <type>
     */
    static function parseDbsLinks($text) {
        preg_match_all("/\[([^]]+)\]/", $text, $out);
        for ($j = 0; $j < count($out[0]); $j++) {
            list($url, $name) = explode("|", $out[1][$j]);
            if (empty($name)) {
                $name = "link";
            }
            $link = '<a href="' . $url . '">' . $name . "</a>";
            $text = str_replace($out[0][$j], $link, $text);
        }
        return $text;
    }

    /**
     * replace latex umlauts to real umlauts for HTML
     * @param String $text
     * @return String converted text
     */
    static function umlauts($text) {
        $text = str_replace('\"o', "ö", $text);
        $text = str_replace('\"{o}', "ö", $text);
        $text = str_replace('\"O', "Ö", $text);
        $text = str_replace('\"{O}', "Ö", $text);

        $text = str_replace('\"u', "ü", $text);
        $text = str_replace('\"{u}', "ü", $text);
        $text = str_replace('\"U', "Ü", $text);
        $text = str_replace('\"{U}', "Ü", $text);

        $text = str_replace('\"a', "ä", $text);
        $text = str_replace('\"{a}', "ä", $text);
        $text = str_replace('\"A', "Ä", $text);
        $text = str_replace('\"{A}', "Ä", $text);

        $text = str_replace('\ss ', "ß", $text);
        return $text;
    }

//    static function toHTML($text) {
//        // htmlentities causes very strange replacements of teh above umlauts!
//        $charset = mb_detect_encoding($text);
//        if ("ASCII" == $charset) { // everything SHOULD be UTF-8
//            $charset = "ISO-8859-1";
//        }
//        $text = htmlentities($text, ENT_QUOTES, $charset, false);
//        return $text;
//    }

    /**
     * Reformat the Authors list as it is given in bibtex to
     * "J. Smith, J. Doe"
     * @param String $authors as they are given in the bibtex file
     * @param Parser $parser Mediawiki parser
     * @param array $authorMap assoc array "j. Smith" => "articlename of J Smith"
     * @return HTML String
     */
    static function formatAuthors($authors, &$parser, $authorMap = null) {
        if ($authorMap == null) {
            $authorMap = array();
        }

        $text = "";
        if (strstr($authors, ",")) {
            $list = preg_split("/ +and +/", $authors);
            for ($i = 0; $i < count($list); $i++) {
                if (strstr($list[$i], ",")) { // Smith, J. => J. Smith
                    list($surname, $firstname) = explode(",", $list[$i]);
                    $list[$i] = $firstname . " " . $surname;
                }
            }
            $text = join(", ", $list);
        } else {
            $text = preg_replace("/ +and +/", ", ", $authors);
        }

        // replace authors with links to their articles
        foreach ($authorMap as $key => $value) {
            // $link = Title::newFromText($value)->getLocalUrl();
            // $text = str_replace($key, "<a href='$link'>$key</a>", $text);
            $text = str_replace($key, "[[$value|$key]]", $text);
        }
        // TODO change this. replaceInternalLinks() is actually private :-/
        $text = $parser->replaceInternalLinks($text);

        return $text;
    }

    /**
     * removes optional fields enclosed by [,] if they still contain any placeholders
     */
    static function removeOptionalFields($linkstring) {
        $save = 10; // just to avoid an inf. loop
        do {
            // Find all optional fields that do not contain any other optional fields.
            // If an opt. field contains place holders, remove the field. Otherwise,
            // just remove the brackets.
            // Do this as long as optional fields are found (but max 10 times).
            preg_match_all("/\[[^\[\]]+\]/", $linkstring, $out);
            $out = $out[0];
            for ($i = 0; $i < count($out); $i++) {
                if (strstr($out[$i], "{")) {
                    // at least one placeholder was not replaced, so remove this optional field
                    $linkstring = str_replace($out[$i], "", $linkstring);
                } else {
                    // no more placeholders, just remove the braces: [,]
                    $linkstring = str_replace($out[$i], substr($out[$i], 1, -1), $linkstring);
                }
            }
        } while (count($out) > 0 && $save-- > 0);

        return $linkstring;
    }

}