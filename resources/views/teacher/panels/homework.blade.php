{{--
    The homework list without the workspace layout, so the teacher dashboard can
    open it in place. Data, permission and timetable scope come from
    HomeworkController::index unchanged.
--}}
<div class="tp-panel-inner">
    @include('homework.partials.list-body')
</div>
