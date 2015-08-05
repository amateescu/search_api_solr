#!/bin/bash

set -e $DRUPAL_TI_DEBUG

# Ensure the right Drupal version is installed.
# Note: This function is re-entrant.
drupal_ti_ensure_drupal

# Add needed dependencies.
cd "$DRUPAL_TI_DRUPAL_DIR"

# These variables come from environments/drupal-*.sh
mkdir -p "$DRUPAL_TI_MODULES_PATH"
cd "$DRUPAL_TI_MODULES_PATH"

# Download Search API and solr
git clone --branch 8.x-1.x http://git.drupal.org/project/search_api.git modules/search_api
curl -sSL https://raw.githubusercontent.com/nickveenhof/travis-solr/master/travis-solr.sh | bash

# Make sure the apache root is in our wanted directory
echo "$(curl -fsSL https://gist.githubusercontent.com/nickveenhof/11386315/raw/b8abaf9304fe12b5cc7752d39c29c1edae8ac2e6/gistfile1.txt)" | sed -e "s,PATH,$TRAVIS_BUILD_DIR/../drupal,g" | sudo tee /etc/apache2/sites-available/default > /dev/null
