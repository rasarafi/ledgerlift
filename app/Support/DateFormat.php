<?php

namespace App\Support;

use DateTime;

/**
 * Port of date_to_mysql() and date_from_mysql() from application/helpers/date_helper.php.
 */
final class DateFormat
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * User-formatted date to Y-m-d; '' when it does not parse.
     */
    public function toMysql(string $date): string
    {
        $parsed = DateTime::createFromFormat($this->format(), $date);

        return $parsed ? $parsed->format('Y-m-d') : '';
    }

    /**
     * Y-m-d to the user's date format; '' for an empty or unparseable value.
     *
     * Legacy hands the value back untouched while $_POST is non-empty
     * (date_helper.php:84), unless the caller opts out.
     */
    public function fromMysql(?string $date, bool $ignorePostCheck = false): string
    {
        if (! $date) {
            return '';
        }

        if ($_POST !== [] && ! $ignorePostCheck) {
            return $date;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        return $parsed ? $parsed->format($this->format()) : '';
    }

    private function format(): string
    {
        return (string) $this->settings->get('date_format', '');
    }
}
