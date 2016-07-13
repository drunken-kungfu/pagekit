<?php

namespace Pagekit\Console\Commands;

use Pagekit\Application\Console\Command;
use Pagekit\Installer\Helper\Composer;
use Pagekit\Installer\Package\PackageManager;
use Pagekit\Installer\Package\PackageScripts;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class InstallCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected $name = 'install';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Installs a Pagekit package';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, '[Package name]:[Version constraint]');
        $this->addOption('prefer-source', null, InputOption::VALUE_NONE, 'Forces installation from package sources when possible, including VCS information.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $packages = [];
        $availableModulesPackages = [];
        $availableModules = [];

        foreach ((array) $this->argument('packages') as $argument) {

            $possiblePath = $this->container->get('path.packages') . '/' . $argument . '/composer.json';

            if (file_exists($possiblePath)) {

                $output->writeln('Package in Packages Directory');

                $info = json_decode(file_get_contents($possiblePath), true);

                $availableModules[] = $argument;

                if (isset($info['require']) && is_array($info['require'])) {
                    $availableModulesPackages = array_merge($availableModulesPackages, $info['require']);
                }
            } else {
                $argument = explode(':', $argument);
                $packages[$argument[0]] = isset($argument[1]) && $argument[1] ? $argument[1] : '*';
            }
        }

        if (count($packages) > 0) {
            $output->writeln('Installing New Package');
            $installer = new PackageManager($output);
            $installer->install($packages, true, $this->option('prefer-source'));
        }

        // Run functions to update and install scripts and composer packages of a module
        // that already exists in the package directory but is not installed
        if (count($availableModulesPackages) > 0) {

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Install scripts? y/n ', true);
            $installScripts = $helper->ask($input, $output, $question);

            $config = [];

            foreach (['path.temp', 'path.cache', 'path.vendor', 'path.artifact', 'path.packages', 'system.api'] as $key) {
                $config[$key] = $this->container->get($key);
            }

            $composer = new Composer($config, $output);
            $composer->install($availableModulesPackages, true, false, $this->option('prefer-source'));

            if($installScripts) {

                foreach ($availableModules as $module) {

                    if (!is_array($pkModule = include $this->container->get('path.packages') . '/' . $module . '/index.php') || !isset($pkModule['name'])) {

                        $output->writeln('Failed to include module \'' . $module . '\' from the package directory or module does not have a name.');

                    } else {

                        $scriptPath = $this->container->get('path.packages') . '/' . $module . '/scripts.php';

                        if (file_exists(!$scriptPath)) {

                            $output->writeln('Cannot install script of module \'' . $module . '\' because the path to its script file does not exist');

                        } else {

                            try {

                                $output->writeln('Loading Database');
                                $this->container['module']->load('database');

                                $output->writeln('Loading ' . $pkModule['name']);
                                $this->container['module']->load($pkModule['name']);

                                if (isset($pkModule['require'])) {
                                    foreach ($pkModule['require'] as $require) {
                                        $output->writeln('Loading \'' . $require . '\'');
                                        $this->container['module']->load($require);
                                    }
                                }

                                $script = new PackageScripts($scriptPath);
                                $output->writeln('Installing scripts...');
                                $script->install();
                                $output->writeln('Success');

                            } catch (\Exception $e) {
                                $output->writeln('Failed to install script');
                                $output->writeln($e->getMessage());
                                $output->writeln($e->getFile());
                                $output->writeln($e->getLine());
                                $output->writeln($e->getTraceAsString());
                            }
                        }
                    }
                }
            }
        }
    }
}
