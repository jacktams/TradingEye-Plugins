<?php
class c_paymentSense
{
	function c_paymentSense()
	{
		require_once(SITE_PATH."modules/ecom/classes/main/PaymentSystem.php");
		include (SITE_PATH."modules/ecom/classes/main/ISOCurrencies.php");
		include (SITE_PATH."modules/ecom/classes/main/ISOCountries.php");
	}
	
	function m_PaymentSense_3D1()
	{
		//error_log("3d1, id:".$this->request['mode']."\n",3,SITE_PATH."plugins/Payment Sense/paymentSense.log");
		//error_log("3d1, print:".print_r($this->request,1)."\n",3,SITE_PATH."plugins/Payment Sense/paymentSense.log");
		$this->obTpl->set_var("TPL_VAR_BREDCRUMBS","&nbsp;&raquo;&nbsp;3d Secure");
		$this->obTpl->set_var("TPL_VAR_BODY",'
				<p>To increase the security of internet transactions Visa and Mastercard have introduced 3D-Secure (like an online version of Chip and PIN). <br>
				<br>
				You have chosen to use a card that is part of the 3D-Secure scheme, so you will need to authenticate yourself with your bank in the section below.
				</p>
				<form id="PS3DS" action="'.$_SESSION['FormAction'].'" method="post" target="ACSFrame">
				<input name="PaReq" type="hidden" value="'.$_SESSION['PaREQ'].'" />
				<input name="MD" type="hidden" value="'.$_SESSION['CrossReference'].'" />
				<input name="TermUrl" type="hidden" value="'.SITE_SAFEURL . 'ecom/index.php?action=checkout.ps3d2&mode='.$this->request['mode'].'" />
				<iframe id="ACSFrame" name="ACSFrame" src="'.SITE_SAFEURL.'plugins/Payment%20Sense/templates/loading.htm'.'" width="100%" height="400" frameborder="0"></iframe>
				</form>
				<script type="text/javascript">
					jQuery(document).ready(function(){
						jQuery("#PS3DS").submit();
					});
				</script>');
	}
	
	function m_PaymentSense_3D2()
	{
		//error_log("3d2, id:".$this->request['mode']."\n",3,SITE_PATH."plugins/Payment Sense/paymentSense.log");
		require_once (SITE_PATH."modules/ecom/classes/main/PaymentFormHelper.php");
		if (isset($_POST['MD']) == false || 
	    isset($_POST['PaRes']) == false)
		{
			$Message = "There were errors collecting the responses back from the ACS";
			$_SESSION['cardsave_error']=$Message;
			$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
			$this->libFunc->m_mosRedirect($retUrl);
		}
		else
		{
			$MD = $_POST['MD'];
			$PaRES = $_POST['PaRes'];
			$FormAction = "PaymentForm.php";
			$ShoppingCartHashDigest = md5( "PaRES=".$PaRES."&CrossReference=".$MD."&SecretKey=".PS_SECRET_KEY);
			$this->obTpl->set_var("TPL_VAR_BREDCRUMBS","&nbsp;&raquo;&nbsp;3d Secure");
			$this->obTpl->set_var("TPL_VAR_BODY",'
			<form id="PS3DS2" action="'.SITE_SAFEURL.'ecom/index.php?action=checkout.ps3dr&mode='.$_GET['mode'].'" method="post" target="_parent">
			<input name="CrossReference" type="hidden" value="'.$MD.'" />
			<input name="PaRES" type="hidden" value="'.$PaRES.'" />
			<input name="ShoppingCartHashDigest" type="hidden" value="'.$ShoppingCartHashDigest.'" />
			</form>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery("#PS3DS2").submit();
				});
			</script>
			');
		}
	}
	public static function calculateHashDigest($szInputString)
	{
		$hashDigest = md5($szInputString);
		return ($hashDigest);
	}
	public static function generateStringToHash2($szPaRES,$szCrossReference,$szSecretKey)
	{
		$szReturnString = "PaRES=".$szPaRES."&CrossReference=".$szCrossReference."&SecretKey=".$szSecretKey;
	}
	function m_PaymentSense_3DR()
	{
		//error_log("3dr, id:".$this->request['mode']."\n",3,SITE_PATH."plugins/Payment Sense/paymentSense.log");
		$PaymentProcessorDomain = PS_GATEWAY_DOMAIN;
		$PaymentProcessorPort = PS_GATEWAY_PORT;
		$MerchantID = PS_MERCHANT_ID;
		$Password = PS_MERCHANT_PASS;
		$CrossReference = $this->request['CrossReference'];
		$PaRES = $this->request['PaRES'];
		if ($PaymentProcessorPort == 443)
		{
			$PaymentProcessorFullDomain = $PaymentProcessorDomain."/";
		}
		else
		{
			$PaymentProcessorFullDomain = $PaymentProcessorDomain.":".$PaymentProcessorPort."/";
		}
		$rgeplRequestGatewayEntryPointList = new RequestGatewayEntryPointList();
		$rgeplRequestGatewayEntryPointList->add("https://gw1.".$PaymentProcessorFullDomain, 100, 1);
		$rgeplRequestGatewayEntryPointList->add("https://gw2.".$PaymentProcessorFullDomain, 200, 1);
		$rgeplRequestGatewayEntryPointList->add("https://gw3.".$PaymentProcessorFullDomain, 300, 1);
		$tdsaThreeDSecureAuthentication = new ThreeDSecureAuthentication($rgeplRequestGatewayEntryPointList);
	
		$tdsaThreeDSecureAuthentication->getMerchantAuthentication()->setMerchantID($MerchantID);
		$tdsaThreeDSecureAuthentication->getMerchantAuthentication()->setPassword($Password);

		$tdsaThreeDSecureAuthentication->getThreeDSecureInputData()->setCrossReference($CrossReference);
		$tdsaThreeDSecureAuthentication->getThreeDSecureInputData()->setPaRES($PaRES);

		$boTransactionProcessed = $tdsaThreeDSecureAuthentication->processTransaction($tdsarThreeDSecureAuthenticationResult, $todTransactionOutputData);

		if ($boTransactionProcessed == false)
		{
			// could not communicate with the payment gateway
			$NextFormMode = "RESULTS";
			$Message = "Couldn't communicate with payment gateway";
			$_SESSION['cardsave_error']=$Message;
			$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
			$this->libFunc->m_mosRedirect($retUrl);
		}
		else
		{
			switch ($tdsarThreeDSecureAuthenticationResult->getStatusCode())
			{
				case 0:
					// status code of 0 - means transaction successful
					$this->obDb->query= "UPDATE ".ORDERS." SET iOrderStatus=1,iPayStatus=1 WHERE iOrderid_PK = '".$_GET['mode']."'";
					$rs = $this->obDb->updateQuery();
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.process&mode=".$_GET['mode']);
					$this->libFunc->m_mosRedirect($retUrl);
					break;
				case 5:
					// status code of 5 - means transaction declined
					$NextFormMode = "RESULTS";
					$Message = $tdsarThreeDSecureAuthenticationResult->getMessage();
					$_SESSION['cardsave_error']=$Message;
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
					$this->libFunc->m_mosRedirect($retUrl);
					break;
				case 20:
					// status code of 20 - means duplicate transaction 
					$NextFormMode = "RESULTS";
					$Message = $tdsarThreeDSecureAuthenticationResult->getMessage();
					if ($tdsarThreeDSecureAuthenticationResult->getPreviousTransactionResult()->getStatusCode()->getValue() == 0)
					{
						$TransactionSuccessful = true;
					}
					else
					{
						$TransactionSuccessful = false;
					}
					$PreviousTransactionMessage = $tdsarThreeDSecureAuthenticationResult->getPreviousTransactionResult()->getMessage();
					$_SESSION['cardsave_error']=$Message . " , " . $PreviousTransactionMessage;
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
					$this->libFunc->m_mosRedirect($retUrl);
					break;
				case 30:
					// status code of 30 - means an error occurred 
					$NextFormMode = "RESULTS";
					$Message = $tdsarThreeDSecureAuthenticationResult->getMessage();
					if ($tdsarThreeDSecureAuthenticationResult->getErrorMessages()->getCount() > 0)
					{
						for ($LoopIndex = 0; $LoopIndex < $tdsarThreeDSecureAuthenticationResult->getErrorMessages()->getCount(); $LoopIndex++)
						{
							$Message = $Message."<br/>".$tdsarThreeDSecureAuthenticationResult->getErrorMessages()->getAt($LoopIndex)."</li>";
						}
					}
					if ($todTransactionOutputData == null)
					{
						$szResponseCrossReference = "";
					}
					else
					{
						$szResponseCrossReference = $todTransactionOutputData->getCrossReference();
					}
					$_SESSION['cardsave_error']=$Message;
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
					$this->libFunc->m_mosRedirect($retUrl);
					break;
				default:
					// unhandled status code  
					$Message=$tdsarThreeDSecureAuthenticationResult->getMessage();
					$TransactionSuccessful = false;
					if ($todTransactionOutputData == null)
					{
						$szResponseCrossReference = "";
					}
					else
					{
						$szResponseCrossReference = $todTransactionOutputData->getCrossReference();
					}
					$_SESSION['cardsave_error']=$Message;
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
					$this->libFunc->m_mosRedirect($retUrl);
					break;
			}
		}
	}
	
	function m_PaymentSense_Direct($orderId2)
	{
		$this->libFunc = new c_libFunctions();
		$orderId = strval($orderId2);
		//constants
		// PS_MERCHANT_ID , PS_MERCHANT_PASS , PS_CURRENCY , PS_GATEWAY_DOMAIN , PS_GATEWAY_PORT
		$Amount = floatval($_SESSION['grandTotal'])*100;
		$MerchantID = PS_MERCHANT_ID;
		$Password = PS_MERCHANT_PASS;
		$CurrencyShort = PS_CURRENCY;
		$OrderID = $orderId;
		$OrderDescription = SITE_URL . " - Invoice #".$orderId;
		$CardName = $_SESSION['cardholder_name'];
		$CardNumber = $_SESSION['cc_number'];
		$ExpiryDateMonth = $_SESSION['cc_month'];
		$ExpiryDateYear = substr($_SESSION['cc_year'],2);
		$StartDateYear = $_SESSION['cc_start_year'];
		$StartDateMonth = $_SESSION['cc_start_month'];
		$IssueNumber = $_SESSION['issuenumber'];
		$CV2 = $_SESSION['cv2'];
		$Address1 = $_SESSION['address1'];
		$Address2 = $_SESSION['address2'];
		$Address3 = '';
		$Address4 = '';
		$City = $_SESSION['city'];
		$this->obDb->query = "SELECT vStateName FROM ".STATES." where iStateId_PK  = '".$_SESSION['bill_state_id']."'";
		$row_state = $this->obDb->fetchQuery();
		$State = $row_state[0]->vStateName;
		$PostCode = $_SESSION['zip'];
		$this->obDb->query = "SELECT vCountryCode FROM ".COUNTRY." where iCountryId_PK  = '".$_SESSION['bill_country_id']."'";
		$row_country = $this->obDb->fetchQuery();
		$billcountryiso = $row_country[0]->vCountryCode;
		$CustomerEmail = $_SESSION['email'];
		$CustomerPhone = $_SESSION['phone'];
		$PaymentProcessorDomain = PS_GATEWAY_DOMAIN;
		$PaymentProcessorPort = PS_GATEWAY_PORT;
		if ($PaymentProcessorPort == 443)
		{
			$PaymentProcessorFullDomain = $PaymentProcessorDomain."/";
		}
		else
		{
			$PaymentProcessorFullDomain = $PaymentProcessorDomain.":".$PaymentProcessorPort."/";
		}
		$iclISOCurrencyList = new ISOCurrencyList();
		$rgeplRequestGatewayEntryPointList = new RequestGatewayEntryPointList();
		$rgeplRequestGatewayEntryPointList->add("https://gw1.".$PaymentProcessorFullDomain, 100, 1);
		$rgeplRequestGatewayEntryPointList->add("https://gw2.".$PaymentProcessorFullDomain, 200, 1);
		$rgeplRequestGatewayEntryPointList->add("https://gw3.".$PaymentProcessorFullDomain, 300, 1);
		$cdtCardDetailsTransaction = new CardDetailsTransaction($rgeplRequestGatewayEntryPointList);
		$cdtCardDetailsTransaction->getMerchantAuthentication()->setMerchantID($MerchantID);
		$cdtCardDetailsTransaction->getMerchantAuthentication()->setPassword($Password);
		$cdtCardDetailsTransaction->getTransactionDetails()->getMessageDetails()->setTransactionType("SALE");
		$cdtCardDetailsTransaction->getTransactionDetails()->getAmount()->setValue($Amount);
		//if ($CurrencyShort != "" && $iclISOCurrencyList->getISOCurrency($CurrencyShort, $icISOCurrency))
		//{
		$cdtCardDetailsTransaction->getTransactionDetails()->getCurrencyCode()->setValue($CurrencyShort);
		//}
		$cdtCardDetailsTransaction->getTransactionDetails()->setOrderID($OrderID);
		$cdtCardDetailsTransaction->getTransactionDetails()->setOrderDescription($OrderDescription);

		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getEchoCardType()->setValue(true);
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getEchoAmountReceived()->setValue(true);
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getEchoAVSCheckResult()->setValue(true);
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getEchoCV2CheckResult()->setValue(true);
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getThreeDSecureOverridePolicy()->setValue(true);
		$cdtCardDetailsTransaction->getTransactionDetails()->getTransactionControl()->getDuplicateDelay()->setValue(60);

		$cdtCardDetailsTransaction->getTransactionDetails()->getThreeDSecureBrowserDetails()->getDeviceCategory()->setValue(0);
		$cdtCardDetailsTransaction->getTransactionDetails()->getThreeDSecureBrowserDetails()->setAcceptHeaders("*/*");
		$cdtCardDetailsTransaction->getTransactionDetails()->getThreeDSecureBrowserDetails()->setUserAgent($_SERVER["HTTP_USER_AGENT"]);

		$cdtCardDetailsTransaction->getCardDetails()->setCardName($CardName);
		$cdtCardDetailsTransaction->getCardDetails()->setCardNumber($CardNumber);

		if ($ExpiryDateMonth != "")
		{
			$cdtCardDetailsTransaction->getCardDetails()->getExpiryDate()->getMonth()->setValue($ExpiryDateMonth);
		}
		if ($ExpiryDateYear != "")
		{
			$cdtCardDetailsTransaction->getCardDetails()->getExpiryDate()->getYear()->setValue($ExpiryDateYear);
		}
		if ($StartDateMonth != "")
		{
			$cdtCardDetailsTransaction->getCardDetails()->getStartDate()->getMonth()->setValue($StartDateMonth);
		}
		if ($StartDateYear != "")
		{
			$cdtCardDetailsTransaction->getCardDetails()->getStartDate()->getYear()->setValue($StartDateYear);
		}

		$cdtCardDetailsTransaction->getCardDetails()->setIssueNumber($IssueNumber);
		$cdtCardDetailsTransaction->getCardDetails()->setCV2($CV2);

		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setAddress1($Address1);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setAddress2($Address2);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setAddress3($Address3);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setAddress4($Address4);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setCity($City);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setState($State);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->setPostCode($PostCode);
		$cdtCardDetailsTransaction->getCustomerDetails()->getBillingAddress()->getCountryCode()->setValue($billcountryiso);
		$cdtCardDetailsTransaction->getCustomerDetails()->setEmailAddress($CustomerEmail);
		$cdtCardDetailsTransaction->getCustomerDetails()->setPhoneNumber($CustomerPhone);
		$cdtCardDetailsTransaction->getCustomerDetails()->setCustomerIPAddress($_SERVER["REMOTE_ADDR"]);
		//error_log($cdtrCardDetailsTransactionResult . " |" . $todTransactionOutputData,3,SITE_PATH."ecom/paymentSense.log");

		$boTransactionProcessed = $cdtCardDetailsTransaction->processTransaction($cdtrCardDetailsTransactionResult, $todTransactionOutputData);

		
		if ($boTransactionProcessed == false)
		{
			// could not communicate with the payment gateway 
			$Message = "Couldn't communicate with payment gateway". $cdtCardDetailsTransaction->getLastException()->getMessage();
			$_SESSION['cardsave_error']=$Message;
			$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
			$this->libFunc->m_mosRedirect($retUrl);
		}
		else
		{
				error_log("status results, id:".$orderId."\n",3,SITE_PATH."plugins/Payment Sense/paymentSense.log");
			switch ($cdtrCardDetailsTransactionResult->getStatusCode())
			{
				case 0:
					// status code of 0 - means transaction successful 
					$this->obDb->query= "UPDATE ".ORDERS." SET iOrderStatus=1,iPayStatus=1 WHERE iOrderid_PK = '".$orderId."'";
					$rs = $this->obDb->updateQuery();
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.process&mode=".$orderId);
					$this->libFunc->m_mosRedirect($retUrl);
					break;
				case 3:
					// status code of 3 - means 3D Secure authentication required 
					$_SESSION['PaREQ'] = $todTransactionOutputData->getThreeDSecureOutputData()->getPaREQ();
					$_SESSION['CrossReference'] = $todTransactionOutputData->getCrossReference();
					$BodyAttributes = " onload=\"document.Form.submit();\"";
					$FormAttributes = " target=\"ACSFrame\"";
					$_SESSION['FormAction'] = $todTransactionOutputData->getThreeDSecureOutputData()->getACSURL();
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL.'ecom/index.php?action=checkout.ps3d&mode=' . $orderId);
					$this->libFunc->m_mosRedirect($retUrl);
					break;
				case 5:
					// status code of 5 - means transaction declined 
					$Message=$cdtrCardDetailsTransactionResult->getMessage();
					$_SESSION['cardsave_error']=$Message;
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
					$this->libFunc->m_mosRedirect($retUrl);
					break;
				case 20:
					// status code of 20 - means duplicate transaction 
					$NextFormMode = "RESULTS";
					$Message = $cdtrCardDetailsTransactionResult->getMessage();
					if ($cdtrCardDetailsTransactionResult->getPreviousTransactionResult()->getStatusCode()->getValue() == 0)
					{
						$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.process&mode=".$orderId);
						$this->libFunc->m_mosRedirect($retUrl);
					}
					$PreviousTransactionMessage = $cdtrCardDetailsTransactionResult->getPreviousTransactionResult()->getMessage();
					$_SESSION['cardsave_error']=$Message." , ".$PreviousTransactionMessage;
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
					$this->libFunc->m_mosRedirect($retUrl);
					break;
				case 30:
					// status code of 30 - means an error occurred 
					$Message = $cdtrCardDetailsTransactionResult->getMessage();
					if ($cdtrCardDetailsTransactionResult->getErrorMessages()->getCount() > 0)
					{
						for ($LoopIndex = 0; $LoopIndex < $cdtrCardDetailsTransactionResult->getErrorMessages()->getCount(); $LoopIndex++)
						{
							$Message = $Message."<br/>".$cdtrCardDetailsTransactionResult->getErrorMessages()->getAt($LoopIndex);
						}
					}
					if ($todTransactionOutputData == null)
					{
						$szResponseCrossReference = "";
					}
					else
					{
						$szResponseCrossReference = $todTransactionOutputData->getCrossReference();
					}
					$_SESSION['cardsave_error']=$Message;
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
					$this->libFunc->m_mosRedirect($retUrl);
					break;
				default:
					
					$Message = $cdtrCardDetailsTransactionResult->getMessage();
					if ($todTransactionOutputData == null)
					{
						$szResponseCrossReference = "";
					}
					else
					{
						$szResponseCrossReference = $todTransactionOutputData->getCrossReference();
					}
					$_SESSION['cardsave_error']=$Message;
					$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.billing");
					$this->libFunc->m_mosRedirect($retUrl);
					break;
			}
		}
		
	}
	
	function m_PaymentSense_Hosted($orderId)
	{
		require_once (SITE_PATH."modules/ecom/classes/main/PaymentFormHelper.php");
		$MerchantID = PSr_MERCHANT_ID;
		$Password = PSr_MERCHANT_PASS;
		$PaymentProcessorDomain = PSr_DOMAIN;
		//$PaymentProcessorDomain = "paymentsensegateway.com";
		// The chosen hash method - can be either "MD5", "SHA1", "HMACMD5" or "HMACSHA1"
		// This method MUST match the hash method set for the merchant in the MMS
		$HashMethod = "MD5";
		$PreSharedKey = PSr_KEY;
		$ResultDeliveryMethod = "SERVER";
		$FormAction = "https://mms.".$PaymentProcessorDomain."/Pages/PublicPages/PaymentForm.aspx";
		// the amount in *minor* currency (i.e. �10.00 passed as "1000")
		$szAmount = strval(100 * floatval($_SESSION['grandTotal']));
		// the currency	- ISO 4217 3-digit numeric (e.g. GBP = 826)
		$szCurrencyCode = strval(PSr_CURRENCY);
		// order ID
		$szOrderID = strval($orderId);
		// the transaction type - can be SALE or PREAUTH
		$szTransactionType = "SALE";
		$szTransactionDateTime = date('Y-m-d H:i:s P');
		$szOrderDescription = "Order From ".SITE_URL." - Invoice Number:".$orderId;
		// these variables allow the payment form to be "seeded" with initial values
		$szCustomerName = $_SESSION['first_name'] . " " . $_SESSION['last_name'];
		$szAddress1 = $_SESSION['address1'];
		$szAddress2 = $_SESSION['address2'];
		$szAddress3 = "";
		$szAddress4 = "";
		$szCity = $_SESSION['city'];
		
		$this->obDb->query = "SELECT vStateName FROM ".STATES." where iStateId_PK  = '".$_SESSION['bill_state_id']."'";
		$row_state = $this->obDb->fetchQuery();
		$szState = $row_state[0]->vStateName;
		$szPostCode = $_SESSION['zip'];
		$this->obDb->query = "SELECT vCountryCode FROM ".COUNTRY." where iCountryId_PK  = '".$_SESSION['bill_country_id']."'";
		$row_country = $this->obDb->fetchQuery();
		$szCountryCode = $row_country[0]->vCountryCode;
		// use these to control which fields on the hosted payment form are
		// mandatory
		$szCV2Mandatory = PaymentFormHelper::boolToString(PSr_CV2_MANDATORY);
		$szAddress1Mandatory = PaymentFormHelper::boolToString(true);
		$szCityMandatory = PaymentFormHelper::boolToString(true);
		$szPostCodeMandatory = PaymentFormHelper::boolToString(true);
		$szStateMandatory = PaymentFormHelper::boolToString(true);
		$szCountryMandatory = PaymentFormHelper::boolToString(true);
		// the URL on this system that the payment form will push the results to (only applicable for 
		// ResultDeliveryMethod = "SERVER")
		if ($ResultDeliveryMethod != "SERVER")
		{
			$szServerResultURL = "";
		}
		else
		{
			$szServerResultURL = SITE_SAFEURL."ecom/index.php?action=checkout.pshcb";
		}
		// set this to true if you want the hosted payment form to display the transaction result
		// to the customer (only applicable for ResultDeliveryMethod = "SERVER")
		if ($ResultDeliveryMethod != "SERVER")
		{
			$szPaymentFormDisplaysResult = "";
		}
		else
		{
			$szPaymentFormDisplaysResult = PaymentFormHelper::boolToString(PSr_RESULTS_DISPLAY);
		}
		// the callback URL on this site that will display the transaction result to the customer
		// (always required unless ResultDeliveryMethod = "SERVER" and PaymentFormDisplaysResult = "true")
		if ($ResultDeliveryMethod == "SERVER" && PaymentFormHelper::stringToBool($szPaymentFormDisplaysResult) == false)
		{
			$szCallbackURL = SITE_SAFEURL."ecom/index.php?action=checkout.pshcb2";
		}
		else
		{
			$szCallbackURL = SITE_SAFEURL."ecom/index.php?action=checkout.pshcb2";
		}

		// get the string to be hashed
		$szStringToHash = PaymentFormHelper::generateStringToHash($MerchantID,
																  $Password,
																  $szAmount,
																  $szCurrencyCode,
																  $szOrderID,
																  $szTransactionType,
																  $szTransactionDateTime,
																  $szCallbackURL,
																  $szOrderDescription,
																  $szCustomerName,
																  $szAddress1,
																  $szAddress2,
																  $szAddress3,
																  $szAddress4,
																  $szCity,
																  $szState,
																  $szPostCode,
																  $szCountryCode,
																  $szCV2Mandatory,
																  $szAddress1Mandatory,
																  $szCityMandatory,
																  $szPostCodeMandatory,
																  $szStateMandatory,
																  $szCountryMandatory,
																  $ResultDeliveryMethod,
																  $szServerResultURL,
																  $szPaymentFormDisplaysResult,
																  $PreSharedKey,
																  $HashMethod);

		// pass this string into the hash function to create the hash digest
		$szHashDigest = PaymentFormHelper::calculateHashDigest($szStringToHash,
															   $PreSharedKey, 
															   $HashMethod);
		//$this->obTpl->set_var("TPL_VAR_BREDCRUMBS","&nbsp;&raquo;&nbsp;Checkout");
		//$this->obTpl->set_var("TPL_VAR_BODY",'
		echo '<html><head><script language="JavaScript" type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.js"></script></head><body>
		<p>Please wait while your are transferred to Payment Sense to complete your payment.</p>
		<form id="psrsubmit" action="'.$FormAction.'" method="post">
			<input type="hidden" name="HashDigest" value="'.$szHashDigest.'" />
			<input type="hidden" name="MerchantID" value="'.$MerchantID.'" />
			<input type="hidden" name="Amount" value="'.$szAmount.'" />
			<input type="hidden" name="CurrencyCode" value="'.$szCurrencyCode.'" />
			<input type="hidden" name="OrderID" value="'.$szOrderID.'" />
			<input type="hidden" name="TransactionType" value="'.$szTransactionType.'" />
			<input type="hidden" name="TransactionDateTime" value="'.$szTransactionDateTime.'" />
			<input type="hidden" name="CallbackURL" value="'.$szCallbackURL.'" />
			<input type="hidden" name="OrderDescription" value="'.$szOrderDescription.'" />
			<input type="hidden" name="CustomerName" value="'.$szCustomerName.'" />
			<input type="hidden" name="Address1" value="'.$szAddress1.'" />
			<input type="hidden" name="Address2" value="'.$szAddress2.'" />
			<input type="hidden" name="Address3" value="'.$szAddress3.'" />
			<input type="hidden" name="Address4" value="'.$szAddress4.'" />
			<input type="hidden" name="City" value="'.$szCity.'" />
			<input type="hidden" name="State" value="'.$szState.'" />
			<input type="hidden" name="PostCode" value="'.$szPostCode.'" />
			<input type="hidden" name="CountryCode" value="'.$szCountryCode.'" />
			<input type="hidden" name="CV2Mandatory" value="'.$szCV2Mandatory.'" />
			<input type="hidden" name="Address1Mandatory" value="'.$szAddress1Mandatory.'" />
			<input type="hidden" name="CityMandatory" value="'.$szCityMandatory.'" />
			<input type="hidden" name="PostCodeMandatory" value="'.$szPostCodeMandatory.'" />
			<input type="hidden" name="StateMandatory" value="'.$szStateMandatory.'" />
			<input type="hidden" name="CountryMandatory" value="'.$szCountryMandatory.'" />
			<input type="hidden" name="ResultDeliveryMethod" value="'.$ResultDeliveryMethod.'" />
			<input type="hidden" name="ServerResultURL" value="'.$szServerResultURL.'" />
			<input type="hidden" name="PaymentFormDisplaysResult" value="'.$szPaymentFormDisplaysResult.'" />
			<input type="hidden" name="ServerResultURLCookieVariables" value="" />
			<input type="hidden" name="ServerResultURLFormVariables" value="" />
			<input type="hidden" name="ServerResultURLQueryStringVariables" value="" />
		</form>
		<script type="text/javascript">
			jQuery(document).ready(function(){
				jQuery("#psrsubmit").submit();
			});
		</script></body></html>';
	}
	
	function m_PaymentSense_Hosted_Callback($who)
	{
		require_once (SITE_PATH."modules/ecom/classes/main/PaymentFormHelper.php");
		if($who == "1")
		{
			//cutomer
			$orderId = $_GET['OrderID'];
			$retUrl=$this->libFunc->m_safeUrl(SITE_SAFEURL."ecom/index.php?action=checkout.process&mode=".$orderId);
			$this->libFunc->m_mosRedirect($retUrl);
		}
		elseif($who == "0")
		{
			//notification
			$nOutputStatusCode = 30;
			$szOutputMessage = "";
			$szUpdateOrderMessage = "";
			$boErrorOccurred = false;
			try
			{
				// read in the transaction result variables
				if (!PaymentFormHelper::getTransactionResultFromPostVariables($_POST, $trTransactionResult, $szHashDigest, $szOutputMessage))
				{
					$nOutputStatusCode = 30;
				}
				else
				{
					if (!PaymentFormHelper::reportTransactionResults($trTransactionResult,
																	 $szUpdateOrderMessage))
					{
						$nOutputStatusCode = 30;
						$szOutputMessage = $szOutputMessage.$szUpdateOrderMessage;
					}
					else
					{
						$nOutputStatusCode = 0;
					}
				}
			}
			catch (Exception $e)
			{
				$nOutputStatusCode = 30;
				$szOutputMessage = $szOutputMessage.$e->getMessage();
			}
			if ($nOutputStatusCode != 0 &&
				$szOutputMessage == "")
			{
				$szOutputMessage = "Unknown error";
			}
			// output the status code and message letting the payment form
			// know whether the transaction result was processed successfully
			echo("StatusCode=".$nOutputStatusCode."&Message=".$szOutputMessage);
			if($nOutputStatusCode == 0)
			{
				
			}
		}
		else
		{
			
		}
	}
}
?>