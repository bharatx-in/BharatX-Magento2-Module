<?php
$url = 'https://web-v2.bharatx.tech/api/merchant/transaction'; // Replace with your API URL
$username = 'testPartnerId'; // Replace with your username
$password = 'testPrivateKey'; // Replace with your password

$user = array(
    'name' => "Priyanshu Dangi",
    'phoneNumber' => "+917581957713"
);

$params = array(
    'transaction' => array(
        'id' => "some_random_id",
        'amount' => 600,
        'mode' => 'TEST',
        'notes' => (object)array()
    ),
    'user' => $user
);

$payload = json_encode($params);

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
    ),
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => $username . ':' . $password,
));

$response = curl_exec($curl);
$error = curl_error($curl);

curl_close($curl);

if ($error) {
    echo 'cURL Error: ' . $error;
} else {
    echo 'Response: ' . $response;
    // Process the response as needed
}
?>
