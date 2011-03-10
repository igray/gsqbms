<?php
/**
 * WP eCommerce QBMS Merchant Gateway
 */
if(!is_callable('get_option')) {
  // This is here to stop error messages on servers with Zend Accelerator, it includes all files before get_option is declared
  // then evidently includes them again, otherwise this code would break these modules
  return;
  exit("Something strange is happening, and \"return\" is not breaking out of a file.");
}
define('QBMS_APP_ID', '186661340');
//define('QBMS_APP_ID', '182719615');
define('QBMS_HOST', 'https://merchantaccount.quickbooks.com/');
//define('QBMS_HOST', 'https://merchantaccount.ptc.quickbooks.com');
define('QBMS_APP_NAME', 'gsqbms.igraycon.com');

global $gateway_checkout_form_fields;
/**
 * WP eCommerce Quickbooks Merchant Services File
 */
$nzshpcrt_gateways[$num] = array(
  'name' => 'Quickbooks Merchant Services',
  'api_version' => 2.0,
  'class_name' => 'wpsc_merchant_qbms',
  'has_recurring_billing' => false,
  'wp_admin_cannot_cancel' => false,
  'requirements' => array(
    'php_version' => 5.0
  ),
  'internalname' => 'wpsc_merchant_qbms',
  'form' => "form_qbms",
  'submit_function' => "submit_qbms",
  'payment_type' => "credit_card",
  'supported_currencies' => array(
    'currency_list' => array('USD')
  )
);


if(in_array('wpsc_merchant_qbms',(array)get_option('custom_gateway_options'))) {
  $gateway_checkout_form_fields[$nzshpcrt_gateways[$num]['internalname']] = "
    <tr>
    <td>Credit Card Number *</td>
    <td>
    <input type='text' value='' name='card_number' />
    </td>
    </tr>
    <tr>
    <td>Credit Card Expiry *</td>
    <td>
    <input type='text' size='2' value='' maxlength='2' name='expiry[month]' />/<input type='text' size='2'  maxlength='2' value='' name='expiry[year]' />
    </td>
    </tr>
    <tr>
    <td>CVV </td>
    <td><input type='text' size='4' value='' maxlength='4' name='card_code' /></td>
    </tr>
  ";
}


/**
 * WP eCommerce qbms.net Standard Merchant Class
 *
 * This is the qbms.net merchant class, it extends the base merchant class
 *
 * @package wp-e-commerce
 * @since 3.7.6
 * @subpackage wpsc-merchants
 */
class wpsc_merchant_qbms extends wpsc_merchant {
  var $name = 'Quickbooks Merchant Services';

  var $arb_requests = array();
  /**
   * construct value array method, converts the data gathered by the base class code to something acceptable to the gateway
   * @access public
   */
  function construct_value_array() {
    $this->credit_card_details = array(
      'card_number' => $_POST['card_number'],
      'expiry_date' => array('year' => $_POST['expiry']['year'], 'month' => $_POST['expiry']['month']),
      'card_code' => $_POST['card_code']
    );

    /*
     (TransRequestID,(((CreditCardNumber|CreditCardToken),ExpirationMonth,ExpirationYear,(IsCardPresent|IsECommerce|IsRecurring)?)|(Track1Data|Track2Data)),Amount,NameOnCard?,CreditCardAddress?,CreditCardCity?,CreditCardState?,CreditCardPostalCode?,CommercialCardCode?,SalesTaxAmount?,CardSecurityCode?,Lodging?,Restaurant?,BatchID?,ClientTransID?,InvoiceID?,UserID?,Comment?)
     */
    $gateway_doc = <<<EOXML
<?xml version="1.0"?>
<?qbmsxml version="4.4"?>
<QBMSXML>
<SignonMsgsRq>
 <SignonDesktopRq>
EOXML;
    $gateway_doc .= "<ClientDateTime>" . date('Y-m-d\TH:i:s') . "</ClientDateTime>";
    $gateway_doc .= "<ApplicationLogin>" . QBMS_APP_NAME . "</ApplicationLogin>";
    $gateway_doc .= "<ConnectionTicket>" . qbms_get_ticket() . "</ConnectionTicket>";
    $gateway_doc .= <<<EOXML
 </SignonDesktopRq>
</SignonMsgsRq>
<QBMSXMLMsgsRq>
 <CustomerCreditCardChargeRq>
EOXML;
    $gateway_doc .= "<TransRequestID>" . $this->cart_data['session_id'] . "</TransRequestID>";
    $gateway_doc .= "<CreditCardNumber>" . $this->credit_card_details['card_number'] . "</CreditCardNumber>";
    $gateway_doc .= "<ExpirationMonth>" . $this->credit_card_details['expiry_date']['month'] . "</ExpirationMonth>";
    $gateway_doc .= "<ExpirationYear>" . $this->credit_card_details['expiry_date']['year'] . "</ExpirationYear>";
    $gateway_doc .= "<IsCardPresent>false</IsCardPresent>";
    $gateway_doc .= "<Amount>" . number_format($this->cart_data['total_price'],2,'.','') . "</Amount>";
    $gateway_doc .= "<NameOnCard>" . $this->cart_data['billing_address']['first_name'] . " " . $this->cart_data['billing_address']['last_name'] . "</NameOnCard>";
    $gateway_doc .= "<CreditCardAddress>" . $this->cart_data['billing_address']['address'] . "</CreditCardAddress>";
    $gateway_doc .= "<CreditCardCity>" . $this->cart_data['billing_address']['city'] . "</CreditCardCity>";
    //$gateway_doc .= "<CreditCardState>" . $this->cart_data['billing_address'][''] . "</CreditCardState>";
    $gateway_doc .= "<CreditCardPostalCode>" . $this->cart_data['billing_address']['post_code'] . "</CreditCardPostalCode>";
    $gateway_doc .= "<CardSecurityCode>" . $this->credit_card_details['card_code'] . "</CardSecurityCode>";
    $gateway_doc .= "<InvoiceID>" . $this->cart_data['session_id'] . "</InvoiceID>";
    $gateway_doc .= "<Comment>" . get_option('qbms_form_description') . "</Comment>";
    $gateway_doc .= <<<EOXML
 </CustomerCreditCardChargeRq>
 </QBMSXMLMsgsRq>
</QBMSXML>
EOXML;

    $this->collected_gateway_data = $gateway_doc;
  }

  /**
   * submit method, sends the received data to the payment gateway
   * @access public
   */
  function submit() {
    $options = array(
      'timeout' => 10,
      'body' => $this->collected_gateway_data,
      'user-agent' => $this->cart_data['software_name'] ." " . get_bloginfo( 'url' ),
      'sslverify' => false,
      'headers' => array('Content-Type' => 'application/x-qbmsxml')
    );

    $qbms_url = QBMS_HOST . "/j/AppGateway";

    $response = wp_remote_post($qbms_url, $options);
    $parsed_response = null;
    if( ! is_wp_error( $response ) ) {
      $parsed_response = $this->parse_qbms_response($response['body']);
    }

    //echo "<pre>";
    //print_r($parsed_response);
    //echo "</pre>";
    //exit();

    if( ! $parsed_response || $parsed_response['signon_status'] != "0") {
      $this->set_error_message(__("There was an error contacting the payment gateway, please try again later.", 'wpsc'));
      $this->return_to_checkout();
      return false;
    }

    if($parsed_response['response_code'] != "0") {
      $this->set_transaction_details( $parsed_response['CreditCardTransID'], 1 );
      $this->set_error_message(__('Your transaction was declined. Please check your credit card details and try again.', 'wpsc'));
      $this->set_auth_code( $parsed_response['response_description'] );
      $this->return_to_checkout();
      return false;
    }
    //'approved';
    $this->set_transaction_details( $parsed_response['CreditCardTransID'], 3 );
    $this->set_auth_code( $parsed_response['AuthorizationCode'] );
    $this->go_to_transaction_results($this->cart_data['session_id']);
    return true;
  }

  /**
   * Set Authorization Code or Error Response from Gateway
   */
  function set_auth_code( $auth_code ) {
    global $wpdb;

    $auth_code = $wpdb->escape( $auth_code );
    $wpdb->query( "UPDATE `" . WPSC_TABLE_PURCHASE_LOGS . "` SET `authcode` = '{$auth_code}'  WHERE `id` = " . absint( $this->purchase_id ) . " LIMIT 1" );
  }
  
  /**
   * parse QBMS response, translate xmldoc into array.
   * @access public
   */
  function parse_qbms_response($response_doc) {
    /*
     * <?xml version="1.0" encoding="ISO-8859-1"?>
<!DOCTYPE QBMSXML PUBLIC "-//INTUIT//DTD QBMSXML QBMS 4.4//EN" "http://merchantaccount.ptc.quickbooks.com/dtds/qbmsxml44.dtd">
<QBMSXML>
 <SignonMsgsRs>
  <SignonDesktopRs statusCode="0" statusSeverity="INFO">
   <ServerDateTime>2010-12-31T00:08:22</ServerDateTime>
   <SessionTicket>V1-127-aM9r7GirH_PlKy6fW_KOCA:182719618</SessionTicket>
  </SignonDesktopRs>
 </SignonMsgsRs>
 <QBMSXMLMsgsRs>
  <CustomerCreditCardChargeRs statusCode="0" statusMessage="Status OK" statusSeverity="INFO">
   <CreditCardTransID>YY1000070106</CreditCardTransID>
   <AuthorizationCode>534398</AuthorizationCode>
   <AVSStreet>Pass</AVSStreet>
   <AVSZip>Pass</AVSZip>
   <CardSecurityCodeMatch>Pass</CardSecurityCodeMatch>
   <MerchantAccountNumber>5247715018025358</MerchantAccountNumber>
   <ReconBatchID>420101231 1Q16085247715018025358AUTO04</ReconBatchID>
   <PaymentGroupingCode>5</PaymentGroupingCode>
   <PaymentStatus>Completed</PaymentStatus>
   <TxnAuthorizationTime>2010-12-31T00:08:22</TxnAuthorizationTime>
   <TxnAuthorizationStamp>1293754102</TxnAuthorizationStamp>
   <ClientTransID>q00880ce</ClientTransID>
  </CustomerCreditCardChargeRs>
 </QBMSXMLMsgsRs>
     */
    $xml = new SimpleXMLElement($response_doc);
    $response = array();
    $signon = $xml->xpath('/QBMSXML/SignonMsgsRs/SignonDesktopRs');
    $response['signon_status'] = (string) $signon[0]['statusCode'];
    if ($response['signon_status'] == "0") {
      $charge = $xml->xpath('/QBMSXML/QBMSXMLMsgsRs/CustomerCreditCardChargeRs');
      $response['response_code'] = (string) $charge[0]['statusCode'];
      $response['response_description'] = (string) $charge[0]['statusMessage'];
      var_dump($charge);
      foreach($charge[0]->children() as $child) {
        $response[$child->getName()] = (string) $child;
      }
    }
    return $response;
  }

}

function submit_qbms() {
  $encrypted_pwd = mcrypt_encrypt( MCRYPT_BLOWFISH, substr(AUTH_KEY, 0, 16), base64_encode($_POST['qbms_password']), MCRYPT_MODE_ECB );
  update_option('qbms_password', base64_encode($encrypted_pwd));
  foreach((array)$_POST['qbms_form'] as $form => $value) {
    update_option(('qbms_form_'.$form), $value);
  }
  return true;
}
function qbms_get_ticket() {
  return base64_decode(mcrypt_decrypt( MCRYPT_BLOWFISH, substr(AUTH_KEY, 0, 16), base64_decode(get_option('qbms_password')), MCRYPT_MODE_ECB ));
}

function form_qbms() {
  $output .= "
    <tr>
      <td>Connection Ticket</td>
    <td>
      <input type='text' size='40' value='".qbms_get_ticket()."' name='qbms_password' />
      <br/>
      <span class='small description'>Copy/Paste your connection ticket from: <a target='_blank' href='" . QBMS_HOST . "/j/sdkconnection?appid=" . QBMS_APP_ID . "&sessionEnabled=false'>Quickbooks Merchant Services</a>.</span>
    </td>
    </tr>
    <tr>
      <td>Transaction Comment</td>
      <td>
        <textarea rows='3' name='qbms_form[description]'>".get_option('qbms_form_description')."</textarea>
      </td>
    </tr>
    ";
  return $output;
}
?>
