@component('mail::message')
# API Error: {{ $source }}

**Error Message:** {{ $errorMessage }}

**Timestamp:** {{ now() }}

@if(count($context) > 0)
## Additional Context
@component('mail::table')
| Key | Value |
|-----|-------|
@foreach($context as $key => $value)
| {{ $key }} | {{ is_array($value) ? json_encode($value) : $value }} |
@endforeach
@endcomponent
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
