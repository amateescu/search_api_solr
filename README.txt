
Apache Solr Multilingual
========================

Apache Solr Multilingual cleanly extends Apache Solr Search Integration
to provide:
  * better support for non-English languages
  * support for multilingual search
  * an easier-to-use administration interface for non-English and
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
* The language-neutral spell checker doesn't use any stop words.
* As soon as a user limits his search by language facet, 
  spell checking becomes language-specific

TODO:
* Admin configuration if spell checker is language-specific if
  site language changes (language selector, URL, ...)
* Admin configuration if more than one suggestion should be made
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

Q: Searching for words containing accents or umlauts does not work!

A: You need to make sure the configuration of your servlet container (Tomcat, Jetty, ...)
supports UTF-8 characters within the URL. For Tomcat you have to add an attribute
URIEncoding="UTF-8" to your Connector definition. See Solr's documentation for details:
http://wiki.apache.org/solr/SolrInstall
http://wiki.apache.org/solr/SolrTomcat

