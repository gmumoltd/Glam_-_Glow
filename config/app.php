<?php

define('BUSINESS_NAME', 'Valmier Enterprise');
define('OWNER_NAME', 'Owner');
define('CURRENCY', 'KES');
define('BASE_URL', '');

/**
 * Free API key from https://ocr.space/OCRAPI (no billing required, 25k
 * requests/month) — used by receipt-import.php to read text off photographed
 * receipts. Get your own key and replace the fallback below, or set the
 * OCR_SPACE_API_KEY environment variable.
 */
define('OCR_SPACE_API_KEY', getenv('OCR_SPACE_API_KEY') ?: 'K88002898388957');
