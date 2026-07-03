<x-mail::message>
# Nova customização solicitada

**Cliente:** {{ $lead->name }}
**E-mail:** {{ $lead->email }}
@if ($lead->phone)
**Telefone:** {{ $lead->phone }}
@endif

{{ $lead->message }}

<x-mail::button :url="route('admin.leads.index')">
Ver no painel
</x-mail::button>

APTK Spirits
</x-mail::message>
