<?php

namespace Mulertech\DevInstaller;

use MulerTech\MTerm\Core\Terminal;
use MulerTech\MTerm\Form\Form;
use MulerTech\MTerm\Form\Field\Template\SelectMultipleArrowTemplate;
use MulerTech\MTerm\Utils\ProgressBar;

class DevInstaller
{
    private Terminal $terminal;
    private bool $aptUpdated = false;
    private string $phpVersion;

    public static function start(): void
    {
        (new self())->run();
    }

    public function __construct()
    {
        $this->terminal = new Terminal();
    }

    public function run(): void
    {
        if (!$this->checkOperatingSystem()) {
            return;
        }

        $this->terminal->clear();
        $this->terminal->writeLine("=== Development Packages Installer ===", "blue");
        $this->terminal->writeLine("Updating package list...", "blue");

        $selectedPackages = $this->displayMenu();

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
                $this->terminal->writeLine("Warning: This installer is optimized for Ubuntu/Debian-based distributions.", "yellow");
                $this->terminal->writeLine("Your distribution ($distro) may not be fully compatible.", "yellow");

                $response = $this->terminal->read("Do you want to continue anyway? (y/n): ");
                if (strtolower($response) !== 'y') {
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
            ['name' => 'PHPStorm', 'method' => 'installPhpStorm', 'root' => 1], // To be tested
            ['name' => 'OpenSSL', 'method' => 'installPackage', 'params' => 'openssl', 'root' => 1],
            ['name' => 'Git with configuration', 'method' => 'installGitWithConfig', 'root' => 1],
            ['name' => 'Git Flow', 'method' => 'installPackage', 'params' => 'git-flow', 'root' => 1],
            ['name' => 'PHP', 'method' => 'installPhp', 'root' => 1],
            ['name' => 'Modules PHP', 'method' => 'installPhpModules', 'root' => 1],
            ['name' => 'Composer', 'method' => 'installComposer', 'root' => 1],
            ['name' => 'VirtualBox', 'method' => 'installVirtualBox', 'root' => 1],
            ['name' => 'Vagrant', 'method' => 'installVagrant', 'root' => 1],
            ['name' => 'PostgreSQL Client', 'method' => 'installPackage', 'params' => 'postgresql-client', 'root' => 1],
            ['name' => 'cURL', 'method' => 'installPackage', 'params' => 'curl', 'root' => 1],
            ['name' => 'Docker', 'method' => 'installDocker', 'root' => 1], // To be tested
            ['name' => 'PNPM', 'method' => 'installPnpm', 'root' => 1],
            ['name' => 'NVM (Only in user session)', 'method' => 'installNvm', 'root' => 0],
            ['name' => 'VSCode', 'method' => 'installVsCode', 'root' => 1], // To be tested
        ];
    }

    private function displayMenu(): array
    {
        $form = new Form($this->terminal);
        
        $field = new SelectMultipleArrowTemplate(
            'packages',
            '=== Development Packages Installer ===' . PHP_EOL . 'Choose the packages you want to install'
        );
        $field->setOptions(array_column($this->getAvailablePackages(), 'name'));
        $form->addField($field);
        
        $form->handle();
        
        if (!$form->isSubmitted() || !$form->isValid()) {
            return [];
        }
        
        $selectedPackages = array_intersect_key($this->getAvailablePackages(), $form->getValue('packages'));

        if (empty($selectedPackages)) {
            return [];
        }

        // Check if there is packages that require root access and others that don't
        $packageNumber = count($selectedPackages);
        $rootNumber = array_sum(array_column($selectedPackages, 'root'));
        $rootSession = getenv('HOME') === '/root';
        if (($packageNumber === $rootNumber && !$rootSession) || ($rootNumber === 0 && $rootSession)) {
            $this->terminal->writeLine(sprintf(
                "All selected packages require %s access",
                $rootNumber === 0 ? 'user' : 'root'
            ), "yellow");
            return [];
        }

        $this->terminal->clear();
        $this->terminal->writeLine("Selected packages:", "blue");
        foreach ($selectedPackages as $package) {
            $this->terminal->writeLine("- " . $package['name'], "blue");
        }
        $this->terminal->writeLine("");

        $confirm = $this->terminal->read("Proceed with installation? (y/n): ");
        if (strtolower($confirm) !== 'y') {
            return [];
        }

        return $selectedPackages;
    }

    private function installSelectedPackages(array $selectedPackages): void
    {
        $this->terminal->writeLine(PHP_EOL . "Installing selected packages...", "blue", true);
        
        $totalPackages = count($selectedPackages);
        $progressBar = new ProgressBar($this->terminal, $totalPackages);
        $progressBar->start();
        
        $currentPackage = 0;
        foreach ($selectedPackages as $package) {
            $this->terminal->writeLine(PHP_EOL . "=== Installing {$package['name']} ===", "blue");

            if (!$this->userMustBeRootOrUser($package['root'] === 1, $package['name'])) {
                break;
            }

            if (isset($package['params'])) {
                $params = is_array($package['params']) ? $package['params'] : [$package['params']];
                $this->{$package['method']}(...$params);
            } else {
                $this->{$package['method']}();
            }

            $currentPackage++;
            $progressBar->setProgress($currentPackage);
        }
        
        $progressBar->finish();
        $this->terminal->writeLine(PHP_EOL . "Package installation is complete !", "green", true);
    }

    private function installPhpStorm(): void
    {
        $username = $this->terminal->read(
            'What is your Linux/Ubuntu username?' . PHP_EOL . 'Leave empty to skip PHPStorm checking and installation: '
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

        copy(
            'https://download.jetbrains.com/toolbox/jetbrains-toolbox-2.5.4.38621.tar.gz',
            "/home/$username/jetbrains-toolbox.tar.gz"
        );
        $this->executeCommand("cd /home/$username && tar -xzf jetbrains-toolbox.tar.gz");
        // Todo : check if it is necessary only into docker
//        $this->executeCommand("apt-get install -y libfuse2 libxi6 libxrender1 libxtst6 mesa-utils libfontconfig libgtk-3-bin");
        // Todo : if not remove this line
        $this->executeCommand("cd $(ls -d /home/$username/* | grep jetbrains-toolbox-) && ./jetbrains-toolbox");
    }

    private function installGitWithConfig(): void
    {
        $package = 'git';
        if ($this->isPackageInstalled($package)) {
            $this->installedMessage($package);
            return;
        }

        $this->aptUpdate();
        $this->executeCommand("DEBIAN_FRONTEND=noninteractive apt-get install -y $package");
        $this->executeCommand("git config --global init.defaultBranch main");
        $this->executeCommand("git config --global pull.rebase true");
        $this->executeCommand("git config --global color.branch auto");
        $this->executeCommand("git config --global core.autocrlf input");

        $email = $this->terminal->read(
            'What is your email address?' . PHP_EOL . 'Leave empty to skip email git configuration (then press enter): '
        );

        if ($email !== '') {
            $this->executeCommand("git config --global user.email \"$email\"");
        }

        $username = $this->terminal->read(
            'What is your git user.name?' . PHP_EOL . 'Leave empty to skip user.name git configuration (then press enter): '
        );

        if ($username !== '') {
            $this->executeCommand("git config --global user.name \"$username\"");
        }
    }

    private function installPhp(): void
    {
        if (!isset($this->phpVersion)) {
            $phpVersion = $this->terminal->read("Enter the PHP version you want to install (e.g., 'php8.3'): ");
            if (!str_starts_with('php', $phpVersion)) {
                $phpVersion = "php$phpVersion";
            }
            $this->phpVersion = $phpVersion;
        }

        if ($this->isPackageInstalled($phpVersion)) {
            $this->installedMessage($phpVersion);
            return;
        }

        $this->executeCommand("add-apt-repository -y ppa:ondrej/php");
        $this->aptUpdate(true);
        $this->executeCommand("DEBIAN_FRONTEND=noninteractive apt-get install -y $phpVersion");
    }

    private function installPhpModules(): void
    {
        if (!isset($this->phpVersion)) {
            $phpVersion = $this->terminal->read("Enter the PHP version for which you want to install modules (e.g., 'php8.3'): ");
            if (!str_starts_with('php', $phpVersion)) {
                $phpVersion = "php$phpVersion";
            }
            $this->phpVersion = $phpVersion;
        }

        $modules = [
            "$this->phpVersion-cli",
            "$this->phpVersion-gd",
            "$this->phpVersion-xml",
            "$this->phpVersion-mbstring",
            "$this->phpVersion-common",
            "$this->phpVersion-bcmath",
            "$this->phpVersion-sqlite3",
            "$this->phpVersion-pgsql",
            "$this->phpVersion-zip",
            "$this->phpVersion-fpm",
            "$this->phpVersion-redis",
            "$this->phpVersion-intl",
            "$this->phpVersion-curl",
            "$this->phpVersion-gmp"
        ];

        $choices = array_combine($modules, $modules);
        $form = new Form($this->terminal);
        $field = new SelectMultipleArrowTemplate(
            'modules',
            'Select the modules you want to install'
        );
        $field->setOptions($choices)->setDefault(array_values($modules));
        $form->addField($field);
        $form->handle();

        if (!$form->isSubmitted() || !$form->isValid()) {
            return;
        }

        $modules = $form->getValue('modules');

        // Display the modules that will be installed
        $this->terminal->writeLine("Checking for installed modules...", "blue");
        $this->terminal->writeLine("The following modules will be installed:", "blue");
        $modulesToInstall = [];
        foreach ($modules as $module) {
            if (!$this->isPackageInstalled($module, false)) {
                $this->terminal->writeLine("- $module", "blue");
                $modulesToInstall[] = $module;
            }
        }

        $this->terminal->writeLine("Add apt repository for PHP...", "blue");
        $this->executeCommand("add-apt-repository -y ppa:ondrej/php");
        $this->aptUpdate(true);
        foreach ($modulesToInstall as $module) {
            $this->installPackage($module, false);
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

        $this->terminal->writeLine("Checking wget, required for Composer installation...", "blue");
        if (!$this->isPackageInstalled('wget', false)) {
            $this->aptUpdate();
            $this->terminal->writeLine("Installing wget...", "blue");
            $this->installPackage('wget', false);
        }

        $installerFile = '/tmp/composer-setup.php';
        copy('https://getcomposer.org/installer', $installerFile);

        $expected_signature = trim($this->executeCommand(
            'wget -q -O - https://composer.github.io/installer.sig')
        );
        $actual_signature = hash_file('sha384', $installerFile);
        if ($expected_signature !== $actual_signature) {
            $this->terminal->writeLine('Installer corrupt', 'red');
            unlink($installerFile);
            return;
        }

        $this->terminal->writeLine('Composer installer verified', 'green');

        // Exécuter le script avec PHP au lieu de l'inclure directement
        $this->executeCommand("php $installerFile --install-dir=/usr/local/bin --filename=composer");
        unlink($installerFile);

        $this->installedMessage($package);
    }

    private function installVirtualBox(): void
    {
        $package = 'virtualbox-7.0';
        if ($this->isPackageInstalled($package)) {
            $this->installedMessage($package);
            return;
        }

        $this->executeCommand(
            "wget -O- https://www.virtualbox.org/download/oracle_vbox_2016.asc | gpg --yes --output /usr/share/keyrings/oracle-virtualbox-2016.gpg --dearmor"
        );
        $this->executeCommand(
            "echo \"deb [arch=amd64 signed-by=/usr/share/keyrings/oracle-virtualbox-2016.gpg] https://download.virtualbox.org/virtualbox/debian $(lsb_release -sc) contrib\" | tee /etc/apt/sources.list.d/virtualbox.list"
        );

        $this->aptUpdate(true);
        $this->installPackage($package, false);
    }

    private function installVagrant(): void
    {
        $package = 'vagrant';
        if ($this->isPackageInstalled($package)) {
            $this->installedMessage($package);
            return;
        }

        $this->executeCommand(
            "wget -O- https://apt.releases.hashicorp.com/gpg | gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg"
        );
        $this->executeCommand(
            "echo \"deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com $(lsb_release -cs) main\" | tee /etc/apt/sources.list.d/hashicorp.list"
        );

        $this->aptUpdate(true);
        $this->installPackage($package, false);

        $configFile = '/etc/php/8.3/fpm/pool.d/www.conf';

        $response = $this->terminal->read(
            "Configure PHP permissions for Vagrant? (y/n): "
        );

        if (strtolower($response) === 'y' && file_exists($configFile)) {
            $content = file_get_contents($configFile);
            $content = str_replace(
                ['user = www-data', 'group = www-data'],
                ['user = vagrant', 'group = vagrant'],
                $content
            );
            file_put_contents($configFile, $content);
            $this->terminal->writeLine("PHP configuration updated to use vagrant user", "green");
        }

        $this->terminal->writeLine("Installing Vagrant plugin...", "blue");
        $this->executeCommand("vagrant plugin install vagrant-hostsupdater");
    }

    private function installDocker(): void
    {
        $package = 'docker-ce';
        if ($this->isPackageInstalled($package)) {
            $this->installedMessage($package);
            return;
        }

        $username = $this->terminal->read('What is your Linux/Ubuntu username? ');

        if ($username === '') {
            $this->terminal->writeLine("Username is required to install Docker", "red");
            return;
        }

        $keyringPath = '/etc/apt/keyrings/docker.asc';
        $sourceListPath = '/etc/apt/sources.list.d/docker.list';


        $this->aptUpdate();
        $this->executeCommand('apt-get install ca-certificates curl');

        $keyringDir = dirname($keyringPath);
        if (!is_dir($keyringDir) && !mkdir($keyringDir, 0755, true)) {
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

        $this->aptUpdate(true);
        $this->executeCommand(
            'apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin'
        );
        $this->executeCommand('groupadd docker');
        $this->executeCommand("usermod -aG docker $username");
        $this->executeCommand('newgrp docker');
        $this->executeCommand('systemctl enable docker.service');
        $this->executeCommand('systemctl enable containerd.service');
    }

    private function installNvm(): void
    {
        $this->terminal->writeLine("Installing NVM...", "blue");
        $home = getenv('HOME');
        $nvmUrl = 'https://raw.githubusercontent.com/nvm-sh/nvm/master/install.sh';
        $installPath = $home . DIRECTORY_SEPARATOR . 'install.sh';
        copy($nvmUrl, $installPath);
        $this->executeCommand("chmod +x $installPath");

        if (!$this->isPackageInstalled('git')
            && !$this->isPackageInstalled('curl')
            && !$this->isPackageInstalled('wget')
        ) {
            $this->terminal->writeLine("You need git, curl, or wget to install nvm", "red");
            $this->terminal->writeLine("Restart the program with admin rights to install them", "yellow");
            return;
        }

        $this->executeCommand('cd ' . dirname($installPath) . ' && ./install.sh');
        $nvmDir = getenv('XDG_CONFIG_HOME') ? getenv('XDG_CONFIG_HOME') . DIRECTORY_SEPARATOR . "nvm" : $home . DIRECTORY_SEPARATOR . ".nvm";
        putenv("NVM_DIR=$nvmDir");

        if (file_exists("$nvmDir/nvm.sh")) {
            $this->executeCommand(". $nvmDir/nvm.sh");
        }

        $this->terminal->writeLine("NVM installed, restart the terminal to use it", "yellow");
    }

    private function userMustBeRootOrUser(bool $rootNeeded, string $packageName): bool
    {
        $userIsRoot = getenv('HOME') === '/root';

        if ($userIsRoot !== $rootNeeded) {
            $this->terminal->writeLine(
                sprintf('The package %s can only be installed by %s', $packageName, $rootNeeded ? 'root' : 'a user'),
                "red"
            );
            $this->terminal->writeLine(
                sprintf('Please log in as %s and restart the program', $rootNeeded ? 'root' : 'a user'),
                "yellow"
            );

            return false;
        }

        return true;
    }

    private function installPnpm(): void
    {
        if ($this->isCommandAvailable('pnpm')) {
            $this->installedMessage('PNPM');
            return;
        }

        $home = getenv('HOME');
        $shell = $this->executeCommand("which sh");

        if (!$this->isPackageInstalled('wget', false)) {
            $this->terminal->writeLine("Installing wget, required for PNPM installation...", "blue");
            $this->installPackage('wget');
        }

        $this->executeCommand(
            "wget -qO- https://get.pnpm.io/install.sh | ENV=\"$home/.bashrc\" SHELL=\"$shell\" sh -"
        );
        $this->terminal->writeLine("PNPM installed successfully, restart the terminal to use it", "yellow");
    }

    private function installVsCode(): void
    {
        $package = 'code';
        if ($this->isPackageInstalled($package)) {
            $this->installedMessage('VSCode');
            return;
        }

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
        $this->terminal->writeLine("VSCode installed successfully", "green");
    }

    private function checkMessage(string $package): void
    {
        $this->terminal->writeLine("Checking for: $package...", "blue");
    }

    private function installedMessage(string $package): void
    {
        $this->terminal->writeLine("$package is already installed!", "green");
    }

    private function endOfProgram(string $message): void
    {
        $this->terminal->writeLine(PHP_EOL . $message, "blue", true);
        $this->terminal->writeLine("End of program, closing soon...", "yellow");
        sleep(5);
    }

    private function executeCommand(string $command): string
    {
        exec($command, $outputArray, $returnCode);

        if ($returnCode !== 0) {
            $this->terminal->writeLine("Error: The command '$command' failed with code $returnCode", "red");
            $this->endOfProgram("Installation aborted due to an error.");
            exit($returnCode);
        }

        return implode(PHP_EOL, $outputArray);
    }

    private function isPackageInstalled(string $package, bool $checkMessage = true): bool
    {
        if ($checkMessage) {
            $this->checkMessage($package);
        }
        exec(
            "dpkg-query -W -f='\${Status}' $package 2>/dev/null | grep -q 'install ok installed'",
            $output,
            $returnValue
        );
        return $returnValue === 0;
    }

    private function askUpdate(string $package): bool
    {
        $response = $this->terminal->read("Update $package? (u=update, c=continue without updating): ");
        return $response === 'u';
    }

    private function installPackage(string $package, bool $checkBefore = true): void
    {
        if ($checkBefore && $this->isPackageInstalled($package)) {
            $this->installedMessage($package);
            return;
        }

        $this->aptUpdate();
        $this->executeCommand("DEBIAN_FRONTEND=noninteractive apt-get install -y $package");
        $this->terminal->writeLine("$package installed successfully", "green");
    }

    private function aptUpdate(bool $force = false): void
    {
        if ($this->aptUpdated && !$force) {
            return;
        }

        $this->executeCommand("apt-get update");
        $this->aptUpdated = true;
    }

    private function isCommandAvailable(string $command, bool $checkMessage = true): bool
    {
        if ($checkMessage) {
            $this->checkMessage($command);
        }

        exec("$command -v 2>/dev/null", $output, $returnValue);
        return $returnValue === 0;
    }
}
