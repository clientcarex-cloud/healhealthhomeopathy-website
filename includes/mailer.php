<?php
/**
 * PHPMailer wiring (no Composer required) + booking email templates.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once dirname(__DIR__) . '/vendor/PHPMailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/PHPMailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/vendor/PHPMailer/src/SMTP.php';

/**
 * Build a configured PHPMailer instance.
 */
function hh_mailer(array $mailCfg): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->CharSet  = PHPMailer::CHARSET_UTF8;
    $mail->Encoding = PHPMailer::ENCODING_BASE64;

    if ($mailCfg['smtp_host'] !== '') {
        $mail->isSMTP();
        $mail->Host = $mailCfg['smtp_host'];
        $mail->Port = $mailCfg['smtp_port'];

        if ($mailCfg['smtp_user'] !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $mailCfg['smtp_user'];
            $mail->Password = $mailCfg['smtp_pass'];
        }

        if ($mailCfg['smtp_secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($mailCfg['smtp_secure'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        if ($mailCfg['smtp_debug'] > 0) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = 'error_log';
        }

        $mail->Timeout = 20;
    } else {
        // No SMTP configured — fall back to the server's sendmail/mail().
        $mail->isMail();
    }

    $mail->setFrom($mailCfg['from_email'], $mailCfg['from_name']);

    return $mail;
}

/**
 * Send the booking request to the clinic inbox.
 *
 * @throws PHPMailerException
 */
function hh_send_booking_to_clinic(array $booking, array $mailCfg): void
{
    $mail = hh_mailer($mailCfg);
    $mail->addAddress($mailCfg['to_email'], $mailCfg['to_name']);

    // Replying to the clinic email goes straight back to the patient.
    if ($booking['email'] !== '') {
        $mail->addReplyTo($booking['email'], $booking['name']);
    }

    $mail->Subject = sprintf(
        'New appointment request — %s (%s)',
        $booking['name'],
        $booking['service_label']
    );

    $mail->isHTML(true);
    $mail->Body    = hh_clinic_email_html($booking);
    $mail->AltBody = hh_clinic_email_text($booking);

    $mail->send();
}

/**
 * Send the patient an acknowledgement.
 */
function hh_send_patient_ack(array $booking, array $mailCfg): void
{
    if ($booking['email'] === '') {
        return;
    }

    $mail = hh_mailer($mailCfg);
    $mail->addAddress($booking['email'], $booking['name']);
    $mail->addReplyTo($mailCfg['to_email'], $mailCfg['to_name']);
    $mail->Subject = 'We received your appointment request — Heal Health Homeopathy';

    $mail->isHTML(true);
    $mail->Body    = hh_patient_email_html($booking);
    $mail->AltBody = hh_patient_email_text($booking);

    $mail->send();
}

// ---------------------------------------------------------------------------
// Templates
// ---------------------------------------------------------------------------

function hh_email_row(string $label, string $value): string
{
    if (trim($value) === '') {
        return '';
    }

    return '<tr>'
        . '<td style="padding:10px 16px;border-bottom:1px solid #e6efe9;color:#5b6f66;'
        . 'font:600 13px/1.4 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;'
        . 'white-space:nowrap;vertical-align:top;">' . e($label) . '</td>'
        . '<td style="padding:10px 16px;border-bottom:1px solid #e6efe9;color:#12211c;'
        . 'font:400 15px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
        . nl2br(e($value)) . '</td>'
        . '</tr>';
}

function hh_clinic_email_html(array $b): string
{
    $rows = hh_email_row('Patient', $b['name'])
        . hh_email_row('Phone', $b['phone'])
        . hh_email_row('Email', $b['email'])
        . hh_email_row('Age', $b['age'])
        . hh_email_row('Concern', $b['service_label'])
        . hh_email_row('Consultation', $b['mode_label'])
        . hh_email_row('Preferred date', $b['date_pretty'])
        . hh_email_row('Preferred slot', $b['slot'])
        . hh_email_row('Message', $b['message'])
        . hh_email_row('Submitted', $b['submitted_at'])
        . hh_email_row('Reference', $b['reference']);

    $waNumber = preg_replace('/\D+/', '', $b['phone']);
    $waLink   = strlen((string) $waNumber) >= 10
        ? 'https://wa.me/91' . substr((string) $waNumber, -10)
        : '';

    $actions = '<a href="tel:' . e($b['phone']) . '" style="display:inline-block;padding:11px 20px;'
        . 'background:#0f5c4d;color:#ffffff;border-radius:999px;text-decoration:none;'
        . 'font:600 14px -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">Call patient</a>';

    if ($waLink !== '') {
        $actions .= '&nbsp;&nbsp;<a href="' . e($waLink) . '" style="display:inline-block;padding:11px 20px;'
            . 'background:#e8f3ee;color:#0f5c4d;border-radius:999px;text-decoration:none;'
            . 'font:600 14px -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">WhatsApp</a>';
    }

    return <<<HTML
<!doctype html>
<html><body style="margin:0;padding:24px;background:#f2f7f4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dfeae4;">
  <tr>
    <td style="padding:24px 28px;background:#0f5c4d;">
      <div style="color:#9fd9c6;font:600 12px/1 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;letter-spacing:.12em;text-transform:uppercase;">New appointment request</div>
      <div style="color:#ffffff;font:700 22px/1.3 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin-top:6px;">Heal Health Homeopathy</div>
    </td>
  </tr>
  <tr><td style="padding:8px 12px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">{$rows}</table>
  </td></tr>
  <tr><td style="padding:20px 28px 28px;">{$actions}</td></tr>
  <tr><td style="padding:16px 28px;background:#f7faf8;color:#7a8b83;font:400 12px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
    Sent automatically from the Heal Health Homeopathy website booking form.
  </td></tr>
</table>
</body></html>
HTML;
}

function hh_clinic_email_text(array $b): string
{
    return "NEW APPOINTMENT REQUEST — Heal Health Homeopathy\n"
        . str_repeat('-', 48) . "\n"
        . "Patient       : {$b['name']}\n"
        . "Phone         : {$b['phone']}\n"
        . "Email         : {$b['email']}\n"
        . "Age           : {$b['age']}\n"
        . "Concern       : {$b['service_label']}\n"
        . "Consultation  : {$b['mode_label']}\n"
        . "Preferred date: {$b['date_pretty']}\n"
        . "Preferred slot: {$b['slot']}\n"
        . "Message       : {$b['message']}\n"
        . "Submitted     : {$b['submitted_at']}\n"
        . "Reference     : {$b['reference']}\n";
}

function hh_patient_email_html(array $b): string
{
    $rows = hh_email_row('Concern', $b['service_label'])
        . hh_email_row('Consultation', $b['mode_label'])
        . hh_email_row('Preferred date', $b['date_pretty'])
        . hh_email_row('Preferred slot', $b['slot'])
        . hh_email_row('Reference', $b['reference']);

    $phone   = HH_PHONE_PRETTY;
    $tel     = HH_PHONE_E164;
    $address = HH_ADDRESS_FULL;
    $map     = HH_MAP_LINK;
    $name    = e($b['name']);

    return <<<HTML
<!doctype html>
<html><body style="margin:0;padding:24px;background:#f2f7f4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dfeae4;">
  <tr>
    <td style="padding:28px;background:#0f5c4d;">
      <div style="color:#ffffff;font:700 22px/1.3 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">Thank you, {$name}</div>
      <div style="color:#9fd9c6;font:400 15px/1.5 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin-top:8px;">
        We have received your appointment request. Our team will call you shortly to confirm the exact time.
      </div>
    </td>
  </tr>
  <tr><td style="padding:8px 12px 4px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">{$rows}</table>
  </td></tr>
  <tr><td style="padding:22px 28px;">
    <div style="color:#12211c;font:600 15px -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin-bottom:8px;">Clinic</div>
    <div style="color:#5b6f66;font:400 14px/1.7 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
      {$address}<br>
      <a href="{$map}" style="color:#0f5c4d;">Open in Google Maps</a><br>
      Phone: <a href="tel:{$tel}" style="color:#0f5c4d;">{$phone}</a>
    </div>
  </td></tr>
  <tr><td style="padding:16px 28px;background:#f7faf8;color:#7a8b83;font:400 12px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
    This request is not a confirmed booking until our team calls you. For urgent medical situations, please contact your nearest hospital.
  </td></tr>
</table>
</body></html>
HTML;
}

function hh_patient_email_text(array $b): string
{
    return "Thank you, {$b['name']}\n\n"
        . "We have received your appointment request at Heal Health Homeopathy. "
        . "Our team will call you shortly to confirm the exact time.\n\n"
        . "Concern       : {$b['service_label']}\n"
        . "Consultation  : {$b['mode_label']}\n"
        . "Preferred date: {$b['date_pretty']}\n"
        . "Preferred slot: {$b['slot']}\n"
        . "Reference     : {$b['reference']}\n\n"
        . 'Clinic: ' . HH_ADDRESS_FULL . "\n"
        . 'Phone : ' . HH_PHONE_PRETTY . "\n\n"
        . "This request is not a confirmed booking until our team calls you.\n";
}
