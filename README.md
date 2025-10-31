# ServerCommandBundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/server-command-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/server-command-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/php-monorepo.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/php-monorepo)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/server-command-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/server-command-bundle)
[![Coverage Status](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)
[![License](https://img.shields.io/packagist/l/tourze/server-command-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/server-command-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/server-command-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/server-command-bundle)

A comprehensive server command execution bundle for Symfony applications, providing 
remote SSH command execution, file transfer, web terminal emulator, and server 
management capabilities.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Routing Configuration](#routing-configuration)
- [Quick Start](#quick-start)
- [API Endpoints](#api-endpoints)
- [SSH Authentication](#ssh-authentication)
- [Development & Testing](#development-testing)
- [Advanced Usage](#advanced-usage)
- [Advanced Features](#advanced-features)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [References](#references)
- [License](#license)

## Features

- **Remote SSH Command Execution** - Execute commands on remote servers with 
  full status tracking
- **Web Terminal Emulator** - Browser-based terminal interface with command history
- **File Transfer** - SFTP-based file upload/download with progress tracking
- **Docker Environment Management** - Automated Docker installation and configuration
- **DNS Configuration Management** - DNS pollution detection and fixing
- **Multiple Authentication Methods** - SSH key authentication with password fallback
- **Asynchronous Processing** - Message queue integration for long-running commands
- **EasyAdmin Integration** - Complete admin interface for command management
- **Comprehensive Logging** - Detailed execution logs and error tracking

## Requirements

- PHP 8.1 or higher
- Symfony 6.4 or higher
- ext-filter extension
- tourze/server-node-bundle for node management

## Installation

```bash
composer require tourze/server-command-bundle
```

### Bundle Registration

```php
// config/bundles.php
return [
    // ...
    ServerCommandBundle\ServerCommandBundle::class => ['all' => true],
    // ...
];
```

## Routing Configuration

Customize routing prefixes if needed:

```yaml
# config/routes.yaml
server_command_terminal:
    resource: "@ServerCommandBundle/Controller/TerminalController.php"
    type: attribute
    prefix: /admin/terminal
```

## Quick Start

### Remote Command Execution

```php
<?php

use ServerNodeBundle\Entity\Node;
use ServerCommandBundle\Service\RemoteCommandService;

// Get the service
$service = $container->get(RemoteCommandService::class);

// Create a command
$command = $service->createCommand(
    $node,              // ServerNodeBundle\Entity\Node instance
    'System Update',    // Command name
    'apt update',       // Command content
    '/root',           // Working directory
    true,              // Use sudo
    300,               // Timeout (seconds)
    ['system']         // Tags
);

// Execute synchronously
$result = $service->executeCommand($command);

// Execute asynchronously via message queue
$service->scheduleCommand($command);
```

### File Transfer

```php
<?php

use ServerCommandBundle\Service\RemoteFileService;

// Get the service
$service = $container->get(RemoteFileService::class);

// Create file transfer
$transfer = $service->createTransfer(
    $node,                          // Target node
    'Upload Config',                // Transfer name
    '/local/path/config.json',      // Local file path
    '/remote/path/config.json',     // Remote file path
    true,                          // Use sudo
    300,                           // Timeout
    ['config']                     // Tags
);

// Execute transfer
$result = $service->executeTransfer($transfer);
```

### Web Terminal Usage

1. Access the admin panel
2. Navigate to "Server Management" -> "Terminal"
3. Select your server node
4. Execute commands in the web-based terminal

**Terminal Features:**
- Multiple server node support
- Command history with arrow key navigation
- Real-time execution status and timing
- Automatic execution logging
- Terminal-like display styling

## API Endpoints

### Terminal API

```bash
# Execute command
POST /admin/terminal/execute
{
    "nodeId": "1",
    "command": "ls -la",
    "workingDir": "/root",
    "useSudo": false
}

# Get command history
GET /admin/terminal/history/{nodeId}
```

## Console Commands

```bash
# Execute specific command by ID
php bin/console server-node:remote-command:execute <command-id>

# Create and execute new command
php bin/console server-node:remote-command:execute \
    --node-id=1 \
    --name="System Update" \
    --command="apt update && apt upgrade -y" \
    --working-dir="/root" \
    --sudo \
    --timeout=600

# Execute all pending commands
php bin/console server-node:remote-command:execute --execute-all-pending
```

## SSH Authentication

The bundle supports multiple SSH authentication methods with intelligent fallback:

### 1. SSH Key Authentication (Recommended)

```php
<?php

use ServerNodeBundle\Entity\Node;

$node = new Node();
$node->setSshHost('192.168.1.100');
$node->setSshPort(22);
$node->setSshUser('root');
$node->setSshPrivateKey('-----BEGIN PRIVATE KEY-----
...
-----END PRIVATE KEY-----');
```

**Supported key formats:**
- RSA private keys (`-----BEGIN RSA PRIVATE KEY-----`)
- OpenSSH private keys (`-----BEGIN OPENSSH PRIVATE KEY-----`)
- PKCS#8 private keys (`-----BEGIN PRIVATE KEY-----`)
- Encrypted private keys (`-----BEGIN ENCRYPTED PRIVATE KEY-----`)

### 2. Password Authentication

```php
<?php

use ServerNodeBundle\Entity\Node;

$node = new Node();
$node->setSshHost('192.168.1.100');
$node->setSshPort(22);
$node->setSshUser('root');
$node->setSshPassword('your_password');
```

### 3. Hybrid Authentication (Smart Fallback)

```php
<?php

use ServerNodeBundle\Entity\Node;

$node = new Node();
$node->setSshHost('192.168.1.100');
$node->setSshPort(22);
$node->setSshUser('root');
$node->setSshPrivateKey('-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----');
$node->setSshPassword('fallback_password'); // Used if key auth fails
```

**Authentication Flow:**
1. **Priority Key Auth**: If private key is configured, try key authentication first
2. **Fallback Password**: If key auth fails or no key configured, try password authentication
3. **Connection Failure**: If all methods fail, throw connection exception

## Development & Testing

### Data Fixtures

The bundle provides comprehensive data fixtures for development and testing:

```bash
# Load all fixtures
php bin/console doctrine:fixtures:load --group=server_ssh

# Load SSH key authentication demo data
php bin/console doctrine:fixtures:load --group=ssh-key-auth

# Load terminal command demo data
php bin/console doctrine:fixtures:load --group=terminal-commands

# Append data (without clearing existing)
php bin/console doctrine:fixtures:load --append --group=server_ssh
```

**Fixture Categories:**
- **Scripts**: System info, disk cleanup, service status checks
- **Execution Records**: Various command statuses and results
- **SSH Authentication**: Key auth tests and fallback demonstrations
- **Terminal History**: Common Linux command examples

## Advanced Usage

### Custom Command Handlers

Create custom command handlers for specialized operations:

```php
<?php

use ServerCommandBundle\Service\RemoteCommandService;
use ServerCommandBundle\Entity\RemoteCommand;

class CustomCommandHandler
{
    public function __construct(
        private RemoteCommandService $commandService
    ) {}

    public function deployApplication(Node $node, string $version): void
    {
        $command = new RemoteCommand();
        $command->setNode($node)
            ->setName("Deploy Application v{$version}")
            ->setCommand("cd /var/www && git pull && git checkout {$version}")
            ->setWorkingDirectory('/var/www')
            ->setUseSudo(false);

        $this->commandService->executeCommand($command);
    }
}
```

### Batch Operations

Execute multiple commands across multiple nodes:

```php
<?php

use ServerCommandBundle\Service\BatchCommandService;

// Execute same command on multiple nodes
$batchService->executeOnNodes(
    nodes: [$node1, $node2, $node3],
    command: 'systemctl restart nginx',
    parallel: true
);

// Execute different commands in sequence
$batchService->executeSequence([
    ['node' => $node1, 'command' => 'service mysql stop'],
    ['node' => $node1, 'command' => 'mysqldump --all-databases > backup.sql'],
    ['node' => $node1, 'command' => 'service mysql start'],
]);
```

### Event Listeners

Listen to command execution events:

```php
<?php

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use ServerCommandBundle\Event\CommandExecutedEvent;

class CommandAuditSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CommandExecutedEvent::class => 'onCommandExecuted',
        ];
    }

    public function onCommandExecuted(CommandExecutedEvent $event): void
    {
        $command = $event->getCommand();
        $this->logger->info('Command executed', [
            'command' => $command->getCommand(),
            'node' => $command->getNode()->getName(),
            'status' => $command->getStatus(),
        ]);
    }
}
```

## Advanced Features

### Docker Environment Management

```php
<?php

use ServerCommandBundle\Service\Quick\DockerEnvironmentService;

// Automated Docker installation and configuration
$dockerService = $container->get(DockerEnvironmentService::class);
$dockerService->checkDockerEnvironment($progressModel, $node);
```

### DNS Configuration Management

```php
<?php

use ServerCommandBundle\Service\Quick\DnsConfigurationService;

// DNS pollution detection and fixing
$dnsService = $container->get(DnsConfigurationService::class);
$dnsService->checkAndFixDns($progressModel, $node);
```

## Troubleshooting

### Common Issues

1. **SSH Connection Failures**
    - Verify server node SSH connection settings
    - Check firewall rules for SSH access
    - Validate SSH key format and permissions

2. **Command Execution Timeouts**
    - Adjust timeout values when creating commands
    - Check network connectivity stability
    - Monitor server resource usage

3. **Permission Errors**
    - Verify SSH user has required command permissions
    - Check sudo configuration for privileged operations
    - Validate file/directory access rights

### Security Considerations

- Use SSH key authentication when possible
- Implement proper access controls for terminal functionality
- Monitor and log all command executions
- Regularly rotate SSH keys and passwords
- Limit sudo access to necessary commands only

## Contributing

Contributions are welcome! Please ensure:
- Code follows PSR-12 standards
- All tests pass (`./vendor/bin/phpunit`)
- PHPStan analysis is clean (`./vendor/bin/phpstan analyse`)
- Documentation is updated for new features

## References

- [phpseclib3 Documentation](https://phpseclib.com/docs/ssh)
- [Symfony Messenger Component](https://symfony.com/doc/current/messenger.html)
- [EasyAdmin Bundle](https://symfony.com/doc/current/bundles/EasyAdminBundle/index.html)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
