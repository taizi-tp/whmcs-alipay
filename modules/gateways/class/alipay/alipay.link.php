<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

class alipay_link {
    public function get_paylink($params){
        if (!function_exists("openssl_open")){
            return '<span style="color:red">Fatal Error:管理员未开启openssl组件<br/>正常情况下该组件必须开启<br/>请开启openssl组件解决该问题</span>';
        }
        if (!function_exists("scandir")){
            return '<span style="color:red">Fatal Error:管理员未开启scandir PHP函数<br/>支付宝Sdk 需要使用该函数<br/>请修改php.ini下的disable_function来解决该问题</span>';
        }
		if (empty($params['alipay_key'])){
            return "管理员未配置 支付宝公钥 , 无法使用该支付接口";
        } 
        if (empty($params['rsa_key'])){
            return "管理员未配置 RSA私钥  , 无法使用该支付接口";
        }		
        $type = Capsule::table("tblpaymentgateways")->where("gateway","alipay")->where("setting","type")->first();
        switch ($type->value) {
            case "即时到账":
                return $this->PcPay($params);
			case "当面付":
                return $this->QrPay($params);	
        }
    }

    public function PcPay($params){
        require_once __DIR__ ."/alipay.class.php";
		
		$aliPay = new PcPayService();
		$aliPay->setAppid($params['app_id']);
		$aliPay->setReturnUrl($params['systemurl']."/modules/gateways/callback/alipay.php");
		$aliPay->setNotifyUrl($params['systemurl']."/modules/gateways/callback/alipay.php");
		$aliPay->setRsaPrivateKey($params['rsa_key']);
		$aliPay->setTotalFee($params['amount']);
		$aliPay->setOutTradeNo("alipay".md5(uniqid())."-".$params['invoiceid']);
		$aliPay->setOrderName("Billing [# ".$params['invoiceid']." ]");		
		$sHtml = $aliPay->doPay();
		return $sHtml;
    }
	
    public function QrPay($params){
		require_once __DIR__ ."/alipay.class.php";

        $aliPay = new QrPayService();
		$aliPay->setAppid($params['app_id']);
		$aliPay->setNotifyUrl($params['systemurl']."/modules/gateways/callback/alipay.php");
		$aliPay->setRsaPrivateKey($params['rsa_key']);
		$aliPay->setTotalFee($params['amount']);
		$aliPay->setOutTradeNo("alipay".md5(uniqid())."-".$params['invoiceid']);
		$aliPay->setOrderName("Billing [# ".$params['invoiceid']." ]");	
		
		$result = $aliPay->doPay();		
		$result = $result['alipay_trade_precreate_response'];

		if($result['msg'] && $result['msg']=='Success'){
			$url = 'https://www.kuaizhan.com/common/encode-png?large=true&data='.$result['qr_code'];
			return "<a href='{$result['qr_code']}'><img src='{$url}' style='width:180px;'><br>请打开支付宝扫码";
		}else{
			return $result['msg'].' : '.$result['sub_msg'];
		}	
    }	
}