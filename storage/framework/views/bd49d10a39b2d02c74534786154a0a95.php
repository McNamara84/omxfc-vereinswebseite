<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Anmeldebestätigung – Maddrax-Fantreffen 2026','description' => 'Deine Anmeldung zum Maddrax-Fantreffen 2026 wurde erfolgreich gespeichert.'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['socialImage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(asset('build/assets/omxfc-logo-Df-1StAj.png'))]); ?>
<div class="bg-gray-50 dark:bg-gray-900 -mt-8 min-h-screen py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Erfolgsbox -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 mb-6">
            <div class="text-center mb-6">
                <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Anmeldung erfolgreich!</h1>
                <p class="text-gray-600 dark:text-gray-400">Wir freuen uns auf dich beim Maddrax-Fantreffen 2026!</p>
            </div>

            <!-- Anmeldedaten -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Deine Anmeldedaten</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-600 dark:text-gray-400">Name:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100"><?php echo e($anmeldung->vorname); ?> <?php echo e($anmeldung->nachname); ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600 dark:text-gray-400">E-Mail:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100"><?php echo e($anmeldung->email); ?></dd>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($anmeldung->mobile): ?>
                    <div class="flex justify-between">
                        <dt class="text-gray-600 dark:text-gray-400">Mobilnummer:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100"><?php echo e($anmeldung->mobile); ?></dd>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($anmeldung->tshirt_bestellt): ?>
                    <div class="flex justify-between">
                        <dt class="text-gray-600 dark:text-gray-400">T-Shirt:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">Größe <?php echo e($anmeldung->tshirt_groesse); ?></dd>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </dl>
            </div>

            <!-- Zahlungsinformationen -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($anmeldung->payment_amount > 0): ?>
            <div class="border-t border-gray-200 dark:border-gray-700 mt-6 pt-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">💳 Zahlungsinformationen</h2>
                <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-4">
                    <p class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
                        Zu zahlender Betrag: <?php echo e(number_format($anmeldung->payment_amount, 2, ',', '.')); ?> €
                    </p>
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        Bitte überweise den Betrag auf unser Vereinskonto oder nutze PayPal.me
                    </p>
                </div>

                <!-- PayPal Button -->
                <a href="https://www.paypal.com/paypalme/OMXFC/<?php echo e(number_format($anmeldung->payment_amount, 2, '.', '')); ?>" 
                   target="_blank"
                   class="block w-full px-6 py-3 bg-[#0070ba] text-white font-semibold rounded-lg hover:bg-[#005a92] text-center mb-4">
                    💳 Jetzt mit PayPal bezahlen (<?php echo e(number_format($anmeldung->payment_amount, 2, ',', '.')); ?> €)
                </a>

                <!-- PayPal-Gastzahlung Anleitung -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">💡 Kein PayPal-Account? Kein Problem!</h3>
                    <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                        Du kannst auch ohne PayPal-Konto oder Kreditkarte bezahlen, indem du PayPal als Gast nutzt. 
                        So musst du kein Konto einrichten und zahlst einfach per SEPA-Lastschrift.
                    </p>
                    <ol class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                        <li class="flex gap-2">
                            <span class="font-semibold text-[#8B0116] flex-shrink-0">1.</span>
                            <span>Klicke oben auf den PayPal-Button. Du wirst zur PayPal-Bezahlseite weitergeleitet.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="font-semibold text-[#8B0116] flex-shrink-0">2.</span>
                            <span>Unter der Login-Maske findest du den Link <strong>"Mit Debitkarte oder Bankkonto zahlen"</strong>. Klicke darauf.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="font-semibold text-[#8B0116] flex-shrink-0">3.</span>
                            <span>Fülle deine Bankdaten (IBAN) aus und aktiviere die Zustimmungs-Checkboxen.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="font-semibold text-[#8B0116] flex-shrink-0">4.</span>
                            <span>Schließe den Bezahlvorgang mit <strong>"Zustimmen und weiter"</strong> ab.</span>
                        </li>
                    </ol>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mt-4 text-center">
                    📧 Bei Fragen zur Zahlung wende dich bitte an <a href="mailto:kassenwart@maddrax-fanclub.de" class="underline hover:text-gray-700 dark:hover:text-gray-300">kassenwart@maddrax-fanclub.de</a>
                </p>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <!-- Bestätigungs-E-Mail -->
            <div class="border-t border-gray-200 dark:border-gray-700 mt-6 pt-6">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Du hast eine Bestätigungs-E-Mail an <strong><?php echo e($anmeldung->email); ?></strong> erhalten.
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Falls du keine E-Mail erhalten hast, überprüfe bitte deinen Spam-Ordner.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <a href="<?php echo e(route('home')); ?>" 
                   class="flex-1 px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-semibold rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 text-center">
                    🏠 Zur Startseite
                </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <a href="<?php echo e(route('dashboard')); ?>" 
                   class="flex-1 px-6 py-3 bg-[#8B0116] text-white font-semibold rounded-lg hover:bg-[#6b000e] text-center">
                    📊 Zum Dashboard
                </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</div>
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
<?php /**PATH C:\xampp\htdocs\omxfc-vereinswebseite\omxfc-vereinswebseite\resources\views/fantreffen/bestaetigung.blade.php ENDPATH**/ ?>