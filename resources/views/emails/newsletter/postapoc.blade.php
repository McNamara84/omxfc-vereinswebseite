<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>
        body { background-color: #1a1a1a; color: #e0e0e0; font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #2d2d2d; }
        h1 { color: #f97316; text-align: center; }
        h2 { color: #f97316; border-bottom: 1px solid #f97316; padding-bottom: 4px; }
        p { line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>MADDRAX Newsletter</h1>
        @foreach($topics as $topic)
            <h2>{{ $topic['title'] }}</h2>
            <p>{!! nl2br(e($topic['content'])) !!}</p>
        @endforeach
        <p style="margin-top: 20px;">Bleib wachsam in der post-apokalyptischen Welt!<br>Der Vorstand des OMXFC</p>
    </div>
</body>
</html>
