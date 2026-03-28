@extends ('crm.layouts.app')
@section('title', "Edit Survey - " . $survey->title)
@section('content-class', 'flex flex-col')
@section ('content')
<breadcrumbs :items="{{$breadcrumbs}}"></breadcrumbs>
<div class="container mx-auto px-4 py-6">

    <survey-creator
        :users='@json($TeamUsers->values())'
        :team='@json($team)'
        :subscription='@json($team->activeSubscription())'
        :initial-data='@json($survey)'
        :is-editing="true"
        survey-id="{{ $survey->uuid }}"
    ></survey-creator>
</div>
@endsection
@push('footer.scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Any additional initialization can go here
});
</script>
@endpush
