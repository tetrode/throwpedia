#!/bin/bash
pushd "$(dirname "$0")" || exit

../bin/throwpedia -f 01-standard-flow/throwpedia.neon
../bin/throwpedia -f 02-direct-new-flow/throwpedia.neon
../bin/throwpedia -f 03-mixed-flow/throwpedia.neon
../bin/throwpedia -f 04-custom-fields-flow/throwpedia.neon
../bin/throwpedia -f 05-call-tree-flow/throwpedia.neon
