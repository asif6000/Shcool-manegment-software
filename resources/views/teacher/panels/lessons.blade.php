{{--
    The lesson list without the workspace layout. LessonController already keeps
    the rows to the periods on the teacher's own timetable; the office sees all.
--}}
<div class="tp-panel-inner">
    @include('lessons.partials.style')
    @include('lessons.partials.script')
    @include('lessons.partials.list-body')
</div>
