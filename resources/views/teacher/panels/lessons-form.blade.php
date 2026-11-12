{{--
    The lesson editor without the workspace layout, so the teacher dashboard can
    open it in place of a page reload. The class and subject lists the panel
    offers are already narrowed to the periods on this teacher's timetable.
--}}
<div class="tp-panel-inner">
    @include('lessons.partials.style')
    @include('lessons.partials.script')
    @include('lessons.partials.form-body')
</div>
