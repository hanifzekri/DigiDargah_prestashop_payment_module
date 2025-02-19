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
* Tested version up to: 8.1
* copyright (C) 2020 DigiDargah
* license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 or later
*/

class DigiDargahValidationModuleFrontController extends ModuleFrontController {
	
	public $errors = [];
	public $warning = [];
	public $success = [];
	public $info = [];
	
	public function notification(){
		$notifications = json_encode(['error' => $this->errors,
									  'warning' => $this->warning,
									  'success' => $this->success,
									  'info' => $this->info]);
		
		if (session_status() == PHP_SESSION_ACTIVE)
			$_SESSION['notifications'] = $notifications;
		elseif (session_status() == PHP_SESSION_NONE) {
			session_start();
            $_SESSION['notifications'] = $notifications;
        } else
			setcookie('notifications', $notifications);
	}

    public function postProcess(){
		
		$cart = $this->context->cart;
        $authorized = false;
        
		$customer = new Customer($cart->id_customer);
        $moduleActive = $this->module->active;

        if (!$moduleActive || empty($cart->id_customer) || empty($cart->id_address_delivery) || empty($cart->id_address_invoice)) {
            Tools::redirect('index.php?controller=order');
        }

        foreach (Module::getPaymentModules() as $module) {
            $authorized = $module['name'] == 'digidargah';
            if ($authorized) break;
        }

        if (!$authorized) {
            $this->errors[] = 'ماژول پرداخت دیجی درگاه فعال نیست.';
            $this->notification();
            Tools::redirect('index.php?controller=order');
        }

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order');
        }

        if (isset($_GET['do'])) {
            $this->callBack($customer);
        }

        $cart_id = $cart->id;
        $api_key = Configuration::get('digidargah_api_key');
        $pay_currency = Configuration::get('digidargah_pay_currency');
        $amount = (float)$cart->getOrderTotal(true, Cart::BOTH);
		$currency = new Currency($cart->id_currency);
		$currency = $currency->iso_code;
		
        $callback = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? "https://" : "http://")
            . $_SERVER['SERVER_NAME']
            . '/index.php?fc=module&module=digidargah&controller=validation&do=callback&cart_id='
            . $cart_id;

        if (empty($amount)) {
            $this->errors[] = 'سبد خرید خالی است و یا کالاهای موجود در آن، قیمت ندارند.';
            $this->notification();
            Tools::redirect('index.php?controller=order');
        }
		
		$params = array(
			'api_key' => $api_key,
			'amount_value' => $amount,
			'amount_currency' => $currency,
			'pay_currency' => $pay_currency,
			'order_id' => $cart_id,
			'respond_type' => 'link',
			'callback' => $callback
		);
		
		$url = 'https://digidargah.com/action/ws/request/create';
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_USERAGENT => $_SERVER["HTTP_USER_AGENT"],
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => json_encode($params),
		]);
		$response = curl_exec($curl);
		curl_close($curl);
		$result = json_decode($response);

        if ($result->status != 'success') {
            $this->errors[] = 'درگاه پرداخت با خطا مواجه شد. <br> پاسخ درگاه : ' . $result->respond;
            $this->notification();
            Tools::redirect('index.php?controller=order');

        } else {
            $this->handleRequestID($cart_id, $result->request_id);
            Tools::redirect($result->respond);
            exit;
        }

    }

    public function callBack($customer){
		
		$cart_id = (int)$_GET['cart_id'];
		$order = new Order($cart_id);
		$request_id = $this->handleRequestID($cart_id, 0);
		
		if ($cart_id <= 0 || !empty($order) and strlen($request_id) < 5) {
			$this->errors[] = 'مشکلی بوجود آمده است. لطفا مجددا تلاش نمایید و یا در صورت نیاز با پشتیبانی مکاتبه کنید.';
			$this->notification();
			Tools::redirect('index.php?controller=order');
			
		} else {
			
			if (empty($order->current_state) || $order->current_state == 1 || $order->current_state == 8) {
				
				$cart = $this->context->cart;
				$amount = (float)$cart->getOrderTotal(true, Cart::BOTH);
				$currency = Context::getContext()->currency;
				
				$api_key = Configuration::get('digidargah_api_key');
				
				$params = array(
					'api_key' => $api_key,
					'order_id' => $cart_id,
					'request_id' => $request_id
				);				
				
				$url = 'https://digidargah.com/action/ws/request/status';
				$curl = curl_init();
				curl_setopt_array($curl, [
					CURLOPT_URL => $url,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_MAXREDIRS => 5,
					CURLOPT_TIMEOUT => 60,
					CURLOPT_USERAGENT => $_SERVER["HTTP_USER_AGENT"],
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => json_encode($params),
				]);
				$response = curl_exec($curl);
				curl_close($curl);
				$result = json_decode($response);

				if ($result->status != 'success') {
					$message = 'درگاه پرداخت با خطا مواجه شد. <br> پاسخ درگاه : ' . $result->respond;
					$this->errors[] = $message;
					$this->notification();
					$this->saveOrderState($cart, $customer, 8, $message);
					Tools::redirect('index.php?controller=order-confirmation');

				} else {

					$verify_status = empty($result->status) ? NULL : $result->status;
					$verify_request_id = empty($result->request_id) ? NULL : $result->request_id;
					$verify_order_id = empty($result->order_id) ? NULL : $result->order_id;
					$verify_amount = empty($result->amount_value) ? NULL : $result->amount_value;

					$message = 'کد پیگیری درگاه : ' . $verify_request_id . ', شماره سبد خرید : ' . $cart_id;

					if (empty($verify_request_id) || empty($verify_amount) || $verify_order_id != $cart_id || number_format($amount, 5) != number_format($verify_amount, 5)) {

						$message = $this->digidargah_get_failed_message($verify_order_id, $verify_request_id);
						$this->saveOrderState($cart, $customer, 8, $message);
						$this->errors[] = $message;
						$this->notification();
						Tools::redirect('index.php?controller=order-confirmation');

					} else {

						$message = $this->digidargah_get_success_message($verify_order_id, $verify_request_id);
						$this->saveOrderState($cart, $customer, 2, $message);
						$this->success[] = $message;
						$this->notification();
						Tools::redirect('index.php?controller=order-confirmation');
					}
				}

			} else {
				$this->errors[] = 'این سفارش پیش از این تایید شده است.';
				$this->notification();
				Tools::redirect('index.php?controller=order-confirmation');
			}
		}
	}
	
	public function handleRequestID($cart_id, $request_id) {
        $sqlcart = 'SELECT checkout_session_data FROM `' . _DB_PREFIX_ . 'cart` WHERE id_cart  = "' . $cart_id . '"';
        $cart = Db::getInstance()->getRow($sqlcart)['checkout_session_data'];
		$cart = json_decode($cart, true);
		if ($request_id == 0) {
			return $cart['digidargahRequestId'];
		} else {
			$cart['digidargahRequestId'] = $request_id;
			$cart = json_encode($cart);
			$sql = 'UPDATE `' . _DB_PREFIX_ . 'cart` SET `checkout_session_data` = ' . "'" . $cart . "'" . ' WHERE `id_cart` = ' . $cart_id;
			return Db::getInstance()->Execute($sql);
		}
    }

    public function saveOrderState($cart, $customer, $state, $message){
        return $this->module->validateOrder(
			(int)$cart->id,
            $state,
            (float)$cart->getOrderTotal(true, Cart::BOTH),
            $this->module->displayName,
            $message,
            null,
            (int)$this->context->currency->id,
            false,
            $customer->secure_key
        );
    }

    public function digidargah_get_success_message($cart_id, $request_id) {
        return str_replace(["{request_id}", "{cart_id}"], [$request_id, $cart_id], Configuration::get('digidargah_success_massage'));
    }

    public function digidargah_get_failed_message($cart_id, $request_id) {
		return str_replace(["{request_id}", "{cart_id}"], [$request_id, $cart_id], Configuration::get('digidargah_failed_massage'));

    }
}
