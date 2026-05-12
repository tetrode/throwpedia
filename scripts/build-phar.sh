#!/bin/bash
pushd "$(dirname "$0")" || exit 1
php -d phar.readonly=0 build-phar.php
mv ../throwpedia.phar .
popd