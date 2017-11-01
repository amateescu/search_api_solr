Upgrading from search_api_solr_multilingual 8.x-1.x to search_api_solr 8.x-2.x
==============================================================================

Before starting the upgrade, ensure that all your composer managed dependencies
are up to date, for example run `composer install` or `composer update`
depending on your setup and update your drupal installation by running
`drush updb` or visiting update.php.

Export all Search API server configs that use a multilingual solr backend.
Export all Search API index configs that use such a server.

Uninstall the search_api_solr_multilingual module from drupal (ignoring these
Exceptions, see https://www.drupal.org/node/2909645):
  `drush pm-uninstall search_api_solr_multilingual`

Remove the module from your composer setup:
  `composer remove drupal/search_api_solr_multilingual`

Upgrade to Search API Solr 8.x-2.x (adjust the version to your needs, for
example ~2.0-dev):
  `composer require drupal/search_api_solr ~2.0`

Re-import all Search API server configs that use a multilingual solr backend.
Re-import all Search API index configs that use such a server.

Run all the update hooks by running `drush updb`.

Re-install the latest solr field type configs:
  `drush sasm-reinstall-ft`

Generate up-to-date configs for all servers and deploy them to your solr
servers:
  `drush solr-gsc ...`

Make sure you export the updated configuration, by running `drush cex sync`.
