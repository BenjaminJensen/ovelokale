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

    default => (function () {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    })(),
};
