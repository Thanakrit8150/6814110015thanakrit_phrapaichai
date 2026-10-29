<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_money_values_can_be_converted_to_integer_minor_units(): void
    {
        self::assertSame(1001, (int) round(10.01 * 100));
        self::assertSame(0, (int) round(0.00 * 100));
    }
}
