<?php

namespace App\Tests\Service;

use App\Service\CalculatorService;
use PHPUnit\Framework\TestCase;

class CalculatorServiceTest extends TestCase
{
    public function test_add(): void
    {
        $service = new CalculatorService();

        $result = $service->add(2, 3);

        $this->assertSame(5, $result);
    }
}