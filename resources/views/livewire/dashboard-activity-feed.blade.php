<div id="dashboard-activities" data-dashboard-activity-feed>
    @include('dashboard.partials.activity-feed', [
        'activities' => $this->activities,
        'filters' => $filters,
        'activeFilter' => $filter,
        'hasMore' => $hasMore,
    ])
</div>
