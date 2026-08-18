#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${ROOT_DIR}/dist"
STAGE_DIR="${BUILD_DIR}/petrik-news-workflow"
ZIP_FILE="${BUILD_DIR}/petrik-news-workflow.zip"

rm -rf "${STAGE_DIR}" "${ZIP_FILE}"
mkdir -p "${STAGE_DIR}"

rsync -a \
  --exclude '.git/' \
  --exclude '.github/' \
  --exclude '.idea/' \
  --exclude '.vscode/' \
  --exclude 'dist/' \
  --exclude 'scripts/' \
  --exclude '*.zip' \
  "${ROOT_DIR}/" "${STAGE_DIR}/"

(
  cd "${BUILD_DIR}"
  zip -qr "$(basename "${ZIP_FILE}")" "petrik-news-workflow"
)

rm -rf "${STAGE_DIR}"
echo "Kész: ${ZIP_FILE}"
