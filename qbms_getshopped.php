<?php
/*
Plugin Name: QBMS Payment Processing for GetShopped
Plugin URI: http://igraycon.com/
Description: Adds Payment Processing using QBMS from Intuit.
Version: 1.0
Author: iGray Consulting, LLC
Author URI: http://igraycon.com/
*/

function qbms_init_gateway($nzshpcrt_gateways){
  global $gateway_checkout_form_fields;
  $num = count($nzshpcrt_gateways)+1;
  include_once('qbms.php');
  return $nzshpcrt_gateways;
}

add_action('wpsc_gateway_modules','qbms_init_gateway');

if(count((array)get_option('custom_gateway_options')) == 1) { 
  // if there is only one active gateway, and it has form fields, append them to the end of the checkout form.
  $active_gateway = implode('',(array)get_option('custom_gateway_options'));
  if ( isset($gateway_checkout_form_fields) && (count((array)$gateway_checkout_form_fields) == 1) && ($gateway_checkout_form_fields[$active_gateway] != '')) {
    $gateway_checkout_form_field =  $gateway_checkout_form_fields[$active_gateway];	
  }
}
?>
