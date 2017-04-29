Search API Solr Datasource
==========================
Search API Solr Datasource is a Drupal 8 module that provides a datasource plugin
for Search API indexes.  The plugin exposes documents from any Solr server to the
index, providing the ability to search and view those documents, regardless of
whether or not they were originally indexed by Drupal.

Warning
-------
This module is still under development and should be considered _pre-alpha,
unstable, and unsecure_.  It is recommended that you only use this module in
development environments.

Contributing
------------
Issues, patches, and pull requests are welcome in the GitHub repository.  This
module is being developed exclusively during my personal free time.  If you would
like to speed this project along, feel free to contribute code or contact me,
dcameron, to sponsor the development.

Installation and Setup
----------------------
These steps have not been tested from a base Drupal install, but they should work
to get a development environment running.

1. Download the module or clone it with Git into your Drupal 8 site's modules
   directory.  Enable the module.
2. Add and configure a Search API server that uses the Solr backend.
   1. Configure this server with the connection information for the Solr server
      that contains the indexed data you want to display.
   2. It may be necessary to check "Retrieve result data from Solr."
3. Add a Search API index for the server you configured in step 2.
   1. Check "Solr Document" under the list of Data Sources.
   2. Configure the Solr Document Datasource by specifying the name of a unique ID
      field from your server's schema.
   3. Finish configuring the index by selecting the appropriate server.  Check
      "Read only" under the Index Options.
4. On the index's Fields tab, add the fields you would like to have shown in Views
   or any other display.  Fields must be added here first before they can be
   displayed, despite appearing in the Add fields list in Views.
5. Create a view or some other display to see the documents from your server.  If
   you are using Views, set it up as you would for any other Search API
   datasource.
   1. In the Add view wizard, select "Index INDEX_NAME" under the View
      Settings.
   2. Configure the view to display fields.
   3. Add the fields that you want to display.

Known Issues
------------
Check the [issue queue](https://github.com/dcameron/search_api_solr_datasource/issues)
for an up-to-date list.
* Search API Solr's backend unsets the ID field that you configure in the
  datasource settings from the results arrays, making it unavailable for display.
