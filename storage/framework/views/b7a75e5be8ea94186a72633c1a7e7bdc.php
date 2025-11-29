<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'tshirtDeadlinePassed' => false,
    'tshirtDeadlineFormatted' => '',
    'daysUntilDeadline' => 0,
    'variant' => 'compact' // 'compact' für Kostenübersicht, 'prominent' für Formular
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'tshirtDeadlinePassed' => false,
    'tshirtDeadlineFormatted' => '',
    'daysUntilDeadline' => 0,
    'variant' => 'compact' // 'compact' für Kostenübersicht, 'prominent' für Formular
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$tshirtDeadlinePassed): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($variant === 'compact'): ?>
        
        <div class="mt-2 p-2 bg-orange-100 dark:bg-orange-900/40 rounded border border-orange-300 dark:border-orange-700"
             <?php if($daysUntilDeadline <= 7): ?> role="alert" <?php endif; ?>>
            <p class="text-xs text-orange-800 dark:text-orange-200 font-semibold">
                ⏰ Bestellfrist: <?php echo e($tshirtDeadlineFormatted); ?>

            </p>
            <p class="text-xs text-orange-700 dark:text-orange-300 mt-0.5">
                T-Shirts können nur bis zur Bestellfrist mitbestellt werden!
            </p>
        </div>
    <?php else: ?>
        
        <div class="mb-3 p-3 bg-gradient-to-r from-orange-100 to-yellow-100 dark:from-orange-900/40 dark:to-yellow-900/40 rounded-lg border border-orange-300 dark:border-orange-600"
             <?php if($daysUntilDeadline <= 7): ?> role="alert" <?php endif; ?>>
            <div class="flex items-center gap-2">
                <span class="text-2xl" aria-hidden="true">👕</span>
                <div>
                    <p class="text-sm font-bold text-orange-800 dark:text-orange-200">
                        T-Shirt nur bis <?php echo e($tshirtDeadlineFormatted); ?> bestellbar!
                    </p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($daysUntilDeadline > 0): ?>
                        <p class="text-xs text-orange-700 dark:text-orange-300">
                            Noch <strong><?php echo e($daysUntilDeadline); ?> <?php echo e($daysUntilDeadline === 1 ? 'Tag' : 'Tage'); ?></strong> Zeit für deine T-Shirt-Bestellung.
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-orange-700 dark:text-orange-300">
                            Heute ist der letzte Tag für T-Shirt-Bestellungen!
                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php else: ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($variant === 'compact'): ?>
        
        <div class="mt-2 p-2 bg-gray-100 dark:bg-gray-700 rounded">
            <p class="text-xs text-gray-600 dark:text-gray-400">
                ❌ Bestellfrist abgelaufen – T-Shirts können nicht mehr bestellt werden.
            </p>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\xampp\htdocs\omxfc-vereinswebseite\omxfc-vereinswebseite\resources\views/components/fantreffen-tshirt-deadline-notice.blade.php ENDPATH**/ ?>