<?php

namespace Webkul\Support\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Webkul\Field\Traits\HasCustomFields;
use Webkul\Security\Models\User;
use Webkul\Support\Database\Factories\CalendarFactory;

class Calendar extends Model
{
    use HasCustomFields, HasFactory, SoftDeletes;

    protected $table = 'calendars';

    protected $fillable = [
        'name',
        'timezone',
        'hours_per_day',
        'is_active',
        'two_weeks_calendar',
        'flexible_hours',
        'full_time_required_hours',
        'resource_type',
        'resource_id',
        'creator_id',
        'company_id',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function attendance()
    {
        return $this->hasMany(CalendarAttendance::class);
    }

    public function resource()
    {
        return $this->morphTo();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($calendar) {
            $calendar->creator_id ??= Auth::id();
        });
    }

    protected static function newFactory(): CalendarFactory
    {
        return CalendarFactory::new();
    }

    public function getWorkDurationData(
        Carbon $fromDatetime,
        Carbon $toDatetime,
        bool $computeLeaves = true,
        ?array $domain = null
    ): array
    {
        if ($computeLeaves) {
            $intervals = $this->getWorkIntervalsBatch($fromDatetime, $toDatetime, domain: $domain)[null];
        } else {
            $intervals = $this->getAttendanceIntervalsBatch($fromDatetime, $toDatetime, domain: $domain)[null];
        }

        return $this->getAttendanceIntervalsDaysData($intervals);
    }

    public function getWorkIntervalsBatch(
        Carbon $startDt,
        Carbon $endDt,
        mixed $resources = null,
        ?array $domain = null,
        ?string $tz = null,
        bool $computeLeaves = true
    ): array
    {
        if (! $resources) {
            $resourcesList = [null];
        } else {
            $resourcesList = is_array($resources) ? array_merge($resources, [null]) : [$resources, null];
        }

        $attendanceIntervals = $this->getAttendanceIntervalsBatch($startDt, $endDt, $resources, tz: $tz);

        if ($computeLeaves) {
            $leaveIntervals = $this->getLeaveIntervalsBatch($startDt, $endDt, $resources, $domain, tz: $tz);

            $result = [];
            
            foreach ($resourcesList as $resource) {
                $resourceId = $resource?->id;

                $result[$resourceId] = $this->subtractIntervals($attendanceIntervals[$resourceId], $leaveIntervals[$resourceId]);
            }

            return $result;
        }

        $result = [];

        foreach ($resourcesList as $resource) {
            $resourceId = $resource?->id;

            $result[$resourceId] = $attendanceIntervals[$resourceId];
        }

        return $result;
    }

    public function getAttendanceIntervalsBatch(
        Carbon $startDt,
        Carbon $endDt,
        mixed $resources = null,
        ?array $domain = null,
        ?string $tz = null,
        bool $lunch = false
    ): array {
        if (! $resources) {
            $resourcesList = [null];
        } else {
            $resourcesList = is_array($resources) ? array_merge($resources, [null]) : [$resources, null];
        }

        $resourceIds = collect($resourcesList)->filter()->pluck('id')->all();

        $attendanceDomain = array_merge($domain ?? [], [
            ['calendar_id', '=', $this->id],
            ['resource_id', 'in', array_merge([null], $resourceIds)],
            ['display_type', '=', null],
            ['day_period', $lunch ? '=' : '!=', 'lunch'],
        ]);

        $attendances = CalendarAttendance::where($attendanceDomain)->get();

        $resourcesPerTz = [];

        foreach ($resourcesList as $resource) {
            $resourceTz = $tz ?? ($resource ? $resource->tz : $this->tz);

            $resourcesPerTz[$resourceTz][] = $resource;
        }

        $attendancePerResource = [];

        $attendancesPerDay = array_fill(0, 14, collect());

        $weekdays = [];

        foreach ($attendances as $attendance) {
            if ($attendance->resource_id) {
                $attendancePerResource[$attendance->resource_id][] = $attendance;
            }

            $weekday = (int) $attendance->dayofweek;

            $weekdays[] = $weekday;

            if ($this->two_weeks_calendar) {
                $weekType = (int) $attendance->week_type;

                $attendancesPerDay[$weekday + 7 * $weekType][] = $attendance;
            } else {
                $attendancesPerDay[$weekday][]     = $attendance;
                $attendancesPerDay[$weekday + 7][] = $attendance;
            }
        }

        $weekdays = array_unique($weekdays);

        $start = $startDt->clone()->utc();
        
        $end = $endDt->clone()->utc();

        $boundsPerTz = [];
        foreach ($resourcesPerTz as $resourceTz => $tzResources) {
            $boundsPerTz[$resourceTz] = [
                $startDt->clone()->setTimezone($resourceTz),
                $endDt->clone()->setTimezone($resourceTz),
            ];

            $start = min($start, $boundsPerTz[$resourceTz][0]->clone()->utc());

            $end = max($end, $boundsPerTz[$resourceTz][1]->clone()->utc());
        }

        $baseResult = [];

        $perResourceResult = [];
        
        $current = $start->clone()->startOfDay();

        while ($current->lte($end)) {
            if (in_array($current->dayOfWeek, $weekdays)) {
                $weekType = CalendarAttendance::getWeekType($current);

                $dayAttends = $attendancesPerDay[$current->dayOfWeek + 7 * $weekType];

                foreach ($dayAttends as $attendance) {
                    if (
                        ($attendance->date_from && $current->lt(Carbon::parse($attendance->date_from))) ||
                        ($attendance->date_to && Carbon::parse($attendance->date_to)->lt($current))
                    ) {
                        continue;
                    }

                    $dayFrom = $current->clone()->setTime(0, 0)->addMinutes($attendance->hour_from * 60);

                    $dayTo = $current->clone()->setTime(0, 0)->addMinutes($attendance->hour_to * 60);

                    if ($attendance->resource_id) {
                        $perResourceResult[$attendance->resource_id][] = [$dayFrom, $dayTo, $attendance];
                    } else {
                        $baseResult[] = [$dayFrom, $dayTo, $attendance];
                    }
                }
            }

            $current->addDay();
        }

        $resultPerTz = [];

        foreach ($resourcesPerTz as $resourceTz => $tzResources) {
            $bounds = $boundsPerTz[$resourceTz];

            $resultPerTz[$resourceTz] = collect($baseResult)->map(fn($val) => [
                Carbon::parse(max($bounds[0], Carbon::parse($val[0])->setTimezone($resourceTz))),
                Carbon::parse(min($bounds[1], Carbon::parse($val[1])->setTimezone($resourceTz))),
                $val[2],
            ])->all();
        }

        $resultPerResourceId = [];

        foreach ($resourcesPerTz as $resourceTz => $tzResources) {
            $res = $resultPerTz[$resourceTz];

            foreach ($tzResources as $resource) {
                $resourceId = $resource?->id;

                if ($resource && isset($perResourceResult[$resourceId])) {
                    $bounds = $boundsPerTz[$resourceTz];

                    $resourceSpecificResult = collect($perResourceResult[$resourceId])->map(fn($val) => [
                        Carbon::parse(max($bounds[0], Carbon::parse($val[0])->setTimezone($resourceTz))),
                        Carbon::parse(min($bounds[1], Carbon::parse($val[1])->setTimezone($resourceTz))),
                        $val[2],
                    ])->all();

                    $resultPerResourceId[$resourceId] = collect(array_merge($res, $resourceSpecificResult));
                } else {
                    $resultPerResourceId[$resourceId] = collect($res);
                }
            }
        }

        return $resultPerResourceId;
    }

    public function getLeaveIntervalsBatch(
        Carbon $startDt,
        Carbon $endDt,
        mixed $resources = null,
        ?array $domain = null,
        ?string $tz = null,
        bool $anyCalendar = false
    ): array {
        if (! $resources) {
            $resourcesList = [null];
        } else {
            $resourcesList = is_array($resources) ? array_merge($resources, [null]) : [$resources, null];
        }

        if ($domain === null) {
            $domain = [['time_type', '=', 'leave']];
        }

        if (! $anyCalendar) {
            $domain[] = function ($query) {
                $query->whereNull('calendar_id')->orWhere('calendar_id', $this->id);
            };
        }

        $domain = array_merge($domain, [
            function ($query) use ($resourcesList) {
                $query->whereNull('resource_id')
                    ->orWhereIn('resource_id', collect($resourcesList)->filter()->pluck('id')->all());
            },
            ['date_from', '<=', $endDt->toDateTimeString()],
            ['date_to', '>=', $startDt->toDateTimeString()],
        ]);

        $result = [];

        $tzDates = [];
        
        $allLeaves = CalendarLeave::where($domain)->get();

        foreach ($allLeaves as $leave) {
            $leaveResource = $leave->resource;

            $leaveCompany = $leave->company;

            $leaveDateFrom = $leave->date_from;
            
            $leaveDateTo = $leave->date_to;

            foreach ($resourcesList as $resource) {
                if (
                    $leaveResource
                    && $leaveResource->id !== ($resource?->id)
                    || (
                        ! $leaveResource
                        && $resource
                        && $resource->company_id !== $leaveCompany?->id
                    )
                ) {
                    continue;
                }

                $resourceTz = $tz ?? ($resource ? $resource->tz : $this->tz);
                $resourceId = $resource?->id;

                $tzKey = $resourceTz . '_start';

                if (! isset($tzDates[$tzKey])) {
                    $tzDates[$tzKey] = $startDt->clone()->setTimezone($resourceTz);
                }

                $start = $tzDates[$tzKey];

                $tzKey = $resourceTz . '_end';

                if (! isset($tzDates[$tzKey])) {
                    $tzDates[$tzKey] = $endDt->clone()->setTimezone($resourceTz);
                }

                $end = $tzDates[$tzKey];

                $dt0 = Carbon::parse($leaveDateFrom)->setTimezone($resourceTz);

                $dt1 = Carbon::parse($leaveDateTo)->setTimezone($resourceTz);

                $result[$resourceId][] = [
                    Carbon::parse(max($start, $dt0)),
                    Carbon::parse(min($end, $dt1)),
                    $leave,
                ];
            }
        }

        $finalResult = [];

        foreach ($resourcesList as $resource) {
            $resourceId = $resource?->id;
            
            $finalResult[$resourceId] = collect($result[$resourceId] ?? []);
        }

        return $finalResult;
    }
}
