<?php
/**
 * Enquiry form mail settings — Hostinger.
 *
 * On the Hostinger server, copy this file to mail-config.php and fill in the
 * mailbox password. mail-config.php is ignored by git, so the password never
 * ends up on GitHub.
 *
 * على سيرفر Hostinger: انسخ هذا الملف باسم mail-config.php وحط باسورد الإيميل.
 * ملف mail-config.php مستثنى من git حتى ما يوصل الباسورد لـ GitHub.
 */
return [
    'to'                => 'info@apexconcretepolishing.com.au', // where enquiries arrive
    'from'              => 'info@apexconcretepolishing.com.au', // must be a Hostinger mailbox on this domain
    'from_name'         => 'Apex Concrete Polishing',
    'smtp_host'         => 'smtp.hostinger.com',
    'smtp_port'         => 465,   // 465 = SSL (recommended), 587 = STARTTLS
    'smtp_user'         => 'info@apexconcretepolishing.com.au',
    'smtp_pass'         => '',    // Hostinger mailbox password. Leave empty to fall back to PHP mail().
    'send_confirmation' => true,  // also email the customer a copy of their enquiry
];
