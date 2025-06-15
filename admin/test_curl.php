<?php
$token = 'hnuLLIV+Nnv1WwrUZfqBEKCn1dEHySdqQBO2nb4BXAJhrdE7XsRlH0Osk6BZqAAWggYNabaQuIjqw1VuoPaDo2abWL7FLU9zSsQC+QyQYykBzs7blYFKFMv40g6a02eDFwGEtHTcgagdyURQCflNZwdB04t89/1O/w1cDnyilFU=';
$userId = 'U103844a6f576752181e53498d97cd6ac';
$message = 'ข้อความทดสอบ';

$data = [
    'to' => $userId,
    'messages' => [
        ['type' => 'text', 'text' => $message]
    ]
];

$ch = curl_init('https://api.line.me/v2/bot/message/push');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_CAINFO, "C:\\AppServ\\cacert.pem"); // เพิ่มบรรทัดนี้ถ้าจำเป็น
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

var_dump($response, $httpcode);
?>