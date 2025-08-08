<?php

Class Zoom
{
	private $clientId;
	private $clientSecret;
	private $accountId;
	private $adminUserEmail;

	public function __construct()
	{
		$this->clientId = get_field('zoom_client_id', 'option');
		$this->clientSecret = get_field('zoom_client_secret', 'option');
		$this->accountId = get_field('zoom_account_id', 'option');
		// The Zoom user who owns the meeting ('your_admin_zoom_email@example.com')
		$this->adminUserEmail = get_field('zoom_admin_user_email', 'option');
	}

	public function getAccessToken()
	{
		$base64Auth = base64_encode("$this->clientId:$this->clientSecret");
		$tokenUrl = "https://zoom.us/oauth/token?grant_type=account_credentials&account_id=$this->accountId";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $tokenUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
		    "Authorization: Basic $base64Auth",
		    "Content-Type: application/x-www-form-urlencoded"
		]);

		$response = curl_exec($ch);
		curl_close($ch);

		$data = json_decode($response, true);
		$accessToken = $data['access_token'];
		return $accessToken;
	}


	/*
	* Sample input
		$meetingInfo = array(
			'topic' => "Mentor Session: Parent & Child",
			'start_time'=> "2025-08-07T15:00:00Z",
			'duration'=> "60",
			'timezone'=> "UTC",
		);
	*/

	public function createZoomMetting($meetingInfo)
	{
		$defaultMeetingInfo = array(
			'duration'=> "60",
			'timezone'=> "UTC",
		);

		// Merge with default so if not provided any keys use defualt ones
		$meetingInfo = wp_parse_args( $meetingInfo, $defaultMeetingInfo );

		$accessToken = $this->getAccessToken();
		$zoomApiUrl = "https://api.zoom.us/v2/users/{$this->adminUserEmail}/meetings";

		$payload = [
		    "topic" => $meetingInfo['topic'],
		    "type" => 2, // Scheduled meeting
		    "start_time" => $meetingInfo['start_time'], // ISO8601 format (UTC time)
		    "duration" => $meetingInfo['duration'], // in minutes
		    "timezone" => $meetingInfo['timezone'],
		    "settings" => [
		        "join_before_host" => false,
		        "waiting_room" => true,
		        "approval_type" => 0, // Automatically approve registrants
		        "audio" => "both",
		        "auto_recording" => "cloud"
		    ]
		];

		$headers = [
		    "Authorization: Bearer $accessToken",
		    "Content-Type: application/json"
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $zoomApiUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode === 201) {


		    $meeting = json_decode($response, true);

		    return json_encode(array(
				'status'=> 'success',
				'data'=> $meeting
			));

		    // $startUrl = $meeting['start_url']; // Mentor uses this (becomes host)
		    // $joinUrl = $meeting['join_url'];   // Parent and child use this

		}

		return json_encode(array(
			'status'=> 'fail',
		));
		
	}

	public function getMeetingUrl($meeting,$type)
	{

        if($type == 'start_url'){
        	return $meeting['start_url']; // Mentor uses this (becomes host)
        }

        if($type == 'join_url'){
        	return $meeting['join_url'];   // Parent and child use this
    	}
	}

}

//test;
add_action('wp_loaded', function ()
{
	
$meetingInfo = array(
	'topic' => "Mentor Session: Parent & Child",
	'start_time'=> "2025-08-15T15:00:00Z",
	'duration'=> "60",
	'timezone'=> "UTC",
);
// $zoom = new Zoom();
// $zoom->createZoomMetting($meetingInfo);
});