<?php
  // Compatible with sf_escaping_strategy: true
  $form = isset($form) ? $sf_data->getRaw('form') : null;
?>
<?php use_helper('I18N') ?>
<?php echo $form ?>
<script type="text/javascript" charset="utf-8">
$(document).ready(function() {
		aMultipleSelectAll({'choose-one':<?php echo json_encode(__('Select to Add', null, 'apostrophe')) ?>});
});
</script>