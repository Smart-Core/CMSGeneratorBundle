<?php

namespace SmartCore\Bundle\CMSGeneratorBundle\Composer;

use Composer\Script\CommandEvent;
use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as SymfonyScriptHandler;

class ScriptHandler extends SymfonyScriptHandler
{
    /**
     * @param $event CommandEvent A instance
     */
    public static function installCheck(CommandEvent $event)
    {
        $options = parent::getOptions($event);
        $appDir = $options['symfony-app-dir'];

        if (null === $appDir) {
            return;
        }

        if (file_exists($appDir.'/Entity/User.php')) {
            $event->getIO()->write('Run <comment>bin/install</comment> to installing CMS.');

            /*
            static::executeCommand($event, $appDir, 'cms:generate:sitebundle', $options['process-timeout']);

            unlink($appDir.'/Entity/User.php');

            static::executeCommand($event, $appDir, 'doctrine:schema:update --force --complete', $options['process-timeout']);

            $event->getIO()->write('<comment>Create super admin user:</comment>');

            static::executeCommand($event, $appDir, 'fos:user:create --super-admin', $options['process-timeout']);
            */
        }
    }
}
