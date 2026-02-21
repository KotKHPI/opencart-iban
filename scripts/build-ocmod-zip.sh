#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC_DIR="${ROOT_DIR}/src"
DIST_DIR="${ROOT_DIR}/dist"

CODE="opencart_iban"
ZIP_PATH="${DIST_DIR}/${CODE}.ocmod.zip"

if [ ! -f "${SRC_DIR}/install.json" ]; then
  echo "ERROR: Missing ${SRC_DIR}/install.json"
  exit 1
fi

mkdir -p "${DIST_DIR}"
rm -f "${ZIP_PATH}"

echo "Building ${ZIP_PATH} ..."
(cd "${SRC_DIR}" && zip -r "${ZIP_PATH}" . -x "*.DS_Store" "*/.DS_Store")

echo "Done."

