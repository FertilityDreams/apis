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

    $newData   = new stdClass();
    $customer  = $data['appointment']['bookings'][0]['customer'] ?? [];
    $custom    = $data['appointment']['bookings'][0]['customFields'] ?? [];
    $countCustom = count($custom);

    $firstName = $customer['firstName'] ?? '';
    $lastName  = $customer['lastName'] ?? '';
    $email     = $customer['email'] ?? '';
    $phone     = $customer['phone'] ?? '';
    $countryPhoneIso = $customer['countryPhoneIso'] ?? '';

    $appointmentId = $data['appointment']['bookings'][0]['appointmentId'] ?? '';
    $token = $data['appointment']['bookings'][0]['token'] ?? '';
    $created = $data['appointment']['bookings'][0]['created'] ?? '';
    $calendar = $data['appointment']['microsoftTeamsUrl'] ?? '';
    $start = $data['appointment']['bookingStart'] ?? '';

    $fullName = trim($firstName . ' ' . $lastName);
    $newToken = $token . '-' . $appointmentId;

    $newData->fullName = $fullName;
    $newData->email = $email;
    $newData->phone = $phone;
    $newData->countryPhoneIso = $countryPhoneIso;
    $newData->appointmentId = $newToken;
    $newData->created = $created;
    $newData->calendar = $calendar;
    $newData->start = $start;
    $newData->aditionalPeople  = $data['appointment']['notifyParticipants'];

    foreach ($custom as $key => $field) {
        $pushObjName = $field['label'] ?? '';
        $pushObjVal = $field['value'] ?? '';
        if (in_array($pushObjName, [
            "Quiero que me contacten por", 
            "I want to be contacted about", 
            "Je veux qu’on me contacte à propos de"
        ])) {
            $pushObjName = "contactForm";
        }
    
        $sanitizedKey = preg_replace('/[^a-zA-Z0-9_]/', '_', $pushObjName);
        $newData->$sanitizedKey = $pushObjVal;
    }

    $lead_id = ""; 
    $lead_basicInfo    = json_encode($newData, JSON_UNESCAPED_UNICODE);
    $lead_appointments = json_encode(new stdClass(), JSON_UNESCAPED_UNICODE);
    $lead_processID = $newToken;
    $lead_status = 1;

    $stmt = $conn->prepare("INSERT INTO ft_crm_leads (lead_id, lead_basicInfo, lead_appointments, lead_processID, lead_status) VALUES (?, ?, ?, ?, ?)");

    $logPathOriginal = __DIR__ . '/log.txt';

    if ($stmt === false) {
        file_put_contents($logPathOriginal, "[" . date('Y-m-d H:i:s') . "] Error al preparar statement: " . $conn->error . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos']);
        exit;
    }

    $stmt->bind_param("ssssi", $lead_id, $lead_basicInfo, $lead_appointments, $lead_processID, $lead_status);

    if ($stmt->execute()) {
        file_put_contents($logPathOriginal, "[" . date('Y-m-d H:i:s') . "] Guardo correctamente." . PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents($logPathOriginal, "[" . date('Y-m-d H:i:s') . "] Error al ejecutar: " . $stmt->error . PHP_EOL, FILE_APPEND);
        file_put_contents($logPathOriginal, "Datos:\nlead_id: $lead_id\nlead_basicInfo: $lead_basicInfo\nlead_appointments: $lead_appointments\nlead_processID: $lead_processID\nlead_status: $lead_status\n", FILE_APPEND);
    }
?>