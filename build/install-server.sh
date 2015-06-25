#!/bin/bash

git clone --branch=3.0.0-incubating-rc1 --depth=1 https://github.com/apache/incubator-tinkerpop.git $HOME
cd $HOME/incubator-tinkerpop/
mvn clean install -Dmaven.test.skip=true
unzip $HOME/incubator-tinkerpop/gremlin-server/target/apache-gremlin-server-3.0.0-incubating-distribution.zip $HOME
