<?php
    $isArabic = ($locale ?? 'ar') === 'ar';
    $dir = $isArabic ? 'rtl' : 'ltr';

    // Email clients fetch this over the public internet: it must be an absolute
    // HTTPS URL, never localhost, a filesystem path or a dev bundler URL.
    $logo = config('app.email_logo_url')
        ?: rtrim(config('app.url'), '/').'/images/iden-logo.png';
    $logo = preg_replace('#^http://#', 'https://', $logo);
    $logoIsPublic = ! preg_match('#^https://(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])#', $logo);
?>
<!DOCTYPE html>
<html lang="<?php echo e($isArabic ? 'ar' : 'en'); ?>" dir="<?php echo e($dir); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($isArabic ? 'تأكيد بريدك الإلكتروني' : 'Confirm your email'); ?></title>
</head>
<body style="margin:0;padding:0;background:#f5f3ff;font-family:'Tajawal','Segoe UI',Arial,sans-serif;color:#2a0740;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f3ff;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:560px;background:#ffffff;border-radius:20px;overflow:hidden;border:1px solid #ede9fe;">
                    <tr>
                        <td align="center" style="background:#2a0740;padding:28px 24px;">
                            <?php if($logoIsPublic): ?>
                                <img src="<?php echo e($logo); ?>" alt="iden" width="170"
                                     style="display:block;border:0;width:170px;max-width:170px;height:auto;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;">
                            <?php else: ?>
                                
                                <span style="display:inline-block;font-size:22px;font-weight:700;letter-spacing:3px;color:#ffffff;">iden</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px;text-align:<?php echo e($isArabic ? 'right' : 'left'); ?>;">
                            <h1 style="margin:0 0 12px;font-size:22px;line-height:1.4;color:#3b0a57;">
                                <?php echo e($isArabic ? 'مرحبًا '.$user->name : 'Welcome '.$user->name); ?>

                            </h1>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.8;color:#4b3b5a;">
                                <?php echo e($isArabic
                                    ? 'يسعدنا انضمامك إلى iden. اضغط على الزر أدناه لتأكيد بريدك الإلكتروني وبدء استخدام حسابك.'
                                    : 'Thanks for joining iden. Confirm your email address with the button below to start using your account.'); ?>

                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                                <tr>
                                    <td align="center" style="background:#3b0a57;border-radius:14px;">
                                        <a href="<?php echo e($url); ?>"
                                           style="display:inline-block;padding:14px 30px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;">
                                            <?php echo e($isArabic ? 'تأكيد البريد الإلكتروني' : 'Confirm email address'); ?>

                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 20px;font-size:13px;line-height:1.8;color:#6b5b7a;">
                                <?php echo e($isArabic
                                    ? 'ينتهي هذا الرابط بعد 60 دقيقة.'
                                    : 'This link expires in 60 minutes.'); ?>

                            </p>

                            <p style="margin:0;font-size:13px;line-height:1.8;color:#6b5b7a;">
                                <?php echo e($isArabic
                                    ? 'إذا لم تُنشئ هذا الحساب، يمكنك تجاهل هذه الرسالة.'
                                    : 'If you did not create this account, you can ignore this email.'); ?>

                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background:#faf8ff;padding:18px 24px;border-top:1px solid #ede9fe;">
                            <p style="margin:0;font-size:12px;color:#8a7a99;">
                                <?php echo e(config('app.name', 'iden')); ?> · <?php echo e($isArabic ? 'الذراع الذكية لأعمالك' : 'The AI Arm of Your Business'); ?>

                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html><?php /**PATH /tmp/iden/iden-update-v11 (1)/backend/resources/views/emails/verify-email.blade.php ENDPATH**/ ?>