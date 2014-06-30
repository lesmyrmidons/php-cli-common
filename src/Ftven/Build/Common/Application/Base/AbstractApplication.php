<?php

/*
 * This file is part of the Cli-common package.
 *
 * (c) France Télévisions Editions Numériques <guillaume.postaire@francetv.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ftven\Build\Common\Application\Base;

use Ftven\Build\Common\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Application;
use Symfony\Component\Config\FileLocator;

/**
 * @author Olivier Hoareau olivier@phppro.fr>
 */
abstract class AbstractApplication extends Application
{
    /**
     * @var ContainerBuilder
     */
    protected $container;
    /**
     *
     */
    public function __construct()
    {
        $this->setContainer(new ContainerBuilder());

        foreach ($this->getConfigLocators() as $location => $files) {
            $loader = new YamlFileLoader($this->getContainer(), new FileLocator($location));
            foreach($files as $file) {
                $loader->load($file);
            }
        }

        parent::__construct(sprintf('ftven-%s', $this->getType()), '@package_version@');

        $this->registerExtensions();
    }
    /**
     * @return ExtensionInterface[]
     */
    public function getExtensions()
    {
        return [];
    }
    /**
     * @return $this
     */
    protected function registerExtensions()
    {
        foreach($this->getExtensions() as $extension) {
            $extension->setApplication($this);
            $this->addCommands($extension->getDefaultCommands());
        }

        return $this;
    }
    /**
     * @param ContainerBuilder $container
     *
     * @return $this
     */
    public function setContainer($container)
    {
        $this->container = $container;

        return $this;
    }
    /**
     * @return ContainerBuilder
     */
    public function getContainer()
    {
        return $this->container;
    }
    /**
     * @return string
     */
    public function getType()
    {
        return strtolower(preg_replace('/Application$/', '', basename(str_replace('\\', '/', get_class($this)))));
    }
    /**
     * @return array
     */
    protected function getConfigLocators()
    {
        $rClass = new \ReflectionClass($this);

        return [
            __DIR__ . '/../Resources'                      => ['services.yml'],
            dirname($rClass->getFileName()) . '/Resources' => ['services.yml'],

        ];
    }
    /**
     * @return Command[]
     */
    protected function registerCommands()
    {
        $commands = [];

        foreach($this->getContainer()->getDefinitions() as $id => $definition) {
            if (0 >= preg_match('/^' . $this->getType() . '\.commands\./', $id)) {
                continue;
            }
            $commands[] = $this->getContainer()->get($id);
        }

        return $commands;
    }
    /**
     * @return Command[]
     */
    protected function registerCommonCommands()
    {
        $commands = [
            $this->getContainer()->get('common.commands.update'),
        ];

        if (true === $this->hasBoxSupport()) {
            $commands[] = $this->getContainer()->get('common.commands.package');
        }

        return $commands;
    }
    /**
     * @return bool
     */
    protected function hasBoxSupport()
    {
        /** @var Filesystem $fs */
        $fs = $this->getContainer()->get('common.services.filesystem');

        return $fs->exists('box.json') && $fs->exists('bin/box');
    }
    /**
     * @return Command[]
     */
    protected function getDefaultCommands()
    {
        return array_merge(
            parent::getDefaultCommands(),
            $this->registerCommonCommands(),
            $this->registerCommands()
        );
    }
}