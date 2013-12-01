# Mediawiki-Bibiography

A bibliography extension that parses and renders BibTex files into MediaWiki pages.
If you're a researcher and run MediaWiki as your CMS - you want this ;-)

# Features
This extension allows processing a central bibliography file in BibTeX format in order to create personalized publication pages for authors, projects or keywords. The BibTeX data can be stored in a file in the filesystem or in a special wiki article.

## Implemented Features
* The bibliography can be stored in a filesystem file or in a separate article of the wiki.
* Multiple authors can share a single BibTeX source and have individual publication lists on their personal pages (=articles)
* Filtering can be done on all attributes of the bibtex file/article.
* Filters can be combined: for example, all papers of year=2010 AND author=xy AND keyword=xyz
* Supports optional bibtex entries ("pages =100--110" may be entered in one bibtex entry but not in another - but if the entry is present, it should be formated as "p. 100-110". If the attribute is not there, "p. " should also not appear)
* Different bibliography-types (article, book, inproceeding, ...) use different styles according to the mandatory and optional fields.
* @unpublished entries will be ignored / not rendered
* Supports @String-replacement as it is can be done by BibTeX
* Automatically adds a separator between entries of different years
* Provide additional links for each BibTeX entry (like PDFs or links to articles with further informations)
* Author names can be linked automatically to predefined wiki articles


## Installation
* Install [Extension:Preloader](http://www.mediawiki.org/wiki/Extension:Preloader) (contained in the repository)
* Copy the contents of the download archive into your extension directory
add `require_once("$IP/extensions/Bibliography/Bibliography.php");` to your LocalSettings.php

Afterwards the following variables can be set in LocalSettings.php (both optional):

* `$wgBibliographyAuthorMap = "NameOfArticle";` specifies the default value if the attribute authormap is not set in the xml tag. Optional / No Default value
* `$wgBibliographyAllowFileRead = true|false;`. Allows to enable/disable the possibility to read the bibtex file and author map from a file on disk. Optional / Default = true
 

## Usage
Keep in mind:

* All filters are case insensitive!
* If a page doesn't change, it might be cached (see Refreshing Pages below)


### Show a complete BibTeX file
For processing the complete BibTeX file from a file in the file system:
`<bibliography src="file:/complete/path/to/BibTeXFile" />`
This is the usual way if a bibtex file is maintained for example in a CVS repository.

For processing the complete BibTeX file from an article in the wiki use:
`<bibliography src="nameOfArticleContaining AllBibtex" />` 

If the filesystem version is used, make sure that the file is readable by the webserver. This means that the file needs to be world-readable. If you have concerns about exposing the file to the world wide web, you might consider a location outside the document root. Nevertheless, the webserver must be able to read the file. If you have no clue about what this means, ask your SysAdmin ;-)

### Filtering one author
In order to display all entries of a single author (Graf in this case) use:
`<bibliography src="BibTexSource" author="Graf" />`
This means, that the string "Graf" must appear in the author keyword of a bibtex entry. The filter will match all following entries:

* Franz Graf and author1 and ...
* Graf, F. and ...
* Graf
 

### Filtering multiple authors
If you want to see all publications where "Graf" and "author2" are authors (but not ne alone), use:
`<bibliography src="BibTexSource" author="Graf,Kriegel" />`
Both authors must appear in the author keyword of the shown entries.


### Filtering other entries
Given the following BibTeX entry:

<pre>@INPROCEEDINGS{doiItentifier0815,
  AUTHOR    = {T. Emrich and F. Graf and H.-P. Kriegel and M. Schubert and M. Thoma and A. Cavallaro},
  TITLE     = {{CT} Slice Localization via Instance-Based Regression},
  BOOKTITLE = {Proceedings of the SPIE Medical Imaging 2010 Conference (SPIE), San Diego, CA, USA},
  VOLUME    = {7623},
  PAGES     = {762320},
  YEAR      = {2010},
  DBSLINKS  = {[/cms/Publications/CT_Slice_Localization_via_Instance-Based_Regression|more information]},
  KEYWORDS  = {medical,database}
}</pre>

Additionally to filtering the author-field in the section above, filters can be defined on all fields of the entry as well: title, booktitle, volume, pages, year, dbslinks, keywords.

In order to filter all publications of the year 2010, use:<br>
`<bibliography src="BibTexSource" year="2010" />`

In order to filter all publications using the keyword "medical", use:<br>
`<bibliography src="BibTexSource" keywords="medical" />`

In order to filter all publications using the keyword "medical" AND "best paper", use:<br>
`<bibliography src="BibTexSource" keywords="medical, best paper" />`

Of course, filters can be combined as well:<br>
`<bibliography src="BibTexSource" year="2010" keywords="medical" />`<br>
This will show all publications tagged by the keyword "medical" in the year 2010.


Or more abstract, given the BibTeX entry:
<pre>@INPROCEEDINGS{...,
  FIELD    = VALUE
}</pre>
You can filter on `<bibliography src="..." field="filter value" />`.

All given filters are case insensitive and combined by and - if not, then you have discovered a bug (please fix and share ;-)).


## Linking author names to wiki articles
Links from the authors to according wiki articles can be created by creating a separate article that contains a mapping from author name to wiki article containing a list in the following format:

<pre>article name|authorname in bibtex
Franz Graf|F. Graf</pre>
Now the extension just needs to know about this article. This can be done in the following ways:

* Let the mapping be defined in an article named "authorMapArticle". Then the extension call can look like this
`<bibliography src="BibTexSource" authormap="authorMapArticle" />`
* You may also read this map from a file: 
`<bibliography src="BibTexSource" authormap="file:/foo/bar/authorMapArticle.txt" />`

If you do not want to set this map in every call of the extension, you can also define a variable in `LocalSettings.php`:

* `$wgBibliographyAuthorMap = "nameOfArticle";` or
* `$wgBibliographyAuthorMap = "file:/foo/bar/nameOfArticle.txt";`


## Special field "DBSLINKS"
The DBSLINKS-field is a special field which - if used - can be used to add links to the output. As the output is NOT wiki-parsed, we use an own syntax for linking to additional information.

Given the following entries:

<pre>@INPROCEEDINGS{...,
  ...
   DBSLINKS  = {[target|label]},
}</pre>
This will produce the following HTML-Code: `<a href="target">label</a>`.

<pre>@INPROCEEDINGS{...,
  ...
   DBSLINKS  = {[url1|label], [foo|bar]},
}</pre>
This will produce the following HTML-Code: `<a href="url1">label</a>, <a href="foo">bar</a>`.

## Refreshing Pages
In order to enhance processing speed, MediaWiki uses a parser cache. This may leed to the effect that the BibTeX base is updated, but your pages show the old content (and people are confused that their edits in the BibTex file don't show up). But the solution is rather simple:

* In order to refresh the page, add `?action=purge` to the URL of the page that you want to refresh.
 or
*  clear the complete parser cache by the deleting the objectCache table (currently provided by the `purgeCache.php` file in the extensions directory).





***
The original hosting location was:
<http://www.dbs.informatik.uni-muenchen.de/cms/Franz_Graf/MediaWiki>
