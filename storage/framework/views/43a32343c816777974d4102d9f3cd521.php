<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve(['title' => 'Zugriff verweigert – Offizieller MADDRAX Fanclub e. V.','description' => 'Du besitzt keine Berechtigung für diesen Bereich.'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="container mx-auto py-12 text-center">
        <h1 class="text-5xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-6">403</h1>
        <img src="<?php echo e(asset('images/errors/403.png')); ?>" alt="Verbotene Zone" class="mx-auto w-64 h-auto mb-6">
        <p class="text-xl text-gray-700 dark:text-gray-300 mb-8">
            <?php echo e(__('Der Zugriff auf diese Seite ist dir nicht gestattet. Solltest du versuchen, trotzdem an diese Inhalte zu kommen, wird dich ein Rudel Taratze holen und Orguudoo bringen!')); ?>

        </p>
        <a href="<?php echo e(url('/')); ?>" class="text-[#8B0116] dark:text-[#ff4b63] underline">
            <?php echo e(__('Zur Startseite')); ?>

        </a>
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
<?php endif; ?><?php /**PATH C:\xampp\htdocs\omxfc-vereinswebseite\omxfc-vereinswebseite\resources\views/errors/403.blade.php ENDPATH**/ ?>