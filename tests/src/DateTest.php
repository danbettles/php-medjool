<?php declare(strict_types=1);

namespace DanBettles\Medjool\Tests;

use DanBettles\Medjool\Date;
use DanBettles\Medjool\IsoWeekdayEnum;
use DanBettles\Medjool\TestCaseAssertionsTrait;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use const false;
use const true;

/**
 * @phpstan-import-type GetlocationArgs from Date
 */
class DateTest extends TestCase
{
    use TestCaseAssertionsTrait;

    private const string FORMAT_DATE = 'Y-m-d';
    private const string FORMAT_DATE_TIME = self::FORMAT_DATE . ' H:i:s.u';

    public function testIsReadonly(): void
    {
        $this->assertTrue(
            new ReflectionClass(Date::class)->isReadOnly(),
        );
    }

    /** @return array<mixed[]> */
    public static function providesDatetimesCreatedFromScalarDatetimeLikeValues(): array
    {
        return [
            'From date string' => [
                '2026-06-17',
                '2026-06-17',
            ],
            'From timestamp string' => [
                '2026-06-17',
                '@1781654400',
            ],
            'From timestamp int' => [
                '2026-06-17',
                1781654400,  // An integer timestamp isn't supported by the PHP classes
            ],
        ];
    }

    #[DataProvider('providesDatetimesCreatedFromScalarDatetimeLikeValues')]
    public function testIsInstantiable(
        string $expectedDateTimeStr,
        string|int $dateTimeLike,
    ): void {
        $date = new Date($dateTimeLike);
        $immutable = new DateTimeImmutable($expectedDateTimeStr);
        $mutable = new DateTime($expectedDateTimeStr);

        $this->assertEquals($immutable, $date->toImmutable());
        $this->assertEquals($mutable, $date->toMutable());
    }

    public function testConstructorDoesNotWrapADatetimeinterfaceObject(): void
    {
        $inputDateTimeStr = '2026-06-17 20:51:00';

        $immutable = new DateTimeImmutable($inputDateTimeStr);
        $mutable = new DateTime($inputDateTimeStr);

        $dateFromImmutable = new Date($immutable);

        // Not identical:
        $this->assertNotSame($immutable, $dateFromImmutable->toImmutable());
        // ...But equal:
        $this->assertEquals($immutable, $dateFromImmutable->toImmutable());
        $this->assertEquals($mutable, $dateFromImmutable->toMutable());

        $dateFromMutable = new Date($mutable);

        // Not identical:
        $this->assertNotSame($mutable, $dateFromMutable->toMutable());
        // ...But equal:
        $this->assertEquals($mutable, $dateFromMutable->toMutable());
        $this->assertEquals($immutable, $dateFromMutable->toImmutable());
    }

    /** @return array<mixed[]> */
    public static function providesRelativeValues(): array
    {
        return [
            ['now'],
            [''],
        ];
    }

    #[DataProvider('providesRelativeValues')]
    public function testConstructorBehavesTheSameAsADatetimeinterfaceConstructorWhenPassedARelativeValue(
        string $relativeValue,
    ): void {
        $date = new Date($relativeValue);
        $immutable = new DateTimeImmutable($relativeValue);
        $mutable = new DateTime($relativeValue);

        $this->assertDateTimeSimilar($immutable, $date->toImmutable());
        $this->assertDateTimeSimilar($mutable, $date->toMutable());
    }

    public function testCanBeInstantiatedWithoutArguments(): void
    {
        $date = new Date();
        $immutable = new DateTimeImmutable();
        $mutable = new DateTime();

        $this->assertDateTimeSimilar($immutable, $date->toImmutable());
        $this->assertDateTimeSimilar($mutable, $date->toMutable());
    }

    public function testCanBeComparedWithOtherInstances(): void
    {
        $origin = new Date('2026-06-24 14:17');

        $this->assertLessThan(
            $origin,
            $origin->modify('-1 second'),
        );

        $this->assertGreaterThan(
            $origin,
            $origin->modify('+1 second'),
        );

        $this->assertEquals(
            $origin,
            clone $origin,
        );
    }

    /** @return array<mixed[]> */
    public static function providesComponents(): array
    {
        return [
            'Return all values by default' => [
                [
                    'year' => 2026,
                    'month' => 6,
                    'day' => 18,
                    'hour' => 10,
                    'minute' => 16,
                    'second' => 42,
                    'microsecond' => 654321,
                ],
                '2026-06-18 10:16:42.654321',
                [],
            ],
            'Return an individual component' => [
                [
                    'year' => 2026,
                ],
                '2026-06-18 10:16:42.654321',
                ['year'],
            ],
            'Return values in the same order as the names in the CSV' => [
                [
                    'day' => 18,
                    'month' => 6,
                    'year' => 2026,
                ],
                '2026-06-18 10:16:42.654321',
                ['day,month,year'],
            ],
            'Allow whitespace in the names CSV' => [
                [
                    'day' => 18,
                    'month' => 6,
                    'year' => 2026,
                ],
                '2026-06-18 10:16:42.654321',
                ['day, month, year'],
            ],
        ];
    }

    /**
     * @param array<string,int> $expectedComponents
     * @param array{string} $methodArgs
     */
    #[DataProvider('providesComponents')]
    public function testGetcomponents(
        array $expectedComponents,
        string $inputDateTimeStr,
        array $methodArgs,
    ): void {
        $date = new Date($inputDateTimeStr);

        $this->assertSame($expectedComponents, $date->getComponents(...$methodArgs));
    }

    /** @return array<mixed[]> */
    public static function providesInvalidNamesCsvs(): array
    {
        return [
            [
                'Invalid component names: foo',
                'foo',
            ],
            [
                'Invalid component names: mont, days',
                'year,mont,days',
            ],
        ];
    }

    #[DataProvider('providesInvalidNamesCsvs')]
    public function testGetcomponentsThrowsAnExceptionIfThereIsAProblemWithTheNamesCsv(
        string $expectedExceptionMessage,
        string $invalidNamesCsv,
    ): void {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        new Date()->getComponents($invalidNamesCsv);
    }

    /** @return array<mixed[]> */
    public static function providesSingleComponents(): array
    {
        $dateTimeStr = '2026-06-18 10:16:42.654321';

        return [
            [
                2026,
                $dateTimeStr,
                ['year'],
            ],
            [
                6,
                $dateTimeStr,
                ['month'],
            ],
            [
                18,
                $dateTimeStr,
                ['day'],
            ],
            [
                10,
                $dateTimeStr,
                ['hour'],
            ],
            [
                16,
                $dateTimeStr,
                ['minute'],
            ],
            [
                42,
                $dateTimeStr,
                ['second'],
            ],
            [
                654321,
                $dateTimeStr,
                ['microsecond'],
            ],
        ];
    }

    /**
     * @param array{string} $methodArgs
     */
    #[DataProvider('providesSingleComponents')]
    public function testGetcomponentReturnsTheValueOfASingleComponent(
        int $expectedValue,
        string $inputDateTimeStr,
        array $methodArgs,
    ): void {
        $date = new Date($inputDateTimeStr);

        $this->assertSame(
            $expectedValue,
            $date->getComponent(...$methodArgs),
        );
    }

    public function testGetcomponentCallsGetcomponents(): void
    {
        $componentName = 'month';
        $expectedValue = 6;

        $dateMock = $this
            ->getMockBuilder(Date::class)
            ->onlyMethods(['getComponents'])
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $dateMock
            ->expects($this->once())
            ->method('getComponents')
            ->with($componentName)
            ->willReturn([$componentName => $expectedValue])
        ;

        $this->assertSame(
            $expectedValue,
            $dateMock->getComponent($componentName),
        );
    }

    /** @return array<mixed[]> */
    public static function providesIsoweekdayenumsByDate(): array
    {
        return [
            [
                IsoWeekdayEnum::Monday,
                '2026-06-15',
            ],
            [
                IsoWeekdayEnum::Tuesday,
                '2026-06-16',
            ],
            [
                IsoWeekdayEnum::Wednesday,
                '2026-06-17',
            ],
            [
                IsoWeekdayEnum::Thursday,
                '2026-06-18',
            ],
            [
                IsoWeekdayEnum::Friday,
                '2026-06-19',
            ],
            [
                IsoWeekdayEnum::Saturday,
                '2026-06-20',
            ],
            [
                IsoWeekdayEnum::Sunday,
                '2026-06-21',
            ],
        ];
    }

    #[DataProvider('providesIsoweekdayenumsByDate')]
    public function testGetisoweekdayReturnsAnEnum(
        IsoWeekdayEnum $expectedEnum,
        string $inputDateTimeStr,
    ): void {
        $this->assertSame(
            $expectedEnum,
            new Date($inputDateTimeStr)->getIsoWeekday(),
        );
    }

    public function testFormatCallsTheProxyMethodOnly(): void
    {
        $expectedDateStr = '2026-06-19';
        $format = self::FORMAT_DATE;

        $dateMock = $this
            ->getMockBuilder(Date::class)
            ->onlyMethods(['formatThePitOnly'])
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $dateMock
            ->expects($this->once())
            ->method('formatThePitOnly')
            ->with($format)
            ->willReturn($expectedDateStr)
        ;

        $this->assertSame(
            $expectedDateStr,
            $dateMock->format($format),
        );
    }

    /** @return array<mixed[]> */
    public static function providesFormattedDatetimes(): array
    {
        return [
            [
                '19 Jun',
                '2026-06-19 14:17:28.123456',
                'j M',
            ],
            [
                '2026-06-19T14:17:28+00:00',
                '2026-06-19 14:17:28.123456',
                'c',
            ],
        ];
    }

    #[DataProvider('providesFormattedDatetimes')]
    public function testFormatBehavesTheSameAsTheDatetimeinterfaceFormatMethod(
        string $expectedDateTimeStr,
        string $inputDateTimeStr,
        string $format,
    ): void {
        $this->assertSame(
            $expectedDateTimeStr,
            new Date($inputDateTimeStr)->format($format),
        );
    }

    /** @return array<mixed[]> */
    public static function providesDatetimesThatHaveBeenResetToAComponent(): array
    {
        return [
            [
                '2026-01-01 00:00:00.000000',
                '2026-06-18 13:07:07.123456',
                ['year'],
            ],
            [
                '2026-06-01 00:00:00.000000',
                '2026-06-18 13:07:07.123456',
                ['month'],
            ],
            [
                '2026-06-18 00:00:00.000000',
                '2026-06-18 13:07:07.123456',
                ['day'],
            ],
            [
                '2026-06-18 00:00:00.000000',
                '2026-06-18 13:07:07.123456',
            ],
            [
                '2026-06-18 13:00:00.000000',
                '2026-06-18 13:07:07.123456',
                ['hour'],
            ],
            [
                '2026-06-18 13:07:00.000000',
                '2026-06-18 13:07:07.123456',
                ['minute'],
            ],
            [
                '2026-06-18 13:07:07.000000',
                '2026-06-18 13:07:07.123456',
                ['second'],
            ],
            // (Can't get the start-of `"microsecond"`)
        ];
    }

    /**
     * @param array{0?:string} $methodArgs
     */
    #[DataProvider('providesDatetimesThatHaveBeenResetToAComponent')]
    public function testStartof(
        string $expectedDateTimeStr,
        string $inputDateTimeStr,
        array $methodArgs = [],
    ): void {
        $date = new Date($inputDateTimeStr);

        $startOfSomething = $date->startOf(...$methodArgs);

        $this->assertInstanceOf(Date::class, $startOfSomething);
        $this->assertNotSame($date, $startOfSomething);

        $this->assertSame(
            $expectedDateTimeStr,
            $startOfSomething->format(self::FORMAT_DATE_TIME),
        );
    }

    /** @return array<mixed[]> */
    public static function providesComponentNamesThatCannotBePassedToStartof(): array
    {
        return [
            'Valid, but inapplicable, component name' => [
                'microsecond',
            ],
            'Invalid component name' => [
                'foo',
            ],
        ];
    }

    #[DataProvider('providesComponentNamesThatCannotBePassedToStartof')]
    public function testStartofThrowsAnExceptionIfTheComponentNameIsInvalid(
        string $invalidComponentName,
    ): void {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage("The component name, `{$invalidComponentName}`, is invalid/inapplicable");

        new Date('2026-06-18 21:09:38.123456')->startOf($invalidComponentName);
    }

    /**
     * @param array{0?:string} $truncArgs
     */
    #[DataProvider('providesDatetimesThatHaveBeenResetToAComponent')]
    public function testTruncCallsStartofOnly(
        string $expectedDateTimeStr,
        string $inputDateTimeStr,
        array $truncArgs = [],
    ): void {
        $expectedDate = new Date($expectedDateTimeStr);

        $dateMock = $this
            ->getMockBuilder(Date::class)
            ->onlyMethods(['startOf'])
            ->setConstructorArgs([$inputDateTimeStr])
            ->getMock()
        ;

        $dateMock
            ->expects($this->once())
            ->method('startOf')
            ->with(...$truncArgs)
            ->willReturn($expectedDate)
        ;

        $something = $dateMock->trunc(...$truncArgs);

        $this->assertSame($expectedDate, $something);
    }

    /** @return array<mixed[]> */
    public static function providesDatetimesInTheFuture(): array
    {
        return [
            'Tomorrow' => [
                true,
                new DateTimeImmutable('+1 day'),
            ],
            'Tomorrow, explicitly not ignoring time' => [
                true,
                new DateTimeImmutable('+1 day'),
                [false],
            ],
            'Tomorrow, ignore time' => [
                true,
                new DateTimeImmutable('+1 day'),
                [true],
            ],

            'Yesterday' => [
                false,
                new DateTimeImmutable('-1 day'),
            ],
            'Yesterday, explicitly not ignoring time' => [
                false,
                new DateTimeImmutable('-1 day'),
                [false],
            ],
            'Yesterday, ignore time' => [
                false,
                new DateTimeImmutable('-1 day'),
                [true],
            ],

            '1 hour ago' => [
                false,
                new DateTimeImmutable('-1 hour'),
            ],

            '1 hour from now' => [
                true,
                new DateTimeImmutable('+1 hour'),
            ],
            '1 hour from now, ignore time' => [
                false,  // (Same day and ignoring time, so not in future)
                new DateTimeImmutable('+1 hour'),
                [true],
            ],
        ];
    }

    /**
     * @phpstan-param GetlocationArgs $methodArgs
     */
    #[DataProvider('providesDatetimesInTheFuture')]
    public function testIsinfuture(
        bool $inFutureOrNot,
        DateTimeImmutable $immutable,
        array $methodArgs = [],
    ): void {
        $immutableClone = clone $immutable;

        $date = new Date($immutable);

        $this->assertSame($inFutureOrNot, $date->isInFuture(...$methodArgs));
        // (Internal value unchanged)
        $this->assertEquals($immutableClone, $date->toImmutable());
    }

    /** @return array<mixed[]> */
    public static function providesDatetimesInThePast(): array
    {
        return [
            'Tomorrow' => [
                false,
                new DateTimeImmutable('+1 day'),
            ],
            'Tomorrow, explicitly not ignoring time' => [
                false,
                new DateTimeImmutable('+1 day'),
                [false],
            ],
            'Tomorrow, ignore time' => [
                false,
                new DateTimeImmutable('+1 day'),
                [true],
            ],

            'Yesterday' => [
                true,
                new DateTimeImmutable('-1 day'),
            ],
            'Yesterday, explicitly not ignoring time' => [
                true,
                new DateTimeImmutable('-1 day'),
                [false],
            ],
            'Yesterday, ignore time' => [
                true,
                new DateTimeImmutable('-1 day'),
                [true],
            ],

            '1 hour ago' => [
                true,
                new DateTimeImmutable('-1 hour'),
            ],

            '1 hour from now' => [
                false,
                new DateTimeImmutable('+1 hour'),
            ],
            '1 hour from now, ignore time' => [
                false,  // (Same day and ignoring time, so not in past)
                new DateTimeImmutable('+1 hour'),
                [true],
            ],
        ];
    }

    /**
     * @phpstan-param GetlocationArgs $methodArgs
     */
    #[DataProvider('providesDatetimesInThePast')]
    public function testIsinpast(
        bool $inPastOrNot,
        DateTimeImmutable $immutable,
        array $methodArgs = [],
    ): void {
        $immutableClone = clone $immutable;

        $date = new Date($immutable);

        $this->assertSame($inPastOrNot, $date->isInPast(...$methodArgs));
        // (Internal value unchanged)
        $this->assertEquals($immutableClone, $date->toImmutable());
    }

    public function testModifyCallsTheProxyMethodOnly(): void
    {
        $expectedImmutable = new DateTimeImmutable('2026-06-18 12:08');
        $modifier = '-1 days';

        $dateMock = $this
            ->getMockBuilder(Date::class)
            ->onlyMethods(['modifyThePitOnly'])
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $dateMock
            ->expects($this->once())
            ->method('modifyThePitOnly')
            ->with($modifier)
            ->willReturn($expectedImmutable)
        ;

        $modified = $dateMock->modify($modifier);

        $this->assertInstanceOf(Date::class, $modified);
        $this->assertNotSame($dateMock, $modified);

        $this->assertEquals($expectedImmutable, $modified->toImmutable());
    }

    /** @return array<mixed[]> */
    public static function providesModifiedDatetimes(): array
    {
        return [
            [
                '2026-06-20 00:00:00.000000',
                '2026-06-19 14:17:28.123456',
                'next saturday',
            ],
            [
                '2026-06-26 14:17:28.123456',
                '2026-06-19 14:17:28.123456',
                '+7 days',
            ],
        ];
    }

    #[DataProvider('providesModifiedDatetimes')]
    public function testModifyBehavesTheSameAsTheDatetimeinterfaceModifyMethod(
        string $expectedDateTimeStr,
        string $inputDateTimeStr,
        string $modifier,
    ): void {
        $modified = new Date($inputDateTimeStr)->modify($modifier);

        $this->assertSame(
            $expectedDateTimeStr,
            $modified->format(self::FORMAT_DATE_TIME),
        );
    }

    public function testTomysqldatestring(): void
    {
        $this->assertSame(
            '2026-06-19',
            new Date('2026-06-19 15:59:18.123456')->toMysqlDateString(),
        );
    }

    /** @return array<mixed[]> */
    public static function providesMysqlFormatDatetimes(): array
    {
        return [
            [
                '2026-06-19 15:59:18',
                '2026-06-19 15:59:18.123456',
                [],
            ],
            [
                '2026-06-19 15:59:18',
                '2026-06-19 15:59:18.123456',
                [false],
            ],
            [
                '2026-06-19 15:59:18.123456',
                '2026-06-19 15:59:18.123456',
                [true],
            ],
        ];
    }

    /**
     * @param array{0?:bool} $methodArgs
     */
    #[DataProvider('providesMysqlFormatDatetimes')]
    public function testTomysqldatetimestring(
        string $expectedDateTimeStr,
        string $inputDateTimeStr,
        array $methodArgs = [],
    ): void {
        $this->assertSame(
            $expectedDateTimeStr,
            new Date($inputDateTimeStr)->toMysqlDateTimeString(...$methodArgs),
        );
    }

    /** @return array<mixed[]> */
    public static function providesDatetimesSwitchedToADifferentTimeZone(): array
    {
        return [
            // Time-zone object:
            [
                '2026-06-22T19:17:00+09:00',
                '2026-06-22T11:17:00+01:00',  // (BST)
                [new DateTimeZone('JST'), /* Time adjusted by default */],
            ],
            [
                '2026-06-22T19:17:00+09:00',
                '2026-06-22T11:17:00+01:00',  // (BST)
                [new DateTimeZone('JST'), true],
            ],
            [
                '2026-06-22T11:17:00+09:00',  // Note: same time!
                '2026-06-22T11:17:00+01:00',  // (BST)
                [new DateTimeZone('JST'), false],
            ],
            // Time-zone string:
            [
                '2026-06-22T19:17:00+09:00',
                '2026-06-22T11:17:00+01:00',  // (BST)
                ['JST', /* Time adjusted by default */],
            ],
            [
                '2026-06-22T19:17:00+09:00',
                '2026-06-22T11:17:00+01:00',  // (BST)
                ['JST', true],
            ],
            [
                '2026-06-22T11:17:00+09:00',  // Note: same time!
                '2026-06-22T11:17:00+01:00',  // (BST)
                ['JST', false],
            ],
        ];
    }

    /**
     * @param array{0:DateTimeZone|string,1?:bool} $methodArgs
     */
    #[DataProvider('providesDatetimesSwitchedToADifferentTimeZone')]
    public function testSettimezone(
        string $expectedDateTimeStr,
        string $inputDateTimeStr,
        array $methodArgs,
    ): void {
        $date = new Date($inputDateTimeStr);
        $something = $date->setTimezone(...$methodArgs);

        $this->assertInstanceOf(Date::class, $something);
        $this->assertNotSame($date, $something);

        $this->assertSame($expectedDateTimeStr, $something->format('c'));
    }

    public function testToisodatetimestring(): void
    {
        // We go around the houses here so it's clear we started with something different to the expected output
        $input = new DateTimeImmutable('2026-06-22 20:15:37')
            ->setTimezone(new DateTimeZone('JST'))
        ;

        $this->assertSame(
            '2026-06-23T05:15:37+09:00',
            new Date($input)->toIsoDateTimeString(),
        );
    }

    public function testMagicTostringCallsToisodatetimestringOnly(): void
    {
        $expectedDateTimeStr = '2026-06-23T05:15:37+09:00';

        $dateMock = $this
            ->getMockBuilder(Date::class)
            ->onlyMethods(['toIsoDateTimeString'])
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $dateMock
            ->expects($this->once())
            ->method('toIsoDateTimeString')
            ->with()
            ->willReturn($expectedDateTimeStr)
        ;

        $this->assertSame(
            $expectedDateTimeStr,
            (string) $dateMock,
        );
    }

    public function testYesterdayReturnsADateForYesterday(): void
    {
        $yesterdayImmutable = new DateTimeImmutable('-1 day');

        $this->assertDateTimeSimilar(
            $yesterdayImmutable,
            Date::yesterday()->toImmutable(),
        );

        $this->assertDateTimeSimilar(
            $yesterdayImmutable,
            Date::yesterday(startOfDay: false)->toImmutable(),
        );

        $this->assertSame(
            $yesterdayImmutable->format(self::FORMAT_DATE . ' 00:00:00.000000'),
            Date::yesterday(startOfDay: true)->format(self::FORMAT_DATE_TIME),
        );
    }

    public function testTomorrowReturnsADateForTomorrow(): void
    {
        $tomorrowImmutable = new DateTimeImmutable('+1 day');

        $this->assertDateTimeSimilar(
            $tomorrowImmutable,
            Date::tomorrow()->toImmutable(),
        );

        $this->assertDateTimeSimilar(
            $tomorrowImmutable,
            Date::tomorrow(startOfDay: false)->toImmutable(),
        );

        $this->assertSame(
            $tomorrowImmutable->format(self::FORMAT_DATE . ' 00:00:00.000000'),
            Date::tomorrow(startOfDay: true)->format(self::FORMAT_DATE_TIME),
        );
    }
}
