<?php

namespace App\Services;

use App\Enums\MeetingRhythmType;
use App\Models\Meeting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class MeetingScheduleService
{
    public function describe(Meeting $meeting): string
    {
        return match ($meeting->rhythm_type) {
            MeetingRhythmType::MonthlyNthWeekday => sprintf(
                'Monatlich am %s %s',
                $this->ordinalLabel($meeting->week_of_month),
                $this->weekdayLabel($meeting->weekday),
            ),
            MeetingRhythmType::MonthlyDayOfMonth => sprintf('Monatlich jeden %d.', $meeting->day_of_month),
            MeetingRhythmType::EveryNWeeks => $this->everyNWeeksLabel($meeting),
            MeetingRhythmType::NoteOnly => $meeting->rhythm_note ?: 'Termin nach Hinweis',
        };
    }

    public function nextOccurrence(Meeting $meeting, CarbonInterface|string|null $reference = null): ?CarbonImmutable
    {
        $referenceDate = $reference instanceof CarbonInterface
            ? CarbonImmutable::instance($reference)
            : ($reference ? CarbonImmutable::parse($reference) : CarbonImmutable::now());

        return match ($meeting->rhythm_type) {
            MeetingRhythmType::MonthlyNthWeekday => $this->nextMonthlyNthWeekday($meeting, $referenceDate),
            MeetingRhythmType::MonthlyDayOfMonth => $this->nextMonthlyDayOfMonth($meeting, $referenceDate),
            MeetingRhythmType::EveryNWeeks => $this->nextEveryNWeeks($meeting, $referenceDate),
            MeetingRhythmType::NoteOnly => null,
        };
    }

    private function nextMonthlyNthWeekday(Meeting $meeting, CarbonImmutable $reference): ?CarbonImmutable
    {
        if (! $meeting->weekday || ! $meeting->week_of_month) {
            return null;
        }

        $monthCursor = $reference->startOfMonth();

        for ($iteration = 0; $iteration < 24; $iteration++) {
            $candidate = $this->nthWeekdayOfMonth($monthCursor, $meeting->weekday, $meeting->week_of_month);

            if ($candidate) {
                $candidate = $this->applyMeetingTime($meeting, $candidate);

                if ($candidate >= $reference) {
                    return $candidate;
                }
            }

            $monthCursor = $monthCursor->addMonth()->startOfMonth();
        }

        return null;
    }

    private function nextMonthlyDayOfMonth(Meeting $meeting, CarbonImmutable $reference): ?CarbonImmutable
    {
        if (! $meeting->day_of_month) {
            return null;
        }

        $monthCursor = $reference->startOfMonth();

        for ($iteration = 0; $iteration < 24; $iteration++) {
            if ($meeting->day_of_month <= $monthCursor->daysInMonth) {
                $candidate = $this->applyMeetingTime(
                    $meeting,
                    $monthCursor->setDay($meeting->day_of_month)
                );

                if ($candidate >= $reference) {
                    return $candidate;
                }
            }

            $monthCursor = $monthCursor->addMonth()->startOfMonth();
        }

        return null;
    }

    private function nextEveryNWeeks(Meeting $meeting, CarbonImmutable $reference): ?CarbonImmutable
    {
        if (! $meeting->starts_on || ! $meeting->interval_weeks) {
            return null;
        }

        $candidate = $this->applyMeetingTime(
            $meeting,
            CarbonImmutable::parse($meeting->starts_on->format('Y-m-d'))
        );

        if ($candidate >= $reference) {
            return $candidate;
        }

        $intervalSeconds = max(1, $meeting->interval_weeks) * 7 * 24 * 60 * 60;
        $elapsedSeconds = max(0, $reference->getTimestamp() - $candidate->getTimestamp());
        $elapsedIntervals = intdiv($elapsedSeconds, $intervalSeconds);
        $candidate = $candidate->addSeconds($elapsedIntervals * $intervalSeconds);

        while ($candidate < $reference) {
            $candidate = $candidate->addSeconds($intervalSeconds);
        }

        return $candidate;
    }

    private function nthWeekdayOfMonth(CarbonImmutable $monthStart, int $weekday, int $weekOfMonth): ?CarbonImmutable
    {
        $offset = ($weekday - $monthStart->dayOfWeekIso + 7) % 7;
        $candidate = $monthStart->addDays($offset + (($weekOfMonth - 1) * 7));

        if ($candidate->month !== $monthStart->month) {
            return null;
        }

        return $candidate;
    }

    private function applyMeetingTime(Meeting $meeting, CarbonImmutable $date): CarbonImmutable
    {
        if (! filled($meeting->time_from)) {
            return $date->startOfDay();
        }

        [$hour, $minute] = array_map('intval', explode(':', $meeting->time_from));

        return $date->setTime($hour, $minute);
    }

    private function everyNWeeksLabel(Meeting $meeting): string
    {
        if (! $meeting->starts_on || ! $meeting->interval_weeks) {
            return 'Rhythmus unvollständig';
        }

        $startDate = $meeting->starts_on->translatedFormat('d.m.Y');

        return match ($meeting->interval_weeks) {
            1 => "Wöchentlich beginnend am {$startDate}",
            2 => "Zweiwöchentlich beginnend am {$startDate}",
            default => "Alle {$meeting->interval_weeks} Wochen beginnend am {$startDate}",
        };
    }

    private function ordinalLabel(?int $weekOfMonth): string
    {
        return match ($weekOfMonth) {
            1 => '1.',
            2 => '2.',
            3 => '3.',
            4 => '4.',
            5 => '5.',
            default => '?',
        };
    }

    private function weekdayLabel(?int $weekday): string
    {
        return match ($weekday) {
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            7 => 'Sonntag',
            default => 'Tag',
        };
    }
}
