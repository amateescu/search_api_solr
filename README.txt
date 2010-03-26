// $Id$

Apache Solr Multilingual
========================

Name: apachesolr_multilingual
Authors: Markus Kalkbrenner | Cocomore AG
         Matthias Huder | Cocomore AG
Drupal: 6.x
Sponsor: Cocomore AG - http://www.cocomore.com
                       http://drupal.cocomore.com


Description
===========

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

1. Your site uses a single non-English language.
   If you additionally installed apachesolr_multilingual_texfile
   continue at "A) Single Language and Apache Solr Multilingual
   Texfile". Otherwise continue at "C) Single Language"

2. Your site uses multiple languages (multilingual).
   If you additionally installed apachesolr_multilingual_texfile
   continue at "B) Multiple Languages and Apache Solr Multilingual
   Texfile". Otherwise continue at "D) Multiple Languages"



A) Single Language and Apache Solr Multilingual Texfile
=======================================================

1. Ensure that all the language you want to cover is
   available and enabled at admin/settings/language

2. Enable the languages you want to cover at
   admin/settings/apachesolr/multilingual
   and "Save configuration"

3. Adjust all solr text files to your needs at
   admin/settings/apachesolr/multilingual

4. Download complete configuration at
   admin/settings/apachesolr/schema_generator

5. Extract apachesolr_multilingual_conf.zip to your solr
   conf directory and restart solr

6. "Re-index all content" at settings/apachesolr/index.


B) Multiple Languages and Apache Solr Multilingual Texfile
==========================================================

1. Ensure that all the languages you want to cover with
   multilingual search are available and enabled at
   admin/settings/language

2. Enable all the languages you want to cover with
   multilingual search at admin/settings/apachesolr/multilingual
   and "Save configuration"

3. Adjust all solr text files to your needs at
   admin/settings/apachesolr/multilingual

4. Download complete configuration at
   admin/settings/apachesolr/schema_generator

5. Extract apachesolr_multilingual_conf.zip to your solr
   conf directory and restart solr

6. "Re-index all content" at settings/apachesolr/index.
   It's important that you already have content in every langauge
   at this point. Otherwise the checkboxes in the next step won't
   exist until you indexed some content in a specific language

7. Go to admin/settings/apachesolr/query-fields and set "Body" and
   "Title" to "Omit". Enable all language specific bodies and titles
   like body_en or title_de by selecting any value you like but not
   "Omit". And don't forget to "Save configuration".


C) Single Language
==================

1. Ensure that all the language you want to cover is
   available and enabled at admin/settings/language

2. Enable the languages you want to cover at
   admin/settings/apachesolr/multilingual
   and "Save configuration"

4. Download schema.xml at
   admin/settings/apachesolr/schema_generator

5. Copy schema.xml to your solr conf directory

6. Ensure that you have these four files in your solr conf
   directory:
     stopwords.txt
     synonyms.txt
     protwords.txt
     compoundwords.txt

7. Restart solr

8. "Re-index all content" at settings/apachesolr/index.


D) Multiple Languages
=====================

1. Ensure that all the languages you want to cover with
   multilingual search are available and enabled at
   admin/settings/language

2. Enable all the languages you want to cover with
   multilingual search at admin/settings/apachesolr/multilingual
   and "Save configuration"

4. Download complete configuration at
   admin/settings/apachesolr/schema_generator

5. Extract apachesolr_multilingual_conf.zip to your solr
   conf directory

6. Ensure that you have these four files in your solr conf
   directory for each language:
     stopwords_LANGUAGE.txt
     synonyms_LANGUAGE.txt
     protwords_LANGUAGE.txt
     compoundwords_LANGUAGE.txt

7. Restart solr

8. "Re-index all content" at settings/apachesolr/index.
   It's important that you already have content in every langauge
   at this point. Otherwise the checkboxes in the next step won't
   exist until you indexed some content in a specific language

7. Go to admin/settings/apachesolr/query-fields and set "Body" and
   "Title" to "Omit". Enable all language specific bodies and titles
   like body_en or title_de by selecting any value you like but not
   "Omit". And don't forget to "Save configuration".


ToDo
====

* language specific spell checking
* handle cck fields and taxonomy

