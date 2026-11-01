@foreach($units as $unit)
    @include('admin-views.unit.partials._row', ['unit' => $unit])
@endforeach
