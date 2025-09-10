<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Charakterbogen</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .section { margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: left; }
        .portrait { width: 120px; height: 120px; object-fit: cover; }
    </style>
</head>
<body>
    <h1>Charakterbogen</h1>
    @if($portrait)
        <img class="portrait" src="{{ $portrait }}" alt="Portrait">
    @endif

    <div class="section">
        <strong>Spieler:</strong> {{ $character['player_name'] ?? '' }}<br>
        <strong>Charakter:</strong> {{ $character['character_name'] ?? '' }}<br>
        <strong>Rasse:</strong> {{ $character['race'] ?? '' }}<br>
        <strong>Kultur:</strong> {{ $character['culture'] ?? '' }}
    </div>

    <div class="section">
        <strong>Beschreibung</strong>
        <p>{{ $character['description'] ?? '' }}</p>
    </div>

    @if($attributes)
    <div class="section">
        <strong>Attribute</strong>
        <table>
            <tr>
                @foreach($attributes as $key => $val)
                    <th>{{ strtoupper($key) }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($attributes as $val)
                    <td>{{ $val }}</td>
                @endforeach
            </tr>
        </table>
    </div>
    @endif

    @if($skills)
    <div class="section">
        <strong>Fertigkeiten</strong>
        <table>
            <tr><th>Name</th><th>FW</th></tr>
            @foreach($skills as $skill)
                <tr>
                    <td>{{ $skill['name'] ?? '' }}</td>
                    <td>{{ $skill['value'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    </div>
    @endif

    <div class="section">
        <strong>Vorteile</strong>
        <ul>
            @foreach($advantages ?? [] as $adv)
                <li>{{ $adv }}</li>
            @endforeach
        </ul>
        <strong>Nachteile</strong>
        <ul>
            @foreach($disadvantages ?? [] as $dis)
                <li>{{ $dis }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <strong>Ausrüstung</strong>
        <p>{{ $character['equipment'] ?? '' }}</p>
    </div>
</body>
</html>
