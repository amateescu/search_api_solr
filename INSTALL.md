Installing Search API Solr Search
=================================

The search_api_solr module manages its dependencies and class loader via
composer. So if you simply downloaded this module from drupal.org you have to
delete it and install it again via composer!

Simply change into Drupal directory and use composer to install search_api_solr:

```
cd $DRUPAL
composer require drupal/search_api_solr
```

Setting up Solr (single core)
-----------------------------

In order for this module to work, you need to set up a Solr server.
For this, you can either purchase a server from a web Solr hosts or set up your
own Solr server on your web server (if you have the necessary rights to do so).
If you want to use a hosted solution, a number of companies are listed on the
module's [project page](https://drupal.org/project/search_api_solr). Otherwise,
please follow the instructions below.
A more detailed set of instructions might be available at
https://drupal.org/node/1999310 .

As a pre-requisite for running your own Solr server, you'll need a Java JRE.

Download the latest version of Solr 6.x or 7.x from
http://www.apache.org/dyn/closer.cgi/lucene/solr/ and unpack the archive
somewhere outside of your web server's document tree. The unpacked Solr
directory is named `$SOLR` in these instructions.

For better performance and more features, 7.7.x or 8.x should be used!

First you have to create a Solr core for Drupal. Therefore you have to create
two directories (replace `$SOLR` and `$CORE` according to your needs):

```
mkdir $SOLR/server/solr/$CORE
mkdir $SOLR/server/solr/$CORE/conf
```

Afterwards, you have to tell SOLR about the new core by creating a
`core.properties` file:

```
echo "name=$CORE" > $SOLR/server/solr/$CORE/core.properties
```

Before starting the Solr server you will have to make sure it uses the proper
configuration files. They aren't always static but vary on your Drupal setup.
But the Search API Solr Search module will create the correct configs for you!

1. Create a Search API Server according to the search_api documentation using
   "Solr" as Backend and the connector that meets your setup.
2. Download the config.zip from the server's details page or by using
   `drush solr-gsc`
3. Extract the config.zip to the conf directory of your new core.

```
unzip config.zip -d $SOLR/server/solr/$CORE/conf
```

NOTE: You have to repeat steps 2 and 3 every time you add a new language to your
Drupal instance or add a custom Solr Field Type! The UI should inform you about
that.

NOTE: There's file called `solrcore.properties` within the set of generated
config files. If you need to fine tune some setting you should do it within this
file if possible instead of modifying `solrconf.xml.

Now you can start your Solr server:

```
$SOLR/bin/solr start
```

Afterwards, go to `http://localhost:8983/solr/#/$CORE` in your web browser to
ensure Solr is running correctly.

CAUTION! For production sites, it is vital that you somehow prevent outside
access to the Solr server. Otherwise, attackers could read, corrupt or delete
all your indexed data. Using the server as described below WON'T prevent this by
default! If it is available, the probably easiest way of preventing this is to
disable outside access to the ports used by Solr through your server's network
configuration or through the use of a firewall.
Other options include adding basic HTTP authentication or renaming the solr/
directory to a random string of characters and using that as the path.

For configuring indexes and searches you have to follow the documention of
search_api.


Setting up Solr Cloud
---------------------

Instead of a single core you have to create a collection in your Solr Cloud
instance. To do so you have to read the Solr handbook.

1. Create a Search API Server according to the search_api documentation using
   "Solr" or "Multilingual Solr" as Backend and the "Solr Cloud" or
   "Solr Cloud with Basic Auth" Connector.
2. Download the config.zip from the server's details page or by using
   `drush solr-gsc
3. Deploy the config.zip via zookeeper.


Using Linux specific Solr Packages
----------------------------------

There's file called `solrcore.properties` within the set of generated
config files. In most cases you have to adjust the `solr.install.dir` property
to match your distribution specifics path, for example

```
solr.install.dir=/opt/solr
```
