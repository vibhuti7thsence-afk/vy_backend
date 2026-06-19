<?php

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../src/bootstrap.php';

// Storage proxy: fetch private S3 objects and stream them to the client.
// Handles both /storage/registrations/file.png and /storage/donations/file.png
$_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
if (str_starts_with($_reqPath, '/storage/')) {
    $s3Key = substr($_reqPath, strlen('/storage/'));  // e.g. "registrations/filename.png"
    // Only allow registrations/ and donations/ prefixes
    if (preg_match('#^(registrations|donations)/[^/]+$#', $s3Key)) {
        $s3 = \App\Services\S3StorageService::fromConfig();
        if ($s3 !== null) {
            try {
                $body = $s3->get($s3Key);
                $ext  = strtolower(pathinfo($s3Key, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png'         => 'image/png',
                    'webp'        => 'image/webp',
                    'pdf'         => 'application/pdf',
                    default       => 'application/octet-stream',
                };
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . strlen($body));
                header('Cache-Control: private, max-age=86400');
                echo $body;
                exit;
            } catch (\Throwable $e) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'File not found']);
                exit;
            }
        }
        // Fall through to local filesystem (non-S3 setup)
    }
}

use App\Controllers\AdminDashboardController;
use App\Controllers\ClassController;
use App\Controllers\ClassRegistrationController;
use App\Controllers\DonationController;
use App\Controllers\HealingFormController;
use App\Controllers\YogaFormController;
use App\Core\ExceptionHandler;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

$router = new Router();
$classController = new ClassRegistrationController();
$classCrudController = new ClassController();
$donationController = new DonationController();
$healingFormController = new HealingFormController();
$yogaFormController = new YogaFormController();
$adminController = new AdminDashboardController();

$healthHandler = static function (): void {
    Response::json([
        'status' => 'ok',
        'message' => 'Backend is running.',
    ]);
};

$docsHandler = static function (): void {
    header('Content-Type: text/html; charset=utf-8');
    try {
        $spec = require __DIR__ . '/../config/openapi.php';
        $specJson = json_encode($spec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($specJson === false) {
            throw new RuntimeException('Failed to encode OpenAPI spec: ' . json_last_error_msg());
        }
        $htmlPath = __DIR__ . '/swagger-ui.html';
        if (!is_file($htmlPath)) {
            throw new RuntimeException('swagger-ui.html not found at ' . $htmlPath);
        }
        $html = file_get_contents($htmlPath);
        $html = str_replace(
            '</head>',
            '<script>window.OPENAPI_SPEC = ' . $specJson . ';</script></head>',
            $html
        );
        echo $html;
    } catch (Throwable $e) {
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Docs Error</title></head><body><h1>Error loading API docs</h1><pre>'
            . htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString(), ENT_QUOTES, 'UTF-8')
            . '</pre></body></html>';
    }
    exit;
};

$router->get('/', static function (): void {
    Response::json([
        'service' => 'yoga-api',
        'health' => '/health',
        'docs' => '/docs',
    ]);
});

$router->get('/health', $healthHandler);
$router->get('/api/health', $healthHandler);
$router->get('/docs', $docsHandler);
$router->get('/api/docs', $docsHandler);

$router->get('/api/openapi.json', static function (): void {
    $spec = require __DIR__ . '/../config/openapi.php';
    Response::json($spec);
});

$router->get('/openapi.json', static function (): void {
    $spec = require __DIR__ . '/../config/openapi.php';
    Response::json($spec);
});

$router->get('/api/classes', [$classController, 'listClasses']);
$router->get('/api/admin/classes', [$classCrudController, 'listAllClasses']);
$router->post('/api/classes', [$classCrudController, 'createClass']);
$router->put('/api/classes', [$classCrudController, 'updateClass']);
$router->delete('/api/admin/classes', [$classCrudController, 'deleteClass']);
$router->put('/api/classes/agreed-fee', [$classController, 'putAgreedFee']);
$router->post('/api/classes/register-payment', [$classController, 'registerPayment']);
$router->get('/api/classes/payment-summary', [$classController, 'paymentSummary']);
$router->get('/api/classes/payment-transactions', [$classController, 'paymentTransactions']);
$router->get('/api/classes/registration-verify', [$classController, 'verifyRegistration']);
$router->post('/api/classes/lookup-user', [$classController, 'lookupUser']);

$router->post('/api/donations', [$donationController, 'store']);
$router->get('/api/donations/verify-eligibility', [$donationController, 'verifyDonationEligibility']);
$router->get('/api/donations', [$donationController, 'listByMobile']);
$router->post('/api/healing-form/submissions', [$healingFormController, 'submit']);
$router->get('/api/healing-form/submissions', [$healingFormController, 'listByMobile']);
$router->post('/api/yoga-form/submissions', [$yogaFormController, 'submit']);
$router->get('/api/yoga-form/submissions', [$yogaFormController, 'listByMobile']);

$router->get('/api/admin/dashboard', [$adminController, 'dashboard']);
$router->get('/api/admin/course-distribution', [$adminController, 'courseDistribution']);
$router->get('/api/admin/recent-activity', [$adminController, 'recentActivity']);
$router->get('/api/admin/registrations', [$adminController, 'listRegistrations']);
$router->get('/api/admin/donations', [$adminController, 'listDonations']);
$router->get('/api/admin/healing-submissions', [$adminController, 'listHealingSubmissions']);
$router->get('/api/admin/donations/summary', [$adminController, 'donationsSummary']);
$router->put('/api/admin/donations/status', [$adminController, 'updateDonationStatus']);

try {
    $request = Request::fromGlobals();
    $router->dispatch($request);
} catch (Throwable $exception) {
    ExceptionHandler::handle($exception);
}
