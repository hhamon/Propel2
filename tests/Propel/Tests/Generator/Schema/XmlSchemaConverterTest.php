<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

use Propel\Generator\Schema\XmlSchemaConverter;

class XmlSchemaConverterTest extends \PHPUnit_Framework_TestCase
{
    public function testGetArrayDefinition()
    {
        $file = __DIR__.'/../../../../Fixtures/full-schema.xml';

        $converter = new XmlSchemaConverter();
        $definition = $converter->getArrayDefinition($file);

        $this->assertCount(1, $definition[0]['tables']);
    }
}