
Apache Solr Multilingual
========================

Apache Solr Multilingual extends Apache Solr Search Integration
in a clean way to provide:
  * better support for non-English languages
  * support for multilingual search
  * an easy to use administration interface for non-English and
multilingual search


Installation
============

1. Place whole apachesolr_multilingual folder into your Drupal
   modules/ or better sites/x/modules/ directory.

2. Enable the apachesolr_multilingual module at
   admin/build/modules

3. Optional but recommended:
   Enable the apachesolr_multilingual_texfile module at
   administer/modules. Apache Solr requires some text files
   like stopwords.txt. This module adds an adminstration
   interface for such files to drupal. If you don't like it
   you need to maintain such files manually.

Now you have different options to complete your setup:

1. Your site uses a unique non-English language.
   If you additionally installed apachesolr_multilingual_texfile
   continue at "A) Unique Language and Apache Solr Multilingual
   Texfile". Otherwise continue at "C) Unique Language"

2. Your site uses multiple languages (multilingual) and your
   content is assigned to languages using the locale module.
   If you additionally installed apachesolr_multilingual_texfile
   continue at "B) Multiple Languages and Apache Solr Multilingual
   Texfile". Otherwise continue at "D) Multiple Languages"



A) Unique Language and Apache Solr Multilingual Texfile
=======================================================

1. Ensure that all the language you want to cover is
   available and enabled at admin/config/regional/language

2. Enable the languages you want to cover at
   admin/config/search/apachesolr/multilingual
   and "Save configuration"

3. Adjust all solr text files to your needs at
   admin/config/search/apachesolr/multilingual

4. Download apachesolr_unique_language_config.zip at
   admin/config/search/apachesolr/schema_generator

5. Extract apachesolr_unique_language_config.zip to your solr
   conf directory and restart solr

6. "Queue content for reindexing" at admin/config/search/apachesolr/index.


B) Multiple Languages and Apache Solr Multilingual Texfile
==========================================================

1. Ensure that all the languages you want to cover with
   multilingual search are available and enabled at
   admin/regional/language

2. Enable all the languages you want to cover with
   multilingual search at admin/config/search/apachesolr/multilingual
   and "Save configuration"

3. Adjust all solr text files to your needs at
   admin/config/search/apachesolr/multilingual

4. Download apachesolr_multilingual_config.zip at
   admin/config/search/apachesolr/schema_generator

5. Extract apachesolr_multilingual_config.zip to your solr
   conf directory and restart solr

6. "Queue content for reindexing" at admin/config/search/apachesolr/index.
   It's important that you already have content in every langauge
   at this point. Otherwise the checkboxes in the next step won't
   exist until you indexed some content in a specific language

7. Go to admin/config/search/apachesolr/query-fields and set "Body" and
   "Title" to "Omit". Enable all language specific bodies and titles
   like body_en or title_de by selecting any value you like but not
   "Omit". And don't forget to "Save configuration".

8. Optional: Like described in 7 omit
     "Body text inside links (A tags)",
     "Body text inside H1 tags",
     "Body text inside H2 or H3 tags",
     "Body text inside H4, H5, or H6 tags",
     "Body text in inline tags like EM or STRONG"
   and turn on the labguage specific fields like
     "tags_a_de",
     "tags_h1_de",
     "tags_h2_h3_de",
     "tags_h4_h5_h6_de",
     "tags_inline_de".

9. Optional: If you insatalled the module "Taxonomy translation" and
   turned on "Index taxonomy term translations" at
   admin/config/search/apachesolr/multilingual you should omit
   "All taxonomy term names" and enable the language specific equivalent
   like "taxonomy_names_de" instead like described in 7.


C) Unique Language
==================

1. Ensure that all the language you want to cover is
   available and enabled at admin/regional/language

2. Enable the languages you want to cover at
   admin/config/search/apachesolr/multilingual
   and "Save configuration"

4. Download schema.xml for unique language setup at
   admin/config/search/apachesolr/schema_generator

5. Copy schema.xml to your solr conf directory

6. Ensure that you have these four files in your solr conf
   directory:
     stopwords.txt
     synonyms.txt
     protwords.txt
     compoundwords.txt

7. Restart solr

8. "Queue content for reindexing" at admin/config/search/apachesolr/index.


D) Multiple Languages
=====================

1. Ensure that all the languages you want to cover with
   multilingual search are available and enabled at
   admin/regional/language

2. Enable all the languages you want to cover with
   multilingual search at admin/config/search/apachesolr/multilingual
   and "Save configuration"

4. Download schema.xml for multilingual setup at
   admin/config/search/apachesolr/schema_generator

5. Copy schema.xml to your solr conf directory

6. Ensure that you have these four files in your solr conf
   directory for each language:
     stopwords_LANGUAGE.txt
     synonyms_LANGUAGE.txt
     protwords_LANGUAGE.txt
     compoundwords_LANGUAGE.txt

7. Restart solr

8. "Queue content for reindexing" at admin/config/search/apachesolr/index.
   It's important that you already have content in every langauge
   at this point. Otherwise the checkboxes in the next step won't
   exist until you indexed some content in a specific language

9. Go to admin/config/search/apachesolr/query-fields and set "Body" and
   "Title" to "Omit". Enable all language specific bodies and titles
   like body_en or title_de by selecting any value you like but not
   "Omit". And don't forget to "Save configuration".

10. Optional: Like described in 9 omit
     "Body text inside links (A tags)",
     "Body text inside H1 tags",
     "Body text inside H2 or H3 tags",
     "Body text inside H4, H5, or H6 tags",
     "Body text in inline tags like EM or STRONG"
   and turn on the labguage specific fields like
     "tags_a_de",
     "tags_h1_de",
     "tags_h2_h3_de",
     "tags_h4_h5_h6_de",
     "tags_inline_de".

11. Optional: If you insatalled the module "Taxonomy translation" and
   turned on "Index taxonomy term translations" at
   admin/config/search/apachesolr/multilingual you should omit
   "All taxonomy term names" and enable the language specific equivalent
   like "taxonomy_names_de" instead like described in 9.


Spell Checker
=============

How it works:
* langauge neutral spell checker doesn't use any stop words.
* as soon as a user limited his search by language facet spell
  checking is language specific

ToDo:
* admin configures if spell checker is language specific if
  site language changes (language selector, URL, ...)
* admin configures if more than one suggestion should be made
  in different languages (expensive because solr needs to be queried
  one time per language)


Apache Solr Text Files
======================

stopwords.txt
=============
TODO


protwords.txt
=============
TODO


synonyms.txt
=============
TODO


compoundwords.txt
=================
TODO


Troubleshooting
===============

Searching for words containing accents or umlauts does not work!
You need to verify the configuration of your servlet container (tomcat, jetty, ...)
to support UTF-8 characters within the URL. For tomcat you have to add an attribute
URIEncoding="UTF-8" to your Connector definition. See Solr's documentation for details:
http://wiki.apache.org/solr/SolrInstall
http://wiki.apache.org/solr/SolrTomcat

