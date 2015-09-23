#!/bin/bash

# run coveralls
php $TRAVIS_BUILD_DIR/vendor/bin/coveralls -v

# configure git
git config --global user.name "Travis CI"
git config --global user.email "dylan.millikin@brightzone.fr"
git config --global push.default simple

#clone doc repo
git clone -b master --depth 1 https://github.com/PommeVerte/PommeVerte.github.io.git $HOME/PommeVerte.github.io

#generate docs
vendor/bin/apidoc api --interactive=0 src/ $HOME/PommeVerte.github.io/gremlin-php/

#update repo and push
cd $HOME/PommeVerte.github.io
git add .
git commit -m "Gremlin-php api update"
git push "https://${GH_TOKEN}@${GH_REF}"
