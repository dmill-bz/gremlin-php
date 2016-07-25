#!/bin/bash

# Add environment java vars
export JAVA_HOME=/usr/lib/jvm/java-8-oracle
export JRE_HOME=/usr/lib/jvm/java-8-oracle

# Install gremlin-server
echo "Downloading & Extracting gremlin-server"
wget --no-check-certificate -O $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION-bin.zip https://archive.apache.org/dist/tinkerpop/$GREMLINSERVER_VERSION/apache-gremlin-server-$GREMLINSERVER_VERSION-bin.zip
unzip -q $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION-bin.zip -d $HOME/
# make a secure server
echo "Extracting secure gremlin-server"
mkdir $HOME/secure
unzip -q $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION-bin.zip -d $HOME/secure/
#Set grape configuration
mkdir ~/.groovy
echo '<ivysettings>
  <settings defaultResolver="downloadGrapes"/>
  <resolvers>
    <chain name="downloadGrapes">
      <filesystem name="cachedGrapes">
        <ivy pattern="${user.home}/.groovy/grapes/[organisation]/[module]/ivy-[revision].xml"/>
        <artifact pattern="${user.home}/.groovy/grapes/[organisation]/[module]/[type]s/[artifact]-[revision].[ext]"/>
      </filesystem>
      <ibiblio name="codehaus" root="http://repository.codehaus.org/" m2compatible="true"/>
      <ibiblio name="central" root="http://central.maven.org/maven2/" m2compatible="true"/>
      <ibiblio name="jitpack" root="https://jitpack.io" m2compatible="true"/>
      <ibiblio name="java.net2" root="http://download.java.net/maven/2/" m2compatible="true"/>
    </chain>
  </resolvers>
</ivysettings>' > ~/.groovy/grapeConfig.xml

# get gremlin-server configuration files
echo "Copying configuration files"
cp ./build/server/gremlin-php-script.groovy $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION/scripts/
cp ./build/server/gremlin-server-php.yaml $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION/conf/
cp ./build/server/neo4j-empty.properties $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION/conf/

# get gremlin-server secure configuration files
echo "Copying secure configuration files"
cp ./build/server/gremlin-php-script-secure.groovy $HOME/secure/apache-gremlin-server-$GREMLINSERVER_VERSION/scripts/
cp ./build/server/gremlin-server-php-secure.yaml $HOME/secure/apache-gremlin-server-$GREMLINSERVER_VERSION/conf/

# get neo4j dependencies
cat ~/.groovy/grapeConfig.xml
echo "Installing Neo4J dependency"
cd $HOME/apache-gremlin-server-$GREMLINSERVER_VERSION
bin/gremlin-server.sh -i org.apache.tinkerpop neo4j-gremlin $GREMLINSERVER_VERSION

# Start gremlin-server in the background and wait for it to be available
echo "Starting regular server"
bin/gremlin-server.sh conf/gremlin-server-php.yaml > /dev/null 2>&1 &

sleep 30

# Start the secure server
echo "Starting secure server"
cd $HOME/secure/apache-gremlin-server-$GREMLINSERVER_VERSION
bin/gremlin-server.sh conf/gremlin-server-php-secure.yaml > /dev/null 2>&1 &

# Wait for all to load
cd $TRAVIS_BUILD_DIR

sleep 30

