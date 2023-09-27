<?php

/*
* Plugin Name: DigiDargah crypto payment gateway for Prestashop
* Description: <a href="https://digidargah.com">DigiDargah</a> crypto payment gateway for Prestashop.
* Version: 1.1
* developer: Hanif Zekri Astaneh
* Author: DigiDargah.com
* Author URI: https://digidargah.com
* Author Email: info@digidargah.com
* Text Domain: DigiDargah_Prestashop_payment_module
* WC tested up to: 8.1
* copyright (C) 2020 DigiDargah
* license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 or later
*/

if (!defined('_PS_VERSION_')) exit;

class DigiDargah extends PaymentModule {
	
	private $_html = '';
    private $_postErrors = array();
	public $address;
	
	public function __construct(){
		$this->name = 'digidargah';
        $this->tab = 'payments_gateways';
        $this->version = '1.1';
        $this->author = 'DigiDargah';
        $this->controllers = array('payment', 'validation');
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;
        $this->displayName = 'DigiDargah';
        $this->description = 'درگاه پرداخت دیجی درگاه';
        $this->confirmUninstall = 'مطمئن هستید ؟';
        $this->ps_versions_compliancy = array('min' => '1.7.0', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        parent::__construct();
    }
	
	public function install(){
		return parent::install() && $this->registerHook('paymentOptions') && $this->registerHook('paymentReturn');
    }
	
	public function uninstall(){
		return parent::uninstall();
    }

    public function getContent(){
		
		if (Tools::isSubmit('digidargah_submit')) {
            Configuration::updateValue('digidargah_api_key', digidargah::sanitize($_POST['digidargah_api_key']));
            Configuration::updateValue('digidargah_pay_currency', digidargah::sanitize($_POST['digidargah_pay_currency']));
            Configuration::updateValue('digidargah_success_massage', digidargah::sanitize($_POST['digidargah_success_massage']));
            Configuration::updateValue('digidargah_failed_massage', digidargah::sanitize($_POST['digidargah_failed_massage']));
            $this->_html .= '<div class="conf confirm">' . $this->l('Settings updated') . '</div>';
        }

        $this->_generateForm();
        return $this->_html;

    }

    public static function sanitize($variable){
        return trim(strip_tags($variable));
    }

    private function _generateForm(){
        $this->_html .= '
		
		<form action="' . $_SERVER['REQUEST_URI'] . '" method="post" class="defaultForm form-horizontal">
		<div class="panel">
		<div class="form-wrapper">
		
		<div class="form-group">
		<label class="control-label col-lg-4 required"> کلید API : </label>
		<div class="col-lg-8">
		<input type="text" name="digidargah_api_key" value="' . Configuration::get('digidargah_api_key') . '">
		<div class="help-block"> برای ایجاد کلید API لطفا به آدرس رو به رو مراجعه نمایید. <a href="https://digidargah.com/cryptosite" target="_blank">https://digidargah.com/cryptosite</a></div>
		</div>
		</div>
		
		<div class="form-group">
        <label class="control-label col-lg-4 required"> ارزهای قابل انتخاب : </label>
		<div class="col-lg-8">
		<input type="text" name="digidargah_pay_currency" value="' . Configuration::get('digidargah_pay_currency') . '">
		<div class="help-block"> به صورت پیش فرض کاربر امکان پرداخت از طریق تمامی <a href="https://digidargah.com/cryptosite" target="_blank"> ارزهای فعال </a> در درگاه را دارد اما در صورتی که تمایل دارید مشتری را محدود به پرداخت از طریق یک یا چند ارز خاص کنید، می توانید از طریق این متغییر نام ارز و یا ارزها را اعلام نمایید. در صورت تمایل به اعلام بیش از یک ارز، آنها را توسط خط تیره ( dash ) از هم جدا کنید. </div>
		</div>
		</div>
		
		<div class="form-group">
        <label class="control-label col-lg-4 required"> پیام پرداخت موفق : </label>
		<div class="col-lg-8">
		<textarea dir="auto" name="digidargah_success_massage" style="height: 100px;">' . (!empty(Configuration::get('digidargah_success_massage')) ? Configuration::get('digidargah_success_massage') : "پرداخت شما با موفقیت انجام شد. <br><br> شماره سبد خرید : {cart_id} <br> کد رهگیری پرداخت : {request_id}") . '</textarea>
		<div class="help-block"> متن پیامی که می خواهید بعد از پرداخت به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کدهای {cart_id} برای نمایش شماره سفارش و {request_id} برای نمایش کد رهگیری دیجی درگاه استفاده نمایید. </div>
		</div>
		</div>
		
		<div class="form-group">
        <label class="control-label col-lg-4 required"> پیام پرداخت ناموفق : </label>
		<div class="col-lg-8">
		<textarea dir="auto" name="digidargah_failed_massage" style="height: 100px;">' . (!empty(Configuration::get('digidargah_failed_massage')) ? Configuration::get('digidargah_failed_massage') : "متاسفانه پرداخت شما با موفقیت انجام نشده است. <br><br> شماره سبد خرید : {cart_id} <br> کد رهگیری پرداخت : {request_id}") . '</textarea>
		<div class="help-block"> متن پیامی که می خواهید بعد از پرداخت به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کدهای {cart_id} برای نمایش شماره سفارش و {request_id} برای نمایش کد رهگیری دیجی درگاه استفاده نمایید. </div>
		</div>
		</div>
		</div>
		
		<div class="panel-footer">
		<input type="submit" name="digidargah_submit" value="' . $this->l('Save') . '" class="btn btn-default pull-right">
		</div>
		
		</div>
		</form>';
    }

    public function hookPaymentOptions($params){
	
		if (!$this->active) return;
		
		//form data will be sent to validation controller when user finishes order process
		$formAction = $this->context->link->getModuleLink($this->name, 'validation', array(), true);
		
		//Assign the url form action to the template var $action
		$this->smarty->assign(['action' => $formAction]);
		
		//Load form template to be displayed in the checkout step
		$paymentForm = $this->fetch('module:digidargah/views/templates/hook/payment_options.tpl');
		
		//Create a PaymentOption object to display module in checkout
		$displayName = 'پرداخت رمز ارزی با دیجی درگاه';
        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
        $newOption->setModuleName($this->displayName)->setCallToActionText($displayName)->setAction($formAction)->setForm($paymentForm);
		$payment_options = array($newOption);
		return $payment_options;
    }
	
	public function hookPaymentReturn($params){
		if (!$this->active) return;
		return $this->fetch('module:digidargah/views/templates/hook/payment_return.tpl');
    }
	
	public function hash_key(){
        $en = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
        $one = rand(1, 26);
        $two = rand(1, 26);
        $three = rand(1, 26);
        return $hash = $en[$one] . rand(0, 9) . rand(0, 9) . $en[$two] . $en[$three] . rand(0, 9) . rand(10, 99);
    }
}

?>
