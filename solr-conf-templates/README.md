The `solr-conf-templates` directory contains config-set templates for different
Solr versions.

**These are templates and are not to be used as config-sets!**

To get a functional config-set you need to generate it via the Drupal admin UI
or with `drush solr-gsc`. See INSTALL.md in the module directory for details.

**Note:** The config generator uses the 7.x template for Solr 8.x, too. The
required adjustments for Solr 8 are done during the generation.
