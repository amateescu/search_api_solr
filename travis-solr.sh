#!/usr/bin/env bash

SOLR_PORT=${SOLR_PORT:-8983}
SOLR_VERSION=${SOLR_VERSION:-6.6.1}
DEBUG=${DEBUG:-0}
SOLR_CORE=${SOLR_CORE:-core0}
HOME=${HOME:-.}

download() {
    [ -d $HOME/downloads ] || mkdir $HOME/downloads
    FILE="$2.tgz"
    if [ -f $HOME/downloads/$FILE ];
    then
       echo "File $FILE exists."
    else
       echo "File $FILE does not exist. Downloading solr from $1..."
       cd $HOME/downloads
       curl -O $1
       cd -
       echo "Downloaded!"
    fi
    tar -zxf $HOME/downloads/$FILE
}

is_solr_up(){
    echo "Checking if solr is up on http://localhost:$SOLR_PORT/solr/admin/cores"
    http_code=`echo $(curl -s -o /dev/null -w "%{http_code}" "http://localhost:$SOLR_PORT/solr/admin/cores")`
    return `test $http_code = "200"`
}

wait_for_solr(){
    while ! is_solr_up; do
        sleep 3
    done
}

run_6() {
    dir_name=$1
    solr_port=$2
    solr_core=$3
    # Install Java 8
    sudo apt-get install -y oracle-java8-installer
    sudo apt-get install -y oracle-java8-set-default
    export SOLR_JAVA_HOME=/usr/lib/jvm/java-8-oracle
    # Run solr
    echo "Running with folder $dir_name"
    echo "Starting solr on port ${solr_port}..."

    # go to the solr folder
    cd $dir_name
    bin/solr start -p $solr_port
    bin/solr start -e techproducts -p 8993
    cd ../
}

download_and_run() {
    url="http://archive.apache.org/dist/lucene/solr/$1/solr-$1.tgz"
    dir_name="solr-$1"

    download $url $dir_name

    case `echo "$1" | cut -d . -f 1` in
        6)
            add_core_6 $dir_name $SOLR_CORE $SOLR_CONFS
            run_6 $dir_name $SOLR_PORT $SOLR_CORE
            ;;
    esac
}

add_core_6() {
    dir_name=$1
    solr_core=$2
    solr_confs=$3
    # prepare our folders
    [[ -d "${dir_name}/server/solr/${solr_core}" ]] || mkdir $dir_name/server/solr/$solr_core
    [[ -d "${dir_name}/server/solr/${solr_core}/conf" ]] || mkdir $dir_name/server/solr/$solr_core/conf
    # copies custom configurations
    cp -R $solr_confs/* $dir_name/server/solr/$solr_core/conf/
    echo "name=$solr_core" > $dir_name/server/solr/$solr_core/core.properties
}

download_and_run $SOLR_VERSION