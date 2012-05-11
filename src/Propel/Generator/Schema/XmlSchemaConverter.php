<?php

namespace Propel\Generator\Schema;

use Propel\Generator\Model\Schema;

class XmlSchemaConverter implements SchemaConverterInterface
{
    private $definition;

    public function __construct()
    {
        $this->definition = array();
    }

    public function getArrayDefinition($file)
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException(sprintf('Unable to load schema file for path "%s".', $file));
        }

        //$doc = new \DOMDocument();
        //$doc->loadXML(file_get_contents($file));

        //return $this->xmlToArray($doc->documentElement);

        return $this->toArray(file_get_contents($file));
    }

    public function supports($file)
    {
        return 'xml' === pathinfo($file, PATHINFO_EXTENSION);
    }

    private function parseNode(\SimpleXmlElement $node)
    {
        $output = $this->extractNodeAttributes($node);

        foreach ($node->children() as $child) {
            $name = $child->getName();
            $key  = $this->getParentKey($name);
            $definition = $this->parseNode($child);

            if (null === $key) {
                $output[$name] = $definition;
            } else {
                $output[$key][] = $definition;
            }
        }

        return $output;
    }

    private function getParentKey($childName)
    {
        $map = array(
            'table'               => 'tables',
            'column'              => 'columns',
            'foreign-key'         => 'foreign-keys',
            'index'               => 'indices',
            'index-column'        => 'index-columns',
            'unique'              => 'uniques',
            'external-schema'     => 'external-schemas',
            'id-method-parameter' => 'id-method-parameters',
            'parameter'           => 'parameters',
        );

        return isset($map[$childName]) ? $map[$childName] : null;
    }

    private function toArray($xml)
    {
        $root = new \SimpleXmlElement($xml);

        $definition  = $this->parseNode($root);
        if (!isset($definition['external-schemas'])) {
            return array($definition);
        }

        $definitions = array();

        // Parse external schemas definitions...

        return $definitions;
    }

    private function extractNodeAttributes(\SimpleXmlElement $node)
    {
        $attributes = array();
        foreach ($node->attributes() as $name => $value) {
            $attributes[$name] = (string) $value;
        }

        return $attributes;
    }
}