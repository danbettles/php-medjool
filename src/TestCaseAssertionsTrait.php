<?php declare(strict_types=1);

namespace DanBettles\Medjool;

use DateTimeInterface;

use function abs;

/**
 * @phpstan-require-extends \PHPUnit\Framework\TestCase
 * @mixin \PHPUnit\Framework\TestCase
 */
trait TestCaseAssertionsTrait
{
    /**
     * Returns `true` if:
     * - the types are the same;
     * - there's no difference between the two values OR there is a difference but it's within the tolerance
     */
    protected function assertDateTimeSimilar(
        DateTimeInterface $expected,
        DateTimeInterface $actual,
        int $toleranceMus = 500,  // (0.5 ms)
    ): void {
        $this->assertInstanceOf(
            $expected::class,
            $actual,
            'Is not the same type of PHP date-time',
        );

        $musDiff = abs((int) $actual->format('Uu') - (int) $expected->format('Uu'));

        $this->assertLessThanOrEqual(
            $toleranceMus,
            $musDiff,
            'The difference between the two PHP date-times is too great',
        );
    }
}
