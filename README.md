# DevInstaller

Automated installation tool for major development packages on Linux (Ubuntu/Debian).

## Prerequisites
- Operating system: Ubuntu, Debian or derivatives
- PHP 8.2, 8.3 or 8.4 installed
- Administrator (root) rights for installing some packages

## Installation

1. **Clone the repository**

```bash
git clone https://github.com/mulertech/dev-installer.git
cd dev-installer
```

2. **Check and install PHP**

The main script checks for PHP (versions 8.2, 8.3 or 8.4). If none is installed, it offers automatic installation (requires sudo/root).

3. **Install Composer dependencies**

The script uses `composer.phar` to install the `mulertech/mterm` package if needed.

4. **Make the script executable**

Before running the script, give it execution rights:

```bash
chmod +x ./dev-installer
```

5. **Run the main script**

```bash
./dev-installer
```

## Usage

The program provides an interactive terminal interface to select packages to install, including:
- PHPStorm
- OpenSSL
- Git (with configuration)
- Git Flow
- PHP
- PHP modules
- Composer
- VirtualBox
- Vagrant
- PostgreSQL Client
- cURL
- Docker
- PNPM
- NVM (user session installation, then install Node.js via NVM: nvm install --lts)
- VSCode

Follow the on-screen instructions to choose packages and enter required information (email, username, etc.).

## How it works
- System and permissions check
- Package selection via interactive menu
- Automated installation with progress tracking
- Custom configuration for some tools (e.g., Git, Vagrant)

## Troubleshooting
- If an installation fails, the program displays an error message and stops.
- For packages requiring root privileges, run the script with `sudo`.

## License
This project is licensed under the MIT license.
