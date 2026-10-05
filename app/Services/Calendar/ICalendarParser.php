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
                $items[] = $this->mapEvent($event, $uid, $start, $end);
            }

            $iterator->next();
        }

        return $items;
    }

    private function parseRawEvents(VCalendar $calendar, string $uid, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $items = [];

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

            $items[] = $this->mapEvent($event, $uid, $start, $end);
        }

        return $items;
    }

    private function mapEvent(object $event, string $uid, DateTimeImmutable $start, ?DateTimeImmutable $end): array
    {
        $utc = new DateTimeZone('UTC');
        $instanceKey = $uid.'|'.$start->setTimezone($utc)->format('Ymd\THis\Z');
        $status = strtoupper(trim((string) ($event->STATUS ?? 'CONFIRMED')));

        return [
            'external_uid' => hash('sha256', $instanceKey),
            'source_uid' => $uid,
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
}
