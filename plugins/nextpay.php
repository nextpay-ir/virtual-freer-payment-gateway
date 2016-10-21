<?
	//-- اطلاعات کلی پلاگین
	$pluginData[nextpay][type] = 'payment';
	$pluginData[nextpay][name] = 'درگاه نکست پی';
	$pluginData[nextpay][uniq] = 'nextpay';
	$pluginData[nextpay][description] = 'درگاه پرداخت  <a href="http://nextpay.ir">نکست پی‌</a>';
	$pluginData[nextpay][author][name] = 'nextpay.ir';
	$pluginData[nextpay][author][url] = 'http://nextpay.ir';
	$pluginData[nextpay][author][email] = 'info@nextpay.ir';
	
	//-- فیلدهای تنظیمات پلاگین
	$pluginData[nextpay][field][config][1][title] = 'کلید مجوزدهی درگاه';
	$pluginData[nextpay][field][config][1][name] = 'api_key';
	$pluginData[nextpay][field][config][2][title] = 'عنوان خرید';
	$pluginData[nextpay][field][config][2][name] = 'title';
	
	//-- تابع انتقال به دروازه پرداخت
	function gateway__nextpay($data)
	{
		global $config,$db,$smarty;
		include_once('include/libs/nusoap.php');
        include_once dirname(__FILE__).'/include/nextpay_payment.php';
        
		$Api_Key 	= trim($data[api_key]);
		$amount 		= round($data[amount]/10);
		$order_id		= $data[invoice_id];
		$callBackUrl 	= $data[callback];

        $parameters = array (
          "api_key"=>$Api_Key,
       	  "order_id"=> $order_id,
          "amount"=>$amount,
          "callback_uri"=>$callBackUrl
        );
        
        $nextpay = new Nextpay_Payment($parameters);
        $result = $nextpay->token();
	
		if (intval($result->code) == -1 )
		{
			$update[payment_rand]		= $result->trans_id ;
			$sql = $db->queryUpdate('payment', $update, 'WHERE `payment_rand` = "'.$order_id.'" LIMIT 1;');
			$db->execute($sql);
			header('location:http://api.nextpay.org/gateway/payment/' . $result->trans_id);
			exit;
		}
		else
		{
			$data[title] = 'خطای سیستم';
			$data[message] = '<font color="red">در اتصال به درگاه نکست پی مشکلی به وجود آمد٬ لطفا از درگاه سایر بانک‌ها استفاده نمایید.</font>'.$result->code.'<br /><a href="index.php" class="button">بازگشت</a>';
			$query	= 'SELECT * FROM `config` WHERE `config_id` = "1" LIMIT 1';
			$conf	= $db->fetch($query);
			$smarty->assign('config', $conf);
			$smarty->assign('data', $data);
			$smarty->display('message.tpl');
		}
	}
	
	//-- تابع بررسی وضعیت پرداخت
	function callback__nextpay($data)
	{
		global $db,$post;
		$trans_id 	= $post['trans_id'];
		$order_id = $post['order_id'];

        include_once('include/libs/nusoap.php');
        include_once dirname(__FILE__).'/include/nextpay_payment.php';

        $Api_Key = $data[api_key];
        $sql 		= 'SELECT * FROM `payment` WHERE `payment_rand` = "'.$trans_id.'" LIMIT 1;';
        $payment 	= $db->fetch($sql);

        $amount		= round($payment[payment_amount]/10);

        $parameters = array
            (
                'api_key'	=> $Api_Key,
                'order_id'	=> $order_id,
                'trans_id' 	=> $trans_id,
                'amount'	=> $amount,
            );

        $nextpay = new Nextpay_Payment();
        $result = $nextpay->verify_request($parameters);
        

        if ($payment[payment_status] == 1)
        {
            if ($result == 0 )//-- موفقیت آمیز
            {
                //-- آماده کردن خروجی
                $output[status]		= 1;
                $output[res_num]	= $trans_id;
                $output[ref_num]	= $oder_id;
                $output[payment_id]	= $payment[payment_id];
            }
            else
            {
                //-- در تایید پرداخت مشکلی به‌وجود آمده است‌
                $output[status]	= 0;
                $output[message]= 'پرداخت توسط نکست پی تایید نشد‌.'.$result;
            }
        }
        else
        {
            //-- قبلا پرداخت شده است‌
            $output[status]	= 0;
            $output[message]= 'سفارش قبلا پرداخت شده است.';
        }

		return $output;
	}
