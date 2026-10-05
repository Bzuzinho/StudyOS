<?php

namespace App\Services\Calendar;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;
use Throwable;

class ICalendarParser
{
    public function parse(string $content, DateTimeInterface $from, DateTimeInterface $to): array
    {
        /** @var VCalendar $calendar */
        $calendar = Reader::read($content);
        $uids = [];

        foreach ($calendar->VEVENT as $event) {
            $uid = trim((string) ($event->UID ?? ''));

            if ($uid !== '') {
                $uids[$uid] = true;
            }
        }

        $items = [];

        foreach (array_keys($uids) as $uid) {
            try {
                $items = [...$items, ...$this->expandUid($calendar, $uid, $from, $to)];
            } catch (Throwable) {
                $items = [...$items, ...$this->parseRawEvents($calendar, $uid, $from, $to)];
            }
        }

        usort($items, fn (array $a, array $b) => $a['starts_at'] <=> $b['starts_at']);

        return array_values($items);
    }

    private function expandUid(VCalendar $calendar, string $uid, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $iterator = new EventIterator($calendar, $uid);
        $iterator->fastForward($from);

        $items = [];
        $guard = 0;
        $isRecurring = $this->isRecurringUid($calendar, $uid);

        while ($iterator->valid() && $guard++ < 5000) {
            $start = DateTimeImmutable::createFromInterface($iterator->getDTStart());
            $end = $iterator->getDTEnd()
                ? DateTimeImmutable::createFromInterface($iterator->getDTEnd())
                : null;

            if ($start > $to) {
                break;
            }

            if ($start >= $from) {
                $event = $iterator->getEventObject();
                $items[] = $this->mapEvent($event, $uid, $start, $end, $isRecurring);
            }

            $iterator->next();
        }

        return $items;
    }

    private function parseRawEvents(VCalendar $calendar, string $uid, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $items = [];
        $isRecurring = $this->isRecurringUid($calendar, $uid);

        foreach ($calendar->VEVENT as $event) {
            if (trim((string) ($event->UID ?? '')) !== $uid || ! isset($event->DTSTART)) {
                continue;
            }

            $start = DateTimeImmutable::createFromInterface($event->DTSTART->getDateTime());

            if ($start < $from || $start > $to) {
                continue;
            }

            $end = isset($event->DTEND)
                ? DateTimeImmutable::createFromInterface($event->DTEND->getDateTime())
                : null;

            $items[] = $this->mapEvent($event, $uid, $start, $end, $isRecurring);
        }

        return $items;
    }

    private function mapEvent(
        object $event,
        string $uid,
        DateTimeImmutable $start,
        ?DateTimeImmutable $end,
        bool $isRecurring,
    ): array {
        $utc = new DateTimeZone('UTC');
        $recurrenceId = null;

        if (isset($event->{'RECURRENCE-ID'})) {
            try {
                $recurrenceId = DateTimeImmutable::createFromInterface($event->{'RECURRENCE-ID'}->getDateTime());
            } catch (Throwable) {
                $recurrenceId = null;
            }
        }

        if ($isRecurring) {
            $identityDate = $recurrenceId ?? $start;
            $instanceKey = $uid.'|'.$identityDate->setTimezone($utc)->format('Ymd\THis\Z');
            $identityKind = 'recurrence';
        } else {
            // A standalone VEVENT keeps the same identity if the university
            // changes its DTSTART/DTEND later.
            $instanceKey = $uid.'|single';
            $identityKind = 'single';
        }

        $status = strtoupper(trim((string) ($event->STATUS ?? 'CONFIRMED')));

        return [
            'external_uid' => hash('sha256', $instanceKey),
            'source_uid' => $uid,
            'source_recurrence_id' => $recurrenceId?->setTimezone($utc)->format(DATE_ATOM),
            'identity_kind' => $identityKind,
            'title' => trim((string) ($event->SUMMARY ?? 'Evento académico')),
            'description' => trim((string) ($event->DESCRIPTION ?? '')),
            'location' => trim((string) ($event->LOCATION ?? '')),
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => $status === 'CANCELLED' ? 'cancelled' : 'scheduled',
            'sequence' => isset($event->SEQUENCE) ? (int) $event->SEQUENCE->getValue() : null,
            'last_modified' => isset($event->{'LAST-MODIFIED'})
                ? $event->{'LAST-MODIFIED'}->getDateTime()->format(DATE_ATOM)
                : null,
        ];
    }

    private function isRecurringUid(VCalendar $calendar, string $uid): bool
    {
        foreach ($calendar->VEVENT as $event) {
            if (trim((string) ($event->UID ?? '')) !== $uid) {
                continue;
            }

            if (isset($event->RRULE) || isset($event->RDATE) || isset($event->{'RECURRENCE-ID'})) {
                return true;
            }
        }

        return false;
    }
}
