<?php

namespace SmartCore\Bundle\CMSGeneratorBundle\Command;

use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class InstallCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDescription('Smart Core CMS clean installer')
            ->setName('cms:install')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When namespace doesn't end with Bundle
     * @throws \RuntimeException         When bundle can't be executed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $appDir = realpath($this->getContainer()->get('kernel')->getRootDir());
        $binDir = 'bin';

        $finder = (new Finder())->directories()->depth('== 0')->name('*SiteBundle')->name('SiteBundle')->in($appDir.'/../src');

        if ($finder->count() == 0) {
            $dialog     = $this->getQuestionHelper();
            $filesystem = new Filesystem();

            $output->writeln('<error>Installing Smart Core CMS. This prosess purge all database tables.</error>');
            $confirm = $dialog->ask($input, $output, new Question('<comment>Are you shure?</comment> [y,N]: ', 'n'));

            if (strtolower($confirm) !== 'y') {
                $output->writeln('<info>Abort.</info>');

                return false;
            }

            $sitename = $dialog->ask($input, $output, new Question('<comment>Site name</comment> [My]: ', 'My'));
            $username = $dialog->ask($input, $output, new Question('<comment>Username</comment> [root]: ', 'root'));
            $email    = $dialog->ask($input, $output, new Question('<comment>Email</comment> [root@world.com]: ', 'root@world.com'));
            $password = $dialog->ask($input, $output, new Question('<comment>Password</comment> [123]: ', '123'));

            $userEntityFilePath = $this->getContainer()->get('kernel')->getBundle('CMSGeneratorBundle')->getPath().'/Resources/skeleton/User.php';

            $process = new Process("cp $userEntityFilePath app/Entity/User.php");
            $process->mustRun();

            static::executeCommand($output, $binDir, 'doctrine:schema:drop --force');
            static::executeCommand($output, $binDir, 'cms:generate:sitebundle --name='.$sitename);

            unlink($appDir.'/Entity/User.php');

            $process = new Process('bash bin/clear_cache');
            $process->run(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    echo 'ERR > '.$buffer;
                } else {
                    echo $buffer;
                }
            });

            static::executeCommand($output, $binDir, 'doctrine:schema:update --force --complete --env=prod');

            $output->writeln('<comment>Create super admin user:</comment>');

            static::executeCommand($output, $binDir, "fos:user:create --super-admin $username $email $password");

            $filesystem->remove('app/config/install.yml');
            $filesystem->remove('app/Entity/.keep');
            $filesystem->remove('app/Entity');
        }

        return null;
    }

    protected function getQuestionHelper()
    {
        $question = $this->getHelperSet()->get('question');
        if (!$question || get_class($question) !== 'Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper') {
            $this->getHelperSet()->set($question = new QuestionHelper());
        }

        return $question;
    }

    protected static function executeCommand(OutputInterface $output, $consoleDir, $cmd, $timeout = 300)
    {
        $php = escapeshellarg(static::getPhp(false));
        $phpArgs = implode(' ', array_map('escapeshellarg', static::getPhpArguments()));
        $console = escapeshellarg($consoleDir.'/console');
        $console .= ' --ansi';

        $process = new Process($php.($phpArgs ? ' '.$phpArgs : '').' '.$console.' '.$cmd, null, null, null, $timeout);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf("An error occurred when executing the \"%s\" command:\n\n%s\n\n%s.", escapeshellarg($cmd), $process->getOutput(), $process->getErrorOutput()));
        }
    }

    protected static function getPhp($includeArgs = true)
    {
        $phpFinder = new PhpExecutableFinder();
        if (!$phpPath = $phpFinder->find($includeArgs)) {
            throw new \RuntimeException('The php executable could not be found, add it to your PATH environment variable and try again');
        }

        return $phpPath;
    }

    protected static function getPhpArguments()
    {
        $arguments = array();

        $phpFinder = new PhpExecutableFinder();
        if (method_exists($phpFinder, 'findArguments')) {
            $arguments = $phpFinder->findArguments();
        }

        if (false !== $ini = php_ini_loaded_file()) {
            $arguments[] = '--php-ini='.$ini;
        }

        return $arguments;
    }
}
