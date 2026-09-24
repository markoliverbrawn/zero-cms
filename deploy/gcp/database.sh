#!/bin/bash
set -e

# ==============================================================================
# ZERO CMS - GCP DATABASE
# ==============================================================================
# Creates or verifies the database through whichever option DB_PROVIDER selects
# (deploy/gcp/db/*.sh). This step never checks which option that is.
# ==============================================================================

source "$(dirname "$0")/config.sh"

db_provision
