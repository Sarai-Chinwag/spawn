FROM wordpress:php8.2-fpm

# =============================================================================
# Spawn Multi-Tenant Container
# WordPress + Data Machine + swappable agent runtime
# =============================================================================

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-client \
    curl \
    git \
    unzip \
    cron \
    less \
    && rm -rf /var/lib/apt/lists/*

# WP-CLI
RUN curl -sS -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

# WP-Cron via system cron (more reliable than depending on web traffic)
RUN echo '*/1 * * * * www-data curl -sf http://localhost/wp-cron.php > /dev/null 2>&1' > /etc/cron.d/wp-cron \
    && chmod 0644 /etc/cron.d/wp-cron \
    && crontab /etc/cron.d/wp-cron

# =============================================================================
# Plugin layer — Data Machine + Spawn
# These are COPY'd at build time from release artifacts.
# Mount as volumes in dev for live editing.
# =============================================================================
COPY plugins/data-machine /var/www/html/wp-content/plugins/data-machine/
COPY plugins/spawn /var/www/html/wp-content/plugins/spawn/

# =============================================================================
# Agent runtime layer (SWAPPABLE)
# Currently a placeholder. Will be OpenCode, wp-agent, or similar.
# Designed as a separate layer so the runtime can be swapped without
# rebuilding the WordPress base.
# =============================================================================
# COPY agent-runtime/ /opt/agent/
# The agent runtime entrypoint is configured via SPAWN_AGENT_RUNTIME env var.
ENV SPAWN_AGENT_RUNTIME=""
ENV SPAWN_AGENT_ENABLED="false"

# =============================================================================
# Health check
# =============================================================================
HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=30s \
    CMD curl -sf http://localhost/ || exit 1

# Persistent data
VOLUME ["/var/www/html/wp-content/uploads"]

# Expose PHP-FPM
EXPOSE 9000
