<?php
    header('Content-Type: application/json');
    include('../../conn/db_conect.php');

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'JSON inválido']);
        exit;
    }
    $newData  = new stdClass();
    $dateData = []; 

    $customer = $data['appointment']['bookings'][0]['customer'] ?? [];
    $custom   = $data['appointment']['bookings'][0]['customFields'] ?? [];
    $count = count($custom);

    $firstName       = $customer['firstName'] ?? '';
    $lastName        = $customer['lastName'] ?? '';
    $email           = $customer['email'] ?? '';
    $phone           = $customer['phone'] ?? '';
    $countryPhoneIso = $customer['countryPhoneIso'] ?? '';

    $appointmentId = $data['appointment']['bookings'][0]['appointmentId'] ?? '';
    $token         = $data['appointment']['bookings'][0]['token'] ?? '';
    $created       = $data['appointment']['bookings'][0]['created'] ?? '';
    $calendar      = $data['appointment']['microsoftTeamsUrl'] ?? '';
    $start         = $data['appointment']['bookingStart'] ?? '';

    $fullName = trim($firstName . ' ' . $lastName);
    $newToken = $token . '-' . $appointmentId;
    $newDataCustom = json_encode($custom, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $newData->fullName         = $fullName;
    $newData->email            = $email;
    $newData->phone            = $phone;
    $newData->countryPhoneIso  = $countryPhoneIso;
    $newData->appointmentId    = $newToken;
    $newData->custom           = $custom;
    $startDateTime = $start; 
    $dateOnly = substr($startDateTime, 0, 10);
    $timeOnly = substr($startDateTime, 11);
    $documents = new stdClass();
    $dateData[$dateOnly] = (object)[
        'titleAppointment'  => $data['appointment']['service']['name'] ?? '',
        'people'            => $data['appointment']['notifyParticipants'] ?? [],
        'time'              => $timeOnly,
        'insights'          => "",
        'documents'         => $documents,
        'calendar'          => $calendar,
        'start'             => $start,
        'status'            => 1,
        'observations'      => $custom[5],
    ];
    $lead_basicInfo = json_encode($newData, JSON_UNESCAPED_UNICODE);
    if ($lead_basicInfo === false) {
        file_put_contents(__DIR__ . '/log.txt', "[" . date('Y-m-d H:i:s') . "] Error en json_encode de lead_basicInfo: " . json_last_error_msg() . PHP_EOL, FILE_APPEND);
    }

    $lead_appointments = json_encode($dateData, JSON_UNESCAPED_UNICODE);
    if ($lead_appointments === false) {
        file_put_contents(__DIR__ . '/log.txt', "[" . date('Y-m-d H:i:s') . "] Error en json_encode de lead_appointments: " . json_last_error_msg() . PHP_EOL, FILE_APPEND);
    }

    $lead_id        = ""; 
    $lead_processID = $newToken;
    $lead_status    = 1;

    $stmt = $conn->prepare("INSERT INTO ft_crm_leads (lead_id, lead_basicInfo, lead_email,  lead_appointments, lead_processID, lead_status) VALUES (?, ?, ?, ?, ?, ?)");

    $logPath = __DIR__ . '/log.txt';

    if ($stmt === false) {
        file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] Error al preparar statement: " . $conn->error . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos']);
        exit;
    }

    $stmt->bind_param("sssssi", $lead_id, $lead_basicInfo, $email, $lead_appointments, $lead_processID, $lead_status);

    if ($stmt->execute()) {
        $newDataJson = json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] " . $newDataJson . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'success', 'message' => 'Lead guardado correctamente']);
    } else {
        file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] Error al ejecutar: " . $stmt->error . PHP_EOL, FILE_APPEND);
        file_put_contents($logPath, "Datos enviados:\nlead_id: $lead_id\nlead_basicInfo: $lead_basicInfo\nlead_appointments: $lead_appointments\nlead_processID: $lead_processID\nlead_status: $lead_status\n", FILE_APPEND);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar lead']);
}
?>