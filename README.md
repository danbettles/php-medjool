# Medjool

A simple extension of PHP's Date/Time module, to make working with dates and times a little smoother.  In general, I think Date/Time is actually pretty great.  In some cases, though, things can feel a little clunky, so that's why Medjool is "like PHP dates, only juicier".

## Overview

At Medjool's heart is the `Date` class, which is a Value Object&mdash;like `DateTimeImmutable`.

Of note:

- The constructor accepts a date-time string, an integer timestamp, a `DateTimeImmutable`, or a `DateTime`, so there's no need to use different methods to create an instance.
- Provides `toIsoDateTimeString()`, `toMysqlDateString()`, and `toMysqlDateTimeString()` to easily format the date.
- `getComponents()` gives you access to all/selected date and time components in one go.
- `startOf()` and its alias, `trunc()`, resets the date to the specified date component.
- `isInPast()` and `isInFuture()` simply read better.
- `setTimezone()` gives you the option to preserve the time.
