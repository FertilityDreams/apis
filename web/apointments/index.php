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

    $firstName        = $customer['firstName'] ?? '';
    $lastName         = $customer['lastName'] ?? '';
    $email            = $customer['email'] ?? '';
    $phone            = $customer['phone'] ?? '';
    $countryPhoneIso  = $customer['countryPhoneIso'] ?? '';
    $appointment_type = 0;

    $appointmentId = $data['appointment']['bookings'][0]['appointmentId'] ?? '';
    $token         = $data['appointment']['bookings'][0]['token'] ?? '';
    $calendar      = $data['appointment']['microsoftTeamsUrl'] ?? '';
    $start         = $data['appointment']['bookingStart'] ?? '';

    $fullName = trim($firstName . ' ' . $lastName);
    $newToken = $token . '-' . $appointmentId;

    $newData->fullName        = $fullName;
    $newData->email           = $email;
    $newData->phone           = $phone;
    $newData->countryPhoneIso = $countryPhoneIso;
    $newData->appointmentId   = $newToken;
    $newData->custom          = $custom;

    $startDateTime = $start; 
    $dateOnly = substr($startDateTime, 0, 10);
    $timeOnly = substr($startDateTime, 11);

    $dateData[$dateOnly] = (object)[
        'titleAppointment' => $data['appointment']['service']['name'] ?? '',
        'people'           => $data['appointment']['notifyParticipants'] ?? [],
        'time'             => $timeOnly,
        'insights'         => "",
        'documents'        => new stdClass(),
        'calendar'         => $calendar,
        'start'            => $start,
        'status'           => 1,
        'observations'     => $custom[5] ?? '',
    ];

    $lead_basicInfo    = json_encode($newData, JSON_UNESCAPED_UNICODE);
    $lead_appointments = json_encode($dateData, JSON_UNESCAPED_UNICODE);

    $lead_id        = generarLeadIdUnico($conn);
    $lead_processID = $newToken;
    $lead_status    = 1;

    date_default_timezone_set('America/Bogota');
    $lead_created   = date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "INSERT INTO hub_crm_leads (lead_id, lead_basicInfo, lead_email, lead_processID, lead_status, lead_created) 
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "sssssi",
        $lead_id,
        $lead_basicInfo,
        $email,
        $lead_processID,
        $lead_status,
        $lead_created
    );

    if ($stmt->execute()) {

        $stmt->close();

        $created_appointment = date('Y-m-d H:i:s');
        $appointment_status  = 1;

        $stmt2 = $conn->prepare(
            "INSERT INTO hub_crm_appointments (id_lead, info_appointment, appointment_status, created_appointment, appointment_type)
            VALUES (?, ?, ?, ?, ?)"
        );

        $stmt2->bind_param(
            "ssisi",
            $lead_id,
            $lead_appointments,
            $appointment_status,
            $created_appointment,
            $appointment_type
        );

        if ($stmt2->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Lead y agenda guardados correctamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Lead guardado, pero error al guardar agenda']);
        }

        $stmt2->close();

    } else {
        file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] Error al ejecutar: " . $stmt->error . PHP_EOL, FILE_APPEND);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar lead']);
        $stmt->close();
    }

    $conn->close();


    function generarHashId(): string {
        $letras = '';
        for ($i = 0; $i < 3; $i++) {
            $letras .= chr(rand(65, 90));
        }
        $numeros = str_pad(strval(rand(0, 9999)), 4, '0', STR_PAD_LEFT);
        return $letras . $numeros;
    }

    function generarLeadIdUnico(mysqli $conn): string {
        do {
            $id = generarHashId();
            $stmt = $conn->prepare("SELECT 1 FROM hub_crm_leads WHERE lead_id = ? LIMIT 1");
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        } while ($exists);
        return $id;
    }
?>