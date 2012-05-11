<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Generator\Model;

use Propel\Generator\Config\GeneratorConfigInterface;
use Propel\Generator\Exception\EngineException;
use Propel\Generator\Platform\PlatformInterface;

/**
 * A class for holding application data structures.
 *
 * @author     Hans Lellelid <hans@xmpl.org> (Propel)
 * @author     Leon Messerschmidt <leon@opticode.co.za> (Torque)
 * @author     John McNally <jmcnally@collab.net> (Torque)
 * @author     Daniel Rall <dlr@finemaltcoding.com> (Torque)
 */
class Schema
{

    /**
     * The list of databases for this application.
     * @var        array Database[]
     */
    private $databases = array();

    /**
     * The platform class for our database(s).
     * @var        string
     */
    private $platform;

    /**
     * The generator configuration
     * @var        GeneratorConfigInterface
     */
    protected $generatorConfig;

    /**
     * Name of the database. Only one database definition
     * is allowed in one XML descriptor.
     */
    private $name;

    /**
     * Flag to ensure that initialization is performed only once.
     * @var        boolean
     */
    private $isInitialized = false;

    /**
     * Creates a new instance for the specified database type.
     *
     * @param      PlatformInterface $platform The default platform object to use for any databases added to this application model.
     */
    public function __construct(PlatformInterface $defaultPlatform = null)
    {
        if (null !== $defaultPlatform) {
            $this->platform = $defaultPlatform;
        }
    }

    /**
     * Sets the platform object to use for any databases added to this application model.
     *
     * @param PlatformInterface $defaultPlatform
     */
    public function setPlatform(PlatformInterface $defaultPlatform)
    {
        $this->platform = $defaultPlatform;
    }

    /**
     * Gets the platform object to use for any databases added to this application model.
     *
     * @return Platform
     */
    public function getPlatform()
    {
        return $this->platform;
    }

    /**
     * Set the generator configuration
     *
     * @param GeneratorConfigInterface $generatorConfig
     */
    public function setGeneratorConfig(GeneratorConfigInterface $generatorConfig)
    {
        $this->generatorConfig = $generatorConfig;
    }

    /**
     * Get the generator configuration
     *
     * @return GeneratorConfigInterface
     */
    public function getGeneratorConfig()
    {
        return $this->generatorConfig;
    }

    /**
     * Set the name of the database.
     *
     * @param      name of the database.
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Get the name of the database.
     *
     * @return     String name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the short name of the database (without the '-schema' postfix).
     *
     * @return     String name
     */
    public function getShortName()
    {
        return str_replace('-schema', '', $this->name);
    }

    /**
     * Return an array of all databases
     *
     * @return     Array of Database objects
     */
    public function getDatabases($doFinalInit = true)
    {
        // this is temporary until we'll have a clean solution
        // for packaging datamodels/requiring schemas
        if ($doFinalInit) {
            $this->doFinalInitialization();
        }

        return $this->databases;
    }

    /**
     * Returns whether this application has multiple databases.
     *
     * @return     boolean True if the application has multiple databases
     */
    public function hasMultipleDatabases()
    {
        return count($this->databases) > 1;
    }

    /**
     * Return the database with the specified name.
     *
     * @param      name database name
     * @return     A Database object.  If it does not exist it returns null
     */
    public function getDatabase($name = null, $doFinalInit = true)
    {
        // this is temporary until we'll have a clean solution
        // for packaging datamodels/requiring schemas
        if ($doFinalInit) {
            $this->doFinalInitialization();
        }

        if (null === $name) {
            return $this->databases[0];
        }

        for ($i = 0, $size = count($this->databases); $i < $size; $i++) {
            $db = $this->databases[$i];
            if ($db->getName() === $name) {
                return $db;
            }
        }

        return null;
    }

    /**
     * Checks whether a database with the specified nam exists in this Schema
     *
     * @param      name database name
     * @return     boolean
     */
    public function hasDatabase($name)
    {
        foreach ($this->databases as $db) {
            if ($db->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a database to the list and sets the Schema property to this
     * Schema
     *
     * @param      db the database to add
     */
    public function addDatabase($db)
    {
        if ($db instanceof Database) {
            $db->setParentSchema($this);
            if (null === $db->getPlatform()) {
                if ($config = $this->getGeneratorConfig()) {
                    $pf = $config->getConfiguredPlatform(null, $db->getName());
                    $db->setPlatform($pf ? $pf : $this->platform);
                } else {
                    $db->setPlatform($this->platform);
                }
            }
            $this->databases[] = $db;

            return $db;
        }

        // XML attributes array / hash
        $d = new Database();
        $d->setParentSchema($this);
        $d->loadFromXML($db);

        return $this->addDatabase($d); // calls self w/ different param type
    }

    public function doFinalInitialization()
    {
        if (!$this->isInitialized) {
            for ($i = 0, $size = count($this->databases); $i < $size; $i++) {
                $this->databases[$i]->doFinalInitialization();
            }
            $this->isInitialized = true;
        }
    }

    /**
     * Merge other Schema objects together into this Schema object
     *
     * @param array[Schema] $schemas
     */
    public function joinSchemas($schemas)
    {
        foreach ($schemas as $schema) {
            foreach ($schema->getDatabases(false) as $addDb) {
                $addDbName = $addDb->getName();
                if ($this->hasDatabase($addDbName)) {
                    $db = $this->getDatabase($addDbName, false);
                    // temporarily reset database namespace to avoid double namespace decoration (see ticket #1355)
                    $namespace = $db->getNamespace();
                    $db->setNamespace(null);
                    // join tables
                    foreach ($addDb->getTables() as $addTable) {
                        if ($db->getTable($addTable->getName())) {
                            throw new Exception(sprintf('Duplicate table found: %s.', $addTable->getName()));
                        }
                        $db->addTable($addTable);
                    }
                    // join database behaviors
                    foreach ($addDb->getBehaviors() as $addBehavior) {
                        if (!$db->hasBehavior($addBehavior->getName())) {
                            $db->addBehavior($addBehavior);
                        }
                    }
                    // restore the database namespace
                    $db->setNamespace($namespace);
                } else {
                    $this->addDatabase($addDb);
                }
            }
        }
    }

    /**
     * Returns the number of tables in all the databases of this Schema object
     *
     * @return integer
     */
    public function countTables()
    {
        $nb = 0;
        foreach ($this->getDatabases() as $database) {
            $nb += $database->countTables();
        }

        return $nb;
    }

    /**
     * Creates a string representation of this Schema.
     * The representation is given in xml format.
     *
     * @return     string Representation in xml format
     */
    public function toString()
    {
        $result = '<app-data>'."\n";
        foreach ($this->databases as $database) {
            $result .= $database->toString();
        }

        if ($this->databases) {
            $result .= "\n";
        }

        $result .= '</app-data>';

        return $result;
    }

    /**
     * Magic string method
     * @see toString()
     */
    public function __toString()
    {
        return $this->toString();
    }

    public function loadDefinition(array $databases)
    {
        foreach ($databases as $database) {
            $db = new Database();
            $db->setParentSchema($this);
            $db->loadDefinition($database);

            $this->addDatabase($db);
        }
    }
}