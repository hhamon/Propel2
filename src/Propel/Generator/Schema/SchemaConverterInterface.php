<?php

namespace Propel\Generator\Schema;

interface SchemaConverterInterface
{
    /**
     * Parses a schema definition and returns an array.
     *
     * @return array
     */
    function getArrayDefinition($file);

    /**
     * Returns whether or not the converter supports a specific schema file.
     *
     * @return Boolean
     */
    function supports($file);
}