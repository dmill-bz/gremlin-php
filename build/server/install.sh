#!/bin/bash

# Add environment java vars
export JAVA_HOME=/usr/lib/jvm/java-8-oracle
export JRE_HOME=/usr/lib/jvm/java-8-oracle

# Install gremlin-server
wget --no-check-certificate -O $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating-bin.zip https://www.apache.org/dist/incubator/tinkerpop/$GREMLINSERVER_VERSION-incubating/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating-bin.zip
unzip $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating-bin.zip -d $HOME/
# make a secure server
mkdir $HOME/secure
unzip $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating-bin.zip -d $HOME/secure/

# get gremlin-server configuration files
cp ./build/server/gremlin-php-script.groovy $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating/scripts/
cp ./build/server/gremlin-server-php.yaml $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating/conf/
cp ./build/server/neo4j-empty.properties $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating/conf/

# get gremlin-server secure configuration files
cp ./build/server/gremlin-php-script-secure.groovy $HOME/secure/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating/scripts/
cp ./build/server/gremlin-server-php-secure.yaml $HOME/secure/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating/conf/

# get neo4j dependencies
cd $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating
bin/gremlin-server.sh -i org.apache.tinkerpop neo4j-gremlin $GREMLINSERVER_VERSION-incubating

# Start gremlin-server in the background and wait for it to be available
bin/gremlin-server.sh conf/gremlin-server-php.yaml > /dev/null 2>&1 &

sleep 30

# Start the secure server
cd $HOME/secure/apache-gremlin-server-$GREMLINSERVER_VERSION-incubating
bin/gremlin-server.sh conf/gremlin-server-php-secure.yaml > /dev/null 2>&1 &

# Wait for all to load
cd $TRAVIS_BUILD_DIR

sleep 30

