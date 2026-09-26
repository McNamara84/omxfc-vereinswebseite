@if(session('success'))
    <div class="alert alert-success my-4" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-error my-4" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
