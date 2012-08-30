
Apache Solr Multilingual
========================

Apache Solr Multilingual extends Apache Solr Search Integration
in a clean way to provide:
  * better support for non-English languages
  * support for multilingual search
  * an easier to use administration interface for non-English and
multilingual search


Installation
============
TODO


Usage
=====
TODO


Spell Checker
=============

How it works:
* langauge neutral spell checker doesn't use any stop words.
* as soon as a user limited his search by language facet spell
  checking is language specific

TODO:
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

