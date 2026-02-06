# Self-Spawn Feature Specification

## Overview

Self-spawn allows users to install OpenClaw directly on their existing WordPress server via the Spawn plugin. No external VPS provisioning needed - the plugin installs OpenClaw locally.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Spawn Plugin                            │
├─────────────────────────────────────────────────────────────┤
│  SaaS Mode (existing)    │    Self-Spawn Mode (new)        │
│  - Triggers vps-prov     │    - Detects environment        │
│  - Remote provisioning   │    - Installs OpenClaw locally  │
│  - Managed credits       │    - BYOK via wp-ai-client      │
└─────────────────────────────────────────────────────────────┘
```

## New Components

### 1. Environment_Detector (`inc/class-environment-detector.php`)

Checks if the server can run OpenClaw:

```php
class Environment_Detector {
    public static function check(): array {
        return [
            'node_available' => self::check_node(),      // Node.js installed?
            'node_version'   => self::get_node_version(), // v18+ required
            'shell_access'   => self::check_shell(),     // Can run shell_exec?
            'is_vps'         => self::detect_vps(),      // VPS vs shared hosting
            'writable_home'  => self::check_home_dir(),  // Can write to ~/.openclaw?
            'systemd'        => self::check_systemd(),   // Can create service?
            'can_install'    => /* all checks pass */,
            'blockers'       => [ /* list of issues */ ],
        ];
    }
}
```

### 2. WP_AI_Client_Bridge (`inc/class-wp-ai-client-bridge.php`)

Reads credentials from wp-ai-client and maps to OpenClaw:

```php
class WP_AI_Client_Bridge {
    const WP_AI_CLIENT_OPTION = 'wp_ai_client_provider_credentials';
    
    // Provider ID -> OpenClaw env var
    const PROVIDER_MAP = [
        'anthropic' => 'ANTHROPIC_API_KEY',
        'openai'    => 'OPENAI_API_KEY',
        'google'    => 'GOOGLE_API_KEY',
    ];
    
    public static function get_credentials(): array;
    public static function has_any_credentials(): bool;
    public static function get_openclaw_env(): array;
    public static function is_wp_ai_client_active(): bool;
}
```

### 3. Self_Spawn (`inc/class-self-spawn.php`)

Main orchestrator for self-spawn:

```php
class Self_Spawn {
    public static function init(): void;
    
    // Check current state
    public static function get_status(): array;
    public static function is_openclaw_installed(): bool;
    public static function is_openclaw_running(): bool;
    
    // Installation
    public static function install(): array; // Returns success/error
    public static function configure(): array;
    public static function start_service(): array;
    
    // Management
    public static function stop_service(): array;
    public static function restart_service(): array;
    public static function uninstall(): array;
    
    // Helpers
    private static function run_command(string $cmd): array;
    private static function write_config(array $env): bool;
}
```

### 4. REST API Endpoints

Add to `REST_API` class:

```php
// Environment check
GET /wp-json/spawn/v1/self-spawn/environment

// Installation
POST /wp-json/spawn/v1/self-spawn/install
POST /wp-json/spawn/v1/self-spawn/configure
POST /wp-json/spawn/v1/self-spawn/start
POST /wp-json/spawn/v1/self-spawn/stop

// Status
GET /wp-json/spawn/v1/self-spawn/status
```

### 5. Admin UI

New section in Spawn settings OR new submenu page:

**Settings → Spawn → Self-Spawn**

```
┌────────────────────────────────────────────────────────────┐
│ Self-Spawn: Deploy AI Agent                                │
├────────────────────────────────────────────────────────────┤
│                                                            │
│ Environment Check:                                         │
│ ✅ Node.js v22.12.0 installed                              │
│ ✅ Shell access available                                  │
│ ✅ VPS detected (can run services)                         │
│ ✅ Home directory writable                                 │
│ ✅ systemd available                                       │
│                                                            │
│ AI Credentials (via wp-ai-client):                         │
│ ✅ Anthropic API key configured                            │
│ ⚠️ OpenAI API key not configured                           │
│ ⚠️ Google API key not configured                           │
│                                                            │
│ [Configure AI Credentials] → links to wp-ai-client page    │
│                                                            │
│ ──────────────────────────────────────────────────────     │
│                                                            │
│ OpenClaw Status: Not Installed                             │
│                                                            │
│ [Install OpenClaw]                                         │
│                                                            │
│ OR if installed:                                           │
│                                                            │
│ OpenClaw Status: Running ✅                                │
│ Gateway URL: http://127.0.0.1:18789                        │
│                                                            │
│ [Stop] [Restart] [Uninstall]                               │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

## Installation Flow

1. User clicks "Install OpenClaw"
2. Plugin checks environment (if not already checked)
3. If blockers exist, show error with guidance
4. Plugin executes installation:
   ```bash
   # Install OpenClaw via npm
   npm install -g openclaw
   
   # Create config directory
   mkdir -p ~/.openclaw
   
   # Write config with credentials from wp-ai-client
   # -> ~/.openclaw/openclaw.json
   
   # Create systemd service (if available)
   # OR run as background process
   
   # Start OpenClaw
   openclaw gateway start
   ```
5. Plugin verifies OpenClaw is running
6. Plugin stores local gateway URL for chat block

## wp-ai-client Integration

### Option A: Bundle as Composer dependency

```json
{
  "require": {
    "wordpress/wp-ai-client": "^0.4"
  }
}
```

Then initialize in Spawn:
```php
add_action('init', function() {
    if (class_exists('WordPress\AI_Client\AI_Client')) {
        \WordPress\AI_Client\AI_Client::init();
    }
});
```

### Option B: Recommend as separate plugin

Show notice if wp-ai-client not active:
```
"For AI credentials management, please install the WordPress AI Client plugin."
```

**Recommendation: Option A (bundle)** - smoother UX, single install.

## Chat Block Integration

The existing chat block needs to detect which mode we're in:

```php
// In chat block render or API
if (Self_Spawn::is_openclaw_installed() && Self_Spawn::is_openclaw_running()) {
    // Use local OpenClaw
    $gateway_url = 'http://127.0.0.1:18789';
} elseif ($customer = Database::get_customer_by_user()) {
    // Use customer's remote OpenClaw (SaaS mode)
    $gateway_url = 'https://' . $customer['domain'] . ':18789';
} else {
    // Not configured
    return 'Please configure OpenClaw first.';
}
```

## Security Considerations

1. **Capability check**: Only `manage_options` can install/configure
2. **Nonce verification**: All admin actions use nonces
3. **Sanitization**: All shell commands are escaped
4. **No arbitrary command execution**: Only predefined commands run
5. **Credentials isolation**: wp-ai-client credentials stay in WP, copied to OpenClaw config

## File Changes Summary

### New Files
- `inc/class-environment-detector.php`
- `inc/class-wp-ai-client-bridge.php`
- `inc/class-self-spawn.php`

### Modified Files
- `spawn.php` - add Self_Spawn::init()
- `inc/autoload.php` - already handles new classes
- `inc/class-admin.php` - add self-spawn settings section
- `inc/class-rest-api.php` - add self-spawn endpoints
- `composer.json` - add wp-ai-client dependency (create if needed)

## Testing Checklist

- [ ] Environment detection on VPS
- [ ] Environment detection on shared hosting (should block)
- [ ] wp-ai-client credential reading
- [ ] OpenClaw installation
- [ ] OpenClaw service start/stop/restart
- [ ] Config generation with credentials
- [ ] Chat block works with local OpenClaw
- [ ] Admin UI shows correct status
- [ ] Error handling for failed installs
