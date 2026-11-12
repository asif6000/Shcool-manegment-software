{{--
    One screen of the attendance register, without the workspace layout, so the
    teacher dashboard can show it in place. The data and the permission check are
    the ones StudentAttendanceController::index already runs.
--}}
<div class="tp-panel-inner">
    @include('attendance.partials.register-css')
    @include('attendance.partials.register-body')
    @include('attendance.partials.register-js')
</div>
