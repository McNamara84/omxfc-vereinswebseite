<?php

namespace App\Enums;

enum MeetingRhythmType: string
{
    case MonthlyNthWeekday = 'monthly_nth_weekday';
    case MonthlyDayOfMonth = 'monthly_day_of_month';
    case EveryNWeeks = 'every_n_weeks';
    case NoteOnly = 'note_only';
}
