#!/bin/bash

# Add environment java vars
export JAVA_HOME=/usr/lib/jvm/java-8-oracle
export JRE_HOME=/usr/lib/jvm/java-8-oracle

SERVER_INSTALL_DIR=$HOME
# Depending on the TP version file names may change 3.1.3 and 3.2.1 use old file names.
if [ $GREMLINSERVER_VERSION = "3.2.1" -o $GREMLINSERVER_VERSION = "3.1.3" ]
then
    TPFILENAME=apache-gremlin-server-$GREMLINSERVER_VERSION
else
    TPFILENAME=apache-tinkerpop-gremlin-server-$GREMLINSERVER_VERSION
fi

# Depending on the TP version we will want to use different configuration files for the server.
if ! [ $GREMLINSERVER_VERSION \< "3.3.0" ]
then
    if ! [ $GREMLINSERVER_VERSION \< "3.4.0" ]
    then
        TP_CONF_DIR="3.4.x"
    else
        TP_CONF_DIR="3.3.x"
    fi
else
    TP_CONF_DIR="3.2.x"
fi

# Install gremlin-server
echo "Downloading & Extracting gremlin-server"
wget --no-check-certificate -O $SERVER_INSTALL_DIR/$TPFILENAME-bin.zip https://archive.apache.org/dist/tinkerpop/$GREMLINSERVER_VERSION/$TPFILENAME-bin.zip
unzip -q $SERVER_INSTALL_DIR/$TPFILENAME-bin.zip -d $SERVER_INSTALL_DIR/
# make a secure server
echo "Extracting secure gremlin-server"
mkdir $SERVER_INSTALL_DIR/secure
unzip -q $SERVER_INSTALL_DIR/$TPFILENAME-bin.zip -d $SERVER_INSTALL_DIR/secure/
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
cp ./build/server/$TP_CONF_DIR/gremlin-php-script.groovy $SERVER_INSTALL_DIR/$TPFILENAME/scripts/

if [ $GRAPHSON_VERSION = "3.0" ]
then
    cp ./build/server/$TP_CONF_DIR/gremlin-server-php-graphson.yaml $SERVER_INSTALL_DIR/$TPFILENAME/conf/gremlin-server-php.yaml
else
    cp ./build/server/$TP_CONF_DIR/gremlin-server-php.yaml $SERVER_INSTALL_DIR/$TPFILENAME/conf/
fi

cp ./build/server/$TP_CONF_DIR/neo4j-empty.properties $SERVER_INSTALL_DIR/$TPFILENAME/conf/

# get gremlin-server secure configuration files
echo "Copying secure configuration files"
cp ./build/server/$TP_CONF_DIR/gremlin-php-script-secure.groovy $SERVER_INSTALL_DIR/secure/$TPFILENAME/scripts/
if [ $GRAPHSON_VERSION = "3.0" ]
then
    cp ./build/server/$TP_CONF_DIR/gremlin-server-php-secure-graphson.yaml $SERVER_INSTALL_DIR/secure/$TPFILENAME/conf/gremlin-server-php-secure.yaml
else
    cp ./build/server/$TP_CONF_DIR/gremlin-server-php-secure.yaml $SERVER_INSTALL_DIR/secure/$TPFILENAME/conf/
fi
# set up keys if necessary
echo "Setting up key for secure testing"
keytool -genkey -noprompt -alias localhost -keyalg RSA -keystore $SERVER_INSTALL_DIR/secure/$TPFILENAME/server.jks -storepass changeit -keypass changeit -dname "CN=testing"


# get neo4j dependencies
cat ~/.groovy/grapeConfig.xml
echo "Installing Neo4J dependency"
cd $SERVER_INSTALL_DIR/$TPFILENAME

if ! [ $GREMLINSERVER_VERSION \< "3.4.0" ]
then
    bin/gremlin-server.sh install org.apache.tinkerpop neo4j-gremlin $GREMLINSERVER_VERSION
else
    bin/gremlin-server.sh -i org.apache.tinkerpop neo4j-gremlin $GREMLINSERVER_VERSION
fi

# Start gremlin-server in the background and wait for it to be available
echo "Starting regular server"
bin/gremlin-server.sh conf/gremlin-server-php.yaml > /dev/null 2>&1 &

sleep 30

# Start the secure server
echo "Starting secure server"
cd $SERVER_INSTALL_DIR/secure/$TPFILENAME
bin/gremlin-server.sh conf/gremlin-server-php-secure.yaml > /dev/null 2>&1 &

# Wait for all to load
cd $TRAVIS_BUILD_DIR

sleep 30
