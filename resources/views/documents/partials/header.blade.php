<div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px;">
    @if($school['logo'])
        <img src="{{ $school['logo'] }}" alt="Logo" style="max-height: 80px; margin-bottom: 10px;">
    @endif
    <h1 style="margin: 5px 0; font-size: 20px;">{{ $school['name'] }}</h1>
    @if($school['address'])
        <p style="margin: 2px 0; font-size: 11px; color: #666;">{{ $school['address'] }}</p>
    @endif
    @if($school['phone'])
        <p style="margin: 2px 0; font-size: 11px; color: #666;">Telp: {{ $school['phone'] }}</p>
    @endif
</div>
