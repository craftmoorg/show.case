<?php

namespace SB {

    /**
     * Автозагрузчик классов
     */
    spl_autoload_register(function ($class) {
        $classDir = '/classes/';
        $fileExtension = '.php';
        if (strpos($class, __NAMESPACE__ . '\\') !== false) {
            $class = str_replace(__NAMESPACE__ . '\\', '', $class);
            $className = str_replace('\\', DIRECTORY_SEPARATOR, $class);
            $filePath = __DIR__ . $classDir . $className . $fileExtension;

            if (file_exists($filePath)) {
                /** @noinspection PhpIncludeInspection */
                require_once $filePath;
            }
        }
    });
}

namespace {
    if (!function_exists("custom_mail")) {
        function custom_mail($to, $subject, $message, $additional_headers, $additional_parameters, $context) {
            if (false && $additional_headers && strpos($additional_headers, 'Comments: SENDER_MODULE_EMAIL')) {
                // Рассылки модуля email маркетинга отправляем через Mindbox
                $subject = preg_replace('/^\=\?UTF\-8\?B\?/ui', '', $subject);
                $subject = preg_replace('/\?\=$/ui', '', $subject);
                if (preg_match('/(\<body[\S\s]+\<\/body\>){1}/ui', $message, $matches)) {
                    $message = trim($matches[1]);
                    $message = preg_replace('/^\<body/ui', '<div', $message);
                    $message = preg_replace('/\<\/body\>$/ui', '</div>', $message);
                    $message = preg_replace('/640px/ui', '580px', $message);
                    $message = preg_replace('/\"640\"/ui', '"580"', $message);
                }
                return \SB\Mindbox::sendMail($to, base64_decode($subject), $message);
            } else {
                if($additional_parameters!="") {
                    return @mail($to, $subject, $message, $additional_headers, $additional_parameters);
                }
                return @mail($to, $subject, $message, $additional_headers);
            }
        }
    }

    include_once('event_handlers.php');
}