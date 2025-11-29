<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Maddrax-Fantreffen 2026 – Offizieller MADDRAX Fanclub e. V.','description' => 'Melde dich jetzt an zum Maddrax-Fantreffen am 9. Mai 2026 in Köln mit Signierstunde und Verleihung der Goldenen Taratze.'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['socialImage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(asset('build/assets/omxfc-logo-Df-1StAj.png'))]); ?>


 <?php $__env->slot('head', null, []); ?> 
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Event",
    "name": "Maddrax-Fantreffen 2026",
    "description": "Das jährliche Fantreffen des Offiziellen MADDRAX Fanclub e. V. mit Signierstunde und Verleihung der Goldenen Taratze.",
    "startDate": "2026-05-09T19:00:00+02:00",
    "endDate": "2026-05-09T23:00:00+02:00",
    "eventStatus": "https://schema.org/EventScheduled",
    "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
    "location": {
        "@type": "Place",
        "name": "L'Osteria Köln Mülheim",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Düsseldorfer Str. 1-3",
            "addressLocality": "Köln",
            "postalCode": "51063",
            "addressCountry": "DE"
        }
    },
    "organizer": {
        "@type": "Organization",
        "name": "Offizieller MADDRAX Fanclub e. V.",
        "url": "<?php echo e(config('app.url')); ?>"
    },
    "offers": [
        {
            "@type": "Offer",
            "name": "Vereinsmitglieder",
            "price": "0",
            "priceCurrency": "EUR",
            "availability": "https://schema.org/InStock",
            "validFrom": "2025-01-01",
            "url": "<?php echo e(route('fantreffen.2026')); ?>"
        },
        {
            "@type": "Offer",
            "name": "Gäste",
            "price": "5.00",
            "priceCurrency": "EUR",
            "availability": "https://schema.org/InStock",
            "validFrom": "2025-01-01",
            "url": "<?php echo e(route('fantreffen.2026')); ?>"
        }
    ],
    "image": "<?php echo e(asset('build/assets/omxfc-logo-Df-1StAj.png')); ?>"
}
</script>


<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "Startseite",
            "item": "<?php echo e(config('app.url')); ?>"
        },
        {
            "@type": "ListItem",
            "position": 2,
            "name": "Veranstaltungen",
            "item": "<?php echo e(config('app.url')); ?>/termine"
        },
        {
            "@type": "ListItem",
            "position": 3,
            "name": "Maddrax-Fantreffen 2026",
            "item": "<?php echo e(route('fantreffen.2026')); ?>"
        }
    ]
}
</script>


<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
        {
            "@type": "Question",
            "name": "Wann findet das Maddrax-Fantreffen 2026 statt?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Das Maddrax-Fantreffen 2026 findet am Samstag, 9. Mai 2026 ab 19:00 Uhr statt."
            }
        },
        {
            "@type": "Question",
            "name": "Wo findet das Fantreffen statt?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Das Fantreffen findet in der L'Osteria Köln Mülheim statt (Düsseldorfer Str. 1-3, 51063 Köln)."
            }
        },
        {
            "@type": "Question",
            "name": "Was kostet die Teilnahme am Fantreffen?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Für Vereinsmitglieder ist die Teilnahme kostenlos. Gäste werden um eine Spende von 5 € gebeten. Optional kann ein Event-T-Shirt für 25 € bestellt werden."
            }
        },
        {
            "@type": "Question",
            "name": "Muss ich Vereinsmitglied sein um teilzunehmen?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Nein, auch Gäste sind herzlich willkommen! Du kannst dich als Gast anmelden oder vorher Mitglied werden, um kostenlos teilzunehmen."
            }
        },
        {
            "@type": "Question",
            "name": "Gibt es eine Signierstunde mit MADDRAX-Autoren?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Ja! Ab 19:00 Uhr gibt es eine Signierstunde, bei der du deine Lieblingsautoren treffen kannst."
            }
        },
        {
            "@type": "Question",
            "name": "Was ist die Goldene Taratze?",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "Die Goldene Taratze ist ein Fan-Preis, der jährlich beim Fantreffen an besondere Personen oder Projekte aus der MADDRAX-Community verliehen wird."
            }
        }
    ]
}
</script>
 <?php $__env->endSlot(); ?>

<div class="bg-gray-50 dark:bg-gray-900 -mt-8">
    <div class="relative bg-gradient-to-br from-[#8B0116] to-[#6b000e] text-white py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-5xl font-bold mb-6">Maddrax-Fantreffen 2026 in Köln</h1>
            <div class="flex flex-col sm:flex-row justify-center gap-6 text-lg mb-6">
                <span>📅 Samstag, 9. Mai 2026</span>
                <span>🕖 ab 19:00 Uhr</span>
                <span>📍 L´Osteria Köln Mülheim</span>
            </div>
            <a href="https://maps.app.goo.gl/dzLHUqVHqJrkWDkr5" target="_blank" class="inline-block px-6 py-3 bg-white text-[#8B0116] font-semibold rounded-lg hover:bg-gray-100">📍 Route in Google Maps</a>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border-l-4 border-green-500 rounded">
                <p class="text-green-800 dark:text-green-200"><?php echo e(session('success')); ?></p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="mb-4 p-4 bg-yellow-100 dark:bg-yellow-900 border-l-4 border-yellow-500 rounded">
            <h3 class="font-bold mb-2">ColoniaCon am selben Wochenende!</h3>
            <p>Am selben Wochenende findet auch die <a href="https://www.coloniacon-tng.de/2026" target="_blank" rel="noopener noreferrer" class="text-yellow-900 dark:text-yellow-100 underline font-semibold hover:text-yellow-700 dark:hover:text-yellow-200">ColoniaCon</a> statt. Der Offizielle MADDRAX Fanclub wird dort ebenfalls mit Programmpunkten vertreten sein.</p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-4 text-[#8B0116] dark:text-[#ff4b63]">Programm</h2>
                    <div class="space-y-4">
                        <div class="flex gap-4">
                            <span class="font-bold text-[#8B0116] dark:text-[#ff4b63]">19:00</span>
                            <div>
                                <h3 class="font-semibold">Signierstunde mit Autoren</h3>
                                <p class="text-gray-600 dark:text-gray-300">Triff deine Lieblingsautoren!</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <span class="font-bold text-[#8B0116] dark:text-[#ff4b63]">20:00</span>
                            <div>
                                <h3 class="font-semibold">Verleihung Goldene Taratze</h3>
                                <p class="text-gray-600 dark:text-gray-300">Die große Preisverleihung!</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-4 text-[#8B0116] dark:text-[#ff4b63]">Kosten</h2>
                    <div class="space-y-3">
                        <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded">
                            <div class="font-semibold text-gray-900 dark:text-white mb-1">Vereinsmitglieder</div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">Teilnahme am Event: <strong class="text-green-600 dark:text-green-400">kostenlos</strong></p>
                        </div>
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded">
                            <div class="font-semibold text-gray-900 dark:text-white mb-1">Gäste</div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">Teilnahme am Event: <strong class="text-blue-600 dark:text-blue-400">5,00 €</strong> Spende erbeten</p>
                        </div>
                        <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded">
                            <div class="font-semibold text-gray-900 dark:text-white mb-1">Event-T-Shirt (optional)</div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                <strong class="text-purple-600 dark:text-purple-400">25,00 €</strong> Spende
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">
                                Für Gäste zusammen mit Teilnahme: 30,00 €
                            </p>
                            <?php if (isset($component)) { $__componentOriginal69d2e70dc7c24f88dbff28cbd067ee07 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal69d2e70dc7c24f88dbff28cbd067ee07 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.fantreffen-tshirt-deadline-notice','data' => ['tshirtDeadlinePassed' => $tshirtDeadlinePassed,'tshirtDeadlineFormatted' => $tshirtDeadlineFormatted,'daysUntilDeadline' => $daysUntilDeadline,'variant' => 'compact']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('fantreffen-tshirt-deadline-notice'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['tshirtDeadlinePassed' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tshirtDeadlinePassed),'tshirtDeadlineFormatted' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tshirtDeadlineFormatted),'daysUntilDeadline' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($daysUntilDeadline),'variant' => 'compact']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal69d2e70dc7c24f88dbff28cbd067ee07)): ?>
<?php $attributes = $__attributesOriginal69d2e70dc7c24f88dbff28cbd067ee07; ?>
<?php unset($__attributesOriginal69d2e70dc7c24f88dbff28cbd067ee07); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal69d2e70dc7c24f88dbff28cbd067ee07)): ?>
<?php $component = $__componentOriginal69d2e70dc7c24f88dbff28cbd067ee07; ?>
<?php unset($__componentOriginal69d2e70dc7c24f88dbff28cbd067ee07); ?>
<?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-4 text-[#8B0116] dark:text-[#ff4b63]">Häufige Fragen</h2>
                    <div class="space-y-3" x-data="{ open: null }">
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
                            <button @click="open = open === 1 ? null : 1" class="flex justify-between w-full text-left font-semibold text-gray-900 dark:text-white">
                                <span>Muss ich Vereinsmitglied sein?</span>
                                <span x-text="open === 1 ? '−' : '+'"></span>
                            </button>
                            <p x-show="open === 1" x-collapse class="mt-2 text-gray-600 dark:text-gray-300 text-sm">
                                Nein! Auch Gäste sind herzlich willkommen. Als Mitglied ist die Teilnahme allerdings kostenlos.
                            </p>
                        </div>
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
                            <button @click="open = open === 2 ? null : 2" class="flex justify-between w-full text-left font-semibold text-gray-900 dark:text-white">
                                <span>Was ist die Goldene Taratze?</span>
                                <span x-text="open === 2 ? '−' : '+'"></span>
                            </button>
                            <p x-show="open === 2" x-collapse class="mt-2 text-gray-600 dark:text-gray-300 text-sm">
                                Ein Fan-Preis, der jährlich an besondere Personen oder Projekte aus der MADDRAX-Community verliehen wird.
                            </p>
                        </div>
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
                            <button @click="open = open === 3 ? null : 3" class="flex justify-between w-full text-left font-semibold text-gray-900 dark:text-white">
                                <span>Kann ich ein T-Shirt bestellen?</span>
                                <span x-text="open === 3 ? '−' : '+'"></span>
                            </button>
                            <p x-show="open === 3" x-collapse class="mt-2 text-gray-600 dark:text-gray-300 text-sm">
                                Ja! Für 25 € Spende (Gäste: 30 € inkl. Teilnahme) kannst du ein exklusives Event-T-Shirt bestellen.
                            </p>
                        </div>
                        <div class="pb-1">
                            <button @click="open = open === 4 ? null : 4" class="flex justify-between w-full text-left font-semibold text-gray-900 dark:text-white">
                                <span>Gibt es eine Signierstunde?</span>
                                <span x-text="open === 4 ? '−' : '+'"></span>
                            </button>
                            <p x-show="open === 4" x-collapse class="mt-2 text-gray-600 dark:text-gray-300 text-sm">
                                Ja! Ab 19:00 Uhr kannst du deine Lieblingsautoren treffen und dir Bücher signieren lassen.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden sticky top-4">
                    <div class="bg-gradient-to-r from-[#8B0116] to-[#a01526] px-6 py-4">
                        <h2 class="text-2xl font-bold text-white">Anmeldung</h2>
                    </div>
                    <div class="p-6">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($errors) && $errors->any()): ?>
                            <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border-l-4 border-red-500 rounded">
                                <ul class="text-sm text-red-800 dark:text-red-200 space-y-1">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <li><?php echo e($error); ?></li>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!Auth::check()): ?>
                            <div class="mb-4 p-3 bg-orange-100 dark:bg-orange-900 rounded">
                                <p class="text-sm">Bist du Vereinsmitglied? <a href="<?php echo e(route('login')); ?>" class="underline font-bold">Jetzt einloggen</a> um kostenlos teilzunehmen!</p>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <form method="POST" action="<?php echo e(route('fantreffen.2026.store')); ?>" id="fantreffen-form" class="space-y-4">
                            <?php echo csrf_field(); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Vorname *</label>
                                    <input type="text" name="vorname" value="<?php echo e(old('vorname')); ?>" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Nachname *</label>
                                    <input type="text" name="nachname" value="<?php echo e(old('nachname')); ?>" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">E-Mail *</label>
                                    <input type="email" name="email" value="<?php echo e(old('email')); ?>" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600" required>
                                </div>
                            <?php else: ?>
                                <div class="p-4 bg-green-100 dark:bg-green-900 rounded">
                                    <p class="text-sm">✅ Angemeldet als <strong><?php echo e($user->vorname); ?> <?php echo e($user->nachname); ?></strong></p>
                                    <p class="text-sm mt-1">Deine Teilnahme ist <strong>kostenlos</strong>!</p>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <div>
                                <label class="block text-sm font-medium mb-2">Mobile Rufnummer (optional)</label>
                                <input type="tel" name="mobile" value="<?php echo e(old('mobile', optional($user)->mobile ?? '')); ?>" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600" placeholder="+49 123 456789">
                                <p class="text-xs text-gray-500 mt-1">Für WhatsApp-Updates</p>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$tshirtDeadlinePassed): ?>
                                <div class="border-t pt-4">
                                    
                                    <?php if (isset($component)) { $__componentOriginal69d2e70dc7c24f88dbff28cbd067ee07 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal69d2e70dc7c24f88dbff28cbd067ee07 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.fantreffen-tshirt-deadline-notice','data' => ['tshirtDeadlinePassed' => $tshirtDeadlinePassed,'tshirtDeadlineFormatted' => $tshirtDeadlineFormatted,'daysUntilDeadline' => $daysUntilDeadline,'variant' => 'prominent']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('fantreffen-tshirt-deadline-notice'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['tshirtDeadlinePassed' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tshirtDeadlinePassed),'tshirtDeadlineFormatted' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tshirtDeadlineFormatted),'daysUntilDeadline' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($daysUntilDeadline),'variant' => 'prominent']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal69d2e70dc7c24f88dbff28cbd067ee07)): ?>
<?php $attributes = $__attributesOriginal69d2e70dc7c24f88dbff28cbd067ee07; ?>
<?php unset($__attributesOriginal69d2e70dc7c24f88dbff28cbd067ee07); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal69d2e70dc7c24f88dbff28cbd067ee07)): ?>
<?php $component = $__componentOriginal69d2e70dc7c24f88dbff28cbd067ee07; ?>
<?php unset($__componentOriginal69d2e70dc7c24f88dbff28cbd067ee07); ?>
<?php endif; ?>
                                    <label class="flex items-start gap-2">
                                        <input type="checkbox" name="tshirt_bestellt" id="tshirt_bestellt" value="1" 
                                               <?php echo e(old('tshirt_bestellt') ? 'checked' : ''); ?>

                                               class="w-5 h-5 mt-0.5">
                                        <div>
                                            <span class="font-medium">Event-T-Shirt bestellen</span>
                                            <p class="text-xs text-gray-500 mt-1">25,00 € Spende<?php echo e(!Auth::check() ? ' (zusammen mit Teilnahme: 30,00 €)' : ''); ?></p>
                                        </div>
                                    </label>
                                    
                                    <div id="tshirt-groesse-container" class="mt-3 hidden">
                                        <label class="block text-sm font-medium mb-2">T-Shirt-Größe *</label>
                                        <select name="tshirt_groesse" id="tshirt_groesse" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:border-gray-600">
                                            <option value="">Bitte wählen...</option>
                                            <option value="XS" <?php echo e(old('tshirt_groesse') === 'XS' ? 'selected' : ''); ?>>XS</option>
                                            <option value="S" <?php echo e(old('tshirt_groesse') === 'S' ? 'selected' : ''); ?>>S</option>
                                            <option value="M" <?php echo e(old('tshirt_groesse') === 'M' ? 'selected' : ''); ?>>M</option>
                                            <option value="L" <?php echo e(old('tshirt_groesse') === 'L' ? 'selected' : ''); ?>>L</option>
                                            <option value="XL" <?php echo e(old('tshirt_groesse') === 'XL' ? 'selected' : ''); ?>>XL</option>
                                            <option value="XXL" <?php echo e(old('tshirt_groesse') === 'XXL' ? 'selected' : ''); ?>>XXL</option>
                                            <option value="XXXL" <?php echo e(old('tshirt_groesse') === 'XXXL' ? 'selected' : ''); ?>>XXXL</option>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <button type="submit" class="w-full px-6 py-3 bg-[#8B0116] text-white font-bold rounded-lg hover:bg-[#6b000e] transition">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paymentAmount > 0): ?>
                                    Weiter zur Zahlung (<?php echo e(number_format($paymentAmount, 2, ',', '.')); ?> €)
                                <?php else: ?>
                                    Jetzt anmelden
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php echo app('Illuminate\Foundation\Vite')(['resources/js/fantreffen.js']); ?>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\omxfc-vereinswebseite\omxfc-vereinswebseite\resources\views/fantreffen/anmeldung.blade.php ENDPATH**/ ?>