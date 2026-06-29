# Flowtriq for Pterodactyl

[![Pterodactyl 1.11+](https://img.shields.io/badge/Pterodactyl-1.11%2B-blue.svg)](https://pterodactyl.io)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: Proprietary](https://img.shields.io/badge/License-Proprietary-lightgrey.svg)](LICENSE)

> **[Integration Guide](https://flowtriq.com/integrations/pterodactyl)** | **[Documentation](https://flowtriq.com/docs)** | **[Sign Up](https://flowtriq.com/signup)**

DDoS detection and service port protection for [Pterodactyl Panel](https://pterodactyl.io), powered by [Flowtriq](https://flowtriq.com). Automatically syncs game server ports, detects attacks, and deploys on-node firewall rules without manual configuration.

---

## How It Works

When a player creates a game server in Pterodactyl, this addon automatically:

1. **Detects the game's ports** (primary + additional allocations) and protocol (TCP/UDP/both)
2. **Syncs them to Flowtriq** as service ports so the agent knows what traffic is legitimate
3. **Monitors for DDoS attacks** targeting those ports or anything else on the node
4. **Deploys firewall blocks** against attack sources in real-time
5. **Shows attack status** to server owners in their Pterodactyl panel

When a server is deleted or ports change, the addon updates Flowtriq automatically. Server owners never touch a firewall rule.

## Features

- **Automatic port sync** -- game server allocations are synced to Flowtriq service ports on create, update, and delete
- **Protocol detection** -- knows Minecraft is TCP, Rust is UDP, FiveM is both, etc. (20+ games preconfigured)
- **System port protection** -- Wings daemon (8080) and SFTP (2022) are always safelisted
- **Two deployment modes**:
  - `per_wings` -- one ftagent per Wings node, each mapped to its own Flowtriq node (recommended)
  - `central` -- one ftagent on the panel server with all ports aggregated
- **Admin panel** -- configure API credentials, deployment mode, and node mappings from the Pterodactyl admin UI
- **Server owner view** -- players see DDoS status, active incidents, and protection status for their server
- **Background jobs** -- polls Flowtriq for node status and active incidents on configurable intervals
- **Artisan commands** -- `flowtriq:install`, `flowtriq:sync`, `flowtriq:status` for CLI management

## Requirements

- Pterodactyl Panel 1.11+ (Laravel 10/11)
- PHP 8.1+
- A [Flowtriq](https://flowtriq.com) account with the agent installed on your Wings node(s)

## Installation

Download the latest release and extract into your Pterodactyl installation:

```bash
cd /var/www/pterodactyl
unzip flowtriq-pterodactyl-v1.0.0.zip -d .
```

Install dependencies and register the addon:

```bash
composer require flowtriq/pterodactyl-addon
php artisan migrate
php artisan flowtriq:install
```

The install command will prompt for your Flowtriq API URL and deploy token (found in **Flowtriq > Settings > API**).

## Configuration

### Environment Variables

```env
FLOWTRIQ_API_URL=https://flowtriq.com
FLOWTRIQ_DEPLOY_TOKEN=your-deploy-token
FLOWTRIQ_MODE=per_wings
```

### Admin Panel

After installation, go to **Admin > Flowtriq** to:
- Set API credentials
- Choose deployment mode
- Map Wings nodes to Flowtriq nodes
- Configure service port sensitivity and response mode

### Config File

Publish the config for full control:

```bash
php artisan vendor:publish --tag=flowtriq-config
```

See `config/flowtriq.php` for all options including protocol detection maps, system ports, and polling intervals.

## Protocol Detection

The addon automatically detects game protocols from Pterodactyl egg names:

| Game | Protocol | Game | Protocol |
|------|----------|------|----------|
| Minecraft (Java) | TCP | Rust | UDP |
| Minecraft (Bedrock) | UDP | ARK | UDP |
| FiveM / RedM | Both | CS2 / CSGO | Both |
| Garry's Mod | UDP | Valheim | UDP |
| Terraria | TCP | Palworld | UDP |
| TeamSpeak | UDP | Squad | UDP |

Games not in the list default to `both` (TCP + UDP). You can extend the map in `config/flowtriq.php`.

## Artisan Commands

```bash
# Interactive setup wizard
php artisan flowtriq:install

# Sync all server allocations to Flowtriq
php artisan flowtriq:sync

# Check connection and node status
php artisan flowtriq:status
```

## Architecture

```
Player creates server in Pterodactyl
        |
        v
AllocationObserver fires
        |
        v
ServicePortSyncService collects:
  - Primary port + all additional allocations
  - Egg name -> protocol detection
  - System ports (Wings 8080, SFTP 2022)
        |
        v
FlowtriqApiClient PATCH /nodes/{uuid}
  -> Updates service_ports on the Flowtriq node
        |
        v
ftagent picks up new config within 5 minutes
  -> Installs iptables accounting chains
  -> Only traffic to registered ports is "service"
  -> Everything else classified as non-service
  -> Threshold crossing triggers detection + blocking
```

## Support

- Documentation: [flowtriq.com/docs](https://flowtriq.com/docs)
- Issues: [github.com/Flowtriq/flowtriq-pterodactyl/issues](https://github.com/Flowtriq/flowtriq-pterodactyl/issues)
- Email: support@flowtriq.com
- Discord: [discord.gg/PjjaHk5T26](https://discord.gg/PjjaHk5T26)

## Get Started

Start your free 14-day trial at [flowtriq.com/signup](https://flowtriq.com/signup).

## License

Proprietary. See [LICENSE](LICENSE) for details.

---

Built by [Flowtriq](https://flowtriq.com) - Real-time DDoS detection and mitigation.
