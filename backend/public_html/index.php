<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

match ($path) {
    '/api/health' => (function () {
        echo json_encode(['status' => 'ok']);
    })(),

    '/api/test-mail' => (function () {
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = getenv('MAIL_HOST') ?: 'mailhog';
        $mail->Port = (int) (getenv('MAIL_PORT') ?: 1025);
        $mail->SMTPAuth = false;
        $mail->setFrom('dev@ovelokale.test', 'Ovelokale Dev');
        $mail->addAddress('test@ovelokale.test');
        $mail->Subject = 'Test email';
        $mail->Body = 'If you can see this in Mailhog, SMTP works.';

        $sent = $mail->send();
        echo json_encode(['sent' => $sent]);
    })(),

    '/api/db-check' => (function () {
        $statement = db()->query('SELECT VERSION()');
        $version = $statement !== false ? $statement->fetchColumn() : false;
        echo json_encode(['mysql_version' => $version]);
    })(),

    '/api/calendar' => (function () {
        $result = App\Http\CalendarController::index($_GET);
        http_response_code($result['status']);
        echo json_encode($result['body']);
    })(),

    '/api/users' => (function () {
        $result = App\Http\UserController::index($_GET);
        http_response_code($result['status']);
        echo json_encode($result['body']);
    })(),

    '/api/bands' => (function () {
        $result = App\Http\BandController::index($_GET);
        http_response_code($result['status']);
        echo json_encode($result['body']);
    })(),

    '/api/bookings' => (function () {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);

            return;
        }

        $body = json_decode((string) file_get_contents('php://input'), true);
        $result = App\Http\BookingController::store(is_array($body) ? $body : []);
        http_response_code($result['status']);
        echo json_encode($result['body']);
    })(),

    '/api/bookings/conflicts' => (function () {
        $result = App\Http\BookingController::conflicts($_GET);
        http_response_code($result['status']);
        echo json_encode($result['body']);
    })(),

    default => (function () {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    })(),
};
