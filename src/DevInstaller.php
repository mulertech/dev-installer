<?php

namespace Mulertech\DevInstaller;

class DevInstaller
{
    public static function start(): void
    {
        (new self())->run();
    }

    public function run(): void
    {
        if (!$this->checkOperatingSystem()) {
            return;
        }

        system('clear');
        system('apt-get update');

        $availablePackages = $this->getAvailablePackages();
        $selectedPackages = $this->displayMenu($availablePackages);

        if (empty($selectedPackages)) {
            $this->endOfProgram("No package selected");
            return;
        }

        $this->installSelectedPackages($selectedPackages);
        $this->endOfProgram("Installations completed");
    }

    private function checkOperatingSystem(): bool
    {
        $os = PHP_OS;

        if (stripos($os, 'WIN') === 0) {
            $this->endOfProgram("This installer is designed for Linux/Ubuntu systems only. Current OS: $os");
            return false;
        }

        // On Linux, verify if it's specifically Ubuntu or a Debian-based distro
        if (file_exists('/etc/os-release')) {
            $osInfo = parse_ini_file('/etc/os-release');
            $distro = $osInfo['ID'] ?? '';

            if (!in_array($distro, ['ubuntu', 'debian', 'linuxmint', 'elementary', 'zorin'])) {
                echo "Warning: This installer is optimized for Ubuntu/Debian-based distributions." . PHP_EOL;
                echo "Your distribution ($distro) may not be fully compatible." . PHP_EOL;

                if ($this->askQuestion("Do you want to continue anyway? (y/n): ") !== 'y') {
                    $this->endOfProgram("Installation aborted by user.");
                    return false;
                }
            }
        }

        return true;
    }

    private function getAvailablePackages(): array
    {
        return [
            1 => ['name' => 'Git with configuration', 'method' => 'installGitWithConfig'],
            2 => ['name' => 'Composer', 'method' => 'installComposer'],
            3 => ['name' => 'PHP', 'method' => 'installPhp'],
            4 => ['name' => 'Modules PHP', 'method' => 'installPhpModules'],
            5 => ['name' => 'PHPStorm', 'method' => 'installPhpStorm'],
            6 => ['name' => 'OpenSSL', 'method' => 'installPackage', 'param' => 'openssl'],
            7 => ['name' => 'Git Flow', 'method' => 'installPackage', 'param' => 'git-flow'],
            8 => ['name' => 'VirtualBox', 'method' => 'installVirtualBox'],
            9 => ['name' => 'Vagrant', 'method' => 'installVagrant'],
            10 => ['name' => 'PostgreSQL Client', 'method' => 'installPackage', 'param' => 'postgresql-client'],
            11 => ['name' => 'cURL', 'method' => 'installPackage', 'param' => 'curl'],
            12 => ['name' => 'Docker', 'method' => 'installDocker'],
            13 => ['name' => 'NVM', 'method' => 'installNvm'],
            14 => ['name' => 'PNPM', 'method' => 'installPnpm'],
            15 => ['name' => 'VSCode', 'method' => 'installVsCode'],
        ];
    }

    private function displayMenu(array $availablePackages): array
    {
        $currentPosition = 0;
        $selectedPackages = [];
        $keys = array_keys($availablePackages);
        $count = count($availablePackages);

        // Activate the special mode of the terminal
        system('stty -icanon -echo');
        system('clear');

        $this->displayTerminalMenu($keys, $availablePackages, $selectedPackages, $currentPosition);

        do {
            $char = fgetc(STDIN);

            if ($char === "\033") { // Escape sequence
                $char .= fgetc(STDIN); // [
                $char .= fgetc(STDIN); // A, B, C, or D

                if ($char === "\033[A") { // Arrow up
                    $currentPosition = ($currentPosition - 1 + $count) % $count;
                } elseif ($char === "\033[B") { // Arrow down
                    $currentPosition = ($currentPosition + 1) % $count;
                }

                $this->displayTerminalMenu($keys, $availablePackages, $selectedPackages, $currentPosition);
            } elseif ($char === ' ') { // Space to select/unselect
                $currentKey = $keys[$currentPosition];
                if (isset($selectedPackages[$currentKey])) {
                    unset($selectedPackages[$currentKey]);
                } else {
                    $selectedPackages[$currentKey] = $availablePackages[$currentKey];
                }

                $this->displayTerminalMenu($keys, $availablePackages, $selectedPackages, $currentPosition);
            } elseif ($char === 'a') {
                $selectedPackages = $availablePackages;
                break;
            }
        } while ($char !== "\n" && $char !== 'q'); // Enter for validation, q to quit

        // Restauration of normal terminal mode
        system('stty icanon echo');

        if ($char === 'q') {
            return [];
        }

        return $selectedPackages;
    }

    private function displayTerminalMenu(
        array $keys,
        array $availablePackages,
        array $selectedPackages,
        int $currentPosition
    ): void {
        system('clear');
        echo "=== Development Packages Installer ===" . PHP_EOL;
        echo "↑/↓: Navigation | SPACE: Selection | ENTER: Confirm | a: All | q: Quit" . PHP_EOL . PHP_EOL;

        foreach ($keys as $index => $key) {
            $package = $availablePackages[$key];
            $selected = isset($selectedPackages[$key]) ? '[✓]' : '[ ]';
            $cursor = ($index === $currentPosition) ? '>' : ' ';
            echo "$cursor $selected {$package['name']}" . PHP_EOL;
        }
    }

    private function installSelectedPackages(array $selectedPackages): void
    {
        echo PHP_EOL . "Installing selected packages..." . PHP_EOL;

        foreach ($selectedPackages as $package) {
            echo PHP_EOL . "=== Installing {$package['name']} ===" . PHP_EOL;

            if (isset($package['param'])) {
                $this->{$package['method']}($package['param']);
            } else {
                $this->{$package['method']}();
            }
        }
    }

    private function installPhpStorm(): void
    {
        $username = $this->askQuestion(
            'What is your Linux/Ubuntu username ?' . PHP_EOL . 'leave empty to skip PHPStorm checking and installation: '
        );

        if ($username === '') {
            return;
        }

        $toolboxPath = "/home/$username/.local/share/JetBrains/Toolbox";
        $this->checkMessage('PHPStorm');

        if (is_dir($toolboxPath)) {
            $this->installedMessage('PHPStorm');
            return;
        }

        if ($this->askInstallation('PHPStorm')) {
            copy(
                'https://download.jetbrains.com/toolbox/jetbrains-toolbox-2.5.4.38621.tar.gz',
                'jetbrains-toolbox.tar.gz'
            );
            $this->executeCommand("cd /home/$username && tar -xzf jetbrains-toolbox.tar.gz");
            $this->executeCommand("cd /home/$username/jetbrains-toolbox && ./jetbrains-toolbox");
        }
    }

    private function installGitWithConfig(): void
    {
        $package = 'git';
        $this->checkMessage($package);

        if ($this->isPackageInstalled($package)) {
            $this->installedMessage($package);
            return;
        }

        if ($this->askInstallation('git with configuration')) {
            $this->executeCommand("apt-get install -y $package");
            $this->executeCommand("git config --global init.defaultBranch main");
            $this->executeCommand("git config --global pull.rebase true");
            $this->executeCommand("git config --global color.branch auto");
            $this->executeCommand("git config --global core.autocrlf input");
        }

        $email = $this->askQuestion(
            'What is your email address ?' . PHP_EOL . 'leave empty to skip email git configuration: '
        );

        if ($email !== '') {
            $this->executeCommand("git config --global user.email \"$email\"");
        }

        $username = $this->askQuestion(
            'What is your git user.name ?' . PHP_EOL . 'leave empty to skip user.name git configuration: '
        );

        if ($username !== '') {
            $this->executeCommand("git config --global user.name \"$username\"");
        }
    }

    private function installPhp(): void
    {
        if (!$this->askInstallation('PHP')) {
            return;
        }

        echo "Enter the PHP version you want to install:" . PHP_EOL . "e.g.: 'php8.3': ";
        $phpVersion = trim(fgets(STDIN));
        $this->checkMessage($phpVersion);

        if ($this->isPackageInstalled($phpVersion)) {
            $this->installedMessage($phpVersion);
            return;
        }

        if ($this->askInstallation($phpVersion)) {
            $this->executeCommand("add-apt-repository ppa:ondrej/php");
            $this->executeCommand("apt-get update");
            $this->executeCommand("apt-get install -y $phpVersion");
        }
    }

    private function installPhpModules(): void
    {
        if (!$this->askInstallation('PHP modules')) {
            return;
        }

        echo "Enter the PHP version for which you want to install modules: ";
        $phpVersion = trim(fgets(STDIN));

        $modules = [
            "php$phpVersion-cli",
            "php$phpVersion-gd",
            "php$phpVersion-xml",
            "php$phpVersion-mbstring",
            "php$phpVersion-common",
            "php$phpVersion-bcmath",
            "php$phpVersion-sqlite3",
            "php$phpVersion-pgsql",
            "php$phpVersion-zip",
            "php$phpVersion-fpm",
            "php$phpVersion-redis",
            "php$phpVersion-intl",
            "php$phpVersion-curl",
            "php$phpVersion-gmp"
        ];

        foreach ($modules as $module) {
            $this->installPackage($module);
        }
    }

    private function installComposer(): void
    {
        $package = 'composer';
        $this->checkMessage($package);

        if (file_exists('/usr/local/bin/composer')) {
            $this->installedMessage($package);

            if ($this->askUpdate($package)) {
                system('composer self-update');
            }

            return;
        }

        if ($this->askInstallation($package)) {
            $installerFile = 'composer-setup.php';
            copy('https://getcomposer.org/installer', $installerFile);

            $expected_signature = trim($this->executeCommand(
                'wget -q -O - https://composer.github.io/installer.sig')
            );
            $actual_signature = hash_file('sha384', $installerFile);
            if ($expected_signature !== $actual_signature) {
                echo 'Installer corrupt' . PHP_EOL;
                unlink($installerFile);
                return;
            }

            echo 'Installer verified' . PHP_EOL;
            include $installerFile;
            unlink($installerFile);
            rename('/usr/local/bin/composer.phar', '/usr/local/bin/composer');

            $this->installedMessage($package);
        }
    }

    private function installVirtualBox(): void
    {
        $package = 'virtualbox-7.0';
        $this->checkMessage($package);

        if ($this->isPackageInstalled($package)) {
            $this->installedMessage($package);
            return;
        }

        if ($this->askInstallation($package)) {
            $this->executeCommand("wget -O- https://www.virtualbox.org/download/oracle_vbox_2016.asc | gpg --yes --output /usr/share/keyrings/oracle-virtualbox-2016.gpg --dearmor");
            $this->executeCommand("echo \"deb [arch=amd64 signed-by=/usr/share/keyrings/oracle-virtualbox-2016.gpg] https://download.virtualbox.org/virtualbox/debian $(lsb_release -sc) contrib\" | tee /etc/apt/sources.list.d/virtualbox.list");
            $this->executeCommand("apt-get update");
            $this->executeCommand("apt-get install -y $package");
        }
    }

    private function installVagrant(): void
    {
        $package = 'vagrant';
        $this->checkMessage($package);

        if ($this->isPackageInstalled($package)) {
            $this->installedMessage($package);
            return;
        }

        if ($this->askInstallation($package)) {
            $this->executeCommand("wget -O- https://apt.releases.hashicorp.com/gpg | gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg");
            $this->executeCommand("echo \"deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com $(lsb_release -cs) main\" | tee /etc/apt/sources.list.d/hashicorp.list");
            $this->executeCommand("apt update && apt install -y $package");
        }

        $configFile = '/etc/php/8.3/fpm/pool.d/www.conf';

        echo "Press i to add permissions for vagrant in php $configFile, c to continue without configuring: ";
        $response = trim(fgets(STDIN));

        if ($response === 'i' && file_exists($configFile)) {
            $content = file_get_contents($configFile);
            $content = str_replace(
                ['user = www-data', 'group = www-data'],
                ['user = vagrant', 'group = vagrant'],
                $content
            );
            file_put_contents($configFile, $content);
        }

        echo "Press i to add permissions for www-data in php $configFile, c to continue without configuring: ";
        $response = trim(fgets(STDIN));

        if ($response === 'i' && file_exists($configFile)) {
            $content = file_get_contents($configFile);
            $content = str_replace(
                ['user = vagrant', 'group = vagrant'],
                ['user = www-data', 'group = www-data'],
                $content
            );
            file_put_contents($configFile, $content);
        }

        $package = 'vagrant-hostsupdater';
        if ($this->askInstallation($package)) {
            $this->executeCommand("vagrant plugin install $package");
        }
    }

    private function installDocker(): void
    {
        $package = 'docker-ce';
        $this->checkMessage($package);

        if ($this->isPackageInstalled($package)) {
            $this->installedMessage($package);
            return;
        }

        if (!$this->askInstallation($package)) {
            return;
        }
        $username = $this->askQuestion('What is your username ?');

        if ($username === '') {
            echo "Username is required to install Docker" . PHP_EOL;
            return;
        }

        $keyringPath = '/etc/apt/keyrings/docker.asc';
        $sourceListPath = '/etc/apt/sources.list.d/docker.list';

        $this->executeCommand('apt-get install ca-certificates curl');

        if (!mkdir('/etc/apt/keyrings', 0755, true)
            && !is_dir('/etc/apt/keyrings')
        ) {
            $this->endOfProgram('Failed to create directory /etc/apt/keyrings');
            return;
        }

        file_put_contents($keyringPath, file_get_contents('https://download.docker.com/linux/ubuntu/gpg'));
        chmod($keyringPath, 0644);

        $sourceListContent = sprintf(
            "deb [arch=%s signed-by=%s] https://download.docker.com/linux/ubuntu %s stable",
            $this->executeCommand('dpkg --print-architecture'),
            $keyringPath,
            trim(shell_exec('. /etc/os-release && echo "$VERSION_CODENAME"'))
        );
        file_put_contents($sourceListPath, $sourceListContent);

        $this->executeCommand('apt-get update');
        $this->executeCommand(
            'apt-get install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin'
        );
        $this->executeCommand('groupadd docker');
        $this->executeCommand("usermod -aG docker $username");
        $this->executeCommand('newgrp docker');
        $this->executeCommand('systemctl enable docker.service');
        $this->executeCommand('systemctl enable containerd.service');
    }

    private function installNvm(): void
    {
        if (!$this->askInstallation('NVM')) {
            return;
        }

        $home = getenv('HOME');
        $nvmUrl = 'https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh';
        if ($home === '/root') {
            echo PHP_EOL . "NVM can only be installed for a user, not for root" . PHP_EOL;
            echo "Please log in as a user and restart the script" . PHP_EOL;
            return;
        }

        echo "Installing NVM..." . PHP_EOL;
        copy($nvmUrl, 'install.sh');
        $this->executeCommand("chmod +x install.sh");
        $this->executeCommand("./install.sh");

        $nvmDir = getenv('XDG_CONFIG_HOME') ? getenv('XDG_CONFIG_HOME') . "/nvm" : $home . "/.nvm";
        putenv("NVM_DIR=$nvmDir");

        if (file_exists("$nvmDir/nvm.sh")) {
            $this->executeCommand("source $nvmDir/nvm.sh");
        }

        echo PHP_EOL . "NVM installed, restart the terminal to use it" . PHP_EOL;
    }

    private function installPnpm(): void
    {
        if ($this->askInstallation('PNPM')) {
            $home = getenv('HOME');
            $shell = $this->executeCommand("which sh");
            $this->executeCommand(
                "wget -qO- https://get.pnpm.io/install.sh | ENV=\"$home/.bashrc\" SHELL=\"$shell\" sh -"
            );
        }
    }

    private function installVsCode(): void
    {
        $package = 'code';
        $this->checkMessage('VSCode');

        if ($this->isPackageInstalled($package)) {
            $this->installedMessage('VSCode');
            return;
        }

        if ($this->askInstallation('VSCode')) {
            $keyringPath = '/usr/share/keyrings/vscode.gpg';

            $this->executeCommand(
                'apt install dirmngr software-properties-common apt-transport-https curl -y'
            );
            file_put_contents(
                $keyringPath,
                file_get_contents('https://packages.microsoft.com/keys/microsoft.asc')
            );
            chmod($keyringPath, 0644);

            file_put_contents(
                '/etc/apt/sources.list.d/vscode.list',
                "deb [arch=amd64 signed-by=$keyringPath] https://packages.microsoft.com/repos/vscode stable main"
            );

            $this->executeCommand('apt update');
            $this->executeCommand('apt install code -y');
        }
    }

    private function checkMessage(string $package): void
    {
        echo "Checking for : $package..." . PHP_EOL;
    }

    private function installedMessage(string $package): void
    {
        echo "$package is already installed !" . PHP_EOL;
    }

    private function askQuestion(string $question): string
    {
        echo $question;
        return trim(fgets(STDIN));
    }

    private function endOfProgram(string $message): void
    {
        echo PHP_EOL . $message . PHP_EOL . "End of program, closing soon..." . PHP_EOL;
        sleep(5);
    }

    private function executeCommand(string $command): string
    {
        exec($command, $outputArray, $returnCode);

        if ($returnCode !== 0) {
            echo "Error: The command '$command' failed with code $returnCode" . PHP_EOL;
            $this->endOfProgram("Installation aborted due to an error.");
            exit($returnCode);
        }

        return implode(PHP_EOL, $outputArray);
    }

    private function isPackageInstalled(string $package): bool
    {
        exec("dpkg -s $package > /dev/null 2>&1", $output, $returnValue);
        return $returnValue === 0;
    }

    private function askInstallation(string $package): bool
    {
        return $this->askQuestion("Press i to install $package, c to continue without installing : ") === 'i';
    }

    private function askUpdate(string $package): bool
    {
        return $this->askQuestion("Press u to update $package, c to continue without updating : ") === 'u';
    }

    private function installPackage(string $package): void
    {
        $this->checkMessage($package);

        if ($this->isPackageInstalled($package)) {
            $this->installedMessage($package);
            return;
        }

        if ($this->askInstallation($package)) {
            $this->executeCommand("apt-get install -y $package");
        }
    }
}