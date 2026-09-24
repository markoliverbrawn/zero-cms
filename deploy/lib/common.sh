#!/bin/bash
# ==============================================================================
# ZERO CMS - PROVIDER-NEUTRAL DEPLOYMENT UTILITIES
# ==============================================================================
# Sourced by every provider toolkit's config script (e.g. deploy/gcp/config.sh).
# Holds only utilities that know nothing about any cloud: logging, input
# validation, secret generation, and the persisted-secrets file. Anything that
# names a provider, resource or CLI belongs in that provider's own folder --
# see deploy/CONTRACT.md.
# ==============================================================================

# Color codes for high-contrast CLI logging
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Absolute path of the deploy/ toolkit root (the directory containing lib/, image/, gcp/ ...).
# Resolved from this file's own location, so it's correct whether the toolkit sits in this repo or
# under a host project's vendor/ directory.
DEPLOY_TOOLKIT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export DEPLOY_TOOLKIT_DIR

# Every script runs from the root of the project being deployed (the directory containing
# public/index.php) -- that directory is the image build context.
require_project_root() {
    if [ ! -f "public/index.php" ]; then
        log_error "This script must be run from the root directory of the project containing public/index.php"
        exit 1
    fi
    PROJECT_ROOT="$(pwd)"
    export PROJECT_ROOT
}

# Rejects values that could break out of the gcloud/aws CLI arguments they're interpolated into.
# Usage: validate_safe_string VALUE NAME [PATTERN]
validate_safe_string() {
    local val="$1"
    local var_name="$2"
    local pattern="${3:-^[a-zA-Z0-9_.-]+$}"
    if [[ ! "$val" =~ $pattern ]]; then
        log_error "Parameter $var_name contains unsafe characters: '$val'. Only alphanumeric characters, hyphens (-), underscores (_), dots (.), and colons (:) are allowed where appropriate."
        exit 1
    fi
}

# For values passed through a provider CLI's KEY=value,KEY=value env-var list, where only commas
# and line breaks are unsafe (the list is always a single quoted argument, never shell-evaluated).
# Never echoes the value, so it's safe for passwords.
# Usage: validate_env_value VALUE NAME
validate_env_value() {
    local val="$1" var_name="$2"
    if [[ "$val" == *","* || "$val" == *$'\n'* || "$val" == *$'\r'* ]]; then
        log_error "$var_name contains a comma or line break, which can't be passed through the deployment's env-var list."
        exit 1
    fi
}

# Prints ",NAME=value" for each named variable that is non-empty, for appending to an env-var list.
# Usage: LIST="base$(env_pairs SMTP_HOST SMTP_PORT)"
env_pairs() {
    local name
    for name in "$@"; do
        [ -n "${!name}" ] && printf ',%s=%s' "$name" "${!name}"
    done
    return 0
}

# Loads a project's non-secret settings file (plain KEY=value lines, # comments allowed). A variable
# already set in the environment wins over the file, so CI variables and one-off overrides
# (`RUN_SEED=true ./deploy/gcp/setup.sh`) still take effect. Values are assigned literally, never
# evaluated.
# Usage: load_settings FILE
load_settings() {
    local file="$1" line key value
    [ -f "$file" ] || return 0
    log_info "Loading project deployment settings from ${file}..."
    while IFS= read -r line || [ -n "$line" ]; do
        [[ "$line" =~ ^[[:space:]]*(#|$) ]] && continue
        if [[ ! "$line" =~ ^([A-Z][A-Z0-9_]*)=(.*)$ ]]; then
            log_error "Unreadable line in ${file}: '$line' (expected KEY=value)."
            exit 1
        fi
        key="${BASH_REMATCH[1]}"
        value="${BASH_REMATCH[2]}"
        value="${value%\"}"; value="${value#\"}"
        declare -p "$key" &>/dev/null && continue
        export "$key=$value"
    done < "$file"
}

# ------------------------------------------------------------------------------
# PERSISTED SECRETS (prevents credential drift across runs)
# ------------------------------------------------------------------------------
# Generated secrets are written to a gitignored, mode-600 file so every later run reuses them
# instead of rotating them. SECRETS_KNOWN collects every name loaded from or generated into that
# file; save_secrets writes them all back, so a secret that one configuration doesn't use (e.g.
# DB_PASS while DB_PROVIDER=aiven) is kept rather than silently dropped.
SECRETS_KNOWN=()
SECRETS_CHANGED=false

_secrets_remember() {
    local name="$1" known
    for known in "${SECRETS_KNOWN[@]}"; do
        [ "$known" = "$name" ] && return 0
    done
    SECRETS_KNOWN+=("$name")
}

# Usage: load_secrets FILE
load_secrets() {
    local file="$1" name
    [ -f "$file" ] || return 0
    log_info "Loading persistent deployment credentials from ${file}..."
    # shellcheck disable=SC1090
    source "$file"
    while IFS= read -r name; do
        _secrets_remember "$name"
    done < <(sed -nE 's/^export ([A-Z0-9_]+)=.*/\1/p' "$file")
}

# Generates NAME if it's empty. KIND is "password" (20 alphanumeric chars, safe to pass through
# --set-env-vars) or "token" (48 hex chars).
# Usage: ensure_secret NAME KIND
ensure_secret() {
    local name="$1" kind="$2"
    _secrets_remember "$name"
    if [ -n "${!name}" ]; then
        export "${name?}"
        return 0
    fi
    if [ "$kind" = "password" ]; then
        log_info "No $name found in environment or configuration. Generating strong random password..."
        export "$name=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 20)"
    else
        log_info "No $name found in environment or configuration. Generating secure token..."
        export "$name=$(openssl rand -hex 24)"
    fi
    SECRETS_CHANGED=true
}

# Writes every known secret back to FILE, but only if one was generated during this run.
# Usage: save_secrets FILE
save_secrets() {
    local file="$1" name
    [ "$SECRETS_CHANGED" = true ] || return 0
    log_info "Saving generated credentials persistently to secure file: ${file}..."
    mkdir -p "$(dirname "$file")"
    {
        echo "# PERSISTENT DEPLOYMENT CREDENTIALS (GITIGNORED)"
        echo "# This file preserves randomized credentials across script executions to prevent password drift."
        for name in "${SECRETS_KNOWN[@]}"; do
            echo "export ${name}=\"${!name}\""
        done
    } > "$file"
    chmod 600 "$file"
    SECRETS_CHANGED=false
    log_success "Credentials saved and secured."
}
