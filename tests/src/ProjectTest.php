<?php declare(strict_types=1);

namespace DanBettles\Medjool\Tests;

use DanBettles\Medjool\Date;
use PHPUnit\Framework\TestCase;

/**
 * Functional tests
 */
class ProjectTest extends TestCase
{
    public function test(): void
    {
        $startOfPrevMonth = new Date('2026-06-19 13:40:16.123456')
            ->modify('-1 month')
            ->startOf('month')
        ;

        $this->assertSame(
            '2026-05-01 00:00:00.000000',
            $startOfPrevMonth->format('Y-m-d H:i:s.u'),
        );

        $startOfNextMonth = new Date('2026-06-19 13:40:16.123456')
            ->modify('+1 month')
            ->startOf('month')
        ;

        $this->assertSame(
            '2026-07-01 00:00:00.000000',
            $startOfNextMonth->format('Y-m-d H:i:s.u'),
        );
    }
}
