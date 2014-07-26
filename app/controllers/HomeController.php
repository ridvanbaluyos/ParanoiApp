<?php

class HomeController extends BaseController {

	/*
	|--------------------------------------------------------------------------
	| Default Home Controller
	|--------------------------------------------------------------------------
	|
	| You may wish to use controllers instead of, or in addition to, Closure
	| based routes. That's great! Here is an example controller method to
	| get you started. To route to this controller, just add the route:
	|
	|	Route::get('/', 'HomeController@showWelcome');
	|
	*/

	protected $layout = 'layouts.main';
	protected $chikkaClientId = '';
	protected $chikkaSecretKey = '';
	protected $chikkaUrl = 'https://post.chikka.com/smsapi/request';
	protected $chikkaShortCode = '2929088888';

	public function showWelcome()
	{
		return View::make('hello');
	}

	public function index()
	{
		return View::make('landing_page');
	}

	public function dashboard()
	{
		return View::make('dashboard');
	}

	public function sms()
	{
		$templateData['recipients'] = SmsRecipients::where('user_id', '=', '1')
						->get();
		return View::make('sms')->with('templateData', $templateData);
	}

	public function smsSubmit()
	{
		$contactNumber = Input::get('contact_number');
		$contactName = Input::get('contact_name');

		$smsRecipients = new SmsRecipients;
		$smsRecipients->name = $contactName;
		$smsRecipients->mobile_number = $contactNumber;
		$smsRecipients->user_id = 1;
		$smsRecipients->updated_at     = time();

		if ($smsRecipients->save()) {
			return Redirect::to('/sms')
                ->with('message', '<strong>Success! </strong> You have sucessfully configured your SMS recipients.')
                ->with('type', 'success');
		} else {
			return Redirect::to('/sms')
                ->with('message', '<strong>Ooops! </strong> Something went wrong. Please try again.')
                ->with('type', 'danger');
		}
	}

	public function email()
	{
		$templateData['recipients'] = EmailRecipients::where('user_id', '=', '1')
										->get();

		// $email = 'ridvan@baluyos.net';
		// $data = array(
		//     'recipient' => $email,
		//     'plateNumber' => 'KVM213'
		// );

		// Mailgun::send('emails.message', $data, function($message) use ($email)
		// {
		//     $message->to($email)->subject('alert - Message from Angel');
		// });

		return View::make('email')->with('templateData', $templateData);
	}

	public function emailSubmit()
	{
		$email = Input::get('email');
		$name = Input::get('name');

		$emailRecipients = new EmailRecipients;
		$emailRecipients->email = $email;
		$emailRecipients->name = $name;
		$emailRecipients->user_id = 1;
		$emailRecipients->updated_at     = time();

		if ($emailRecipients->save()) {
			return Redirect::to('/email')
                ->with('message', '<strong>Success! </strong> You have sucessfully configured your Email recipients.')
                ->with('type', 'success');
		} else {
			return Redirect::to('/email')
                ->with('message', '<strong>Ooops! </strong> Something went wrong. Please try again.')
                ->with('type', 'danger');
		}
	}

	public function socialNetworks()
	{
		$templateData = array();
		return View::make('social_networks')->with('templateData', $templateData);
	}

	public function chikkaReceiver()
	{
		try {
			$messageType = Input::get("message_type");
		} catch (Exception $e) {
			echo "Error";
			exit;
		}

		if ($messageType == 'incoming') {
			try {
				$message = $_POST["message"];
		        $mobileNumber = $_POST["mobile_number"];
		        $shortCode = $_POST["shortcode"];
		        $requestId = $_POST["request_id"];
		        $timestamp = $_POST["timestamp"];

		        $smsTracker = new SmsTracker;
		        $smsTracker->message_type = $messageType;
		        $smsTracker->mobile_number = $mobileNumber;
		        $smsTracker->shortcode = $shortCode;
		        $smsTracker->request_id = $requestId;
		        $smsTracker->message = $message;
		        $smsTracker->timestamp = $timestamp;

		        if ($smsTracker->save()) {
		        	$this->propagate($message); // let the magic begin!
		        	echo "Accepted";
		        	exit;
		        } else {
		        	echo "Error";
		        	exit;
		        }

		        exit;
			} catch (PDOException $e) {
				echo "Error";

				exit;
			}
		} else {
			echo "Error";
			exit;
		}
	}

	private function propagate($message)
	{
		$recipients = SmsRecipients::where('user_id', '=', '1')
						->get();

		foreach ($recipients as $recipient) {
			$this->sendSMS($recipient->mobile_number, $message);
		}

		$recipients = array();
		$recipients = EmailRecipients::where('user_id', '=', '1')
						->get();

		foreach ($recipients as $recipient) {
			$this->sendEmail($recipient, $message);
		}
	}

	public function sendSMS($recipient, $message)
	{
		$params = array(
		    "message_type" => "SEND",
		    "mobile_number" => $recipient,
		    "shortcode" => $this->chikkaShortCode,
		    "message_id" => str_pad(rand(), 32, '0', STR_PAD_LEFT),
		    "message" => $message,
		    "client_id" => $this->chikkaClientId,
		    "secret_key" => $this->chikkaSecretKey
		);

		$query = '';
		foreach($params as $key=>$param)
		{
		    $query .= '&' . $key . '=' . $param;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->chikkaUrl);
		curl_setopt($ch, CURLOPT_POST, count($params));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$response = curl_exec($ch);
		curl_close($ch);
		// exit(0);
	}

	public function sendEmail($recipient, $text)
	{
		$plateNumber = $text;
		$email = $recipient->email;
		$data = array(
		    'recipient' => $email,
		    'plateNumber' => $plateNumber
		);

		Mailgun::send('emails.message', $data, function($message) use ($email)
		{
		    $message->to($email)->subject('alert - Message from Angel');
		});
	}
}
