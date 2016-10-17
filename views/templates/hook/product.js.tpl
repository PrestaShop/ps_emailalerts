<script type="application/javascript">
  $('input#mailalert_combination').val('{$id_product_attribute}');
  if (!{$already_subscribed} && 0 >= {$quantity}) {
    $('div.mailalert').show();
    $('#mailalert_link').show();
    $('#oos_customer_email').show();
    $('#oos_customer_email_result').hide();
  } else {
    $('div.mailalert').hide();
  }
</script>
