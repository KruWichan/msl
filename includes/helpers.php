<?php
if (!function_exists('explodeTags')) {
    function explodeTags($tagNames) {
        if (empty($tagNames)) return [];
        return array_map('trim', explode(',', $tagNames));
    }
}

// New function for Thai date formatting
if (!function_exists('thai_date')) {
    function thai_date($date_str) {
        if (!$date_str || $date_str == '0000-00-00' || $date_str == 'NULL') {
            return '-';
        }
        try {
            $date = new DateTime($date_str);
        } catch (Exception $e) {
            return '-';
        }
        $thai_months = [
            '01' => 'ม.ค.', '02' => 'ก.พ.', '03' => 'มี.ค.', '04' => 'เม.ย.',
            '05' => 'พ.ค.', '06' => 'มิ.ย.', '07' => 'ก.ค.', '08' => 'ส.ค.',
            '09' => 'ก.ย.', '10' => 'ต.ค.', '11' => 'พ.ย.', '12' => 'ธ.ค.'
        ];
        $day = $date->format('d');
        $month = $thai_months[$date->format('m')];
        $year = $date->format('Y') + 543;
        return "{$day} {$month} {$year}";
    }
}

// Function to adjust brightness of a hex color
if (!function_exists('adjustBrightness')) {
    function adjustBrightness($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $r = max(0, min(255, $r + $percent));
        $g = max(0, min(255, $g + $percent));
        $b = max(0, min(255, $b + $percent));
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}

if (!function_exists('getPaymentStatusThai')) {
    function getPaymentStatusThai($status) {
        $map = [
            'pending' => 'รอตรวจสอบ',
            'approved' => 'อนุมัติ',
            'rejected' => 'ปฏิเสธ'
        ];
        return $map[$status] ?? $status;
    }
}

if (!function_exists('getOrderStatusThai')) {
    function getOrderStatusThai($status) {
        $map = [
            'pending' => 'รอดำเนินการ',
            'processing' => 'กำลังดำเนินการ',
            'completed' => 'สำเร็จ',
            'cancelled' => 'ยกเลิก',
            'shipped' => 'จัดส่งแล้ว'
        ];
        return $map[$status] ?? $status;
    }
}
?>