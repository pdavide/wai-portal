<?php

namespace Tests\Unit;

use App\Traits\HasEnumLongDescription;
use BenSampo\Enum\Enum;
use Tests\TestCase;

/**
 * Enum descriptions tests.
 */
class HasEnumLongDescriptionTest extends TestCase
{
    /**
     * Test description retrieval using fallback due to missing translations.
     */
    public function testGetDescriptions(): void
    {
        $class = new class(0) extends Enum {
            use HasEnumLongDescription;
            public const TEST_VALUE = 0;
        };

        $this->assertEquals('Test value', $class::getLongDescription($class::TEST_VALUE));
        $this->assertEquals('Test value', $class::getDescription($class::TEST_VALUE));
    }
}
