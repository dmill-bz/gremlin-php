#!/bin/bash

if [ "$TRAVIS_BRANCH" == "master" ] && [ "$TRAVIS_PHP_VERSION" == "5.6" ] && [ "$GRAPHSON_VERSION" == "3.0" ]; then

    # run coveralls
    php $TRAVIS_BUILD_DIR/vendor/bin/php-coveralls -v

    # configure git
    git config --global user.name "Travis CI"
    git config --global user.email "dylan.millikin@brightzone.fr"
    git config --global push.default simple

    #clone doc repo
    git clone -b master --depth 1 https://github.com/PommeVerte/PommeVerte.github.io.git $TRAVIS_BUILD_DIR/build/logs/PommeVerte.github.io

    #generate docs
    $TRAVIS_BUILD_DIR/vendor/bin/apidoc api --interactive=0 $TRAVIS_BUILD_DIR/src/ $TRAVIS_BUILD_DIR/build/logs/PommeVerte.github.io/gremlin-php/
    [ -f $HOME/PommeVerte.github.io/gremlin-php/errors.txt ] && cat $HOME/PommeVerte.github.io/gremlin-php/errors.txt

fi
