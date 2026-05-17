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
        .topic { margin-bottom: 28px; }
        .topic-content { line-height: 1.6; }
        .topic-content a { color: #fdba74; }
        .topic-content h1, .topic-content h2, .topic-content h3, .topic-content h4, .topic-content h5, .topic-content h6 { color: #fdba74; }
        .topic-content ul, .topic-content ol { padding-left: 20px; }
        .topic-image { display: block; width: 100%; max-width: 100%; height: auto; margin: 0 0 16px; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>MADDRAX Newsletter</h1>
        @foreach(\App\Support\NewsletterTopics::normalize($topics ?? []) as $topic)
            <div class="topic">
                <h2>{{ filled($topic['title'] ?? null) ? $topic['title'] : 'Ohne Titel' }}</h2>

                @foreach(($topic['images'] ?? []) as $image)
                    <img src="{{ url(Storage::disk('public')->url($image)) }}" alt="Bild zum Thema {{ filled($topic['title'] ?? null) ? $topic['title'] : 'Ohne Titel' }}" class="topic-image">
                @endforeach

                <div class="topic-content">{!! \App\Support\NewsletterTopics::renderHtml($topic['content'] ?? '') !!}</div>
            </div>
        @endforeach
        <p style="margin-top: 20px;">Tuma sa feesa,<br>Tanja 1.Vorsitzende</p>
    </div>
</body>
</html>
