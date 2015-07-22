#!/bin/bash

BASE_DIR=$(dirname $0)/..

build=${BASE_DIR}/build
dist=${BASE_DIR}/dist

if [ "$#" != 2 ]; then
  echo "Usage: $0 <name> <domain>"
  exit 1
fi

name=$1
domain=$2

# Remove dist/
rm -rf ${dist}
if [ "$?" != "0" ]; then
  echo "Can't delete ${dist}"
  exit 1
fi

# Copy PHP files
files=`find ${build} -type f -name '*.php' | sed "s|${build}/||"`
for file in ${files}; do
  mkdir -p ${dist}/`dirname ${file}`
  cp ${build}/${file} ${dist}/${file}
  if [ "$?" != "0" ]; then
    echo "Can't copy ${file}"
    exit 1
  fi
done

# Generate WP style.css
cat > ${dist}/style.css <<EOF
/*
Theme Name: ${name}
Theme URI: https://github.com/tmadeira/dolores/
Author: Tiago Madeira
Author URI: http://tiagomadeira.com/
Description: WordPress theme for participative platforms.
Version: 0.0.1
*/
EOF
if [ "$?" != "0" ]; then
  echo "Can't create WP style.css"
  exit 1
fi

# Generate assets
assets_file=${dist}/dlib/generated_assets.php

cat > ${assets_file} <<EOF
<?php
function dolores_assets_get_theme_uri(\$file) {
  \$assets = Array(
EOF
if [ "$?" != "0" ]; then
  echo "Can't write to ${assets_file}"
  exit 1
fi

files=`find ${build} -type f -not -name '*.php' | \
  sed "s|${build}/||"`

css=`cat ${build}/style.min.css`

mkdir ${dist}/assets
for file in ${files}; do
  if [ "$file" = "style.css" ] || [ "$file" = "script.js" ]; then
    continue
  fi

  hash=`md5sum ${build}/${file} | cut -c 1-16`
  extension=`echo ${file} | cut -d'.' -f2-`
  asset=assets/${hash}.${extension}
  cp ${build}/${file} ${dist}/${asset}
  if [ "$?" != "0" ]; then
    echo "Can't copy ${file}"
    exit 1
  fi

  if [ -n "`echo "$file" | grep '\.min\.'`" ]; then
    file=`echo $file | sed 's/\.min\././'`
  fi
  echo "    '${file}' => '${asset}'," >> ${assets_file}
done

cat >> ${assets_file} <<EOF
    '' => ''
  );

  if (!array_key_exists(\$file, \$assets)) {
    die("O arquivo '\$file' nao foi encontrado.");
  }

  \$uri = get_template_directory_uri();
  return \$uri . '/' . \$assets[\$file];
}

function dolores_assets_print_style() {
  ?>
  <style type="text/css">${css}</style>
  <?php
}
EOF
if [ "$?" != "0" ]; then
  echo "Can't write to ${assets_file}"
  exit 1
fi

rsync -avu --delete -e ssh ${BASE_DIR}/dist/ \
  www-data@${domain}:~/${domain}/wp-content/themes/${name}

if [ "$?" != "0" ]; then
  echo "Can't copy files to server"
  exit 1
fi