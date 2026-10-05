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
        self::assertSame('single', $events[0]['identity_kind']);
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
        self::assertSame(['recurrence'], array_values(array_unique(array_column($events, 'identity_kind'))));
    }

    public function test_a_standalone_event_keeps_its_identity_when_rescheduled(): void
    {
        $before = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:reschedule@example.test
DTSTART;TZID=Europe/Lisbon:20261024T093000
DTEND;TZID=Europe/Lisbon:20261024T110000
SUMMARY:Introdução à Gestão
END:VEVENT
END:VCALENDAR
ICS;

        $after = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:reschedule@example.test
DTSTART;TZID=Europe/Lisbon:20261024T103000
DTEND;TZID=Europe/Lisbon:20261024T120000
SUMMARY:Introdução à Gestão
END:VEVENT
END:VCALENDAR
ICS;

        $parser = new ICalendarParser();
        $from = new DateTimeImmutable('2026-10-20T00:00:00+01:00');
        $to = new DateTimeImmutable('2026-10-30T23:59:59+00:00');

        $beforeEvent = $parser->parse($before, $from, $to)[0];
        $afterEvent = $parser->parse($after, $from, $to)[0];

        self::assertSame($beforeEvent['external_uid'], $afterEvent['external_uid']);
        self::assertNotSame(
            $beforeEvent['starts_at']->format(DATE_ATOM),
            $afterEvent['starts_at']->format(DATE_ATOM),
        );
    }

    public function test_a_recurring_override_uses_recurrence_id_as_stable_identity(): void
    {
        $before = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:series@example.test
DTSTART;TZID=Europe/Lisbon:20261005T180000
DTEND;TZID=Europe/Lisbon:20261005T200000
RRULE:FREQ=WEEKLY;COUNT=3
SUMMARY:Macroeconomia
END:VEVENT
BEGIN:VEVENT
UID:series@example.test
RECURRENCE-ID;TZID=Europe/Lisbon:20261012T180000
DTSTART;TZID=Europe/Lisbon:20261012T190000
DTEND;TZID=Europe/Lisbon:20261012T210000
SUMMARY:Macroeconomia
END:VEVENT
END:VCALENDAR
ICS;

        $after = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:series@example.test
DTSTART;TZID=Europe/Lisbon:20261005T180000
DTEND;TZID=Europe/Lisbon:20261005T200000
RRULE:FREQ=WEEKLY;COUNT=3
SUMMARY:Macroeconomia
END:VEVENT
BEGIN:VEVENT
UID:series@example.test
RECURRENCE-ID;TZID=Europe/Lisbon:20261012T180000
DTSTART;TZID=Europe/Lisbon:20261013T190000
DTEND;TZID=Europe/Lisbon:20261013T210000
SUMMARY:Macroeconomia
END:VEVENT
END:VCALENDAR
ICS;

        $parser = new ICalendarParser();
        $from = new DateTimeImmutable('2026-10-01T00:00:00+01:00');
        $to = new DateTimeImmutable('2026-10-31T23:59:59+00:00');

        $beforeEvents = $parser->parse($before, $from, $to);
        $afterEvents = $parser->parse($after, $from, $to);

        $beforeOverride = collect($beforeEvents)->first(
            fn (array $event) => $event['source_recurrence_id'] !== null,
        );
        $afterOverride = collect($afterEvents)->first(
            fn (array $event) => $event['source_recurrence_id'] !== null,
        );

        self::assertNotNull($beforeOverride);
        self::assertNotNull($afterOverride);
        self::assertSame($beforeOverride['external_uid'], $afterOverride['external_uid']);
        self::assertSame($beforeOverride['source_recurrence_id'], $afterOverride['source_recurrence_id']);
    }
}
