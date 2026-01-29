

<?php if (isset($component)) { $__componentOriginalc04b147acd0e65cc1a77f86fb0e81580 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc04b147acd0e65cc1a77f86fb0e81580 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'e60dd9d2c3a62d619c9acb38f20d5aa5::button.index','data' => ['attributes' => $attributes->class('shrink-0'),'variant' => 'subtle','square' => true,'xData' => true,'xOn:click' => '$dispatch(\'flux-sidebar-toggle\')','ariaLabel' => ''.e(__('Toggle sidebar')).'','dataFluxSidebarToggle' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('flux::button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['attributes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($attributes->class('shrink-0')),'variant' => 'subtle','square' => true,'x-data' => true,'x-on:click' => '$dispatch(\'flux-sidebar-toggle\')','aria-label' => ''.e(__('Toggle sidebar')).'','data-flux-sidebar-toggle' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc04b147acd0e65cc1a77f86fb0e81580)): ?>
<?php $attributes = $__attributesOriginalc04b147acd0e65cc1a77f86fb0e81580; ?>
<?php unset($__attributesOriginalc04b147acd0e65cc1a77f86fb0e81580); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc04b147acd0e65cc1a77f86fb0e81580)): ?>
<?php $component = $__componentOriginalc04b147acd0e65cc1a77f86fb0e81580; ?>
<?php unset($__componentOriginalc04b147acd0e65cc1a77f86fb0e81580); ?>
<?php endif; ?>
<<<<<<<< HEAD:storage/framework/views/f7b4001712bb89b8d93b94ba44513a9b.php
<?php /**PATH C:\xampp\htdocs\Flexcon-tracker\vendor\livewire\flux\src/../stubs/resources/views/flux/sidebar/toggle.blade.php ENDPATH**/ ?>
========
<?php /**PATH C:\xampp\htdocs\flexcon-tracker\vendor\livewire\flux\src/../stubs/resources/views/flux/sidebar/toggle.blade.php ENDPATH**/ ?>
>>>>>>>> d03ef7f7e809088ac98092fed940e42e2cd21541:storage/framework/views/8ceac2e2543c77341db2130d1a7082db.php
