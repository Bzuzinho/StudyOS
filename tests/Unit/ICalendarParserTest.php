<?php

namespace Tests\Unit;

use App\Services\Calendar\ICalendarParser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ICalendarParserTest extends TestCase
{
    public function test_it_parses_a_calendar_event_with_location(): void
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//StudyOS Test//EN
BEGIN:VEVENT
UID:class-1@example.test
DTSTART;TZID=Europe/Lisbon:20261005T180000
DTEND;TZID=Europe/Lisbon:20261005T200000
SUMMARY:Métodos Quantitativos
LOCATION:A.S2.5
DESCRIPTION:Aula TP
END:VEVENT
END:VCALENDAR
ICS;

        $events = (new ICalendarParser())->parse(
            $ics,
            new DateTimeImmutable('2026-10-01T00:00:00+01:00'),
            new DateTimeImmutable('2026-10-10T23:59:59+01:00'),
        );

        self::assertCount(1, $events);
        self::assertSame('Métodos Quantitativos', $events[0]['title']);
        self::assertSame('A.S2.5', $events[0]['location']);
        self::assertSame('scheduled', $events[0]['status']);
        self::assertSame('2026-10-05T18:00:00+01:00', $events[0]['starts_at']->format(DATE_ATOM));
    }

    public function test_it_expands_a_recurring_event_inside_the_window(): void
    {
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//StudyOS Test//EN
BEGIN:VEVENT
UID:weekly@example.test
DTSTART;TZID=Europe/Lisbon:20261005T180000
DTEND;TZID=Europe/Lisbon:20261005T200000
RRULE:FREQ=WEEKLY;COUNT=3
SUMMARY:Macroeconomia
LOCATION:A.S2.5
END:VEVENT
END:VCALENDAR
ICS;

        $events = (new ICalendarParser())->parse(
            $ics,
            new DateTimeImmutable('2026-10-01T00:00:00+01:00'),
            new DateTimeImmutable('2026-10-31T23:59:59+00:00'),
        );

        self::assertCount(3, $events);
        self::assertCount(3, array_unique(array_column($events, 'external_uid')));
    }
}
