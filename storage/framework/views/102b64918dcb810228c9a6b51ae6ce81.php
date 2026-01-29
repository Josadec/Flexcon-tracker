

<?php
$classes = Flux::classes()
    ->add('[:where(&)]:min-w-48 p-[.3125rem]')
    ->add('rounded-lg shadow-xs')
    ->add('border border-zinc-200 dark:border-zinc-600')
    ->add('bg-white dark:bg-zinc-700')
    ->add('focus:outline-hidden')
    ;
?>

<ui-menu
    <?php echo e($attributes->class($classes)); ?>

    popover="manual"
    data-flux-menu
>
    <?php echo e($slot); ?>

</ui-menu>
<<<<<<<< HEAD:storage/framework/views/67bbf37c80cc8e3ea05810c65585649f.php
<?php /**PATH C:\xampp\htdocs\Flexcon-tracker\vendor\livewire\flux\src/../stubs/resources/views/flux/menu/index.blade.php ENDPATH**/ ?>
========
<?php /**PATH C:\xampp\htdocs\flexcon-tracker\vendor\livewire\flux\src/../stubs/resources/views/flux/menu/index.blade.php ENDPATH**/ ?>
>>>>>>>> d03ef7f7e809088ac98092fed940e42e2cd21541:storage/framework/views/102b64918dcb810228c9a6b51ae6ce81.php
