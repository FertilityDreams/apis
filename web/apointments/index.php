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

    $logPath = __DIR__ . '/log.txt';

    $newData  = new stdClass();
    $dateData = []; 

    $customer = $data['appointment']['bookings'][0]['customer'] ?? [];
    $custom   = $data['appointment']['bookings'][0]['customFields'] ?? [];

    $firstName       = $customer['firstName'] ?? '';
    $lastName        = $customer['lastName'] ?? '';
    $email           = $customer['email'] ?? '';
    $phone           = $customer['phone'] ?? '';
    $countryPhoneIso = $customer['countryPhoneIso'] ?? '';

    $appointmentId = $data['appointment']['bookings'][0]['appointmentId'] ?? '';
    $token         = $data['appointment']['bookings'][0]['token'] ?? '';
    $calendar      = $data['appointment']['microsoftTeamsUrl'] ?? '';
    $start         = $data['appointment']['bookingStart'] ?? '';

    $fullName = trim($firstName . ' ' . $lastName);
    $newToken = $token . '-' . $appointmentId;

    $newData->fullName         = $fullName;
    $newData->email            = $email;
    $newData->phone            = $phone;
    $newData->countryPhoneIso  = $countryPhoneIso;
    $newData->appointmentId    = $newToken;
    $newData->custom           = $custom;

    $startDateTime = $start; 
    $dateOnly = substr($startDateTime, 0, 10);
    $timeOnly = substr($startDateTime, 11);

    $dateData[$dateOnly] = (object)[
        'titleAppointment'  => $data['appointment']['service']['name'] ?? '',
        'people'            => $data['appointment']['notifyParticipants'] ?? [],
        'time'              => $timeOnly,
        'insights'          => "",
        'documents'         => new stdClass(),
        'calendar'          => $calendar,
        'start'             => $start,
        'status'            => 1,
        'observations'      => $custom[5] ?? '',
    ];

    $lead_basicInfo      = json_encode($newData, JSON_UNESCAPED_UNICODE);
    $lead_appointments   = json_encode($dateData, JSON_UNESCAPED_UNICODE);

    $lead_id        = "";  
    $lead_processID = $newToken;
    $lead_status    = 1;

    date_default_timezone_set('America/Bogota');
    $lead_created = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("INSERT INTO hub_crm_leads (lead_id, lead_basicInfo, lead_email, lead_appointments, lead_processID, lead_status, lead_created) VALUES (?, ?, ?, ?, ?, ?, ?)");

    if ($stmt === false) {
        file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] Error al preparar statement: " . $conn->error . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos']);
        exit;
    }

    $stmt->bind_param(
        "sssssis",
        $lead_id,
        $lead_basicInfo,
        $email,
        $lead_appointments,
        $lead_processID,
        $lead_status,
        $lead_created
    );

    if ($stmt->execute()) {
        $newDataJson = json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] " . $newDataJson . PHP_EOL, FILE_APPEND);
        echo json_encode(['status' => 'success', 'message' => 'Lead guardado correctamente']);
    } else {
        file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] Error al ejecutar: " . $stmt->error . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar lead']);
    }

    $stmt->close();
    $conn->close();
