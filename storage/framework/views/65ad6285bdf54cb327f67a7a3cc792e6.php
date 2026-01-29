

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'position' => 'bottom',
    'align' => 'start',
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
    'position' => 'bottom',
    'align' => 'start',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
// Support adding the .self modifier to the wire:model directive...
if (($wireModel = $attributes->wire('model')) && $wireModel->directive && ! $wireModel->hasModifier('self')) {
    unset($attributes[$wireModel->directive]);

    $wireModel->directive .= '.self';

    $attributes = $attributes->merge([$wireModel->directive => $wireModel->value]);
}
?>

<ui-dropdown position="<?php echo e($position); ?> <?php echo e($align); ?>" <?php echo e($attributes); ?> data-flux-dropdown>
    <?php echo e($slot); ?>

</ui-dropdown>
<<<<<<<< HEAD:storage/framework/views/726ff7ba4da46a251d9967a3319815f9.php
<?php /**PATH C:\xampp\htdocs\Flexcon-tracker\vendor\livewire\flux\src/../stubs/resources/views/flux/dropdown.blade.php ENDPATH**/ ?>
========
<?php /**PATH C:\xampp\htdocs\flexcon-tracker\vendor\livewire\flux\src/../stubs/resources/views/flux/dropdown.blade.php ENDPATH**/ ?>
>>>>>>>> d03ef7f7e809088ac98092fed940e42e2cd21541:storage/framework/views/65ad6285bdf54cb327f67a7a3cc792e6.php
