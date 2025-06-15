<?php
// includes/line_messaging_api.php

if (!function_exists('sendLinePushMessage')) {
    function sendLinePushMessage($channelAccessToken, $userId, $messages) {
        if (empty($channelAccessToken) || empty($userId) || empty($messages)) {
            error_log('[LINE Messaging API] sendLinePushMessage: Missing access token, userId, or messages.');
            return false;
        }

        // รองรับทั้ง string และ array
        if (is_string($messages)) {
            // รองรับข้อความหลายบรรทัด (แยกเป็นหลาย message)
            $lines = explode("\n", $messages);
            $messages = [];
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $messages[] = ['type' => 'text', 'text' => $line];
                }
            }
            if (empty($messages)) {
                $messages = [['type' => 'text', 'text' => ' ']];
            }
        }

        $url = 'https://api.line.me/v2/bot/message/push';
        $data = [
            'to' => $userId,
            'messages' => $messages
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $channelAccessToken
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($result === false || $http_code != 200) {
            error_log('[LINE Messaging API] sendLinePushMessage failed: ' . $curl_error . ' | HTTP: ' . $http_code . ' | Response: ' . $result);
            return false;
        }
        return true;
    }
}

if (!function_exists('sendLineReplyMessage')) {
    function sendLineReplyMessage($channelAccessToken, $replyToken, $messages) {
        if (empty($channelAccessToken) || empty($replyToken) || empty($messages)) {
            error_log('[LINE Messaging API] sendLineReplyMessage: Missing access token, replyToken, or messages.');
            return false;
        }

        if (is_string($messages)) {
            // รองรับข้อความหลายบรรทัด (แยกเป็นหลาย message)
            $lines = explode("\n", $messages);
            $messages = [];
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $messages[] = ['type' => 'text', 'text' => $line];
                }
            }
            if (empty($messages)) {
                $messages = [['type' => 'text', 'text' => ' ']];
            }
        }

        $url = 'https://api.line.me/v2/bot/message/reply';
        $data = [
            'replyToken' => $replyToken,
            'messages' => $messages
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $channelAccessToken
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($result === false || $http_code != 200) {
            error_log('[LINE Messaging API] sendLineReplyMessage failed: ' . $curl_error . ' | HTTP: ' . $http_code . ' | Response: ' . $result);
            return false;
        }
        return true;
    }
}

if (!function_exists('sendLineMessage')) {
    function sendLineMessage($channelAccessToken, $userId, $messages, $replyToken = null) {
        if ($replyToken) {
            return sendLineReplyMessage($channelAccessToken, $replyToken, $messages);
        } else {
            return sendLinePushMessage($channelAccessToken, $userId, $messages);
        }
    }
}
?>
