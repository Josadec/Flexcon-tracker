    <?php $layout->viewContext->mergeIntoNewEnvironment($__env); ?>

    <?php $__env->startComponent($layout->view, $layout->params); ?>
        <?php $__env->slot($layout->slotOrSection); ?>
            <?php echo $content; ?>

        <?php $__env->endSlot(); ?>

        <?php
        // Manually forward slots defined in the Livewire template into the layout component...
        foreach ($layout->viewContext->slots[-1] ?? [] as $name => $slot) {
            $__env->slot($name, attributes: $slot->attributes->getAttributes());
            echo $slot->toHtml();
            $__env->endSlot();
        }
        ?>
<<<<<<<< HEAD:storage/framework/views/54f15a4ce931af681205c2fa1f852458.php
    <?php echo $__env->renderComponent(); ?><?php /**PATH C:\xampp\htdocs\Flexcon-tracker\storage\framework\views/4943bc92ebba41e8b0e508149542e0ad.blade.php ENDPATH**/ ?>
========
    <?php echo $__env->renderComponent(); ?><?php /**PATH C:\xampp\htdocs\flexcon-tracker\storage\framework\views/4943bc92ebba41e8b0e508149542e0ad.blade.php ENDPATH**/ ?>
>>>>>>>> d03ef7f7e809088ac98092fed940e42e2cd21541:storage/framework/views/7e1a4e8a0c58439d03b6416c96fe8828.php
