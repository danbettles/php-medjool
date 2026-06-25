<?php declare(strict_types=1);

namespace DanBettles\Medjool;

use DateMalformedStringException;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use LogicException;
use NoDiscard;
use OutOfBoundsException;
use TypeError;

use function array_column;
use function array_combine;
use function array_flip;
use function array_intersect_key;
use function array_key_exists;
use function array_key_last;
use function array_keys;
use function array_map;
use function array_replace;
use function count;
use function explode;
use function implode;
use function intval;
use function is_int;
use function str_contains;
use function trim;

use const false;
use const null;
use const true;

/**
 * Value object
 *
 * @phpstan-type GetlocationArgs array{0?:bool}
 */
readonly class Date
{
    private const string NO_DISCARD_MESSAGE = (
        "`Date` is a Value Object: it won't be mutated by calling this method.  "
            . "Therefore, not using the return value is wasteful/pointless."
    );

    private const string LOC_PAST = 'past';
    private const string LOC_PRESENT = 'present';
    private const string LOC_FUTURE = 'future';

    private const string COMPONENT_YEAR = 'year';
    private const string COMPONENT_MONTH = 'month';
    private const string COMPONENT_DAY = 'day';
    private const string COMPONENT_HOUR = 'hour';
    private const string COMPONENT_MINUTE = 'minute';
    private const string COMPONENT_SECOND = 'second';
    private const string COMPONENT_MICROSECOND = 'microsecond';

    private const string FORMAT_MYSQL_DATE = 'Y-m-d';
    private const string FORMAT_MYSQL_TIME = 'H:i:s';
    private const string FORMAT_MYSQL_TIME_FULL = (self::FORMAT_MYSQL_TIME . '.u');
    private const string FORMAT_MYSQL_DATE_TIME = (self::FORMAT_MYSQL_DATE . ' ' . self::FORMAT_MYSQL_TIME);
    private const string FORMAT_MYSQL_DATE_TIME_FULL = (self::FORMAT_MYSQL_DATE . ' ' . self::FORMAT_MYSQL_TIME_FULL);

    /**
     * Date-component metadata
     *
     * N.B. In descending-size order
     *
     * @var list<array{name:string,zeroValue:int|null,format:string}>
     */
    private const array COMPONENTS = [
        [
            'name' => self::COMPONENT_YEAR,
            'zeroValue' => null,  // (N/A)
            'format' => 'x',  // (Expanded if required, or standard if possible (like Y))
        ],
        [
            'name' => self::COMPONENT_MONTH,
            'zeroValue' => 1,
            'format' => 'n',
        ],
        [
            'name' => self::COMPONENT_DAY,
            'zeroValue' => 1,
            'format' => 'j',
        ],
        [
            'name' => self::COMPONENT_HOUR,
            'zeroValue' => 0,
            'format' => 'G',
        ],
        [
            'name' => self::COMPONENT_MINUTE,
            'zeroValue' => 0,
            'format' => 'i',
        ],
        [
            'name' => self::COMPONENT_SECOND,
            'zeroValue' => 0,
            'format' => 's',
        ],
        [
            'name' => self::COMPONENT_MICROSECOND,
            'zeroValue' => 0,
            'format' => 'u',
        ],
    ];

    /**
     * Maps components to their `DateTimeImmutable` setter
     *
     * N.B. Method-parameter order
     *
     * @var array<string,list<string>>
     */
    private const array COMPONENT_NAMES_PER_SETTER = [
        'setDate' => [
            self::COMPONENT_YEAR,
            self::COMPONENT_MONTH,
            self::COMPONENT_DAY,
        ],
        'setTime' => [
            self::COMPONENT_HOUR,
            self::COMPONENT_MINUTE,
            self::COMPONENT_SECOND,
            self::COMPONENT_MICROSECOND,
        ],
    ];

    /**
     * N.B. Strictly immutable only!
     */
    private DateTimeImmutable $pit;

    /**
     * Factory method, returns an instance created from the input; returns `null` if the input couldn't be translated
     *
     * By design, exceptions will leak out if something goes *very* wrong
     */
    public static function tryFrom(mixed $something): self|null
    {
        try {
            // @phpstan-ignore argument.type (Because I don't want to repeat the rule about accepted types)
            return new self($something);
        } catch (TypeError $ex) {
            if (str_contains($ex->getMessage(), __CLASS__ . '::__construct(): Argument ')) {
                // We'll allow a `TypeError` only at the point of entry
                return null;
            }

            throw $ex;
        } catch (DateMalformedStringException $ex) {
            return null;
        }
    }

    /**
     * Factory method, returns this instant
     */
    public static function now(): self
    {
        return new self();
    }

    /**
     * Factory method, returns the start of today
     */
    public static function today(): self
    {
        return new self()->trunc();
    }

    /**
     * Factory method, returns the start of yesterday
     */
    public static function yesterday(): self
    {
        return new self('-1 day')->trunc();
    }

    /**
     * Factory method, returns the start of tomorrow
     */
    public static function tomorrow(): self
    {
        return new self('+1 day')->trunc();
    }

    public function __construct(
        string|int|DateTimeInterface $dateTimeLike = 'now',
    ) {
        $this->pit = match (true) {
            $dateTimeLike instanceof DateTimeInterface
                => DateTimeImmutable::createFromInterface($dateTimeLike),
            default
                => new DateTimeImmutable(is_int($dateTimeLike) ? "@{$dateTimeLike}" : $dateTimeLike),
        };
    }

    /**
     * Also functions as a proxy for tests
     */
    protected function modifyThePitOnly(string $modifier): DateTimeImmutable
    {
        return $this->pit->modify($modifier);
    }

    /**
     * Behaves the same as `DateTimeImmutable::modify()` and `DateTime::modify()`
     *
     * For external use only
     */
    #[NoDiscard(self::NO_DISCARD_MESSAGE)]
    public function modify(string $modifier): self
    {
        return new self($this->modifyThePitOnly($modifier));
    }

    /**
     * Also functions as a proxy for tests
     */
    protected function formatThePitOnly(string $format): string
    {
        return $this->pit->format($format);
    }

    /**
     * Behaves the same as `DateTimeInterface::format()`
     *
     * For external use only
     */
    public function format(string $format): string
    {
        return $this->formatThePitOnly($format);
    }

    /**
     * For external use only
     */
    public function toImmutable(): DateTimeImmutable
    {
        // (No need to clone because it's an immutable)
        return $this->pit;
    }

    /**
     * For external use only
     */
    public function toMutable(): DateTime
    {
        return DateTime::createFromImmutable($this->pit);
    }

    /**
     * The MySQL date format is "YYYY-MM-DD"
     *
     * See https://dev.mysql.com/doc/refman/9.1/en/datetime.html
     */
    public function toMysqlDateString(): string
    {
        return $this->formatThePitOnly(self::FORMAT_MYSQL_DATE);
    }

    /**
     * The usual MySQL date-time format is "YYYY-MM-DD hh:mm:ss".  You can opt to include the "fractional seconds part"
     * (microseconds).
     *
     * See https://dev.mysql.com/doc/refman/9.1/en/datetime.html
     */
    public function toMysqlDateTimeString(bool $fsp = false): string
    {
        return $this->formatThePitOnly(
            $fsp ? self::FORMAT_MYSQL_DATE_TIME_FULL : self::FORMAT_MYSQL_DATE_TIME,
        );
    }

    /**
     * Returns the date in ISO-8601 (Expanded) format
     */
    public function toIsoDateString(): string
    {
        return explode('T', $this->toIsoDateTimeString())[0];
    }

    /**
     * Returns the date and time in ISO-8601 Expanded format, which is supported by JavaScript's `Date` object -- see
     * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date#date_time_string_format
     */
    public function toIsoDateTimeString(): string
    {
        return $this->formatThePitOnly(DateTimeInterface::ISO8601_EXPANDED);
    }

    /**
     * Returns the date formatted according to the ISO 8601 spec
     */
    public function __toString(): string
    {
        return $this->toIsoDateTimeString();
    }

    public function getTimestamp(): int
    {
        return $this->pit->getTimestamp();
    }

    /**
     * Returns: all components in descending-size order; or only the named components in the specified order
     *
     * @return array<string,int>
     * @throws LogicException If one/more component names are invalid
     */
    public function getComponents(string $namesCsv = ''): array
    {
        $sep = ',';

        $formatCharsByComponentName = array_column(self::COMPONENTS, 'format', 'name');

        if ('' !== $namesCsv) {
            $names = array_map(trim(...), explode($sep, $namesCsv));
            // (Component-defs order)
            $filteredMap = array_intersect_key($formatCharsByComponentName, array_flip($names));

            if (count($names) !== count($filteredMap)) {
                $invalidNames = array_diff($names, array_keys($filteredMap));

                throw new LogicException('Invalid component names: ' . implode(', ', $invalidNames));
            }

            // (User-specified order)
            $formatCharsByComponentName = array_replace(array_flip($names), $filteredMap);
        }

        $formatCharsCsv = implode($sep, $formatCharsByComponentName);

        $rawComponents = array_combine(
            array_keys($formatCharsByComponentName),
            explode($sep, $this->formatThePitOnly($formatCharsCsv)),
        );

        return array_map(intval(...), $rawComponents);
    }

    /**
     * Returns the value of a single component
     */
    public function getComponent(string $name): int
    {
        return $this->getComponents($name)[$name];
    }

    /**
     * @param array<string,int> $overrides
     */
    #[NoDiscard(self::NO_DISCARD_MESSAGE)]
    private function setComponents(array $overrides): self
    {
        $existing = $this->getComponents();

        // @todo Validate overrides

        $replacements = array_replace($existing, $overrides);
        $immutable = $this->pit;

        foreach (self::COMPONENT_NAMES_PER_SETTER as $setterName => $componentNames) {
            $setterArgs = array_intersect_key($replacements, array_flip($componentNames));
            $immutable = $immutable->{$setterName}(...$setterArgs);
        }

        return new self($immutable);
    }

    /**
     * Obscure
     */
    public function getIsoWeekday(): IsoWeekdayEnum
    {
        return IsoWeekdayEnum::from(
            (int) $this->formatThePitOnly('N'),
        );
    }

    /**
     * Returns a new instance that has been reset to the specified date component
     *
     * Examples:
     * - Start-of day -- the default -- resets the time => midnight on the current day
     * - Start-of month resets the day-number and the time => midnight on the first of the month
     *
     * @throws OutOfBoundsException If the component name is invalid/inapplicable
     */
    #[NoDiscard(self::NO_DISCARD_MESSAGE)]
    public function startOf(string $componentName = self::COMPONENT_DAY): self
    {
        $zeroValsByComponent = array_column(self::COMPONENTS, 'zeroValue', 'name');

        $componentNameIsValid = array_key_exists($componentName, $zeroValsByComponent)
            && array_key_last($zeroValsByComponent) !== $componentName
        ;

        if (!$componentNameIsValid) {
            throw new OutOfBoundsException("The component name, `{$componentName}`, is invalid/inapplicable");
        }

        $overrideValueOfCurrComponent = false;
        $overrides = [];

        foreach ($zeroValsByComponent as $currComponentName => $componentZeroValue) {
            if ($overrideValueOfCurrComponent) {
                $overrides[$currComponentName] = $componentZeroValue;

                continue;
            }

            if ($componentName === $currComponentName) {
                $overrideValueOfCurrComponent = true;

                // continue;
            }
        }

        return $this->setComponents($overrides);
    }

    /**
     * Alias for `startOf()`.  Named after, and behaves like, Oracle's `TRUNC` (date) function -- which is kinda what
     * inspired `startOf()`.
     */
    #[NoDiscard(self::NO_DISCARD_MESSAGE)]
    public function trunc(string $componentName = self::COMPONENT_DAY): self
    {
        return $this->startOf($componentName);
    }

    /**
     * Returns `-1` if the date is less than the other; `1` if it's greater; `0` if they're equal
     */
    private function compare(self $other): int
    {
        if ($this < $other) {
            return -1;
        }

        if ($this > $other) {
            return 1;
        }

        return 0;
    }

    /**
     * Returns the general temporal 'location' (past, present, or future) of the date
     *
     * @todo Create `LocationEnum` if this method is exposed
     */
    private function getLocation(bool $ignoreTime = false): string
    {
        $left = $this;
        $right = new self();

        if ($ignoreTime) {
            $left = $left->trunc();
            $right = $right->trunc();
        }

        return match ($left->compare($right)) {
            -1 => self::LOC_PAST,
            1 => self::LOC_FUTURE,
            default => self::LOC_PRESENT,
        };
    }

    public function isInPast(bool $ignoreTime = false): bool
    {
        return self::LOC_PAST === $this->getLocation($ignoreTime);
    }

    public function isInFuture(bool $ignoreTime = false): bool
    {
        return self::LOC_FUTURE === $this->getLocation($ignoreTime);
    }

    /**
     * Changes the time zone.  You can opt to preserve the time component.
     *
     * Named after its `DateTimeImmutable` counterpart -- hence the spelling.
     */
    #[NoDiscard(self::NO_DISCARD_MESSAGE)]
    public function setTimezone(
        DateTimeZone|string $dateTimeZone,
        bool $adjustTime = true,
    ): self {
        if (is_string($dateTimeZone)) {
            $dateTimeZone = new DateTimeZone($dateTimeZone);
        }

        $origOffset = $this->pit->getOffset();

        $newPit = $this->pit->setTimezone($dateTimeZone);

        if (!$adjustTime) {
            $secondsToAdjustBy = $origOffset - $newPit->getOffset();
            $newPit = $newPit->modify("+{$secondsToAdjustBy} seconds");
        }

        return new self($newPit);
    }
}
