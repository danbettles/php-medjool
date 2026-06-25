<?php

$timeZoneForTests = 'UTC';

date_default_timezone_set($timeZoneForTests);

// phpcs:ignore DanBettles.Debug.PHPOutputFunctions.Found
echo "\e[97;41m ㊟ Time-zone set to `{$timeZoneForTests}` for tests \e[0m\n\n";
