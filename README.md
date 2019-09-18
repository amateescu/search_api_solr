Installation
------------

The search_api_solr module manages its dependencies and class loader via
composer. So if you simply downloaded this module from drupal.org you have to
delete it and install it again via composer!

Simply change into the Drupal directory and use composer to install search_api_solr:

```
cd $DRUPAL
composer require drupal/search_api_solr
```

**Warning!** Unless https://www.drupal.org/project/drupal/issues/2876675 is
committed to Drupal Core and released you need to modify the composer command:

```
cd $DRUPAL
composer require symfony/event-dispatcher:"4.3.4 as 3.4.99" drupal/search_api_solr
```

Solr search
-----------

This module provides an implementation of the Search API which uses an Apache
Solr search server for indexing and searching. Before enabling or using this
module, you'll have to follow the instructions given in INSTALL.md first.

The minimum support version for Search API Solr Search 8.x-3.x is Solr 6.4.
Any version below might work if you use your own Solr config.

Supported optional features
---------------------------

All Search API datatypes are supported by using appropriate Solr datatypes for
indexing them.

The "direct" parse mode for queries will result in the keys being directly used
as the query to Solr using the
[Standard Parse Mode](https://lucene.apache.org/solr/guide/7_2/the-standard-query-parser.html).

Regarding third-party features, the following are supported:

- autocomplete
  - Introduced by module: search_api_autocomplete
  - Lets you add autocompletion capabilities to search forms on the site.
- facets
  - Introduced by module: facet
  - Allows you to create facetted searches for dynamically filtering search
    results.
- more like this
  - Introduced by module: search_api
  - Lets you display items that are similar to a given one. Use, e.g., to create
    a "More like this" block for node pages build with Views.
- multisite
  - Introduced by module: search_api_solr
- spellcheck
  - Introduced by module: search_api_solr
  - Views integration provided by search_api_spellcheck
- attachments
  - Introduced by module: search_api_attachments
- location
  - Introduced by module: search_api_location

If you feel some service option is missing, or have other ideas for improving
this implementation, please file a feature request in the project's issue queue,
at https://drupal.org/project/issues/search_api_solr.

Processors
----------

Please consider that, since Solr handles tokenizing, stemming and other
preprocessing tasks, activating any preprocessors in a search index' settings is
usually not needed or even cumbersome. If you are adding an index to a Solr
server you should therefore then disable all processors which handle such
classic preprocessing tasks.

If you create a new index, such processors won't be offered anymore since
8.x-2.0.

But the remaining processors are useful and should be activated. For example the
HTML filter or the Highlighting processor.

By default the Highlighting processor provided by Search API uses PHP to create
highlighted snippets or an excerpt based on the entities loaded from the
database. Solr itself can do that much better, especially for different
languages. If you check `Retrieve result data from Solr` and `Highlight
retrieved data` on the index edit page, the Highlighting processor will use
this data directly and bypass it's own logic. To do the highlighting, Solr will
use the configuration of the Highlighting processor.

Connectors
----------

The communication details between Drupal and Solr is implemented by connectors.
This module includes:
  - Standard Connector
  - BasicAuth Connector
  - Solr Cloud Connector
  - Solr Cloud BasicAuth Connector

There are service provider specific connectors available, for example from Acquia
and platform.sh. Please contact your provider for details if you don't run your
own Solr server.

Customizing your Solr server
----------------------------

It's highly recommended that you don't modify the schema.xml and solrconfig.xml
files manually because this module dynamically generates them for you.

Most features that can be configured within these config files are reflected
by drupal configs that could be handled via drupal's own config management.

You can also create your own Solr field types by providing additional field
config YAML files. Have a look at this module's config folder to see examples.

Such field types can target a specific Solr version and a "domain". For example
"Apple" means two different things in a "fruits" domain or a "computer" domain.

Troubleshooting Views
---------------------

When displaying search results from Solr in Views using the Search API Views
integration, you have the choice to fetch the displayed values from Solr by
enabling "Retrieve result data from Solr" on the server edit page. Otherwise
Solr will only return the IDs and Search API loads the values from the database.

If you decide to retrieve the values from Solr you have to enable "Skip item
access checks" in the query options in the views advanced settings. Otherwise
the database objects will be loaded again for this check.
It's obvious that you have to apply required access checks during indexing in
this setup. For example using the corresponding processor or by having different
indexes for different user roles.

In general it's recommended to *disable the Views cache*. By default the Solr
search index is updated asynchronously from Drupal, and this interferes with the
Views cache. Having the cache enabled will cause stale results to be shown, and
new content might not show up at all.

In case you really need caching (for example because you are showing some search
results on your front page) then you use the 'Search API (time based)' cache
plugin. This will make sure the cache is cleared at certain time intervals, so
your results will remain relevant. This can work well for views that have no
exposed filters and are set up by site administrators.

Since 8.x-2.0 in combination with Solr 6.6 or higher you can also use the
'Search API (tag based)' cache. But in this case you need to ensure that you
enable "Finalize index before first search" and "Wait for commit after last
finalization" in the "Solr specific index options".

But be aware that this will slow down the first search after any modification to
an index. So you have to choose if no caching or tag based caching in
combination with finalization is the better solution for your setup.
The decision depends on how frequent index modification happen or how expensive
your queries are.

If you index some drupal fields multiple times in the same index and modify the
single values differently via our API before the values get indexed, you'll
notice that Views will randomly output the same value for all of these fields if
you enabled "Retrieve result data from Solr". In this case you have to enable
the "Solr dummy fields" processor and add as many dummy fields to the index as
you require. Afterwards you should manipulate these fields via API.

Support
-------

Support is currently provided via our
[issue queue](https://www.drupal.org/project/issues/search_api_solr?version=8.x)
or on https://drupalchat.me/channel/search.

Developers
----------

Whenever you need to enhance the functionality you should do it using the API
instead of extending the SearchApiSolrBackend class!

To customize connection-specific things you should provide your own
implementation of the \Drupal\search_api_solr\SolrConnectorInterface.

A lot of customization can be achieved using YAML files and drupal's
configuration management.

We leverage the [solarium library](http://www.solarium-project.org/). You can
also interact with solarium's API using our hooks and callbacks or via event
listeners.
This way you can for example add any solr specific parameter to a query you need.

But if you create Search API Queries by yourself in code there's an easier way.
You can simply set the required parameter as option prefixed by 'solr_param_'.

So these two lines are "similar":
```
$search_api_query->setOption('solr_param_mm', '75%');

$solarium_query->setParam('mm', '75%');
```

Patches and Issues Workflow
---------------------------

Our test suite includes integration tests that require a real Solr server. This
requirement can't be provided by the drupal.org test infrastructure.
Therefore we leverage travis for our tests and had to establish a more complex
workflow:
  1. open an issue on drupal.org as usual
  2. upload the patch for being reviewed to that issue on drupal.org as usual
  3. fork https://github.com/mkalkbrenner/search_api_solr
  4. apply your patch and file a PR on github
  5. add a link to the github PR to the drupal.org issue

The PR on github will automatically be tested by travis and the test results will
be reflected in the PR conversation.

Running the test suite locally
------------------------------

This module comes with a suite of automated tests. To execute those, you just
need to have a (correctly configured) Solr instance running at the following
address:
```
http://localhost:8983/solr/d8
```
This represents a core named "d8" in a default installation of Solr.

The easiest way to get it running properly configured for the tests is to use
docker-compose. The required config is included in the module.
Have a look at the first part of this [video](https://youtu.be/NkLBTiRiQiI).
Simply run this command within the module folder:
```
docker-compose up
```

The tests themselves could be started by running something like this in your
drupal folder:
```
phpunit -c core --group search_api_solr
```
(The exact command varies on your setup and paths.)
