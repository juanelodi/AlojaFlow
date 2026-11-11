#!/usr/bin/env bash
# AlojaFlow · Copyright (C) 2026 Juan Galeana Durán · SPDX-License-Identifier: FSL-1.1-ALv2
#
# Arranca el Apache de Wamp sobre una carpeta, en primer plano, para probar el instalador:
#   desarrollo/apache-prueba/arrancar.sh E:/carpeta-de-prueba 8004
set -euo pipefail

RAIZ="$1"
PUERTO="${2:-8004}"
APACHE="C:/wamp64/bin/apache/apache2.4.65"
PHP="C:/wamp64/bin/php/php8.3.28"
TRABAJO="$(cygpath -m "${TMPDIR:-/tmp}")/alojaflow-apache-$PUERTO"
AQUI="$(cd "$(dirname "$0")" && pwd)"

mkdir -p "$TRABAJO"
sed -e "s#@@APACHE@@#$APACHE#g" -e "s#@@PHP@@#$PHP#g" -e "s#@@RAIZ@@#$RAIZ#g" -e "s#@@PUERTO@@#$PUERTO#g" -e "s#@@TRABAJO@@#$TRABAJO#g" \
    "$AQUI/httpd.conf.plantilla" > "$TRABAJO/httpd.conf"

PATH="$PHP:$PATH" exec "$APACHE/bin/httpd.exe" -f "$TRABAJO/httpd.conf" -X
