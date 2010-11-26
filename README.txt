$Id$

Solr search
-----------

This module provides an implementation of the Search API which uses an Apache
Solr search server for indexing and searching. Before enabling or using this
module, you'll have to follow the instructions given in INSTALL.txt first.

Supported optional features
---------------------------

All Search API datatypes are supported by using appropriate Solr datatypes for
indexing them. By default, "String"/"URI" and "Integer"/"Duration" are defined
equivalently. However, through manual configuration of the used schema.xml this
can be changed arbitrarily. Using your own Solr extensions is thereby also
possible. 

The "direct" parse mode for queries will result in the keys being directly used
as the query to Solr. For details about Lucene's query syntax, see [1]. There
are also some Solr additions to this, listed at [2]. Note however that, by
default, this module uses the dismax query handler, so searches like
"field:value" won't work with the "direct" mode.

[1] http://lucene.apache.org/java/2_9_1/queryparsersyntax.html
[2] http://wiki.apache.org/solr/SolrQuerySyntax

Regarding third-party features, this module supports the "search_api_facets"
feature, introduced by the module of the same name. This lets you create
facetted searches for any index lying on a Solr server.

If you feel some service option is missing, or have other ideas for improving
this implementation, please file a feature request in the project's issue queue,
at [3], using the "Solr search" component.

[3] http://drupal.org/project/issues/search_api

Specifics
---------

Please consider that, since Solr handles tokenizing, stemming and other
preprocessing tasks, activating any preprocessors in a search index' settings is
usually not needed or even cumbersome. If you are adding an index to a Solr
server you should therefore then disable all processors which handle such
classic preprocessing tasks.

Also, due to the way Solr works, using a single field for fulltext searching
will result in the smallest index size and best search performance, as well as
possibly having other advantages, too. Therefore, if you don't need to search
different sets of fields in different searches on an index, it is adviced that
you simply set all fields that should be searched to type "Fulltext" (but don't
check "Indexed"), add the "Fulltext field" data alter callback and only index
the newly added field named "Fulltext".

Developers
----------

The SearchApiSolrService class has a few custom extensions, documented with its
code. Methods of note are:
- deleteItems()
- ping()
