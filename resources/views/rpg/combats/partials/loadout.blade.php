<fieldset class="space-y-2"><legend>Waffen in den Händen (ohne Auswahl: unbewaffnet)</legend>
    @foreach($acting['weapons'] as $weapon)@if(!$weapon['natural'] && !$weapon['broken'] && $weapon['position'] === null)
        <label class="flex items-center gap-2"><input class="checkbox" type="checkbox" name="weapons[]" value="{{ $weapon['instance'] }}">{{ $weapon['name'] }} ({{ $weapon['instance'] }}, {{ $weapon['hands'] }} Hand/Hände)</label>
    @endif @endforeach
    <label class="block">Schild<select name="shield" class="select"><option value="0">Kein Schild</option>@if($acting['profile']['shield'] && !$acting['shield_broken'])<option value="1">{{ $acting['profile']['shield']['name'] }} verwenden</option>@endif</select></label>
    <p class="text-sm">Zwei Waffen benötigen jeweils höchstens eine Hand und passende Fertigkeiten ab 3. Schilde belegen eine Hand.</p>
</fieldset>
